<?php

return [
    'default' => [
        'provider' => env('PHPKAIHARNESS_PROVIDER', 'qwen'),
        'model' => env('PHPKAIHARNESS_MODEL', 'qwen-plus'),
        'max_iterations' => 50,
    ],
    'failover' => [
        'enabled' => false,
        'clients' => [],
    ],
    'cache' => [
        'enabled' => true,
        'threshold' => 0.88,
        'db_path' => env('PHPKAIHARNESS_DB') ?: (function_exists('app') && method_exists(app(), 'storagePath') ? storage_path('app/phpkaiharness/monitor.db') : null),
        'eligibility' => [
            'reject_patterns' => ['iteration limit', 'cURL error', 'LLM execution error', '⚠️'],
            'reject_empty' => true,
            'reject_min_length' => 20,
        ],
        'namespaces' => [
            'enabled' => true,
        ],
        'ttl_seconds' => 0,
        'redis' => [
            'enabled' => env('PHPKAIHARNESS_CACHE_REDIS_ENABLED', true),
            'connection' => env('PHPKAIHARNESS_CACHE_REDIS_CONNECTION', 'default'),
            'decay_mode' => env('PHPKAIHARNESS_CACHE_DECAY_MODE', 'dissipative'),
            'subjective_field' => [
                'enabled' => env('PHPKAIHARNESS_CACHE_SUBJECTIVE_FIELD', true),
                'bias_weight' => env('PHPKAIHARNESS_CACHE_BIAS_WEIGHT', 0.15),
            ],
            'order_sensitive' => env('PHPKAIHARNESS_CACHE_ORDER_SENSITIVE', true),
        ],
    ],
    'pii_masking' => [
        'enabled' => true,
        'patterns' => [
            'EMAIL' => '/[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}/',
            'IP' => '/\\b(?:\\d{1,3}\\.){3}\\d{1,3}\\b/',
            'CREDIT_CARD' => '/\\b(?:\\d[ \\-]*?){13,16}\\b/',
            'API_KEY' => '/\\b[A-Za-z0-9_\\-]{32,64}\\b/',
            'PHONE' => '/\\b\\d{3}[\\s\\-]?\\d{3}[\\s\\-]?\\d{4}\\b/',
        ],
    ],
    'rate_limits' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'cooldown_ms' => 0,
    ],
    'guardrails' => [
        'enabled' => true,
        'high_risk_tools' => [
            0 => 'wsl_command',
            1 => 'delete_*',
            2 => 'execute_*',
            3 => 'rm_*',
        ],
        'authorized_scopes' => [
            0 => 'admin',
            1 => 'sizing',
            2 => 'analytics',
            3 => 'read-only',
        ],
        'tool_scope_map' => [
            'wsl_command' => [
                0 => 'admin',
            ],
            'delete_*' => [
                0 => 'admin',
                1 => 'write',
            ],
            'execute_*' => [
                0 => 'admin',
            ],
        ],
    ],
    'optimizer' => [
        'enabled' => true,
    ],
    'ontology' => [
        'enabled' => true,
        'embedding_column' => 'embedding',
        'similarity_threshold' => 0.3,
        'max_records' => 3,
    ],
    'policy_guardrail' => [
        'enabled' => true,
    ],
    'compaction' => [
        'strategy' => 'sliding_window',
        'max_turns' => 6,
        'max_tokens_threshold' => 4000,
        'compression' => [
            'enabled' => false,
            'line_threshold' => 150,
        ],
    ],
    'bootstrap' => [
        'enabled' => false,
    ],
    'budget' => [
        'enabled' => true,
        'max_tokens' => 30000,
    ],
    'cognitive_memory' => [
        'enabled' => true,
        'extraction_mode' => 'sync',
        'quality_filter' => [
            'min_length' => 15,
            'reject_patterns' => ['maybe', 'might', 'possibly', 'i think', 'not sure', 'unclear', 'todo', 'tbd', 'fixme', 'hack', 'placeholder', 'lorem ipsum', 'test data', 'dummy', 'sample', 'example', 'placeholder text'],
            'reject_markdown_only' => true,
        ],
        'dedup' => [
            'enabled' => true,
            'similarity_threshold' => 0.85,
        ],
    ],
    'draft_verification' => [
        'enabled' => true,
    ],
    'quantum_harness' => [
        'enabled' => env('PHPKAIHARNESS_QUANTUM_ENABLED', false),
        'db_path' => env('PHPKAIHARNESS_QUANTUM_DB') ?: (function_exists('app') && method_exists(app(), 'storagePath') ? storage_path('app/phpkaiharness/agent_memory.sqlite') : null),
        'alpha' => env('PHPKAIHARNESS_QUANTUM_ALPHA', 0.7),
        'beta' => env('PHPKAIHARNESS_QUANTUM_BETA', 0.3),
        'similarity_threshold' => env('PHPKAIHARNESS_QUANTUM_THRESHOLD', 0.30),
        'max_anchors' => env('PHPKAIHARNESS_QUANTUM_LIMIT', 3),
    ],
    'qwen_provider' => [
        'enabled' => env('PHPKAIHARNESS_QWEN_ENABLED', true),
        'api_key' => env('PHPKAIHARNESS_QWEN_KEY') ?: (env('QWEN_API_KEY') ?: env('DASHSCOPE_API_KEY', '')),
        'url' => env('PHPKAIHARNESS_QWEN_URL') ?: (env('QWEN_URL') ?: env('DASHSCOPE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1')),
        'model' => env('PHPKAIHARNESS_QWEN_MODEL', 'qwen-plus'),
        'light_model' => env('PHPKAIHARNESS_QWEN_LIGHT_MODEL', 'qwen-turbo'),
        'structured_output' => env('PHPKAIHARNESS_QWEN_STRUCTURED', 'json_object'),
        'max_tokens' => env('PHPKAIHARNESS_QWEN_MAX_TOKENS', 4096),
    ],
    'feature_graph' => [
        'nodes' => [
            'draft_verification' => ['enabled' => true],
            'environment_bootstrap' => ['enabled' => false],
            'context_compression' => ['enabled' => false],
            'model_optimizer' => ['enabled' => true],
            'ontology_injection' => ['enabled' => true],
            'semantic_cache' => ['enabled' => true],
            'context_compactor' => ['enabled' => true],
            'guardrails' => ['enabled' => false],
            'cognitive_memory' => ['enabled' => true],
            'quantum_harness' => ['enabled' => true],
        ],
    ],
    'telemetry' => [
        'enabled' => true,
        'route_prefix' => 'harness',
        'middleware' => [
            'web',
            'auth',
            'permission:harness_analytics',
        ],
    ],
];
