<?php

$files = glob('testandcompare-warm/traces/B-full-harness/*.json');
sort($files);
foreach ($files as $f) {
    $t = json_decode(file_get_contents($f), true);
    if (strpos($t['response'] ?? '', 'error') !== false || strpos($t['response'] ?? '', '⚠️') !== false) {
        echo basename($f)." ERROR:\n";
        echo '  '.substr($t['response'], 0, 300)."\n\n";
    }
}
