<?php

$sessionsBase = '/var/www/storage/app/phpkaiharness/sessions';

// Check ONE session in detail
$dir = $sessionsBase . '/testcmp__B-full-harness_0';
$dbPath = $dir . '/monitor.db';

echo "=== CHECKING: $dbPath ===\n";
echo "File exists: " . (file_exists($dbPath) ? 'YES' : 'NO') . "\n";
echo "File size: " . (file_exists($dbPath) ? filesize($dbPath) : 'N/A') . " bytes\n\n";

if (!file_exists($dbPath)) {
    // Try listing what files ARE in the dir
    echo "Files in dir:\n";
    foreach (glob($dir . '/*') as $f) {
        echo "  " . basename($f) . " (" . filesize($f) . " bytes)\n";
    }
    exit(1);
}

$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n\n";

foreach ($tables as $table) {
    $cnt = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "[$table] row count: $cnt\n";
    
    if ($cnt > 0 && $cnt <= 50) {
        // dump columns
        $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        echo "  Columns: " . implode(', ', array_column($cols, 'name')) . "\n";
        
        // dump a sample row
        $sample = $pdo->query("SELECT * FROM $table LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sample as $row) {
            foreach ($row as $k => $v) {
                if (strlen((string)$v) > 200) {
                    $v = substr((string)$v, 0, 200) . '...';
                }
                echo "    $k => $v\n";
            }
            echo "  ---\n";
        }
    }
    echo "\n";
}

// Now scan ALL sessions to understand total data
echo "\n=== SCANNING ALL B-FULL-HARNESS SESSIONS ===\n";
$totalSessions = 0;
$totalDetails  = 0;
$detailTypes   = [];

for ($i = 0; $i < 20; $i++) {
    $p = "$sessionsBase/testcmp__B-full-harness_$i/monitor.db";
    if (!file_exists($p) || filesize($p) === 0) {
        echo "  [B-full-harness_$i] MISSING or EMPTY\n";
        continue;
    }
    $db = new PDO("sqlite:$p");
    $sc = $db->query("SELECT COUNT(*) FROM harness_sessions")->fetchColumn();
    $dc = $db->query("SELECT COUNT(*) FROM harness_details")->fetchColumn();
    $totalSessions += $sc;
    $totalDetails  += $dc;
    
    if ($dc > 0) {
        $types = $db->query("SELECT DISTINCT type FROM harness_details")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($types as $t) {
            $detailTypes[$t] = ($detailTypes[$t] ?? 0) + 1;
        }
    }
    echo "  [B-full-harness_$i] sessions=$sc details=$dc\n";
}

echo "\nTOTAL sessions: $totalSessions, details: $totalDetails\n";
echo "Detail types found: " . json_encode($detailTypes) . "\n";
