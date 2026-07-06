<?php

namespace App\Services\TestCompare;

use App\Services\AiConfigHelper;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a single test request+response using an LLM judge.
 * Scores on: accuracy, completeness, relevance, quality (0-10 each).
 * Returns a structured evaluation usable across all 4 modes for comparison.
 */
class AiEvaluator
{
    private Client $http;

    private string $judgeProvider;

    private string $judgeModel;

    private string $judgeBaseUrl;

    private string $judgeApiKey;

    public function __construct()
    {
        $aiConfig = AiConfigHelper::configure();
        $provider = $aiConfig['provider'];
        $this->judgeProvider = $provider instanceof \BackedEnum ? $provider->value : (string) $provider;
        $this->judgeModel = $aiConfig['model'];
        $this->judgeBaseUrl = $this->resolveBaseUrl($this->judgeProvider);
        $this->judgeApiKey = $this->resolveApiKey($this->judgeProvider);

        $this->http = new Client(['timeout' => 120]);
    }

    /**
     * Evaluate a single (prompt, response) pair.
     * Returns a structured array with scores and reasoning.
     *
     * @return array{score: int, accuracy: int, completeness: int, relevance: int, quality: int, verdict: string, strengths: string, weaknesses: string, error: string|null}
     */
    public function evaluate(string $prompt, string $response, string $category, string $agent): array
    {
        if (empty(trim($response))) {
            return $this->emptyEval('Empty response');
        }

        $systemPrompt = <<<'PROMPT'
You are an expert AI response evaluator for an Elasticsearch cost estimation assistant.
Evaluate the response strictly and objectively.
You MUST respond with ONLY valid JSON — no markdown, no backticks, no explanation outside the JSON.

Scoring criteria (0-10 each):
- accuracy: Is the information technically correct? (0=wrong, 10=fully correct)
- completeness: Does it fully address all parts of the question? (0=missing everything, 10=complete)
- relevance: Is the response focused and on-topic? (0=off-topic, 10=highly relevant)
- quality: Is the response well-structured, clear, and professional? (0=poor, 10=excellent)

Response format (ONLY this JSON, nothing else):
{
  "accuracy": <0-10>,
  "completeness": <0-10>,
  "relevance": <0-10>,
  "quality": <0-10>,
  "verdict": "<one sentence: Pass|Partial|Fail — why>",
  "strengths": "<key strengths in 1-2 sentences>",
  "weaknesses": "<key weaknesses in 1-2 sentences, or 'None' if flawless>"
}
PROMPT;

        $userContent = "CATEGORY: {$category}\nAGENT: {$agent}\n\nUSER QUESTION:\n{$prompt}\n\nAGENT RESPONSE:\n".mb_substr($response, 0, 3000);

        try {
            $payload = [
                'model' => $this->judgeModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'max_tokens' => 400,
                'temperature' => 0.1,
            ];

            $resp = $this->http->post($this->judgeBaseUrl.'/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->judgeApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($resp->getBody()->getContents(), true);
            $content = trim($body['choices'][0]['message']['content'] ?? '');

            // Strip thinking tags if present
            if (preg_match('/(.*?)<\/think>/s', $content, $matches)) {
                $content = trim(str_replace($matches[0], '', $content));
            }

            // Extract JSON (handle cases where model wraps in backticks)
            if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/', $content, $m)) {
                $content = $m[1];
            }
            if (preg_match('/\{[\s\S]+\}/', $content, $m)) {
                $content = $m[0];
            }

            $parsed = json_decode($content, true);

            if (! is_array($parsed)) {
                return $this->emptyEval('Invalid JSON from evaluator: '.$content);
            }

            $accuracy = max(0, min(10, (int) ($parsed['accuracy'] ?? 0)));
            $completeness = max(0, min(10, (int) ($parsed['completeness'] ?? 0)));
            $relevance = max(0, min(10, (int) ($parsed['relevance'] ?? 0)));
            $quality = max(0, min(10, (int) ($parsed['quality'] ?? 0)));
            $score = (int) round(($accuracy + $completeness + $relevance + $quality) / 4);

            return [
                'score' => $score,
                'accuracy' => $accuracy,
                'completeness' => $completeness,
                'relevance' => $relevance,
                'quality' => $quality,
                'verdict' => $parsed['verdict'] ?? '',
                'strengths' => $parsed['strengths'] ?? '',
                'weaknesses' => $parsed['weaknesses'] ?? '',
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('AiEvaluator failed: '.$e->getMessage());

            return $this->emptyEval($e->getMessage());
        }
    }

    /**
     * Evaluate all 4 modes for a single request index and determine the winner.
     *
     * @param  array<string, array>  $tracesByMode  mode => trace array
     * @return array{evaluations: array, winner: string|null, winner_score: int}
     */
    public function evaluateRequest(array $tracesByMode, int $index): array
    {
        $evaluations = [];
        $bestScore = -1;
        $winner = null;

        foreach ($tracesByMode as $mode => $traces) {
            $trace = $traces[$index] ?? null;
            if (! $trace) {
                continue;
            }

            $prompt = $trace['prompts']['raw_user_prompt'] ?? '';
            $response = $trace['response'] ?? '';
            $category = $trace['category'] ?? '';
            $agent = $trace['agent'] ?? '';

            $eval = $this->evaluate($prompt, $response, $category, $agent);
            $evaluations[$mode] = $eval;

            if ($eval['score'] > $bestScore && $eval['error'] === null) {
                $bestScore = $eval['score'];
                $winner = $mode;
            }
        }

        return [
            'evaluations' => $evaluations,
            'winner' => $winner,
            'winner_score' => $bestScore,
        ];
    }

    /**
     * @return array{score: int, accuracy: int, completeness: int, relevance: int, quality: int, verdict: string, strengths: string, weaknesses: string, error: string}
     */
    private function emptyEval(string $reason): array
    {
        return [
            'score' => 0,
            'accuracy' => 0,
            'completeness' => 0,
            'relevance' => 0,
            'quality' => 0,
            'verdict' => '',
            'strengths' => '',
            'weaknesses' => '',
            'error' => $reason,
        ];
    }

    private function resolveBaseUrl(string $provider): string
    {
        $configUrl = config("ai.providers.{$provider}.url");
        if ($configUrl) {
            return rtrim($configUrl, '/');
        }

        return match ($provider) {
            'qwen' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'ollama' => 'http://127.0.0.1:11434/v1',
            default => 'http://127.0.0.1:1234/v1',
        };
    }

    private function resolveApiKey(string $provider): string
    {
        return config("ai.providers.{$provider}.key", '') ?: '';
    }
}
