#!/bin/bash
# PochiClock デプロイスクリプト
# 使用方法: bash deploy.sh

set -e

SERVER="xserver-smartclock"
REMOTE_DIR="~/twinklemark.xsrv.jp/pochiclock"
PHP="/usr/bin/php8.4"

echo "=== PochiClock Deploy ==="

# 1. ローカルの変更をpush
echo "[1/5] Pushing to GitHub..."
git push origin main

# 2. サーバーでpull
echo "[2/5] Pulling on server..."
ssh $SERVER "cd $REMOTE_DIR && git pull origin main"

# 3. Composer install
echo "[3/5] Installing dependencies..."
ssh $SERVER "cd $REMOTE_DIR && $PHP /usr/bin/composer install --no-dev --optimize-autoloader 2>&1 | tail -3"

# 4. マイグレーション
echo "[4/5] Running migrations..."
ssh $SERVER "cd $REMOTE_DIR && $PHP artisan migrate --force"

# 5. キャッシュクリア＆再構築
echo "[5/5] Clearing and rebuilding caches..."
ssh $SERVER "cd $REMOTE_DIR && $PHP artisan config:cache && $PHP artisan route:clear && $PHP artisan view:cache"

echo ""
echo "=== Deploy complete ==="
echo "URL: https://twinklemark.xsrv.jp/pochiclock"
