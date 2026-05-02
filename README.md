# WordPress 開発環境

Windows 11 WSL2 (Ubuntu 24.04) 上の Docker + VS Code Dev Container で WordPress + MariaDB を動かす構成です。

## 構成

| サービス    | イメージ                                 | ポート                  |
| ----------- | ---------------------------------------- | ----------------------- |
| `wordpress` | `wordpress:latest` ベース（Xdebug 追加） | `http://localhost:8080` |
| `db`        | `mariadb:10.11`                          | コンテナ内のみ (3306)   |

- WordPress ファイル: `./wordpress_data`（ホスト bind mount → PHP を直接編集可）
- DB データ: named volume `db_data`
- Xdebug ポート: `9003`

## 起動手順

### 1. `.env` を用意する

```bash
cp .env.example .env
# .env を開いてパスワード等を設定する
```

### 2. Dev Container を開く

VS Code でリポジトリを開き、コマンドパレットから **「Dev Containers: Reopen in Container」** を選択します。
初回はイメージのビルドに数分かかります。起動後、WordPress の初期インストールまで自動で完了します。

管理画面: <http://localhost:8080/wp-admin>（認証情報は `.env` の `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` を参照）

## 停止・削除

```bash
# コンテナ停止（データは残る）
docker compose stop

# コンテナ＋ネットワーク削除（データは残る）
docker compose down

# DB データも含めて完全削除
docker compose down -v
```

> [!WARNING]
> `down -v` を実行すると DB の全データが消えます。

## カスタマイズ領域

```text
wordpress_data/wp-content/
├── themes/    … テーマ
└── plugins/   … プラグイン
```

PHP はファイル保存で即反映されます（コンテナ再起動不要）。

## Xdebug によるステップ実行

VS Code 拡張 **PHP Debug** (`xdebug.php-debug`) を入れた状態で、「実行とデバッグ」から **Listen for Xdebug** を起動（F5）します。`wordpress_data/` 配下の任意の `.php` にブレークポイントを設定してブラウザでアクセスすると停止します。
