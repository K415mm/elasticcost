#!/bin/bash
echo "=== COLD TRACES (testandcompare) ==="
for mode in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
  count=$(ls /var/www/elasticcost/testandcompare/traces/$mode/request-*.json 2>/dev/null | wc -l)
  echo "$mode: $count traces"
done

echo ""
echo "=== WARM TRACES (testandcompare-warm) ==="
for mode in A1-direct-api A2-loop-no-features B-full-harness B-warm-harness; do
  count=$(ls /var/www/elasticcost/testandcompare-warm/traces/$mode/request-*.json 2>/dev/null | wc -l)
  echo "$mode: $count traces"
done

echo ""
echo "=== COLD SUMMARY ==="
cat /var/www/elasticcost/testandcompare/comparison-summary.json 2>/dev/null

echo ""
echo "=== WARM SUMMARY ==="
cat /var/www/elasticcost/testandcompare-warm/comparison-summary.json 2>/dev/null

echo ""
echo "=== RECENT LOG ERRORS ==="
docker exec elasticcost-octane grep -i "TestRunner\|test-compare\|B-warm\|B-full" /var/www/storage/logs/laravel.log 2>/dev/null | tail -30

echo ""
echo "=== ALL RECENT ERRORS (last 50 lines) ==="
docker exec elasticcost-octane tail -50 /var/www/storage/logs/laravel.log 2>/dev/null | grep -i "error\|exception\|fatal" | tail -20
