<?php

namespace Phpkaiharness\Optimize;

use PDO;
use Phpkaiharness\Contracts\SemanticMemoryInterface;
use Phpkaiharness\Monitor\SqliteMonitorStore;

/**
 * Semantic Caching with vector-based matching (Palantir AIP-inspired).
 *
 * Checks incoming prompts against cached responses using:
 * 1. Vector semantic search (via SemanticMemoryInterface) - true AI-native matching
 * 2. Exact string matching
 * 3. Levenshtein fuzzy string distance similarity (fallback)
 */
class SemanticCache
{
    private ?PDO $pdo = null;

    private float $threshold;

    private string $dbPath;

    private ?SemanticMemoryInterface $semanticMemory = null;

    /** @var array<string> Patterns that make a response ineligible for caching */
    private array $rejectPatterns;

    private bool $rejectEmpty;

    private int $rejectMinLength;

    private string $namespace;

    public function __construct(
        ?PDO $pdo = null,
        float $threshold = 0.88,
        ?string $dbPath = null,
        ?SemanticMemoryInterface $semanticMemory = null,
        ?array $eligibilityConfig = null,
        string $namespace = 'default'
    ) {
        $this->pdo = $pdo;
        $this->threshold = $threshold;
        $this->dbPath = $dbPath ?? (getenv('PHPKAIHARNESS_DB') ?: SqliteMonitorStore::defaultDbPath());
        $this->semanticMemory = $semanticMemory;
        $this->namespace = $namespace;

        // Resolve eligibility config from parameter or config()
        if ($eligibilityConfig !== null) {
            $this->rejectPatterns = $eligibilityConfig['reject_patterns'] ?? ['⚠️', 'cURL error', 'LLM execution error', 'iteration limit'];
            $this->rejectEmpty = $eligibilityConfig['reject_empty'] ?? true;
            $this->rejectMinLength = $eligibilityConfig['reject_min_length'] ?? 20;
        } elseif (function_exists('config')) {
            $cfg = config('harness.cache.eligibility', []);
            $this->rejectPatterns = $cfg['reject_patterns'] ?? ['⚠️', 'cURL error', 'LLM execution error', 'iteration limit'];
            $this->rejectEmpty = $cfg['reject_empty'] ?? true;
            $this->rejectMinLength = $cfg['reject_min_length'] ?? 20;
        } else {
            $this->rejectPatterns = ['⚠️', 'cURL error', 'LLM execution error', 'iteration limit'];
            $this->rejectEmpty = true;
            $this->rejectMinLength = 20;
        }
    }

    /**
     * Set or update the SemanticMemoryInterface for vector-based lookups.
     */
    public function setSemanticMemory(?SemanticMemoryInterface $semanticMemory): self
    {
        $this->semanticMemory = $semanticMemory;

        return $this;
    }

