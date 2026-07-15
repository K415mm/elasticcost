<?php

return [
    'config_mode' => 'philosophy',
    'routing' => [
        'local_intent' => [
            'enabled' => true,
            'confidence_threshold' => 0.9,
        ],
        // E4: Dirac ComplexityClassifier tuning — adjust without code changes
        'complexity' => [
            'simple_amplitude' => 1.0,   // Base weight for Simple domain
            'complicated_amplitude' => 0.0,   // Base weight for Complicated domain
            'complex_amplitude' => 0.0,   // Base weight for Complex domain
            'symmetry_threshold' => 0.8,   // Eigenvalue threshold for symmetry/antisymmetry split
            'entity_keywords' => [
                'client', 'scenario', 'asset', 'sizing', 'profit', 'mssp',
                'user', 'role', 'permission',
            ],
            'mutating_keywords' => [
                'update', 'delete', 'modify', 'create', 'run', 'simulate',
                'change', 'ingest', 'set',
            ],
        ],
        // E5: Keyword fast-path rules — each entry short-circuits to a static response
        // Pattern must be a valid PHP regex string, e.g. '/hello|hi/i'
        'keyword_rules' => [
            // Example: ['pattern' => '/^hello$/i', 'response' => 'Hello! How can I help?'],
        ],
    ],
    'default' => [
        'provider' => 'ollama',
        'model' => null,
        'max_iterations' => 50,
    ],
    'failover' => [
        'enabled' => true,
        'clients' => [
            ['provider' => 'ollama',   'model' => 'llama3.2'],
            ['provider' => 'lmstudio', 'model' => 'gemma-2b-it'],
        ],
    ],
    'feature_graph' => [
        'nodes' => [
            'draft_verification' => [
                'enabled' => true,
            ],
            'environment_bootstrap' => [
                'enabled' => false,
            ],
            'context_compression' => [
                'enabled' => true,
            ],
            'model_optimizer' => [
                'enabled' => true,
            ],
            'ontology_injection' => [
                'enabled' => true,
            ],
            'semantic_cache' => [
                'enabled' => true,
            ],
            'context_compactor' => [
                'enabled' => true,
            ],
            'guardrails' => [
                'enabled' => true,
            ],
            'cognitive_memory' => [
                'enabled' => true,
            ],
            'quantum_harness' => [
                'enabled' => true,
            ],
        ],
    ],
    'cache' => [
        'enabled' => true,
        'threshold' => 0.88,
        'db_path' => 'S:\elasticcost\storage\app/phpkaiharness/monitor.db',
        'redis' => [
            'enabled' => true,
            'connection' => 'default',
            'decay_mode' => 'dissipative',
            'subjective_field' => [
                'enabled' => true,
                'bias_weight' => 0.15,
            ],
            'order_sensitive' => true,
        ],
        'verify_with_llm' => true,
        'verify_model' => 'qwen-turbo',
    ],
    'pii_masking' => [
        'enabled' => true,
        'patterns' => [
            'EMAIL' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            'IP' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            'CREDIT_CARD' => '/\b(?:\d[ \-]*?){13,16}\b/',
            'API_KEY' => '/\b[A-Za-z0-9_\-]{32,64}\b/',
            'PHONE' => '/\b\d{3}[\s\-]?\d{3}[\s\-]?\d{4}\b/',
        ],
    ],
    'rate_limiting' => [
        'enabled' => false,
        'requests_per_minute' => 1200,
        'cooldown_ms' => 0,
    ],
    'guardrails' => [
        'enabled' => true,
        'high_risk_tools' => [
            'wsl_command',
            'delete_*',
            'execute_*',
            'rm_*',
        ],
        'authorized_scopes' => [
            'admin',
            'sizing',
            'analytics',
            'read-only',
        ],
        'tool_scope_map' => [
            'wsl_command' => [
                'admin',
            ],
            'delete_*' => [
                'admin',
                'write',
            ],
            'execute_*' => [
                'admin',
            ],
        ],
    ],
    'optimizer' => [
        'enabled' => true,
    ],
    'ontology' => [
        'enabled' => true,
        'embedding_column' => 'embedding',
        'similarity_threshold' => 0.15,
        'max_records' => 5,
        'db_path' => null,
        'namespaces' => [
            'enabled' => true,
        ],
    ],
    'policy_guardrail' => [
        'enabled' => true,
    ],
    'compaction' => [
        'strategy' => 'sliding_window',
        'max_turns' => 200,
        'max_tokens_threshold' => 40000,
        'compression' => [
            'enabled' => true,
            'line_threshold' => 150,
        ],
    ],
    'bootstrap' => [
        'enabled' => false,
    ],
    'budget' => [
        'enabled' => false,
        'max_tokens' => 30000000,
    ],
    'cognitive_memory' => [
        'enabled' => true,
        'max_depth' => 3,
        'coherence_threshold' => 0.15,
        'decay_rate' => 0.05,
    ],
    'draft_verification' => [
        'enabled' => true,
    ],
    'quantum_harness' => [
        'enabled' => true,
        'db_path' => 'S:\elasticcost\storage\app/phpkaiharness/agent_memory.sqlite',
        'alpha' => 0.7,
        'beta' => 0.3,
        'similarity_threshold' => 0.15,
        'max_anchors' => 5,
        'coherence_decay' => 0.05,
        'density_matrix_bias' => 0.1,
        // E2: Cross-session shared Quantum Memory
        // When true, all user sessions read/write from a single shared_memory.sqlite
        // enabling accumulated knowledge to persist across sessions.
        'shared_memory_enabled' => false,
        'shared_db_path' => null,  // null = storage/app/phpkaiharness/shared_memory.sqlite
    ],
    'qwen_provider' => [
        'enabled' => true,
        'api_key' => null,
        'url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        'model' => 'qwen-plus',
        'light_model' => 'qwen-turbo',
        'structured_output' => 'json_schema',
        'max_tokens' => 12000,
    ],
    'session_isolation' => [
        'enabled' => true,
        'base_path' => null,
        'cleanup_hours' => 24,
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
