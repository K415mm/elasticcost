<?php

$files = glob('testandcompare/traces/A1-direct-api/*.json');
foreach ($files as $f) {
    $t = json_decode(file_get_contents($f), true);
    $name = basename($f);
    echo sprintf(
        "%-45s  success=%s  latency=%5dms  quantum=%d  ctx_inj=%d  stages=%d  llm=%d  tools=%d  draft=%s  errors=%d\n",
        $name,
        $t['success'] ? 'Y' : 'N',
        $t['timing']['latency_ms'],
        $t['quantum_memory']['nodes_retrieved'] ?? 0,
        count($t['context_injected'] ?? []),
        count($t['pipeline_stages'] ?? []),
        $t['llm_calls'] ?? 0,
        $t['tool_calls']['count'] ?? 0,
        ! empty($t['draft_verification']['draft']) ? 'Y' : 'N',
        count($t['errors'] ?? [])
    );
}
