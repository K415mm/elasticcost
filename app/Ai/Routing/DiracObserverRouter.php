<?php

namespace App\Ai\Routing;

/**
 * Second Dirac Complexity Router acting as a quantum observer.
 * Monitors individual batch jobs (agent loop iterations) in the fast-path-keyword pipeline.
 * Measures the state vector |ψ⟩ to decide whether it has collapsed into a final answer:
 * |ψ⟩ = c_c |Complete⟩ + c_i |Iterate⟩
 */
class DiracObserverRouter
{
    public const DECISION_COMPLETE = 'complete';

    public const DECISION_ITERATE = 'iterate';

    /**
     * Evaluate the state and decide if the loop should complete or iterate.
     *
     * @param  array  $history  Conversation history.
     * @param  array  $toolCalls  Pending tool calls generated in the current iteration.
     */
    public function evaluate(array $history, array $toolCalls): string
    {
        // 1. Supplementary Condition Check: If there are active tool calls, it violates the
        // supplementary condition of a static vacuum state, forcing further iteration.
        if (count($toolCalls) > 0) {
            return self::DECISION_ITERATE;
        }

        // Get the latest assistant response
        $lastAssistantResponse = '';
        foreach (array_reverse($history) as $message) {
            if (($message['role'] ?? '') === 'assistant') {
                $lastAssistantResponse = $message['content'] ?? '';
                break;
            }
        }

        if (empty($lastAssistantResponse)) {
            return self::DECISION_ITERATE;
        }

        // 2. Tokenize and project latest response as a normalized state vector |ψ⟩
        $tokens = $this->tokenize($lastAssistantResponse);
        $psi = $this->buildTokenWeights($tokens);

        // Calculate Class Operator average of transpositions: χ_c = n_c^-1 * Σ P_c
        $eigenvalue = $this->evaluatePermutationSymmetry($psi);

        // 3. Define probability amplitudes
        $c_c = 0.5; // Complete amplitude
        $c_i = 0.5; // Iterate amplitude

        // High permutation symmetry (eigenvalue ≈ 1) indicates convergence to a stable answer.
        // Low symmetry (eigenvalue < 0.8) indicates changing context or incomplete thoughts.
        if ($eigenvalue >= 0.8) {
            $c_c += 0.8;
        } else {
            $c_i += 0.6 * (1.0 - $eigenvalue);
        }

        // 4. Verify supplementary conditions against finality keywords
        $incompleteKeywords = ['running', 'executing', 'fetching', 'please wait', 'progress', 'processing'];
        $cleanText = strtolower($lastAssistantResponse);
        $hasIncompleteMarkers = false;
        foreach ($incompleteKeywords as $word) {
            if (str_contains($cleanText, $word)) {
                $hasIncompleteMarkers = true;
                break;
            }
        }

        if ($hasIncompleteMarkers) {
            $c_i += 1.2;
            $c_c -= 0.8;
        }

        // 5. Measure probability densities and force collapse
        $p_complete = pow(max(0.0, $c_c), 2);
        $p_iterate = pow(max(0.0, $c_i), 2);

        if ($p_complete >= $p_iterate) {
            return self::DECISION_COMPLETE;
        }

        return self::DECISION_ITERATE;
    }

    /**
     * Tokenize string.
     */
    private function tokenize(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/i', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $stopwords = ['a', 'an', 'the', 'is', 'are', 'was', 'to', 'for', 'of', 'in', 'on', 'at', 'by'];

        return array_values(array_filter($words, fn ($word) => ! in_array($word, $stopwords, true)));
    }

    /**
     * Build token weights.
     */
    private function buildTokenWeights(array $tokens): array
    {
        if (empty($tokens)) {
            return [0.0];
        }

        $weights = [];
        foreach ($tokens as $token) {
            $weight = strlen($token) * 0.1;
            $charSum = 0;
            for ($i = 0; $i < strlen($token); $i++) {
                $charSum += ord($token[$i]);
            }
            $weight += ($charSum % 100) * 0.01;
            $weights[] = $weight;
        }

        $sumSq = array_sum(array_map(fn ($w) => $w * $w, $weights));
        $norm = $sumSq > 0 ? sqrt($sumSq) : 1.0;

        return array_map(fn ($w) => $w / $norm, $weights);
    }

    /**
     * Evaluate permutation symmetry eigenvalue: <ψ | χ_c | ψ>
     */
    private function evaluatePermutationSymmetry(array $psi): float
    {
        $n = count($psi);
        if ($n <= 1) {
            return 1.0;
        }

        $similarities = [];
        for ($k = 0; $k < $n - 1; $k++) {
            $permuted = $psi;
            $temp = $permuted[$k];
            $permuted[$k] = $permuted[$k + 1];
            $permuted[$k + 1] = $temp;

            $innerProduct = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $innerProduct += $psi[$i] * $permuted[$i];
            }
            $similarities[] = $innerProduct;
        }

        return array_sum($similarities) / count($similarities);
    }
}
