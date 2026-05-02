#!/usr/bin/env bash
set -euo pipefail

# .env ファイルが存在する場合は読み込む（コメント行・空行は無視）。
# コンテナ内では /workspaces/wordpress/.env にマウントされている前提。
ENV_FILE="${ENV_FILE:-/workspaces/wordpress/.env}"
if [ -f "${ENV_FILE}" ]; then
	# shellcheck disable=SC2046
	export $(grep -v '^\s*#' "${ENV_FILE}" | grep -v '^\s*$' | xargs)
fi

WP_PATH="${WP_PATH:-/var/www/html}"
WP_URL="${WP_URL:-http://localhost:8080}"
WP_TITLE="${WP_TITLE:-CloudWorks WordPress}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD is required}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
WP_INIT_WAIT_SECONDS="${WP_INIT_WAIT_SECONDS:-60}"

# WP-CLI 呼び出しを 1 か所にまとめて、path / allow-root の指定漏れを防ぐ。
wp_cmd() {
	wp --path="${WP_PATH}" --allow-root "$@"
}

log() {
	printf '[wp-init] %s\n' "$1"
}

# WordPress 本体がまだマウントされていない場合は、何もせず終了する。
if [ ! -f "${WP_PATH}/wp-config.php" ]; then
	log "Skipping bootstrap because ${WP_PATH}/wp-config.php was not found."
	exit 0
fi

deadline=$(( $(date +%s) + WP_INIT_WAIT_SECONDS ))

# DB が空のときだけ初回インストールを試みる。
# 起動直後は DB がまだ応答できないことがあるため、一定時間だけリトライする。
if ! wp_cmd core is-installed >/dev/null 2>&1; then
	log "WordPress is not installed. Waiting for the database and running core install."
	until wp_cmd core install \
		--url="${WP_URL}" \
		--title="${WP_TITLE}" \
		--admin_user="${WP_ADMIN_USER}" \
		--admin_password="${WP_ADMIN_PASSWORD}" \
		--admin_email="${WP_ADMIN_EMAIL}" >/dev/null 2>&1; do
		if [ "$(date +%s)" -ge "${deadline}" ]; then
			log "Skipping bootstrap because WordPress could not be installed within ${WP_INIT_WAIT_SECONDS}s."
			exit 0
		fi
		sleep 2
	done
	log "WordPress core install completed."
fi

# WordPress は入っていても管理者ユーザーが消えているケースがあるため、
# 管理者ユーザーだけ個別に存在確認して、なければ再作成する。
if ! wp_cmd user get "${WP_ADMIN_USER}" >/dev/null 2>&1; then
	log "Admin user '${WP_ADMIN_USER}' was not found. Creating it."
	wp_cmd user create "${WP_ADMIN_USER}" "${WP_ADMIN_EMAIL}" \
		--role=administrator \
		--user_pass="${WP_ADMIN_PASSWORD}"
	log "Admin user '${WP_ADMIN_USER}' created."
fi

log "WordPress and admin user are already present."
