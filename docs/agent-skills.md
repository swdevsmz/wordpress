# Agent Skills

このワークスペースには Agent Skills の本体を `.ai/skills/` に置いています。

- Claude Code 用: `.claude/skills` -> `.ai/skills`
- Codex 用: `.codex/skills` -> `.ai/skills`

Windows では管理者権限なしでディレクトリ symbolic link を作れないため、ローカルでは junction を使っています。Claude Code / Codex 側からは通常の `skills` ディレクトリとして読めます。確実に認識させるには、セッションを再起動してください。

Git では `.ai/skills/` だけを管理対象にし、`.claude/skills/` と `.codex/skills/` は `.gitignore` で除外しています。

## リンクを作り直す

macOS / Linux:

```bash
mkdir -p .claude .codex
ln -sfn ../.ai/skills .claude/skills
ln -sfn ../.ai/skills .codex/skills
```

Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force .claude, .codex
New-Item -ItemType Junction -Path .claude\skills -Target .ai\skills
New-Item -ItemType Junction -Path .codex\skills -Target .ai\skills
```

## 導入済み

| Skill | 説明 |
| --- | --- |
| `blueprint` | WordPress Playground の blueprint JSON ファイルを作成・編集・レビューするときに使う Skill。 |
| `github-create-issue` | バグ、機能要望、TODO、調査メモ、セキュリティ指摘、ユーザー報告から GitHub Issue を作るための Skill。 |
| `github-create-pr` | ローカル変更から GitHub Pull Request を作成・下書き・公開するための Skill。 |
| `php-pro` | PHP 8.3+、Laravel、Symfony、Composer、PHPStan、PSR、PHPUnit/Pest、DTO、DI、REST/GraphQL API などの PHP 実装補助 Skill。 |
| `skill-creator` | 新しい Skill の作成、既存 Skill の改善、評価、ベンチマーク、description 最適化を行うための Skill。 |
| `wordpress-router` | WordPress リポジトリを分類し、ブロック、テーマ、REST API、WP-CLI、性能、セキュリティ、テストなど適切な workflow/Skill に振り分ける Skill。 |
| `wp-abilities-api` | WordPress Abilities API (`wp_register_ability` など) の定義、カテゴリ、メタ情報、REST 公開、権限チェックを扱う Skill。 |
| `wp-block-development` | Gutenberg ブロック開発用。`block.json`、属性、serialization、supports、dynamic rendering、deprecations、ビルド・テスト workflow を扱う Skill。 |
| `wp-block-themes` | WordPress block theme 開発用。`theme.json`、templates、template parts、patterns、style variations、Site Editor のトラブルシュートを扱う Skill。 |
| `wp-interactivity-api` | WordPress Interactivity API 用。`data-wp-*` directives、store/state/actions、`viewScriptModule`、hydration、directive 挙動を扱う Skill。 |
| `wp-performance` | WordPress 性能調査・改善用。WP-CLI profile/doctor、Server-Timing、Query Monitor、DB/query、autoload options、object cache、cron、HTTP API を扱う Skill。 |
| `wp-phpstan` | WordPress プロジェクトでの PHPStan 設定、実行、修正、baseline、WordPress 固有 typing、第三者 plugin class 対応を扱う Skill。 |
| `wp-playground` | WordPress Playground 用。ブラウザ/CLI の一時 WordPress、blueprints、plugin/theme auto-mount、WP/PHP version 切り替え、Xdebug を扱う Skill。 |
| `wp-plugin-development` | WordPress plugin 開発用。architecture、hooks、activation/deactivation/uninstall、admin UI、Settings API、data storage、cron、security、release packaging を扱う Skill。 |
| `wp-plugin-directory-guidelines` | WordPress.org Plugin Directory guidelines、GPL、license headers、upsell/freemium、naming/trademark、plugin slug、審査落ち理由の確認用 Skill。 |
| `wp-project-triage` | WordPress リポジトリを決定的に検査し、project type、tooling、tests、version hints、guardrails を JSON レポート化する Skill。 |
| `wp-rest-api` | WordPress REST API 用。`register_rest_route`、controller、schema/argument validation、`permission_callback`、response shaping、CPT/taxonomy REST 公開を扱う Skill。 |
| `wp-wpcli-and-ops` | WP-CLI 運用用。search-replace、DB export/import、plugin/theme/user/content 管理、cron、cache、multisite、自動化、`wp-cli.yml` を扱う Skill。 |
| `wpds` | WordPress Design System (WPDS) の components、tokens、patterns を使った UI 構築用 Skill。 |

主な外部ソース:

- WordPress 公式: [WordPress/agent-skills](https://github.com/WordPress/agent-skills)
- PHP 実装補助: [Jeffallan/claude-skills の `php-pro`](https://github.com/Jeffallan/claude-skills/tree/main/skills/php-pro)

## 更新方法

WordPress 公式 Skills を更新する場合:

```bash
git clone https://github.com/WordPress/agent-skills.git /tmp/wordpress-agent-skills
cd /tmp/wordpress-agent-skills
node shared/scripts/skillpack-build.mjs --clean
node shared/scripts/skillpack-install.mjs --dest=/path/to/this/repo --targets=claude
```

`php-pro` を更新する場合:

```bash
git clone https://github.com/Jeffallan/claude-skills.git /tmp/claude-skills
cp -R /tmp/claude-skills/skills/php-pro /path/to/this/repo/.ai/skills/php-pro
```

第三者 Skill は `SKILL.md` と `scripts/` の差分を確認してから更新してください。
