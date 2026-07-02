<?php

$files = glob('testandcompare-warm/traces/B-full-harness/*.json');
foreach ($files as $f) {
    $t = json_decode(file_get_contents($f), true);
    $resp = substr($t['response'] ?? '', 0, 120);
    echo basename($f)."\n";
    echo '  provider='.$t['provider'].' model='.$t['model']."\n";
    echo '  success='.($t['success'] ? 'Y' : 'N').' resp_len='.strlen($t['response'] ?? '')."\n";
    echo '  response: '.$resp."\n\n";
}
