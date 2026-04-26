#!/bin/sh
set -eu

# ローカル環境で FCM REST API の登録・解除フローを確認する簡易スクリプトです。
PAGE_URL="http://localhost:8080/?p=8"
REGISTER_URL="http://localhost:8080/?rest_route=/my-push-fcm/v1/register"
UNREGISTER_URL="http://localhost:8080/?rest_route=/my-push-fcm/v1/unregister"
WEB_CONFIG_URL="http://localhost:8080/?rest_route=/my-push-fcm/v1/web-config"
TOKEN="fcm-test-$(date +%s)-$$"

# フロントエンドに出力された window.MyPushFCM から REST nonce を取り出します。
NONCE=$(curl -s -A 'Mozilla/5.0 (FCMTest)' "$PAGE_URL" | grep -oE '"nonce":"[a-f0-9]+"' | head -n1 | sed -E 's/.*"nonce":"([a-f0-9]+)".*/\1/')

if [ -z "$NONCE" ]; then
	# 指定投稿に nonce が出ていない場合はトップページでも試します。
	echo "could not extract nonce; trying wp-login.php fallback" >&2
	NONCE=$(curl -s -A 'Mozilla/5.0 (FCMTest)' "http://localhost:8080/" | grep -oE '"nonce":"[a-f0-9]+"' | head -n1 | sed -E 's/.*"nonce":"([a-f0-9]+)".*/\1/' || true)
fi

printf "nonce=%s\n" "$NONCE"
printf "token=%s\n" "$TOKEN"

echo "-- GET /web-config --"
curl -s -w "\nHTTP=%{http_code}\n" "$WEB_CONFIG_URL"

# テスト用の疑似 FCM トークンを登録します。
REG_BODY=$(printf '{"token":"%s","platform":"android","app_id":"com.example.app","device_label":"Pixel Test"}' "$TOKEN")

echo "-- POST /register --"
curl -s -A 'Mozilla/5.0 (FCMTest)' \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $NONCE" \
	-X POST \
	--data "$REG_BODY" \
	-w "\nHTTP=%{http_code}\n" \
	"$REGISTER_URL"

echo "-- POST /unregister --"
curl -s -A 'Mozilla/5.0 (FCMTest)' \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $NONCE" \
	-X POST \
	--data "$(printf '{"token":"%s"}' "$TOKEN")" \
	-w "\nHTTP=%{http_code}\n" \
	"$UNREGISTER_URL"
