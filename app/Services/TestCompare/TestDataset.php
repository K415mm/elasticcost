<?php

namespace App\Services\TestCompare;

/**
 * Enhanced Benchmark Dataset specifically targeting all 7 phpkaiharness options:
 * 1. Draft Verification (Speculative draft + fast model verification)
 * 2. Ontology RAG (pgvector document chunks retrieval from documentation_chunks)
 * 3. Semantic Cache (Warm hits & exact/fuzzy semantic cache matching)
 * 4. Quantum Memory (Superposition interference & multi-hop entanglement context synthesis)
 * 5. Context Compression (Prompt compression middleware token reduction)
 * 6. Compaction (Sliding window context compaction across multi-turn interactions)
 * 7. Cognitive Graph Memory (Fact/entity extraction & querying via QueryGraphMemoryTool)
 */
class TestDataset
{
    /**
     * @return array<int, array{agent: string, prompt: string, category: string, description: string, expects_tools: bool, target_feature: string}>
     */
    public static function all(): array
    {
        return [
            // ── 1. Draft Verification Benchmarks ───────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Provide a complex 4-tier Elasticsearch architecture plan for 2TB/day of logs with 365 days retention. Include draft specs, node allocations, and cost estimates.',
                'category' => 'draft-verification',
                'description' => 'Draft Verification: Speculative proposal generation verified by fast verification pass',
                'expects_tools' => false,
                'target_feature' => 'draft_verification',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Propose a complete MSSP pricing structure for a financial enterprise with 1,500 endpoints and 50 firewalls. Draft the L1/L2/L3 analyst breakdown and verify margins.',
                'category' => 'draft-verification',
                'description' => 'Draft Verification: Enterprise MSSP costing proposal verification',
                'expects_tools' => false,
                'target_feature' => 'draft_verification',
            ],

            // ── 2. Ontology RAG Benchmarks ────────────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'What does the RaiseGuard MDR 360 specification say about 4-Tier Index Lifecycle Management (ILM) for log retention?',
                'category' => 'ontology-rag',
                'description' => 'Ontology RAG: Document chunk retrieval from RaiseGuard MDR 360 specification',
                'expects_tools' => false,
                'target_feature' => 'ontology_rag',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'According to the uploaded architecture documentation, what are the exact storage tiering rules for Hot, Warm, Cold, and Frozen nodes?',
                'category' => 'ontology-rag',
                'description' => 'Ontology RAG: Semantic search over documentation_chunks table',
                'expects_tools' => false,
                'target_feature' => 'ontology_rag',
            ],

