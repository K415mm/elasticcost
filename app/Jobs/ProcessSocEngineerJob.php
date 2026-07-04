<?php

namespace App\Jobs;

use App\Ai\Agents\RgSocEngineer;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Services\AiConfigHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSocEngineerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public string $messageId,
        public string $conversationId,
        public string $prompt,
        public string $provider,
        public string $model,
        public ?string $phpSessionId = null,
    ) {}

    public function handle(): void
    {
        $message = AgentConversationMessage::find($this->messageId);
        $conversation = AgentConversation::find($this->conversationId);

        if (! $message || ! $conversation) {
            Log::warning('ProcessSocEngineerJob: message or conversation not found', [
                'message_id' => $this->messageId,
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        try {
            $agent = new RgSocEngineer;
            $agent->phpSessionId = $this->phpSessionId;

            $multiConfig = AiConfigHelper::configureMultiModel();

            $response = $agent->prompt(
                $this->prompt,
                provider: $multiConfig['light']['provider'],
                model: $multiConfig['light']['model'],
            );

            $message->update([
                'content' => $response->text,
                'meta' => ['status' => 'completed', 'job_id' => $message->meta['job_id'] ?? null],
            ]);
            $conversation->touch();

        } catch (\Throwable $e) {
            Log::error('ProcessSocEngineerJob failed: '.$e->getMessage(), [
                'message_id' => $this->messageId,
                'exception' => $e,
            ]);

            $message->update([
                'content' => '⚠️ Agent encountered an error: '.$e->getMessage(),
                'meta' => ['status' => 'failed', 'job_id' => $message->meta['job_id'] ?? null],
            ]);
            $conversation->touch();
        }
    }

    public function failed(\Throwable $e): void
    {
        $message = AgentConversationMessage::find($this->messageId);

        if ($message) {
            $message->update([
                'content' => '⚠️ Agent job failed to process: '.$e->getMessage(),
                'meta' => ['status' => 'failed', 'job_id' => $message->meta['job_id'] ?? null],
            ]);

            $conversation = AgentConversation::find($this->conversationId);
            if ($conversation) {
                $conversation->touch();
            }
        }
    }
}
