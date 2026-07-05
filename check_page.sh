#!/bin/bash
echo '=== LATEST DIR CONTENTS ==='
ls -la /var/www/testandcompare/latest/ 2>/dev/null || echo 'no latest'
echo ''
echo '=== SUMMARY FILE ==='
ls -la /var/www/testandcompare/latest/comparison-summary.json 2>/dev/null || echo 'NO SUMMARY YET'
echo ''
echo '=== TRACES DIR ==='
ls /var/www/testandcompare/latest/traces/ 2>/dev/null || echo 'no traces dir'
echo ''
echo '=== TRACE COUNTS ==='
for m in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
  c=$(ls /var/www/testandcompare/latest/traces/$m/request-*.json 2>/dev/null | wc -l)
  echo "  $m: $c"
done
echo ''
echo '=== LOG TAIL ==='
tail -10 /var/www/storage/logs/test-compare-run.log 2>/dev/null
echo ''
echo '=== PROCESS ==='
ps aux | grep phpkaiharness | grep -v grep || echo 'not running'
