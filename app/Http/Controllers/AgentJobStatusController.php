<?php

namespace App\Http\Controllers;

use App\Models\AgentConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AgentJobStatusController extends Controller
{
    /**
     * Check the status of a queued RG SOC Engineer agent job.
     *
     * Returns: { status: 'pending'|'completed'|'failed', message: {...}|null }
     */
    public function show(string $id): JsonResponse
    {
        // Try locating by message ID first
        $message = AgentConversationMessage::find($id);

        // Fallback: locate by jobId (for database queue compatibility / legacy tests)
        if (! $message) {
            $message = AgentConversationMessage::where('role', 'assistant')
                ->whereJsonContains('meta->job_id', $id)
                ->latest()
                ->first();
        }

        if (! $message) {
            return response()->json([
                'status' => 'pending',
                'message' => null,
            ]);
        }

        $status = $message->meta['status'] ?? 'pending';

        // Double-check: if still marked pending and using database queue, check if job is lost
        if ($status === 'pending' && config('queue.default') === 'database') {
            $jobId = $message->meta['job_id'] ?? null;
            if ($jobId) {
                $jobStillInQueue = DB::table('jobs')->where('id', $jobId)->exists();

                if (! $jobStillInQueue) {
                    // Job left the queue without updating the message — mark as failed
                    $message->update([
                        'content' => '⚠️ Agent job was lost from the queue. Please retry.',
                        'meta' => ['status' => 'failed', 'job_id' => $jobId],
                    ]);
                    $status = 'failed';
                    $message->refresh();
                }
            }
        }

        return response()->json([
            'status' => $status,
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'agent' => $message->agent,
                'content' => $message->content,
                'html' => \Str::markdown($message->content),
                'created_at' => $message->created_at->format('H:i'),
                'meta' => $message->meta,
            ],
        ]);
    }
}
