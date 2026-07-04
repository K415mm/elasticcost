#!/bin/bash
cd /var/www/elasticcost/public/vendor/harness/webfonts
rm -f *.woff2 *.ttf

BASE="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts"

for f in fa-brands-400.woff2 fa-regular-400.woff2 fa-solid-900.woff2 fa-v4compatibility.woff2 fa-brands-400.ttf fa-regular-400.ttf fa-solid-900.ttf; do
  echo "Downloading $f..."
  curl -sL -o "$f" "$BASE/$f"
  SIZE=$(stat -c%s "$f" 2>/dev/null || echo 0)
  echo "  Size: $SIZE bytes"
done

echo "=== Webfonts installed ==="
ls -la /var/www/elasticcost/public/vendor/harness/webfonts/
