#!/bin/sh
set -eu
for u in "/" "/?p=1" "/wp-login.php" "/?page_id=2"; do
	count=$(curl -s "http://localhost$u" | grep -c 'data-my-push-subscribe' || true)
	printf "%s -> data-my-push-subscribe matches: %s\n" "$u" "$count"
done
