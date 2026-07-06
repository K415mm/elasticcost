<?php
// Quick path debug
$base = '/var/www/storage/app/phpkaiharness/sessions';
for ($i = 0; $i < 20; $i++) {
    $p = "$base/testcmp__B-full-harness_$i/monitor.db";
    $e = file_exists($p);
    $s = $e ? filesize($p) : 0;
    echo "[$i] exists=" . ($e ? 'Y' : 'N') . " size=$s\n";
}
echo "---WARM---\n";
for ($i = 0; $i < 20; $i++) {
    $p = "$base/testcmp__B-warm-harness_$i/monitor.db";
    $e = file_exists($p);
    $s = $e ? filesize($p) : 0;
    echo "[$i] exists=" . ($e ? 'Y' : 'N') . " size=$s\n";
}
