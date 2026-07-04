<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ElasticCostAssistant;
use App\Ai\Agents\RgSocEngineer;
use App\Ai\Analytics\LaravelAnalyticsCollector;
use App\Jobs\ProcessSocEngineerJob;
use App\Models\AgentConversation;
use App\Services\AiConfigHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Phpkaiharness\Core\AgentLoop;
use Phpkaiharness\Llm\LaravelAiClient;
use Phpkaiharness\Session\SessionManager;

class AiChatController extends Controller
{
    /**
     * Display the AI Chat interface.
     */
    public function index(Request $request, ?string $id = null)
    {
        $conversations = AgentConversation::orderBy('updated_at', 'desc')->get();
        $activeConversation = null;

        if ($id) {
            $activeConversation = AgentConversation::with('messages')->find($id);
        }

        return view('dashboard.ai_chat', compact('conversations', 'activeConversation'));
    }

    /**
     * Store a new conversation message and generate AI response.
     * For RgSocEngineer, queues the job and returns immediately.
     */
    public function storeMessage(Request $request, ?string $id = null)
    {
        $request->validate([
            'message' => 'required|string',
            'agent' => 'nullable|string|in:ElasticCostAssistant,RgSocEngineer',
        ]);

        $userMessageContent = $request->input('message');
        $agentName = $request->input('agent', 'ElasticCostAssistant');
        $activeConversation = null;

        if ($id) {
            $activeConversation = AgentConversation::find($id);
        }

        if (! $activeConversation) {
            $title = Str::limit($userMessageContent, 35, '...');
            $activeConversation = AgentConversation::create([
                'title' => $title,
            ]);
        }

        // Save user message
        $activeConversation->messages()->create([
            'role' => 'user',
            'content' => $userMessageContent,
            'agent' => $agentName,
        ]);

        // Instant Greetings Pre-check for SOC Engineer (replies instantly in 0ms)
        $trimmedMsg = strtolower(trim($userMessageContent));
        $greetings = ['hi', 'hello', 'hey', 'greetings', 'yo', 'halo', 'ahoy'];
        if ($agentName === 'RgSocEngineer' && in_array($trimmedMsg, $greetings)) {
            $activeConversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Hello! I am the **RG SOC Engineer**. I can help you inspect system details, modify global settings, enable/disable security agent coverage on assets, update device counts, or manage analyst allocations. What would you like to do today?',
                'agent' => $agentName,
                'meta' => ['status' => 'completed'],
            ]);
            $activeConversation->touch();

