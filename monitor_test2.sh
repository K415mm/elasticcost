#!/bin/bash
echo '=== INSIDE CONTAINER ==='
echo '--- PID/MARKER FILES ---'
ls -la /var/www/storage/logs/test-compare-run.* 2>/dev/null || echo 'none'
echo ''
echo '--- PID CONTENT ---'
cat /var/www/storage/logs/test-compare-run.pid 2>/dev/null || echo 'no pid'
echo ''
echo '--- RUN ID ---'
cat /var/www/storage/logs/test-compare-run.id 2>/dev/null || echo 'no run id'
echo ''
echo '--- MARKER ---'
cat /var/www/storage/logs/test-compare-run.marker 2>/dev/null || echo 'no marker'
echo ''
echo '--- PROCESS ---'
ps aux | grep 'test:phpkaiharness' | grep -v grep || echo 'no process'
echo ''
echo '--- RUNS DIR ---'
ls -la /var/www/testandcompare/runs/ 2>/dev/null || echo 'no runs dir'
echo ''
echo '--- LATEST SYMLINK ---'
ls -la /var/www/testandcompare/latest 2>/dev/null || echo 'no latest'
echo ''
echo '--- TRACE COUNTS ---'
for d in /var/www/testandcompare/runs/*/; do
  if [ -d "$d" ]; then
    echo "Run: $(basename $d)"
    for m in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
      c=$(ls $d/traces/$m/request-*.json 2>/dev/null | wc -l)
      echo "  $m: $c"
    done
  fi
done
echo ''
echo '--- OLD TRACES (root testandcompare) ---'
for m in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
  c=$(ls /var/www/testandcompare/traces/$m/request-*.json 2>/dev/null | wc -l)
  echo "  $m: $c"
done
echo ''
echo '--- LOG (last 30 lines) ---'
tail -30 /var/www/storage/logs/test-compare-run.log 2>/dev/null || echo 'no log file'
echo ''
echo '--- LARAVEL LOG (last 20 lines, test-related) ---'
tail -100 /var/www/storage/logs/laravel.log 2>/dev/null | grep -i 'test\|TestRunner\|phpkaiharness' | tail -20 || echo 'no relevant log entries'