            // ── 3. Semantic Cache Benchmarks ──────────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'What is the RAM-to-disk ratio for Hot tier in Elasticsearch?',
                'category' => 'semantic-cache-exact',
                'description' => 'Semantic Cache: Exact prompt query (cold run populates cache, warm run hits cache)',
                'expects_tools' => false,
                'target_feature' => 'semantic_cache',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Tell me the RAM to disk ratio in Hot tier for ES clusters.',
                'category' => 'semantic-cache-fuzzy',
                'description' => 'Semantic Cache: Fuzzy prompt match for Levenshtein/semantic vector lookup',
                'expects_tools' => false,
                'target_feature' => 'semantic_cache',
            ],

            // ── 4. Quantum Memory Benchmarks ─────────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Synthesize the current quantum memory state and phase angle superposition for Security and DataProcessing agent interactions.',
                'category' => 'quantum-memory',
                'description' => 'Quantum Memory: Multi-hop entanglement traversal and phase-angle superposition retrieval',
                'expects_tools' => false,
                'target_feature' => 'quantum_memory',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'What entangled cognitive nodes exist in the quantum memory graph regarding client sizing constraints?',
                'category' => 'quantum-memory',
                'description' => 'Quantum Memory: Retrieval of superpositioned context envelope',
                'expects_tools' => false,
                'target_feature' => 'quantum_memory',
            ],

            // ── 5. Context Compression Benchmarks ─────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'IMPORTANT NOTICE: Please read this entire detailed system instruction carefully before responding. System context: ElasticCost is an enterprise AI-assisted Elasticsearch sizing and MSSP costing platform. Below are extensive details, guidelines, rules, and background information that you must adhere to strictly. Rule 1: Always be precise. Rule 2: Always output JSON or markdown tables when requested. Rule 3: Use official RAM-to-disk ratios (Hot 1:30, Warm 1:100, Cold 1:500, Frozen 1:1000). Now, given 500GB/day of logs with 30 days retention in Hot tier only, what is the exact hardware requirement?',
                'category' => 'context-compression',
                'description' => 'Context Compression: Verbose prompt testing compression middleware token reduction',
                'expects_tools' => false,
                'target_feature' => 'context_compression',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Background Overview: We are conducting an in-depth infrastructure audit for our managed security operations center (SOC). The client has multiple branches across Europe and North Africa with diverse log volumes, compliance requirements, and retention mandates. Question: How do we calculate the MSSP SOC staffing cost for a client with 200 devices across 3 asset types? Explain the L1, L2, L3 pricing model in detail.',
                'category' => 'context-compression',
                'description' => 'Context Compression: Multi-paragraph prompt testing noise stripping',
                'expects_tools' => false,
                'target_feature' => 'context_compression',
            ],

            // ── 6. Compaction Benchmarks ──────────────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'I have a 10-node cluster. Turn 1: Add 500GB/day log intake. Turn 2: Change retention to 60 days. Turn 3: Add 1 replica shard. What is the total storage requirement after all turns?',
                'category' => 'compaction',
                'description' => 'Compaction: Multi-turn interaction scenario testing sliding window compaction',
                'expects_tools' => false,
                'target_feature' => 'compaction',
            ],

            // ── 7. Cognitive Graph Memory Benchmarks ──────────────────────────
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Query the cognitive graph memory for any recorded facts about client device inventories or global pricing rates.',
                'category' => 'cognitive-graph-memory',
                'description' => 'Cognitive Graph Memory: Executing QueryGraphMemoryTool to search harness_facts',
                'expects_tools' => true,
                'target_feature' => 'cognitive_graph_memory',
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'List all clients in the system with their current device counts.',
                'category' => 'db-query-simple',
                'description' => 'Database Query: Simple database query via GetSystemDetailsTool',
                'expects_tools' => true,
                'target_feature' => 'db_tools',
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'What are the current global settings? Show me all configured rates and prices.',
                'category' => 'db-query-settings',
                'description' => 'Database Query: Query global settings table via GetSystemDetailsTool',
                'expects_tools' => true,
                'target_feature' => 'db_tools',
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Add 2 FortiGate firewalls to Acme Corp device count.',
                'category' => 'db-update-simple',
                'description' => 'Database Update: Update device counts via UpdateClientInventoryTool',
                'expects_tools' => true,
                'target_feature' => 'db_tools',
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Set the SIEM agent monthly cost per device to 25 USD.',
                'category' => 'db-update-setting',
                'description' => 'Database Update: Update global setting via UpdateGlobalSettingTool',
                'expects_tools' => true,
                'target_feature' => 'db_tools',
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Create a new client named "TechCorp Industries" with 100 Active Directory devices, 30 FortiGate firewalls, and 50 EDR endpoints.',
                'category' => 'db-create-client',
                'description' => 'Database Create: Multi-step client creation via CreateClientTool',
                'expects_tools' => true,
                'target_feature' => 'db_tools',
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => '3tini el liste mta3 el clients w chnou 3andhom men devices b kol type.',
                'category' => 'db-query-tunisian',
                'description' => 'Tunisian Arabic DB Query: Query inventory in dialect',
                'expects_tools' => true,
                'target_feature' => 'db_tools',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Quelle est la différence entre les tiers Hot, Warm, Cold et Frozen en termes de ratio RAM-disque?',
                'category' => 'sizing-french',
                'description' => 'French Sizing Query: Tier differences in French',
                'expects_tools' => false,
                'target_feature' => 'multilingual',
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => '3andi cluster ES ken n7eb 1TB log nhar w 90 jour retention, chnou lazem men hardware? hot w warm tiers',
                'category' => 'sizing-tunisian',
                'description' => 'Tunisian Sizing Query: Sizing calculation in Tunisian Arabic',
                'expects_tools' => false,
                'target_feature' => 'multilingual',
            ],
        ];
    }

    /**
     * Get only the ElasticCost Assistant prompts.
     */
    public static function elasticCostPrompts(): array
    {
        return array_filter(self::all(), fn ($r) => $r['agent'] === 'ElasticCostAssistant');
    }

    /**
     * Get only the RG SOC Engineer prompts.
     */
    public static function rgSocPrompts(): array
    {
        return array_filter(self::all(), fn ($r) => $r['agent'] === 'RgSocEngineer');
    }
}
