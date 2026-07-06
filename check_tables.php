<?php

$dir = '/var/www/storage/app/phpkaiharness/sessions/testcmp__B-full-harness_0';
$dbPath = $dir . '/monitor.db';

if (!file_exists($dbPath)) {
    echo "DB does not exist: $dbPath\n";
    exit(1);
}

$db = new PDO("sqlite:$dbPath");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== TABLES ===\n";
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "- $t\n";
}

echo "\n=== HARNESS SESSIONS ===\n";
$sessions = $db->query("SELECT * FROM harness_sessions")->fetchAll(PDO::FETCH_ASSOC);
print_r($sessions);

echo "\n=== HARNESS DETAILS ===\n";
$details = $db->query("SELECT * FROM harness_details")->fetchAll(PDO::FETCH_ASSOC);
print_r($details);
