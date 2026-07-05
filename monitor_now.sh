#!/bin/bash
echo "=== MONITOR $(date) ==="

echo '--- PROCESS CHECK ---'
ps aux | grep 'test:phpkaiharness' | grep -v grep || echo 'no process running'

echo ''
echo '--- STATE FILES ---'
ls -la /var/www/elasticcost/storage/logs/test-compare-run.* 2>/dev/null || echo 'none'

echo ''
echo '--- RUN ID ---'
cat /var/www/elasticcost/storage/logs/test-compare-run.id 2>/dev/null || echo 'no run id'

echo ''
echo '--- MARKER ---'
cat /var/www/elasticcost/storage/logs/test-compare-run.marker 2>/dev/null || echo 'no marker'

echo ''
echo '--- RUNS DIR ---'
ls -la /var/www/elasticcost/testandcompare/runs/ 2>/dev/null || echo 'no runs dir'

echo ''
echo '--- LATEST SYMLINK ---'
ls -la /var/www/elasticcost/testandcompare/latest 2>/dev/null || echo 'no latest'

echo ''
echo '--- TRACE COUNTS PER RUN ---'
for d in /var/www/elasticcost/testandcompare/runs/*/; do
  if [ -d "$d" ]; then
    echo "Run: $(basename $d)"
    for m in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
      c=$(ls $d/traces/$m/request-*.json 2>/dev/null | wc -l)
      echo "  $m: $c"
    done
    echo "  TOTAL: $(ls $d/traces/*/request-*.json 2>/dev/null | wc -l)"
  fi
done

echo ''
echo '--- LOG (last 40 lines) ---'
tail -40 /var/www/elasticcost/storage/logs/test-compare-run.log 2>/dev/null || echo 'no log file'

echo ''
echo '--- LARAVEL ERRORS (last 10) ---'
tail -200 /var/www/elasticcost/storage/logs/laravel.log 2>/dev/null | grep -i 'error\|exception\|permission' | tail -10 || echo 'no errors'
