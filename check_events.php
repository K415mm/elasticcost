<?php

$pdo = new PDO('sqlite:storage/app/phpkaiharness/monitor.db');

echo "=== quantum_ingest events ===\n";
$stmt = $pdo->query("SELECT session_id, payload, response FROM harness_details WHERE type = 'quantum_ingest'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Session: {$row['session_id']}\n";
    echo "  Payload: {$row['payload']}\n";
    echo "  Response: {$row['response']}\n\n";
}

echo "=== quantum events ===\n";
$stmt = $pdo->query("SELECT session_id, payload, response FROM harness_details WHERE type = 'quantum'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Session: {$row['session_id']}\n";
    echo "  Payload: {$row['payload']}\n";
    echo "  Response: {$row['response']}\n\n";
}

echo "=== ontology events ===\n";
$stmt = $pdo->query("SELECT session_id, payload, response FROM harness_details WHERE type = 'ontology'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Session: {$row['session_id']}\n";
    $payload = json_decode($row['payload'], true);
    echo '  Evaluated records: '.($payload['evaluated_records_count'] ?? 0)."\n";
    echo "  Response: {$row['response']}\n\n";
}

echo "=== cognitive_memory events ===\n";
$stmt = $pdo->query("SELECT session_id, payload, response FROM harness_details WHERE type = 'cognitive_memory'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'Count: '.count($rows)."\n";
