#!/bin/bash
RUN_ID=$(cat /var/www/storage/logs/test-compare-run.id 2>/dev/null)
echo "=== RUN ID: $RUN_ID ==="
echo ''
echo '=== LOG TAIL (last 20) ==='
tail -20 /var/www/storage/logs/test-compare-run.log 2>/dev/null || echo 'no log'
echo ''
echo '=== TRACE COUNTS ==='
for m in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
  c=$(ls /var/www/testandcompare/runs/$RUN_ID/traces/$m/request-*.json 2>/dev/null | wc -l)
  echo "  $m: $c"
done
TOTAL=$(ls /var/www/testandcompare/runs/$RUN_ID/traces/*/request-*.json 2>/dev/null | wc -l)
echo "  TOTAL: $TOTAL/68"
echo ''
echo '=== LATEST SYMLINK ==='
ls -la /var/www/testandcompare/latest 2>/dev/null || echo 'none'
echo ''
echo '=== PROCESS ==='
ps aux | grep phpkaiharness | grep -v grep || echo 'not running'
echo ''
echo '=== ERRORS IN LOG ==='
grep -i 'error\|exception\|permission\|failed' /var/www/storage/logs/test-compare-run.log 2>/dev/null | tail -5 || echo 'no errors'
