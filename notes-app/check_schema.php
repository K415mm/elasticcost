<?php

$pdo = new PDO('sqlite:'.__DIR__.'/database.sqlite');
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables: '.implode(', ', $tables)."\n";
foreach ($tables as $tbl) {
    $count = $pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
    echo " - {$tbl}: ".number_format($count)." rows\n";
}
