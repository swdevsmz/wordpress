#!/bin/sh
set -eu

PAGE_URL="http://localhost:8080/?p=8"
SUBSCRIBE_URL="http://localhost:8080/?rest_route=/my-push/v1/subscribe"
UNSUBSCRIBE_URL="http://localhost:8080/?rest_route=/my-push/v1/unsubscribe"
ENDPOINT="https://fcm.googleapis.com/fcm/send/test-$(date +%s)-$$"

NONCE=$(curl -s -A 'Mozilla/5.0 (TestUA)' "$PAGE_URL" | grep -oE '"nonce":"[a-f0-9]+"' | head -n1 | sed -E 's/.*"nonce":"([a-f0-9]+)".*/\1/')

if [ -z "$NONCE" ]; then
	echo "could not extract nonce" >&2
	exit 1
fi

printf "nonce=%s\n" "$NONCE"
printf "endpoint=%s\n" "$ENDPOINT"

BODY=$(printf '{"endpoint":"%s","keys":{"p256dh":"BPublicKeySample","auth":"AuthTokenSample"}}' "$ENDPOINT")

echo "-- POST /subscribe --"
curl -s -A 'Mozilla/5.0 (TestUA)' \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $NONCE" \
	-X POST \
	--data "$BODY" \
	-w "\nHTTP=%{http_code}\n" \
	"$SUBSCRIBE_URL"

echo "-- POST /unsubscribe --"
curl -s -A 'Mozilla/5.0 (TestUA)' \
	-H "Content-Type: application/json" \
	-H "X-WP-Nonce: $NONCE" \
	-X POST \
	--data "$(printf '{"endpoint":"%s"}' "$ENDPOINT")" \
	-w "\nHTTP=%{http_code}\n" \
	"$UNSUBSCRIBE_URL"
