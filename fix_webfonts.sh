#!/bin/bash
cd /var/www/elasticcost/public/vendor/harness
mkdir -p webfonts
cd webfonts

# Download Font Awesome 6 free webfonts
BASE="https://github.com/FortAwesome/Font-Awesome/releases/download/6.5.1"

for f in fa-brands-400.woff2 fa-regular-400.woff2 fa-solid-900.woff2 fa-v4compatibility.woff2 fa-brands-400.ttf fa-regular-400.ttf fa-solid-900.ttf; do
  if [ ! -f "$f" ]; then
    echo "Downloading $f..."
    curl -sL -o "$f" "$BASE/$f" 2>&1
  fi
done

echo "=== Webfonts installed ==="
ls -la /var/www/elasticcost/public/vendor/harness/webfonts/
