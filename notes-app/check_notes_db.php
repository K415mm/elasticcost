<?php

$pdo = new PDO('sqlite:'.__DIR__.'/database.sqlite');
$count = $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
$userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo 'Total Notes in notes-app DB: '.number_format($count)."\n";
echo 'Total Users in notes-app DB: '.number_format($userCount)."\n";
