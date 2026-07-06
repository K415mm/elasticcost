<?php

namespace Phpkaiharness\Tests;

use PDO;
use Phpkaiharness\Contracts\SemanticMemoryInterface;
use Phpkaiharness\Monitor\SqliteMonitorStore;
use Phpkaiharness\Optimize\SemanticCache;

class CacheEligibilityTest extends PhpkaiharnessTestCase
{
    private string $dbPath;

    private ?PDO $pdo;

    private SqliteMonitorStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_cache_'.uniqid().'.db';
        $this->pdo = new PDO('sqlite:'.$this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->store = new SqliteMonitorStore($this->dbPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
    }

    public function test_is_cacheable_rejects_empty_responses(): void
    {
        $cache = new SemanticCache(pdo: $this->pdo, threshold: 0.88, dbPath: $this->dbPath);

        $this->assertFalse($cache->isCacheable(''));
        $this->assertFalse($cache->isCacheable('   '));
    }

    public function test_is_cacheable_rejects_short_responses(): void
    {
        $cache = new SemanticCache(pdo: $this->pdo, threshold: 0.88, dbPath: $this->dbPath);

        $this->assertFalse($cache->isCacheable('short'));
        $this->assertFalse($cache->isCacheable('too brief response'));
    }

    public function test_is_cacheable_rejects_error_patterns(): void
    {
        $cache = new SemanticCache(pdo: $this->pdo, threshold: 0.88, dbPath: $this->dbPath);

        $this->assertFalse($cache->isCacheable('⚠️ LLM execution error: something went wrong'));
        $this->assertFalse($cache->isCacheable('cURL error 28: connection timed out'));
        $this->assertFalse($cache->isCacheable('The request hit the iteration limit and was stopped'));
    }

    public function test_is_cacheable_accepts_valid_responses(): void
    {
        $cache = new SemanticCache(pdo: $this->pdo, threshold: 0.88, dbPath: $this->dbPath);

        $this->assertTrue($cache->isCacheable('The client was successfully created with the following details and configuration.'));
        $this->assertTrue($cache->isCacheable('Database migration completed. All 15 tables were created without errors.'));
    }

    public function test_is_cacheable_uses_custom_eligibility_config(): void
    {
        $cache = new SemanticCache(
            pdo: $this->pdo,
            threshold: 0.88,
            dbPath: $this->dbPath,
            eligibilityConfig: [
                'reject_patterns' => ['forbidden'],
                'reject_empty' => true,
                'reject_min_length' => 50,
            ]
        );

        $this->assertFalse($cache->isCacheable('this response contains forbidden content and is long enough'));
        $this->assertFalse($cache->isCacheable('too short to cache'));
        $this->assertTrue($cache->isCacheable('This is a perfectly valid response that is long enough to be cached properly.'));
    }

    public function test_store_rejects_ineligible_responses(): void
    {
        // Use a mock semantic memory to track what gets stored
        $mockMemory = new class implements SemanticMemoryInterface
        {
            public array $stored = [];

            public function addMemory(string $text, array $embedding, string $source = ''): void
            {
                $this->stored[] = $text;
            }

            public function search(string $query, float $threshold = 0.3, int $limit = 3): array
            {
                return [];
            }
        };

        $cache = new SemanticCache(
            pdo: $this->pdo,
            threshold: 0.88,
            dbPath: $this->dbPath,
            semanticMemory: $mockMemory
        );

        // Error response should not be stored
        $cache->store('test prompt', '⚠️ LLM execution error', [0.1, 0.2]);
        $this->assertEmpty($mockMemory->stored);

        // Valid response should be stored
        $cache->store('test prompt', 'This is a valid cached response that is long enough.', [0.1, 0.2]);
        $this->assertCount(1, $mockMemory->stored);
    }

    public function test_invalidate_removes_matching_entries(): void
    {
        $sessionId = 'test-session-'.uniqid();
        $this->store->startSession($sessionId, 'test prompt about clients', 'test');
        $this->store->endSession($sessionId, 'valid response here that is long enough', 100, 1);

        $cache = new SemanticCache(pdo: $this->pdo, threshold: 0.88, dbPath: $this->dbPath);

        $invalidated = $cache->invalidate('clients');
        $this->assertGreaterThan(0, $invalidated);
    }

    public function test_invalidate_all_removes_all_non_cache_hit_entries(): void
    {
        $sid1 = 'test-session-'.uniqid();
        $sid2 = 'test-session-'.uniqid();
        $this->store->startSession($sid1, 'prompt one', 'test');
        $this->store->endSession($sid1, 'response one is valid and long enough', 100, 1);
        $this->store->startSession($sid2, 'prompt two', 'test');
        $this->store->endSession($sid2, 'response two is valid and long enough', 100, 1);

        $cache = new SemanticCache(pdo: $this->pdo, threshold: 0.88, dbPath: $this->dbPath);

        $invalidated = $cache->invalidate();
        $this->assertGreaterThan(0, $invalidated);
    }

    public function test_match_digits_compares_numbers_properly(): void
    {
        // Identical prompts
        $this->assertTrue(SemanticCache::matchDigits('client id 2', 'client id 2'));

        // Different numeric values
        $this->assertFalse(SemanticCache::matchDigits('client id 2', 'client id 1'));

        // No digits present
        $this->assertTrue(SemanticCache::matchDigits('hello world', 'hello universe'));

        // One has digits, other does not
        $this->assertFalse(SemanticCache::matchDigits('hello world 5', 'hello universe'));

        // Different order but same digits should match (digits set)
        $this->assertTrue(SemanticCache::matchDigits('clients 1 and 2', 'clients 2 and 1'));
    }
}
