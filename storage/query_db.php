<?php
$db = new PDO('sqlite:s:/elasticcost/storage/app/phpkaiharness/monitor.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Session types in harness_details:\n";
$stmt = $db->query('SELECT DISTINCT type FROM harness_details');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Type: " . $row['type'] . "\n";
}

echo "\nLatest 5 details:\n";
$stmt = $db->query('SELECT * FROM harness_details ORDER BY id DESC LIMIT 5');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}, Type: {$row['type']}, Name: {$row['name']}, SessionID: {$row['session_id']}\n";
    echo "Payload: " . substr($row['payload'], 0, 150) . "...\n";
    echo "Response: " . substr($row['response'], 0, 150) . "...\n\n";
}
