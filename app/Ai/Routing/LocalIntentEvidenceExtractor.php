<?php

namespace App\Ai\Routing;

final class LocalIntentEvidenceExtractor
{
    /** @var array<string> */
    private const ACTION_VERBS = [
        'add', 'allocate', 'assign', 'change', 'create', 'delete', 'disable',
        'enable', 'modify', 'register', 'remove', 'set', 'update',
    ];

    /** @var array<string> */
    private const TARGETS = [
        'active directory', 'agent', 'allocation', 'asset', 'client', 'count',
        'device', 'edr', 'mdr', 'price', 'salary', 'setting', 'siem', 'status',
    ];

    /** @var array<string> */
    private const READ_VERBS = [
        'check', 'find', 'get', 'list', 'retrieve', 'show', 'view', 'what',
        'which', 'who', 'when', 'where', 'how much',
    ];

    /** @var array<string> */
    private const NEGATIONS = [
        'cannot', 'could not', 'do not', 'don\'t', 'never', 'no need to', 'not',
        'without', 'would not', 'wouldn\'t',
    ];

    /** @var array<string> */
    private const HYPOTHETICAL_MARKERS = [
        'could you explain', 'how would', 'how do i', 'how can i', 'if i',
        'what if', 'would it be possible',
    ];

    public function extract(string $prompt): LocalIntentEvidence
    {
        $normalized = $this->normalize($prompt);
        $tokens = $this->tokens($normalized);
        $actionVerbs = $this->matchingTerms($tokens, self::ACTION_VERBS);
        $targets = $this->matchingTerms($tokens, self::TARGETS, true);
        $readVerbs = $this->matchingTerms($tokens, self::READ_VERBS, true);
        $hasNegation = $this->containsPhrase($normalized, self::NEGATIONS);
        $isQuestion = str_contains($normalized, '?') || $this->containsPhrase($normalized, self::READ_VERBS);
        $isHypothetical = $this->containsPhrase($normalized, self::HYPOTHETICAL_MARKERS);
        $requiresCurrentState = $targets !== [] && ($actionVerbs !== [] || $readVerbs !== []);
        $signals = [];
        $confidence = 0.0;

        if ($actionVerbs !== []) {
            $signals[] = 'action_verb:'.implode(',', $actionVerbs);
            $confidence += 0.6;
        }
        if ($targets !== []) {
            $signals[] = 'target:'.implode(',', $targets);
            $confidence += 0.3;
        }
        if ($readVerbs !== []) {
            $signals[] = 'read_verb:'.implode(',', $readVerbs);
            $confidence += 0.2;
        }
        if ($isQuestion) {
            $signals[] = 'question_form';
            $confidence -= 0.15;
        }
        if ($hasNegation) {
            $signals[] = 'negated';
            $confidence -= 0.7;
        }
        if ($isHypothetical) {
            $signals[] = 'hypothetical';
            $confidence -= 0.4;
        }

        return new LocalIntentEvidence(
            prompt: $normalized,
            actionVerbs: $actionVerbs,
            targets: $targets,
            signals: $signals,
            hasNegation: $hasNegation,
            isQuestion: $isQuestion,
            isHypothetical: $isHypothetical,
            requiresCurrentState: $requiresCurrentState,
            confidence: round(max(0.0, min(1.0, $confidence)), 3),
        );
    }

    private function normalize(string $prompt): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $prompt) ?? $prompt));
    }

    /**
     * @return array<string>
     */
    private function tokens(string $prompt): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $prompt, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  array<string>  $tokens
     * @param  array<string>  $terms
     * @return array<string>
     */
    private function matchingTerms(array $tokens, array $terms, bool $allowPhrase = false): array
    {
        $prompt = implode(' ', $tokens);
        $matches = [];

        foreach ($terms as $term) {
            if ($allowPhrase && str_contains($term, ' ')) {
                if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?=$|[^\p{L}\p{N}])/u', $prompt) === 1) {
                    $matches[] = $term;
                }

                continue;
            }

            if (in_array($term, $tokens, true)) {
                $matches[] = $term;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string>  $phrases
     */
    private function containsPhrase(string $prompt, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?=$|[^\p{L}\p{N}])/u', $prompt) === 1) {
                return true;
            }
        }

        return false;
    }
}
