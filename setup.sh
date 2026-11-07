#!/usr/bin/env bash
# Unduh CodeIgniter 3.1.13 dan salin folder "system" (hanya sekali).
set -e
cd "$(dirname "$0")"
if [ -d system/core ]; then echo "Folder system sudah ada."; exit 0; fi
curl -L -o ci3.zip https://github.com/bcit-ci/CodeIgniter/archive/refs/tags/3.1.13.zip
unzip -q ci3.zip -d ci3_tmp
rm -rf system && cp -r ci3_tmp/CodeIgniter-3.1.13/system ./system
rm -rf ci3_tmp ci3.zip
chmod -R 775 application/cache application/logs uploads 2>/dev/null || true
echo "Selesai. Lanjut: edit application/config/local.php lalu import database."
