<?php

namespace Tests\Unit;

use Phpkaiharness\Optimize\SemanticCache;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the quantum-inspired semantic core matching added to
 * SemanticCache to prevent cross-prompt false cache hits.
 *
 * Bug: the previous Levenshtein-only fuzzy matcher would return the SAME
 * cached response for structurally similar but semantically different
 * prompts (e.g. sharing filler words), causing every subsequent request
 * in a test run to receive an identical, stale answer instead of invoking
 * the LLM.
 */
class SemanticCacheSemanticCoreTest extends TestCase
{
    public function test_extract_semantic_core_strips_filler_words(): void
    {
        $core = SemanticCache::extractSemanticCore('Hey, could you please tell me the total cost of the EC2 instance?');

        $this->assertNotContains('the', $core);
        $this->assertNotContains('please', $core);
        $this->assertNotContains('tell', $core);
        $this->assertContains('total', $core);
        $this->assertContains('cost', $core);
        $this->assertContains('instance', $core);
    }

    public function test_extract_semantic_core_handles_french_stop_words(): void
    {
        $core = SemanticCache::extractSemanticCore('Vous donnez le coût total du serveur pour nous');

        $this->assertNotContains('vous', $core);
        $this->assertNotContains('donnez', $core);
        $this->assertNotContains('le', $core);
        $this->assertContains('coût', $core);
        $this->assertContains('total', $core);
        $this->assertContains('serveur', $core);
    }

    public function test_semantic_core_hash_is_order_sensitive(): void
    {
        // Non-commutative: "user hacked system" must differ from "system hacked user"
        $hashA = SemanticCache::semanticCoreHash('The user hacked the system');
        $hashB = SemanticCache::semanticCoreHash('The system hacked the user');

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_semantic_core_hash_is_stable_for_identical_prompts(): void
    {
        $hashA = SemanticCache::semanticCoreHash('What is the total EC2 cost this month?');
        $hashB = SemanticCache::semanticCoreHash('What is the total EC2 cost this month?');

        $this->assertSame($hashA, $hashB);
    }

    public function test_semantic_core_hash_differs_for_different_intents(): void
    {
        $hashA = SemanticCache::semanticCoreHash('What is the total EC2 cost this month?');
        $hashB = SemanticCache::semanticCoreHash('What is the total S3 storage usage this month?');

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_core_overlap_is_one_for_identical_cores(): void
    {
        $core = SemanticCache::extractSemanticCore('total cost EC2 instance');
        $overlap = SemanticCache::coreOverlap($core, $core);

        $this->assertSame(1.0, $overlap);
    }

    public function test_core_overlap_is_zero_for_completely_different_cores(): void
    {
        $coreA = SemanticCache::extractSemanticCore('EC2 instance pricing details');
        $coreB = SemanticCache::extractSemanticCore('database backup schedule configuration');

        $overlap = SemanticCache::coreOverlap($coreA, $coreB);

        $this->assertSame(0.0, $overlap);
    }

    public function test_core_overlap_is_partial_for_related_but_different_prompts(): void
    {
        // Regression case: these two prompts are Levenshtein-similar (share
        // most words) but ask about DIFFERENT AWS services. The old fuzzy
        // matcher would treat these as a cache hit; the semantic core
        // overlap guard should flag this as low overlap.
        $coreA = SemanticCache::extractSemanticCore('What is the total cost of running an EC2 instance?');
        $coreB = SemanticCache::extractSemanticCore('What is the total cost of running an RDS instance?');

        $overlap = SemanticCache::coreOverlap($coreA, $coreB);

        // "ec2" vs "rds" differ; overlap should not be perfect
        $this->assertLessThan(1.0, $overlap);
        $this->assertGreaterThan(0.0, $overlap);
    }

    public function test_core_overlap_empty_arrays_returns_zero(): void
    {
        $this->assertSame(0.0, SemanticCache::coreOverlap([], []));
        $this->assertSame(0.0, SemanticCache::coreOverlap(['token'], []));
    }

    public function test_normalize_prompt_strips_task_wrapper(): void
    {
        $wrapped = "TASK: What is the EC2 cost?\n\nCONVERSATION CONTEXT: some prior turns";
        $normalized = SemanticCache::normalizePrompt($wrapped);

        $this->assertSame('what is the ec2 cost?', $normalized);
    }

    public function test_different_prompts_with_shared_vocabulary_produce_low_overlap(): void
    {
        // Simulates the reported bug: two different test-suite prompts that
        // share generic domain words ("cost", "show", "please") but ask
        // about entirely different resources should NOT be considered a
        // semantic match.
        $coreA = SemanticCache::extractSemanticCore('Please show me the cost breakdown for Lambda functions');
        $coreB = SemanticCache::extractSemanticCore('Please show me the cost breakdown for RDS databases');

        $overlap = SemanticCache::coreOverlap($coreA, $coreB);

        // Should share "cost" and "breakdown" but differ on the core subject
        $this->assertLessThan(0.9, $overlap);
    }
}
