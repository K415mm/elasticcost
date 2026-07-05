#!/bin/bash
echo '=== TESTANDCOMPARE DIR PERMISSIONS ==='
ls -la /var/www/testandcompare/
echo ''
echo '=== OWNER ==='
stat /var/www/testandcompare/
echo ''
echo '=== TRY CREATE RUNS DIR ==='
su -s /bin/bash -c 'mkdir -p /var/www/testandcompare/runs/test-perm && echo "SUCCESS: can create" && rm -rf /var/www/testandcompare/runs/test-perm' www-data 2>&1 || echo "FAILED: cannot create as www-data"
echo ''
echo '=== PROCESS CHECK (from host) ==='
ps aux | grep 'test:phpkaiharness' | grep -v grep || echo 'no process running'
echo ''
echo '=== DOCKER USER ==='
docker exec elasticcost-octane id 2>/dev/null || echo 'cannot check'
echo ''
echo '=== FULL LOG ==='
cat /var/www/storage/logs/test-compare-run.log 2>/dev/null | head -60
