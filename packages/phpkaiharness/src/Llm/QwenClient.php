<?php

namespace Phpkaiharness\Llm;

use Exception;
use GuzzleHttp\Client;
use Phpkaiharness\Contracts\AnalyticsCollectorInterface;
use Phpkaiharness\Contracts\LlmClientInterface;

class QwenClient implements LlmClientInterface
{
    protected Client $httpClient;

    protected string $apiKey;

    protected string $baseUrl;

    protected string $defaultModel;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, string $defaultModel = 'qwen-plus')
    {
        $envKey = env('PHPKAIHARNESS_QWEN_KEY') ?: (env('QWEN_API_KEY') ?: env('DASHSCOPE_API_KEY'));
        $configKey = function_exists('config') && app()->bound('config')
            ? (config('ai.providers.qwen.key') ?: config('ai.providers.dashscope.key'))
            : null;
        $this->apiKey = $apiKey ?: ($envKey ?: ($configKey ?: ''));

        $envUrl = env('PHPKAIHARNESS_QWEN_URL') ?: (env('QWEN_URL') ?: env('DASHSCOPE_URL'));
        $configUrl = function_exists('config') && app()->bound('config')
            ? (config('ai.providers.qwen.url') ?: config('ai.providers.dashscope.url'))
            : null;
        $resolvedUrl = $baseUrl ?: ($envUrl ?: ($configUrl ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'));
        $this->baseUrl = rtrim($resolvedUrl, '/');

        $this->defaultModel = $defaultModel;

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 300.0,
            'connect_timeout' => 15.0,
            'verify' => function_exists('config') && config('app.env') === 'local' ? false : true,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function getResolvedModel(): string
    {
        return $this->defaultModel;
    }

    public function chat(
        string $systemPrompt,
        array $messages,
        array $tools = [],
        string $model = '',
        ?string $sessionId = null,
        ?AnalyticsCollectorInterface $collector = null,
        ?callable $onChunk = null
    ): array {
        $resolvedModel = empty($model) ? $this->defaultModel : $model;

        $compiledMessages = [];
        if (! empty($systemPrompt)) {
            $compiledMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $compiledMsg = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? '',
            ];
            if (isset($msg['tool_calls']) && $msg['tool_calls'] !== null) {
                $formattedToolCalls = [];
                foreach ($msg['tool_calls'] as $tc) {
                    $args = $tc['arguments'] ?? [];
                    if (is_array($args)) {
                        $args = empty($args) ? '{}' : json_encode((object) $args, JSON_UNESCAPED_UNICODE);
                    } elseif ($args === '' || $args === null) {
                        $args = '{}';
                    }
                    $formattedToolCalls[] = [
                        'id' => $tc['id'] ?? uniqid('call_'),
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'] ?? '',
                            'arguments' => $args,
                        ],
                    ];
                }
                $compiledMsg['content'] = null;
                $compiledMsg['tool_calls'] = $formattedToolCalls;
            }
            if (isset($msg['tool_call_id'])) {
                $compiledMsg['tool_call_id'] = $msg['tool_call_id'];
            }
            if (isset($msg['name'])) {
                $compiledMsg['name'] = $msg['name'];
            }
            $compiledMessages[] = $compiledMsg;
        }

        $payload = [
            'model' => $resolvedModel,
            'messages' => $compiledMessages,
            'stream' => $onChunk !== null,
            'max_tokens' => (int) (function_exists('config') ? config('harness.qwen_provider.max_tokens', 4096) : 4096),
            'temperature' => 0.7,
            'top_p' => 0.8,
            'repetition_penalty' => 1.1,
        ];

        // qwen3 models require enable_thinking=false for non-streaming calls
        if (str_starts_with($resolvedModel, 'qwen3') || str_starts_with($resolvedModel, 'qwq')) {
            $payload['enable_thinking'] = false;
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $isJsonMode = false;
        if (function_exists('config') && config('harness.qwen_provider.structured_output') === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
            $isJsonMode = true;
        }

        if ($isJsonMode) {
            $hasJsonKeyword = false;
            foreach ($compiledMessages as $msg) {
                if (stripos((string) ($msg['content'] ?? ''), 'json') !== false) {
                    $hasJsonKeyword = true;
                    break;
                }
            }
            if (! $hasJsonKeyword && ! empty($compiledMessages)) {
                $compiledMessages[0]['content'] .= "\nIMPORTANT: You must respond strictly in JSON format.";
                $payload['messages'] = $compiledMessages;
            }
        }

        $startTime = microtime(true);

        try {
            if ($onChunk !== null) {
                return $this->chatStreaming($payload, $startTime, $resolvedModel, $sessionId, $collector, $onChunk);
            }

            $response = $this->httpClient->post($this->baseUrl.'/chat/completions', ['json' => $payload]);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $body = json_decode($response->getBody()->getContents(), true);

            if (! isset($body['choices'][0]['message'])) {
                throw new Exception('Malformed response from Qwen Cloud API: '.json_encode($body));
            }

            $choiceMessage = $body['choices'][0]['message'];
            $content = $choiceMessage['content'] ?? '';
            $reasoningContent = $choiceMessage['reasoning_content'] ?? '';

            // Qwen3.5+ models: content = actual answer, reasoning_content = thinking
            // Always prefer content (the answer). Only use reasoning_content as fallback.
            if (empty($content) && ! empty($reasoningContent)) {
                $content = $reasoningContent;
            }

            $content = $this->parseThinkingResponse($content);
            $rawToolCalls = $choiceMessage['tool_calls'] ?? [];
            $formattedToolCalls = [];
            foreach ($rawToolCalls as $tc) {
                $function = $tc['function'] ?? [];
                $toolArgsRaw = $function['arguments'] ?? '';
                $arguments = [];
                if (! empty($toolArgsRaw)) {
                    $arguments = is_string($toolArgsRaw) ? (json_decode($toolArgsRaw, true) ?? []) : $toolArgsRaw;
                }
                $formattedToolCalls[] = [
                    'id' => $tc['id'] ?? uniqid('call_'),
                    'name' => $function['name'] ?? '',
                    'arguments' => $arguments,
                ];
            }

            $usage = [
                'prompt_tokens' => $body['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
            ];

            if ($collector && $sessionId) {
                $collector->recordLlmCall($sessionId, $resolvedModel, $payload, $body, $durationMs, $usage);
            }

            return ['content' => $content, 'tool_calls' => $formattedToolCalls];

        } catch (Exception $e) {
            throw new Exception('Qwen Cloud connection failed: '.$e->getMessage(), 0, $e);
        }
    }

    protected function parseThinkingResponse(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (str_starts_with($content, '>"') && str_ends_with($content, '""')) {
            $content = trim(substr($content, 2, -2));
        } elseif (str_starts_with($content, '>"')) {
            $content = trim(substr($content, 2));
        } elseif (str_ends_with($content, '""')) {
            $content = trim(substr($content, 0, -2));
        }

        if ($content === '') {
            return '';
        }

        // Strip <think>...</think> tags (Qwen3.5+ thinking format)
        if (preg_match('/<think>(.*?)<\/think>/s', $content, $matches)) {
            $after = trim(str_replace($matches[0], '', $content));

            return $after !== '' ? $after : '';
        }

        // Strip unclosed <think> tag (model started thinking but didn't close)
        if (preg_match('/<think>(.*)/s', $content, $matches)) {
            $after = trim(str_replace($matches[0], '', $content));

            return $after !== '' ? $after : '';
        }

        // Strip <thought>...</thought> tags (legacy format)
        if (preg_match('/<thought>(.*?)<\/thought>/s', $content, $matches)) {
            $after = trim(str_replace($matches[0], '', $content));

            return $after !== '' ? $after : trim($matches[1]);
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded) && array_key_exists('thought', $decoded)) {
            if (! empty($decoded['response'])) {
                return (string) $decoded['response'];
            }
            $jsonEnd = strpos($content, '}') + 1;
            $after = trim(substr($content, $jsonEnd));

            return $after !== '' ? $after : '';
        }

        if (is_array($decoded) && array_key_exists('text', $decoded) && count($decoded) === 1) {
            return (string) $decoded['text'];
        }

        return $content;
    }

    /**
     * Handle streaming response from Qwen Cloud (SSE).
     *
     * Properly accumulates tool_call deltas so they are not silently dropped
     * when streaming mode is active (triggered by AgentLoop setEventDispatcher).
     */
    protected function chatStreaming(
        array $payload,
        float $startTime,
        string $resolvedModel,
        ?string $sessionId,
        ?AnalyticsCollectorInterface $collector,
        callable $onChunk
    ): array {
        $response = $this->httpClient->post($this->baseUrl.'/chat/completions', [
            'json' => $payload,
            'stream' => true,
        ]);

        $fullContent = '';
        $reasoningContent = '';
        $toolCallsAccum = [];
        $body = $response->getBody();

        while (! $body->eof()) {
            $line = '';
            $char = '';
            while (! $body->eof() && ($char = $body->read(1)) !== "\n") {
                $line .= $char;
            }

            $line = trim($line);
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }

            if (str_starts_with($line, 'data: ')) {
                $parsed = json_decode(substr($line, 6), true);
                if (! is_array($parsed)) {
                    continue;
                }
                $delta = $parsed['choices'][0]['delta'] ?? [];

                $contentChunk = $delta['content'] ?? '';
                if ($contentChunk !== '') {
                    $fullContent .= $contentChunk;
                    $onChunk($contentChunk);
                }

                $reasoningChunk = $delta['reasoning_content'] ?? '';
                if ($reasoningChunk !== '') {
                    $reasoningContent .= $reasoningChunk;
                }

                foreach ($delta['tool_calls'] ?? [] as $tcDelta) {
                    $idx = $tcDelta['index'] ?? 0;
                    if (! isset($toolCallsAccum[$idx])) {
                        $toolCallsAccum[$idx] = [
                            'id' => $tcDelta['id'] ?? uniqid('call_'),
                            'type' => $tcDelta['type'] ?? 'function',
                            'function' => ['name' => '', 'arguments' => ''],
                        ];
                    }
                    if (! empty($tcDelta['id'])) {
                        $toolCallsAccum[$idx]['id'] = $tcDelta['id'];
                    }
                    $fn = $tcDelta['function'] ?? [];
                    if (! empty($fn['name'])) {
                        $toolCallsAccum[$idx]['function']['name'] .= $fn['name'];
                    }
                    if (isset($fn['arguments'])) {
                        $toolCallsAccum[$idx]['function']['arguments'] .= $fn['arguments'];
                    }
                }
            }
        }

        if (empty(trim($fullContent)) && ! empty($reasoningContent)) {
            $fullContent = $reasoningContent;
        }
        $fullContent = $this->parseThinkingResponse($fullContent);

        $formattedToolCalls = [];
        ksort($toolCallsAccum);
        foreach ($toolCallsAccum as $tc) {
            $rawArgs = $tc['function']['arguments'] ?? '';
            $arguments = ! empty($rawArgs) ? (json_decode($rawArgs, true) ?? []) : [];
            $formattedToolCalls[] = [
                'id' => $tc['id'],
                'name' => $tc['function']['name'] ?? '',
                'arguments' => $arguments,
            ];
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        if ($collector && $sessionId) {
            $collector->recordLlmCall($sessionId, $resolvedModel, $payload, ['content' => $fullContent], $durationMs, ['prompt_tokens' => 0, 'completion_tokens' => 0]);
        }

        return ['content' => $fullContent, 'tool_calls' => $formattedToolCalls];
    }
}
