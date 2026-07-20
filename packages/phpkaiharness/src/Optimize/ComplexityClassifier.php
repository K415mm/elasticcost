<?php

namespace Phpkaiharness\Optimize;

/**
 * Cynefin complexity classifier using Dirac Bra-Ket state projection.
 * Models the query's state vector |ψ⟩ as a superposition of Simple, Complicated, and Complex bases:
 * |ψ⟩ = c_s |Simple⟩ + c_d |Complicated⟩ + c_x |Complex⟩
 * Triggers state collapse (measurement) into the highest probability density base.
 */
class ComplexityClassifier
{
    public const DOMAIN_SIMPLE = 'simple';

    public const DOMAIN_COMPLICATED = 'complicated';

    public const DOMAIN_COMPLEX = 'complex';

    /**
     * Classify prompt complexity using Dirac state projection amplitudes.
     *
     * @param  string  $prompt  The user query.
     * @param  array<string>  $registeredTools  Active agent tools.
     */
    public static function classify(string $prompt, array $registeredTools = []): string
    {
        // E4: Load tunable parameters from config (falls back to original defaults)
        $cfg = [];
        if (function_exists('config')) {
            try {
                $cfg = config('harness.routing.complexity', []);
            } catch (\Throwable $e) { /* use defaults */
            }
        }

        $cleanPrompt = strtolower(trim($prompt));

        // 1. Tokenize query into high-value semantic particles (tokens)
        $tokens = self::tokenize($cleanPrompt);

        // 2. Program Permutations as Dynamical Variables & compute Class Operator average
        $psi = self::buildTokenWeights($tokens);
        $eigenvalue = self::evaluatePermutationSymmetry($psi);

        // 3. Define Hilbert space coefficients (probability amplitudes) — configurable seeds
        $c_s = (float) ($cfg['simple_amplitude'] ?? 1.0);
        $c_d = (float) ($cfg['complicated_amplitude'] ?? 0.0);
        $c_x = (float) ($cfg['complex_amplitude'] ?? 0.0);

        // 4. Project Permutation Symmetry onto Symmetrical vs. Antisymmetrical bases
        $symmetryThreshold = (float) ($cfg['symmetry_threshold'] ?? 0.8);
        if ($eigenvalue >= $symmetryThreshold) {
            $c_s += 1.2;
        } else {
            $c_x += 0.8 * (1.0 - $eigenvalue);
        }

        // 5. Project mutating tools onto the Complex basis
        $mutatingKeywords = $cfg['mutating_keywords']
            ?? ['update', 'delete', 'modify', 'create', 'run', 'simulate', 'change', 'ingest', 'set'];

        $hasActionTools = false;
        foreach ($registeredTools as $tool) {
            $toolLower = strtolower($tool);
            foreach ($mutatingKeywords as $keyword) {
                if (str_contains($toolLower, $keyword)) {
                    $hasActionTools = true;
                    break 2;
                }
            }
        }

        $hasMutatingIntent = false;
        foreach ($mutatingKeywords as $keyword) {
            if (preg_match("/\b{$keyword}\b/i", $cleanPrompt)) {
                $hasMutatingIntent = true;
                break;
            }
        }

        if ($hasActionTools) {
            $c_x += 2.0;
            $c_s -= 1.0;
        }
        if ($hasMutatingIntent) {
            $c_x += 2.0;
            $c_s -= 1.0;
        }

        // 6. Project database entities / RAG requirement onto the Complicated basis
        $entityKeywords = $cfg['entity_keywords']
            ?? ['client', 'scenario', 'asset', 'sizing', 'profit', 'mssp', 'user', 'role', 'permission'];

        $hasEntities = false;
        foreach ($entityKeywords as $entity) {
            if (preg_match("/\b{$entity}\b/i", $cleanPrompt)) {
                $hasEntities = true;
                break;
            }
        }

        if ($hasEntities) {
            $c_d += 1.8;
            $c_s -= 1.0;
        }
        if (count($registeredTools) > 0 && ! $hasActionTools) {
            $c_d += 1.8;
            $c_s -= 1.0;
        }

        // 7. Calculate measurement probability densities
        $p_simple = pow(max(0.0, $c_s), 2);
        $p_complicated = pow(max(0.0, $c_d), 2);
        $p_complex = pow(max(0.0, $c_x), 2);

        // 8. Trigger state collapse
        $maxProb = max($p_simple, $p_complicated, $p_complex);

        if ($maxProb === $p_complex && $c_x > 0.0) {
            return self::DOMAIN_COMPLEX;
        }
        if ($maxProb === $p_complicated && $c_d > 0.0) {
            return self::DOMAIN_COMPLICATED;
        }

        return self::DOMAIN_SIMPLE;
    }

    /**
     * Tokenize the prompt to extract semantic particles, ignoring minor stopwords.
     *
     * @return array<string>
     */
    private static function tokenize(string $prompt): array
    {
        $words = preg_split('/[^a-z0-9]+/i', strtolower($prompt), -1, PREG_SPLIT_NO_EMPTY);
        $stopwords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'to', 'for', 'of', 'in', 'on', 'at', 'by', 'me', 'you', 'it'];

        return array_values(array_filter($words, fn ($word) => ! in_array($word, $stopwords, true)));
    }

    /**
     * Build the semantic weight vector |ψ⟩ for the extracted tokens.
     *
     * @param  array<string>  $tokens
     * @return array<float>
     */
    private static function buildTokenWeights(array $tokens): array
    {
        if (empty($tokens)) {
            return [0.0];
        }

        $weights = [];
        foreach ($tokens as $token) {
            // Assign a semantic weight based on character length and character sum
            $weight = strlen($token) * 0.1;
            $charSum = 0;
            for ($i = 0; $i < strlen($token); $i++) {
                $charSum += ord($token[$i]);
            }
            $weight += ($charSum % 100) * 0.01;
            $weights[] = $weight;
        }

        // Normalize the state vector |ψ⟩
        $sumSq = array_sum(array_map(fn ($w) => $w * $w, $weights));
        $norm = $sumSq > 0 ? sqrt($sumSq) : 1.0;
        $weights = array_map(fn ($w) => $w / $norm, $weights);

        return $weights;
    }

    /**
     * Apply permutation operators and evaluate the symmetry eigenvalue.
     * Computes the effect of the class operator χ_c = n_c^-1 * Σ P_c
     * returns an eigenvalue close to 1.0 for high symmetry, or lower/negative for asymmetry.
     *
     * @param  array<float>  $psi
     */
    private static function evaluatePermutationSymmetry(array $psi): float
    {
        $n = count($psi);
        if ($n <= 1) {
            return 1.0; // Single particle system is always symmetrical
        }

        // Apply transposition operators P_k that swap adjacent components
        // and measure the inner product/similarity of the resulting states.
        $similarities = [];
        for ($k = 0; $k < $n - 1; $k++) {
            $permuted = $psi;
            // Swap adjacent components (permutation operator P_k)
            $temp = $permuted[$k];
            $permuted[$k] = $permuted[$k + 1];
            $permuted[$k + 1] = $temp;

            // Measure inner product: <ψ | P_k | ψ>
            $innerProduct = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $innerProduct += $psi[$i] * $permuted[$i];
            }
            $similarities[] = $innerProduct;
        }

        // Compute average eigenvalue of class operator χ_c: <ψ | χ_c | ψ>
        // Because χ_c = 1/(n-1) * Σ P_k
        return array_sum($similarities) / count($similarities);
    }
}
