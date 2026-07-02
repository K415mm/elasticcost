<?php

$files = glob('testandcompare/traces/A1-direct-api/*.json');
sort($files);
$f = $files[0] ?? null;
if ($f) {
    $t = json_decode(file_get_contents($f), true);
    echo 'provider: '.$t['provider']."\n";
    echo 'model: '.$t['model']."\n";
    echo 'success: '.($t['success'] ? 'Y' : 'N')."\n";
    echo 'error: '.($t['error'] ?? 'none')."\n";
    echo 'response: '.substr($t['response'] ?? '', 0, 300)."\n";
}
