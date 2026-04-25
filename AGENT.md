# Agent Instructions

このリポジトリは、Podman Compose 上の WordPress を使って **カスタムテーマ** と **カスタムプラグイン** を作るためのワークスペースです。

## 目的と編集対象

- カスタムテーマ: `wordpress_data/wp-content/themes/`
  - 既存の作業用テーマ: `wordpress_data/wp-content/themes/my-child-theme/`
  - 詳細手順: `docs/custom-theme.md`
- カスタムプラグイン: `wordpress_data/wp-content/plugins/`
  - 既存の作業用プラグイン: `wordpress_data/wp-content/plugins/my-api-plugin/`
  - 詳細手順: `docs/custom-plugin.md`
- WordPress コア本体 (`wordpress_data/wp-admin/`, `wordpress_data/wp-includes/`) は基本的に変更しない。
- PHP は保存すると次のリクエストで反映される。通常はコンテナ再起動不要。

## 基本ワークフロー

1. 要件を「見た目」か「機能」に分ける。
   - 見た目、テンプレート、CSS、表示順、ブロックテーマ: テーマで実装する。
   - REST API、DB、カスタム投稿タイプ、管理画面、cron、外部連携、ビジネスロジック: プラグインで実装する。
2. まず `docs/custom-theme.md` または `docs/custom-plugin.md` を確認する。
3. 次に `.ai/skills/wordpress-router/SKILL.md` を読み、必要な専門 Skill に進む。
4. 実装は WordPress の API と規約を優先する。
5. セキュリティ確認を必ず行う。
   - 入力は sanitize / validate する。
   - 出力は `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` などで escape する。
   - 権限が必要な処理は `current_user_can` を使う。
   - state-changing なフォーム/AJAX/REST 操作には nonce や `permission_callback` を使う。
6. 変更後は最小限でも PHP 構文チェック、画面/API の smoke test、必要に応じて Xdebug 確認を行う。

## Skill の使い分け

Agent Skills 本体は `.ai/skills/` にあります。各 AI エージェントは、作業内容に応じて該当する `SKILL.md` を読んで従ってください。

最初の入口:

- WordPress 全般の分類: `.ai/skills/wordpress-router/SKILL.md`
- リポジトリ調査: `.ai/skills/wp-project-triage/SKILL.md`

テーマ作成:

- カスタムテーマ / block theme: `.ai/skills/wp-block-themes/SKILL.md`
- Gutenberg block: `.ai/skills/wp-block-development/SKILL.md`
- WordPress Design System: `.ai/skills/wpds/SKILL.md`

プラグイン作成:

- プラグイン構成、hooks、Settings API、lifecycle、security: `.ai/skills/wp-plugin-development/SKILL.md`
- REST API endpoint: `.ai/skills/wp-rest-api/SKILL.md`
- WP-CLI / 運用: `.ai/skills/wp-wpcli-and-ops/SKILL.md`
- PHP 実装一般: `.ai/skills/php-pro/SKILL.md`

品質確認:

- PHPStan / 静的解析: `.ai/skills/wp-phpstan/SKILL.md`
- 性能調査: `.ai/skills/wp-performance/SKILL.md`
- Plugin Directory 審査観点: `.ai/skills/wp-plugin-directory-guidelines/SKILL.md`

GitHub 作業:

- Issue 作成: `.ai/skills/github-create-issue/SKILL.md`
- PR 作成: `.ai/skills/github-create-pr/SKILL.md`

## ローカル環境の前提

- WordPress URL: `http://localhost:8080`
- Compose service: `wordpress`, `db`
- ソース bind mount: `wordpress_data:/var/www/html`
- Xdebug 設定: `xdebug.ini`
- VS Code debug 設定: `.vscode/launch.json`

よく使う確認コマンド:

```bash
podman compose ps
podman compose exec wordpress php -v
podman compose exec wordpress php -l /var/www/html/wp-content/plugins/my-api-plugin/my-api-plugin.php
podman compose logs -f wordpress
```

## 実装時の注意

- WordPress コアや公式同梱テーマ/プラグインを直接改造しない。
- 自作テーマ/プラグインに閉じて変更する。
- 既存の未コミット変更を勝手に戻さない。
- 本番反映を意識する場合は `README.md` のデプロイ説明と `deploy.sh` の同期対象を確認する。
- 秘密情報、認証情報、個人情報をコミットしない。

## Agent 用リンク

Skill 用リンク:

- Claude Code: `.claude/skills`
- Codex: `.codex/skills`
- GitHub Copilot: `.github/skills`
- Gemini: `.gemini/skills`

指示ファイル用リンク:

- Claude Code: `CLAUDE.md` -> `AGENT.md`
- Gemini: `GEMINI.md` -> `AGENT.md`
- GitHub Copilot: `.github/copilot-instructions.md` -> `../AGENT.md`

リンクがない環境では、この `AGENT.md` と `.ai/skills` を直接参照してください。