            return response()->json([
                'success' => true,
                'queued' => false,
                'conversation_id' => $activeConversation->id,
                'title' => $activeConversation->title,
                'messages' => $activeConversation->messages()->orderBy('created_at', 'asc')->get()->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'agent' => $m->agent,
                    'content' => $m->content,
                    'html' => Str::markdown($m->content),
                    'created_at' => $m->created_at->format('H:i'),
                    'meta' => $m->meta,
                ]),
            ]);
        }

        // Build prompt with conversation history (sliding window of last 6 messages)
        $allHistoryMessages = $activeConversation->messages()->orderBy('created_at', 'asc')->get();

        // Filter out temporary/failed assistant messages
        $filteredMessages = $allHistoryMessages->filter(function ($msg) {
            if ($msg->role === 'assistant') {
                if (str_starts_with($msg->content, '⚠️') ||
                    str_starts_with($msg->content, '_Agent is working on your request') ||
                    ($msg->meta['status'] ?? '') === 'failed') {
                    return false;
                }
            }

            return true;
        });

        // Take only the last 6 messages
        $slidingWindow = $filteredMessages->take(-6);

        $prompt = "Below is the history of the conversation so far, followed by the latest user question. Use this context to answer the user.\n\n";

        foreach ($slidingWindow as $msg) {
            $roleLabel = ($msg->role === 'user') ? 'User' : (($msg->agent === 'RgSocEngineer') ? 'RG SOC Engineer' : 'ElasticCost Assistant');

            // Prune extremely long messages (e.g. reports) to preserve token context
            $content = $msg->content;
            if (mb_strlen($content, 'UTF-8') > 1500) {
                $content = mb_substr($content, 0, 800, 'UTF-8')."\n\n... [Content truncated due to length to preserve token context] ...\n\n".mb_substr($content, -400, null, 'UTF-8');
            }

            $prompt .= "### {$roleLabel}:\n{$content}\n\n";
        }

        $prompt .= '### '.(($agentName === 'RgSocEngineer') ? 'RG SOC Engineer' : 'ElasticCost Assistant').":\n";

        if ($agentName === 'RgSocEngineer') {
            return $this->queueSocEngineer($activeConversation, $prompt, $agentName);
        }

        return $this->runSynchronous($activeConversation, $prompt, $agentName);
    }

    /**
     * Queue the RG SOC Engineer agent and return a job_id for status polling.
     */
    private function queueSocEngineer(AgentConversation $conversation, string $prompt, string $agentName)
    {
        try {
            $multiConfig = AiConfigHelper::configureMultiModel();

            // Create a pending placeholder message for the client UI polling
            $pendingMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => '_Agent is working on your request..._',
                'agent' => $agentName,
                'meta' => ['status' => 'pending', 'job_id' => null],
            ]);

            $messageId = $pendingMessage->id;
            $phpSessionId = 'phpsess_'.session()->getId();

            $providerStr = $multiConfig['light']['provider'] instanceof \BackedEnum
                ? $multiConfig['light']['provider']->value
                : (string) $multiConfig['light']['provider'];

            // Dispatch the job class — no closures, no SerializableClosure issues
            ProcessSocEngineerJob::dispatch(
                $messageId,
                $conversation->id,
                $prompt,
                $providerStr,
                $multiConfig['light']['model'],
                $phpSessionId,
            );

            // Capture the latest dispatched job ID from the queue
            $latestJob = DB::table('jobs')->orderByDesc('id')->first();
            $jobId = $latestJob?->id;

            $pendingMessage->update([
                'meta' => ['status' => 'pending', 'job_id' => $jobId],
            ]);

            $conversation->touch();

            if (config('queue.default') === 'database') {
                $this->triggerBackgroundQueueWorker();
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'job_id' => $jobId,
                'message_id' => $messageId,
                'conversation_id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages()->orderBy('created_at', 'asc')->get()->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'agent' => $m->agent,
                    'content' => $m->content,
                    'html' => Str::markdown($m->content),
                    'created_at' => $m->created_at->format('H:i'),
                    'meta' => $m->meta,
                ]),
            ]);

        } catch (\Exception $e) {
            \Log::error('RG SOC Engineer queue error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to queue RG SOC Engineer: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger a background queue worker asynchronously.
     */
    private function triggerBackgroundQueueWorker(): void
    {
        if (config('queue.default') === 'redis') {
            return;
        }

        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $command = "\"{$phpBinary}\" \"{$artisanPath}\" queue:work --once --tries=1 --timeout=600";

        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            pclose(popen("start /B cmd /c {$command}", 'r'));
        } else {
            exec("{$command} > /dev/null 2>&1 &");
        }
    }

    /**
     * Run ElasticCost Assistant synchronously.
     */
    private function runSynchronous(AgentConversation $conversation, string $prompt, string $agentName)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $aiConfig = AiConfigHelper::configure();
            $agent = new ElasticCostAssistant;

            if ($agent::isFaked()) {
                $response = $agent->prompt($prompt);
                $responseText = $response->text;

                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $responseText,
                    'agent' => $agentName,
                ]);

                $conversation->touch();

                return response()->json([
                    'success' => true,
                    'queued' => false,
                    'conversation_id' => $conversation->id,
                    'title' => $conversation->title,
                    'messages' => $conversation->messages()->orderBy('created_at', 'asc')->get()->map(fn ($m) => [
                        'role' => $m->role,
                        'agent' => $m->agent,
                        'content' => $m->content,
                        'html' => Str::markdown($m->content),
                        'created_at' => $m->created_at->format('H:i'),
                        'meta' => $m->meta,
                    ]),
                ]);
            }

            $sessionId = 'phpsess_'.session()->getId();
            /** @var SessionManager $sessionManager */
            $sessionManager = app(SessionManager::class);
            $sessionManager->activateSession($sessionId);
            $analytics = new LaravelAnalyticsCollector($sessionManager->resolveMonitorDbPath($sessionId));

            $provider = $aiConfig['provider'];
            $providerStr = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;

            $llmClient = new LaravelAiClient($providerStr, $aiConfig['model']);

            $loop = new AgentLoop(
                llmClient: $llmClient,
                systemPrompt: $agent->instructions(),
                model: $aiConfig['model'],
                maxIterations: 1
            );
            $loop->setAgentName($agentName);

            $startTime = microtime(true);

            $history = [];
            $responseText = $loop->run(
                userPrompt: $prompt,
                history: $history,
                sessionId: $sessionId,
                collector: $analytics
            );

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $analytics->endSession($sessionId, $responseText, $durationMs, 1);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $responseText,
                'agent' => $agentName,
            ]);

            $conversation->touch();

            return response()->json([
                'success' => true,
                'queued' => false,
                'conversation_id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages()->orderBy('created_at', 'asc')->get()->map(fn ($m) => [
                    'role' => $m->role,
                    'agent' => $m->agent,
                    'content' => $m->content,
                    'html' => Str::markdown($m->content),
                    'created_at' => $m->created_at->format('H:i'),
                    'meta' => $m->meta,
                ]),
            ]);

        } catch (\Exception $e) {
            \Log::error('AI Chat Assistant error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'AI backend failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an entire conversation thread.
     */
    public function destroy(string $id)
    {
        $conversation = AgentConversation::findOrFail($id);

        $conversation->messages()->delete();
        $conversation->delete();

        return redirect()->route('ai-chat.index')
            ->with('success', 'Chat thread deleted successfully.');
    }
}
