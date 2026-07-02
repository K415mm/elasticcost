<?php

$sessionDbs = glob('storage/app/phpkaiharness/sessions/*/monitor.db');
foreach ($sessionDbs as $db) {
    $pdo = new PDO('sqlite:'.$db);
    $stmt = $pdo->query("SELECT type, name, response FROM harness_details WHERE type = 'cognitive_memory'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (! empty($rows)) {
        echo "DB: $db\n";
        foreach ($rows as $row) {
            echo "  {$row['type']} / {$row['name']}: {$row['response']}\n";
        }
    }
}
echo 'Done checking '.count($sessionDbs)." session DBs\n";
