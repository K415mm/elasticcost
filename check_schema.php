<?php

$mainPdo = new PDO('sqlite:storage/app/phpkaiharness/monitor.db');
echo "=== harness_facts schema ===\n";
foreach ($mainPdo->query('PRAGMA table_info(harness_facts)')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['name']} ({$col['type']})\n";
}
echo "\n=== harness_sessions schema ===\n";
foreach ($mainPdo->query('PRAGMA table_info(harness_sessions)')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['name']} ({$col['type']})\n";
}
echo "\n=== harness_details schema ===\n";
foreach ($mainPdo->query('PRAGMA table_info(harness_details)')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['name']} ({$col['type']})\n";
}

// Check a sample facts row from a session DB
$sessionDbs = glob('storage/app/phpkaiharness/sessions/*/monitor.db');
foreach ($sessionDbs as $db) {
    $spdo = new PDO('sqlite:'.$db);
    try {
        $row = $spdo->query('SELECT * FROM harness_facts LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "\n=== Sample harness_facts row (from session DB) ===\n";
            print_r($row);
            break;
        }
    } catch (Exception $e) {
    }
}

$quantumPdo = new PDO('sqlite:storage/app/phpkaiharness/agent_memory.sqlite');
echo "\n=== memory_nodes schema ===\n";
foreach ($quantumPdo->query('PRAGMA table_info(memory_nodes)')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['name']} ({$col['type']})\n";
}
echo "\n=== memory_vectors schema ===\n";
foreach ($quantumPdo->query('PRAGMA table_info(memory_vectors)')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['name']} ({$col['type']})\n";
}

// Check session quantum DBs
$sessionQuantum = glob('storage/app/phpkaiharness/sessions/*/agent_memory.sqlite');
echo "\nSession quantum DBs found: ".count($sessionQuantum)."\n";
