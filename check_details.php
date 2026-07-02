<?php

$pdo = new PDO('sqlite:storage/app/phpkaiharness/monitor.db');
$stmt = $pdo->query('SELECT type, name, COUNT(*) as cnt FROM harness_details GROUP BY type, name ORDER BY cnt DESC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-25s %-40s %d\n", $row['type'], $row['name'], $row['cnt']);
}
