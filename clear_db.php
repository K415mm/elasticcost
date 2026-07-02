<?php

$pdo = new PDO('sqlite:storage/app/phpkaiharness/monitor.db');
$pdo->exec('DELETE FROM harness_sessions WHERE 1=1');
$pdo->exec('DELETE FROM harness_details WHERE 1=1');
$pdo->exec('DELETE FROM harness_facts WHERE 1=1');
$pdo2 = new PDO('sqlite:storage/app/phpkaiharness/agent_memory.sqlite');
$pdo2->exec('DELETE FROM memory_nodes WHERE 1=1');
$pdo2->exec('DELETE FROM memory_vectors WHERE 1=1');
$pdo2->exec('DELETE FROM memory_edges WHERE 1=1');
$pdo2->exec('DELETE FROM entanglement_pairs WHERE 1=1');
// Also clear all per-session monitor.db files
$sessionDbs = glob('storage/app/phpkaiharness/sessions/*/monitor.db');
foreach ($sessionDbs as $sessionDb) {
    $spdo = new PDO('sqlite:'.$sessionDb);
    foreach (['harness_sessions', 'harness_details', 'harness_facts'] as $table) {
        try {
            $spdo->exec("DELETE FROM {$table} WHERE 1=1");
        } catch (Exception $e) {
            // Table may not exist in this DB
        }
    }
}
echo 'Cleared '.count($sessionDbs)." per-session DBs\n";
echo "DBs cleared\n";