    /**
     * Check if a response is eligible for caching.
     *
     * Rejects empty responses, error responses, iteration-limit messages,
     * and responses matching configured reject patterns.
     */
    public function isCacheable(string $response): bool
    {
        if ($this->rejectEmpty && trim($response) === '') {
            return false;
        }

        if (mb_strlen($response) < $this->rejectMinLength) {
            return false;
        }

        $lower = strtolower($response);
        foreach ($this->rejectPatterns as $pattern) {
            if (str_contains($lower, strtolower($pattern))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Invalidate cached entries matching the given prompt pattern.
     *
     * Called after tool writes that mutate state, to prevent stale cache hits.
     */
    public function invalidate(?string $promptPattern = null): int
    {
        $db = $this->getPdo();
        if (! $db) {
            return 0;
        }

        try {
            if ($promptPattern !== null) {
                $stmt = $db->prepare(
                    "DELETE FROM harness_sessions
                     WHERE prompt LIKE :pattern
                       AND method != 'semantic-cache-hit'"
                );
                $stmt->execute([':pattern' => '%'.$promptPattern.'%']);

                return $stmt->rowCount();
            }

            // Invalidate all non-cache-hit sessions
            $stmt = $db->prepare(
                "DELETE FROM harness_sessions
                 WHERE method != 'semantic-cache-hit'"
            );
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Build the SQL fragment for rejecting ineligible responses.
     */
    private function rejectClause(): string
    {
        $clauses = [
            "response != ''",
            "method != 'semantic-cache-hit'",
        ];

        foreach ($this->rejectPatterns as $pattern) {
            $escaped = str_replace("'", "''", $pattern);
            $clauses[] = "response NOT LIKE '%{$escaped}%'";
        }

        return implode(' AND ', $clauses);
    }

    /**
     * Resolve the PDO connection if not already provided.
     */
    private function getPdo(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (file_exists($this->dbPath)) {
            try {
                $this->pdo = new PDO('sqlite:'.$this->dbPath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Return null if cannot connect
                return null;
            }
        }

        return $this->pdo;
    }

    /**
     * Lookup a prompt in the cache using three-tier matching:
     * 1. Vector semantic search (AI-native matching) - if SemanticMemoryInterface available
     * 2. Exact string match
     * 3. Levenshtein fuzzy string similarity
     *
     * Returns the cached response string on success, or null on cache miss.
     */
    public function lookup(string $prompt): ?string
    {
        $cleanPrompt = trim($prompt);

        // 1. Vector semantic search (Palantir AIP-style AI-native caching)
        if ($this->semanticMemory !== null) {
            $semanticResult = $this->lookupSemantic($cleanPrompt);
            if ($semanticResult !== null) {
                return $semanticResult;
            }
        }

        $db = $this->getPdo();
        if (! $db) {
            return null;
        }

        $reject = $this->rejectClause();

        // 2. Try exact match (exclude cache hits and ineligible responses)
        try {
            $stmt = $db->prepare(
                "SELECT response FROM harness_sessions
                 WHERE prompt = :prompt
                   AND {$reject}
                 ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->execute([':prompt' => $cleanPrompt]);
            $row = $stmt->fetch();
            if ($row && ! empty($row['response'])) {
                return $row['response'];
            }
        } catch (\Exception $e) {
            // Table might not exist yet
            return null;
        }

        // 3. Try Levenshtein fuzzy match on recent sessions (limit to 150 to keep it fast)
        try {
            $stmt = $db->query(
                "SELECT prompt, response FROM harness_sessions
                 WHERE {$reject}
                 ORDER BY created_at DESC LIMIT 150"
            );
            $sessions = $stmt->fetchAll();

            foreach ($sessions as $session) {
                $cachedPrompt = trim($session['prompt']);
                if (empty($cachedPrompt)) {
                    continue;
                }

                // If prompts are very different in length, skip to save computation
                $lenNew = strlen($cleanPrompt);
                $lenCached = strlen($cachedPrompt);
                $maxLen = max($lenNew, $lenCached);
                if ($maxLen === 0) {
                    continue;
                }

                $lenDiff = abs($lenNew - $lenCached);
                if (($lenDiff / $maxLen) > (1.0 - $this->threshold)) {
                    continue;
                }

                // Compute levenshtein distance
                // Note: levenshtein has a limit of 255 characters. For longer strings,
                // we check a substring or exact match only.
                if ($maxLen <= 255) {
                    $dist = levenshtein($cleanPrompt, $cachedPrompt);
                    $similarity = 1.0 - ($dist / $maxLen);

                    if ($similarity >= $this->threshold) {
                        return $session['response'];
                    }
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Vector-based semantic lookup using SemanticMemoryInterface.
     *
     * Searches for semantically similar prompts (e.g., "What is the weather?" matches "Tell me the forecast")
     * instead of relying on string similarity.
     */
    private function lookupSemantic(string $prompt): ?string
    {
        if ($this->semanticMemory === null) {
            return null;
        }

        try {
            $results = $this->semanticMemory->search(
                query: $prompt,
                threshold: $this->threshold,
                limit: 1
            );

            if (! empty($results) && $results[0]['score'] >= $this->threshold) {
                $text = $results[0]['text'];
                // Don't return ineligible cached responses
                if (! $this->isCacheable($text)) {
                    return null;
                }

                return $text;
            }
        } catch (\Exception $e) {
            // Silently fall back to string-based matching
            return null;
        }

        return null;
    }

    /**
     * Store a prompt-response pair in the semantic memory cache.
     *
     * Note: The embedding must be computed externally before calling this method,
     * as the cache layer does not have direct access to an embedding model.
     *
     * @param  string  $prompt  The original user prompt/query.
     * @param  string  $response  The LLM response to cache.
     * @param  array<float>  $embedding  Pre-computed vector embedding of the prompt.
     */
    public function store(string $prompt, string $response, array $embedding): void
    {
        if ($this->semanticMemory === null) {
            return;
        }

        if (! $this->isCacheable($response)) {
            return;
        }

        try {
            $this->semanticMemory->addMemory(
                text: $response,
                embedding: $embedding,
                source: 'semantic-cache:'.substr($prompt, 0, 100)
            );
        } catch (\Exception $e) {
            // Silently ignore storage failures - caching is best-effort
        }
    }
}
