<?php

namespace App\Services\TestCompare;

/**
 * 20 test requests: 10 ElasticCost Assistant + 10 RG SOC Engineer.
 * Varied complexity, languages (English, French, Tunisian Arabic), and tool-call requirements.
 */
class TestDataset
{
    /**
     * @return array<int, array{agent: string, prompt: string, category: string, description: string, expects_tools: bool}>
     */
    public static function all(): array
    {
        return [
            // ── ElasticCost Assistant (10) ──────────────────────────────────
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'What is the RAM-to-disk ratio for Hot tier in Elasticsearch?',
                'category' => 'sizing-basic',
                'description' => 'Simple factual question about ES sizing ratios',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'I need to size an Elasticsearch cluster for 500GB/day of logs with 30 days retention. Hot tier only. What hardware do I need?',
                'category' => 'sizing-calculation',
                'description' => 'Multi-step sizing calculation requiring ratios and formulas',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Quelle est la différence entre les tiers Hot, Warm, Cold et Frozen en termes de ratio RAM-disque?',
                'category' => 'sizing-french',
                'description' => 'French language question about ES tier differences',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'How do I calculate the MSSP SOC staffing cost for a client with 200 devices across 3 asset types? Explain the L1, L2, L3 pricing model.',
                'category' => 'costing-calculation',
                'description' => 'SOC costing calculation with staffing tiers',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => '3andi cluster ES ken n7eb 1TB log nhar w 90 jour retention, chnou lazem men hardware? hot w warm tiers',
                'category' => 'sizing-tunisian',
                'description' => 'Tunisian Arabic dialect — sizing 1TB/day 90-day retention',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'What is the impact of replica shards on storage requirements? If I have 500GB of primary data with 1 replica, what is my total storage need?',
                'category' => 'sizing-replicas',
                'description' => 'Replica impact calculation',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Explain the assurance markup benefit in MSSP costing. How does it affect the final client MRC?',
                'category' => 'costing-concept',
                'description' => 'Conceptual question about MSSP pricing model',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'Je veux convertir un coût de 5000 USD en EUR et TND. Quels sont les taux de change actuels dans le système?',
                'category' => 'costing-currency-french',
                'description' => 'French — currency conversion question',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'What shard size do you recommend for optimal Elasticsearch performance? Explain why 30-50GB is the sweet spot.',
                'category' => 'sizing-shards',
                'description' => 'Shard sizing best practices explanation',
                'expects_tools' => false,
            ],
            [
                'agent' => 'ElasticCostAssistant',
                'prompt' => 'knwa 3andi client jdid w n7eb na7seb el SOC cost mt3ou. 3andou 150 device Active Directory w 50 FortiGate. chnou el cout mensuel?',
                'category' => 'costing-tunisian',
                'description' => 'Tunisian Arabic — SOC cost calculation for a new client',
                'expects_tools' => false,
            ],

            // ── RG SOC Engineer (10) ────────────────────────────────────────
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'List all clients in the system with their current device counts.',
                'category' => 'db-query-simple',
                'description' => 'Simple database query — requires GetSystemDetailsTool',
                'expects_tools' => true,
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'What are the current global settings? Show me all configured rates and prices.',
                'category' => 'db-query-settings',
                'description' => 'Query global settings table',
                'expects_tools' => true,
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Add 2 FortiGate firewalls to Acme Corp device count.',
                'category' => 'db-update-simple',
                'description' => 'Simple device count update — requires GetClientInventoryTool + UpdateClientInventoryTool',
                'expects_tools' => true,
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Set the SIEM agent monthly cost per device to 25 USD.',
                'category' => 'db-update-setting',
                'description' => 'Update a global setting — requires UpdateGlobalSettingTool',
                'expects_tools' => true,
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => '3tini el liste mta3 el clients w chnou 3andhom men devices b kol type.',
                'category' => 'db-query-tunisian',
                'description' => 'Tunisian Arabic — list clients and device counts by type',
                'expects_tools' => true,
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Create a new client named "TechCorp Industries" with 100 Active Directory devices, 30 FortiGate firewalls, and 50 EDR endpoints.',
                'category' => 'db-create-client',
                'description' => 'Multi-step client creation — requires GetSystemDetailsTool + CreateClientTool',
                'expects_tools' => true,
            ],
            [
                'agent' => 'RgSocEngineer',
                'prompt' => 'Show me the complete system state: all clients, their inventory, global settings, scenarios, and available asset types. Format as a comprehensive report.',
                'category' => 'db-query-comprehensive',
                'description' => 'Comprehensive system state query — requires GetSystemDetailsTool with large output',
                'expects_tools' => true,
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
