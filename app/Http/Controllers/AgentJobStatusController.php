<?php

namespace App\Http\Controllers;

use App\Models\AgentConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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

        // Double-check: if still marked pending, check if the job is gone from the queue
        if ($status === 'pending') {
            $jobId = $message->meta['job_id'] ?? null;

            if (config('queue.default') === 'database' && $jobId) {
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
            } elseif (config('queue.default') === 'redis' && $jobId) {
                // Check if the job is still in the Redis queue
                $jobStillInQueue = false;
                try {
                    $queuedJobs = Redis::lrange('queues:default', 0, -1);
                    foreach ($queuedJobs as $jobPayload) {
                        $decoded = json_decode($jobPayload, true);
                        if (isset($decoded['id']) && (string) $decoded['id'] === (string) $jobId) {
                            $jobStillInQueue = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Redis check failed — skip this check
                }

                // Also check Horizon failed jobs table
                $horizonFailed = false;
                try {
                    $horizonFailed = DB::table('failed_jobs')
                        ->where('uuid', $jobId)
                        ->orWhere('connection', 'redis')
                        ->exists();
                } catch (\Throwable $e) {
                    // Table might not exist — skip
                }

                if (! $jobStillInQueue && ! $horizonFailed) {
                    // Job is gone from queue but not in failed_jobs — it might still be processing
                    // Check if the message has been pending for more than 5 minutes
                    $pendingMinutes = $message->created_at->diffInMinutes(now());
                    if ($pendingMinutes > 5) {
                        $message->update([
                            'content' => '⚠️ Agent job timed out. Please retry.',
                            'meta' => ['status' => 'failed', 'job_id' => $jobId],
                        ]);
                        $status = 'failed';
                        $message->refresh();
                    }
                } elseif ($horizonFailed) {
                    $message->update([
                        'content' => '⚠️ Agent job failed during processing. Please retry.',
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
