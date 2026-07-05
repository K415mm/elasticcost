#!/bin/bash
echo "=== PID file ==="
cat /var/www/elasticcost/storage/logs/test-compare-run.pid 2>/dev/null || echo "no pid file"
echo ""
echo "=== Run log (last 80 lines) ==="
tail -80 /var/www/elasticcost/storage/logs/test-compare-run.log 2>/dev/null || echo "no log file"
echo ""
echo "=== Trace counts ==="
for mode in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
    dir=/var/www/elasticcost/testandcompare/traces/$mode
    count=$(ls "$dir"/request-*.json 2>/dev/null | wc -l)
    echo "$mode: $count"
done
echo ""
echo "=== Laravel log (TestRunner errors) ==="
docker exec elasticcost-octane tail -200 /var/www/storage/logs/laravel.log 2>/dev/null | grep -i "TestRunner\|test-compare\|phpkaiharness" | tail -30
echo ""
echo "=== Check if process still running ==="
ps aux | grep "test:phpkaiharness" | grep -v grep
