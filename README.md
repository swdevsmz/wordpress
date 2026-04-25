# WordPress (Podman Compose)

Podman Compose で WordPress + MariaDB をローカル起動するための最小構成です。

## 構成

| サービス    | イメージ                           | ポート                  |
| ----------- | ---------------------------------- | ----------------------- |
| `wordpress` | `wordpress:latest` + Xdebug (自作) | `http://localhost:8080` |
| `db`        | `mariadb:10.11`                    | コンテナ内のみ (3306)   |

- WordPress のソース: `./wordpress_data` (ホストに bind mount → **PHP ソースを直接編集可**)
- DB のデータ: named volume `db_data`

## 前提

- Podman / Podman Desktop がインストール済み (`podman --version` で確認)
- ポート `8080` が空いていること
- `podman compose` サブコマンドが使えること (Podman 4.x 以降)
  - 使えない場合は `pip install podman-compose` で `podman-compose` を導入し、以下のコマンド中の `podman compose` を `podman-compose` に読み替えてください。

> **Podman マシンの起動 (Windows / Mac)**
>
> Windows / Mac は内部で Linux VM を使うため、事前に VM を起動しておきます。
>
> ```bash
> podman machine init   # 初回のみ
> podman machine start
> ```

## 起動

初回起動時はイメージ pull と WordPress ファイルの展開が走るため少し時間がかかります。

> [!IMPORTANT]
> 初回のみ bind mount 先のディレクトリを手動で作成してください。Podman は Docker と違いホスト側パスを自動作成しません (未作成だと `statfs ... no such file or directory` エラーで起動失敗します)。
>
> ```bash
> mkdir wordpress_data
> ```

```bash
# バックグラウンドで起動
podman compose up -d

# ログを見ながら起動 (Ctrl+C で抜けてもコンテナは止まらない)
podman compose up
```

起動後、ブラウザで <http://localhost:8080> を開くと WordPress の初期セットアップ画面が表示されます。

### 起動確認

```bash
podman compose ps
```

`wordpress` と `db` が `running` / `healthy` になっていれば OK。

## 停止

コンテナを停止するだけ (データは残る、再起動で続きから使える)。

```bash
podman compose stop
```

再開:

```bash
podman compose start
```

## 削除

### コンテナとネットワークのみ削除 (データは残す)

```bash
podman compose down
```

`./wordpress_data` と `db_data` volume は残るため、`podman compose up -d` で元の状態に戻せます。

### データも含めて完全削除

DB の named volume まで削除:

```bash
podman compose down -v
```

さらに WordPress のファイルも含めて完全にリセットする場合:

```bash
podman compose down -v
rm -rf ./wordpress_data
```

> [!WARNING]
> `down -v` と `rm -rf ./wordpress_data` を実行すると、投稿・設定・アップロード画像・テーマ等が**すべて消えます**。必要ならバックアップを取ってから実行してください。

### イメージも削除してディスクを空ける

```bash
podman compose down -v
podman rmi wordpress:latest mariadb:10.11
```

## PHP ソースの見方・ディレクトリ構成

`./wordpress_data:/var/www/html` の bind mount により、**コンテナ内の `/var/www/html` がホストの `./wordpress_data` に同期されます**。
初回 `podman compose up` 実行時に WordPress 公式イメージが中身を `./wordpress_data` にコピーするので、そのあと VS Code などでそのまま PHP を閲覧・編集できます。

### 主なディレクトリ構成

```text
wordpress_data/
├── index.php              … リクエストのエントリポイント
├── wp-config.php          … DB 接続情報など (Podman 起動時に自動生成)
├── wp-config-sample.php   … 設定ファイルの雛形
├── wp-load.php / wp-settings.php … ブートストラップ
├── wp-login.php           … ログイン画面
├── .htaccess              … URL リライト (パーマリンク設定後に生成)
│
├── wp-admin/              … 管理画面 (コア。基本さわらない)
├── wp-includes/           … コア関数・クラス群 (さわらない)
│
└── wp-content/            … ★ユーザーがさわる領域はほぼここ
    ├── themes/            … テーマ (見た目)
    │   ├── twentytwentyfour/
    │   └── ...
    ├── plugins/           … プラグイン
    │   ├── akismet/
    │   └── hello.php
    ├── uploads/           … 画像などアップロードファイル (自動生成)
    ├── languages/         … 翻訳ファイル
    └── index.php
```

### どこをさわるべきか

| やりたいこと            | 編集場所                                                                      |
| ----------------------- | ----------------------------------------------------------------------------- |
| テーマの見た目を変える  | `wp-content/themes/<テーマ名>/`                                               |
| 自作テーマを作る        | `wp-content/themes/` に新規ディレクトリを作成                                 |
| 自作プラグインを作る    | `wp-content/plugins/` に新規ディレクトリを作成                                |
| DB 接続情報など環境設定 | `wp-config.php` (通常は `compose.yaml` の env で上書きされるので直接編集不要) |
| コア本体                | `wp-admin/` `wp-includes/` → **触らない** (アップデートで消える)              |

### 編集の反映

PHP はインタプリタ言語なのでファイル保存 = 即反映です (ブラウザ再読み込みで OK)。
コンテナの再起動は不要です。

### 権限まわりの注意 (Podman)

rootless Podman (Linux) ではコンテナ内の `www-data` ユーザーとホストのユーザーが UID マッピングされます。
ホスト側から編集して保存できない / コンテナ側から書き込みできない場合は以下を試してください。

```bash
# ホスト側の所有者を自分に戻す
podman unshare chown -R $(id -u):$(id -g) ./wordpress_data

# SELinux 環境なら compose.yaml の volume を `:Z` 付きに
#   - ./wordpress_data:/var/www/html:Z
```

Windows (WSL2) / Mac の Podman Desktop 環境では通常このケアは不要です。

## よく使うコマンド

```bash
# ログ表示 (末尾追従)
podman compose logs -f

# 特定サービスのログのみ
podman compose logs -f wordpress

# コンテナ内に入る
podman compose exec wordpress bash
podman compose exec db bash

# MariaDB に直接入る
podman compose exec db mariadb -u wp_user -pwp_password wordpress

# 再起動
podman compose restart
```

## VS Code でステップ実行デバッグ (Xdebug)

`wordpress` コンテナに Xdebug をインストールしてあり、ホストの VS Code からステップ実行できます。

### 関連ファイル

| ファイル                                   | 役割                                                                         |
| ------------------------------------------ | ---------------------------------------------------------------------------- |
| [Dockerfile](Dockerfile)                   | `wordpress:latest` をベースに Xdebug を `pecl install` する自作イメージ      |
| [xdebug.ini](xdebug.ini)                   | Xdebug 3 の設定 (`mode=debug` / port `9003` / `host.containers.internal` 宛) |
| [.vscode/launch.json](.vscode/launch.json) | VS Code の Listen 設定とパスマッピング                                       |
| [compose.yaml](compose.yaml)               | `image:` を `build: .` に変更し、`extra_hosts` で `host-gateway` を追加      |

### デバッグの前提

- VS Code 拡張 **PHP Debug** (`xdebug.php-debug`) をインストール
- ポート 9003 がホスト側で他に使われていないこと

### 手順

1. イメージを再ビルド (Xdebug 入りに切り替え)

   ```bash
   podman compose down
   podman compose build
   podman compose up -d
   ```

2. Xdebug が有効になっているか確認

   ```bash
   podman compose exec wordpress php -v
   # → 末尾に "with Xdebug v3.x" と表示されれば OK
   ```

3. VS Code の「実行とデバッグ」から **Listen for Xdebug** を起動 (F5)
4. `wordpress_data/` 配下の `.php` (テーマ・プラグイン・コアどこでも可) にブレークポイントを設定
5. ブラウザで <http://localhost:8080> にアクセス → 該当行で停止し、変数・コールスタックを確認できます

### パスマッピング

`./wordpress_data:/var/www/html` の bind mount に合わせて、コンテナ内のパスとホスト側のパスを対応付けます (これがズレるとブレークポイントが効きません)。

```jsonc
// .vscode/launch.json
"pathMappings": {
  "/var/www/html": "${workspaceFolder}/wordpress_data"
}
```

### Xdebug のトラブルシューティング

- **ブレークポイントで止まらない**
  - コンテナ内の Xdebug ログを確認: `podman compose exec wordpress cat /tmp/xdebug.log`
  - VS Code の Listen が起動していない / 別ポートで待ち受けていないか確認
- **`Could not connect to client` がログに出る**
  - `host.containers.internal` がコンテナから解決できていない可能性
  - rootful Podman (Linux) の場合は `xdebug.ini` の `client_host` をホストの実 IP (例: `172.17.0.1`) に変更
  - `compose.yaml` の `extra_hosts: - "host.containers.internal:host-gateway"` が効いているか確認
- **本番にデプロイ時は Xdebug を切りたい**
  - 本番では [compose.prod.yaml](compose.prod.yaml) で素の `wordpress:latest` を使うか、`xdebug.mode=off` の ini を上書き bind mount する

## サーバーへのデプロイ

ローカル ([compose.yaml](compose.yaml)) で動かした WordPress の自作テーマ/プラグインを、サーバー ([compose.prod.yaml](compose.prod.yaml)) に rsync で同期する構成です。

### 全体像

```text
┌────────────────────────────┐           ┌────────────────────────────┐
│ ローカル (compose.yaml)    │           │ サーバー (compose.prod.yaml)│
│                            │           │                             │
│ wordpress_data/wp-content/ │  deploy.sh│ /srv/wordpress/wp-content/  │
│   themes/my-child-theme/   │ ────────▶ │   themes/my-child-theme/    │
│   plugins/my-api-plugin/   │   rsync   │   plugins/my-api-plugin/    │
│                            │           │        │ (:ro bind mount)   │
│                            │           │        ▼                    │
│                            │           │  wordpress コンテナ         │
└────────────────────────────┘           └────────────────────────────┘
```

### ファイル構成

| ファイル                                 | 用途                                                             |
| ---------------------------------------- | ---------------------------------------------------------------- |
| [compose.yaml](compose.yaml)             | ローカル開発用。`wordpress_data/` をフル bind mount で直編集可能 |
| [compose.prod.yaml](compose.prod.yaml)   | サーバー用。コアは named volume に隠し、カスタム資産だけ上書き   |
| [deploy.sh](deploy.sh)                   | ローカル → サーバーへの rsync スクリプト                         |

### ローカルと本番の違い

|                | ローカル                         | 本番                                   |
| -------------- | -------------------------------- | -------------------------------------- |
| project name   | `wordpress` (ディレクトリ名)     | `wordpress-prod` (`name:` で明示)      |
| WordPress コア | `./wordpress_data` を bind mount | named volume `wp_html` に隠蔽          |
| カスタム資産   | `wordpress_data/wp-content/...`  | `./wp-content/...` を **:ro** で上書き |
| ポート         | `8080:80`                        | `8081:80`                              |
| DB 認証情報    | `compose.yaml` にベタ書き        | `compose.prod.yaml` に同じ値をベタ書き |

project name とポートが分離されているので、同じマシン上で両方を並行稼働できます (デプロイの動作確認用):

```bash
# ターミナル A: ローカル開発環境
podman compose up -d
# → http://localhost:8080

# ターミナル B: 本番相当環境 (同じマシンで検証用)
docker compose -f compose.prod.yaml up -d
# → http://localhost:8081
```

コンテナ・volume も `wordpress_*` と `wordpress-prod_*` で名前空間が分かれるため干渉しません。

### サーバー側の初回セットアップ

```bash
# サーバー上で
sudo mkdir -p /srv/wordpress/wp-content/{themes,plugins}
sudo chown -R $USER:$USER /srv/wordpress
cd /srv/wordpress

# ローカルから compose.prod.yaml をコピー
scp local:path/to/wordpress/compose.prod.yaml .

# 起動
docker compose -f compose.prod.yaml up -d
```

### デプロイ

ローカルで自作テーマ/プラグインを編集したら、以下のコマンドで同期します。

```bash
# 初回のみ実行権限付与
chmod +x deploy.sh

# 同期
SERVER_HOST=user@example.com ./deploy.sh
```

PHP は保存 = 即反映なのでコンテナ再起動は基本不要です。初回だけサーバーの管理画面 (`https://本番ドメイン/wp-admin`) でテーマ/プラグインを「有効化」してください。

### 同期対象を追加する

新しいカスタムテーマ/プラグインを増やす時は 2 箇所に追記:

1. [deploy.sh](deploy.sh) の `TARGETS` 配列に `themes/<name>` or `plugins/<name>` を追加
2. [compose.prod.yaml](compose.prod.yaml) の `volumes` に同じ bind mount 行を追加

### 注意点

- **rsync はコードのみ同期**。DB (投稿・設定・ユーザー) と `uploads/` (画像) は含まれません。ローカルのコンテンツをそのまま本番に持っていきたい場合は `mariadb-dump` + `wp search-replace` で別途移行してください
- **ローカルと本番は独立したサイト**。ローカルでプラグインを有効化しても本番側では有効化されません
- **同期対象は [deploy.sh](deploy.sh) の `TARGETS` 配列に書いたものだけ**。配列に無いディレクトリは rsync されません
- **DB パスワードは検証用のベタ書き**。本番公開するなら `compose.prod.yaml` の値を強いランダム値に変更するか env_file 化を検討してください

## トラブルシューティング

- **`podman compose` コマンドがない**
  - `pip install podman-compose` で `podman-compose` を入れ、本 README のコマンドを `podman-compose ...` に置き換え
- **`Cannot connect to Podman` エラー**
  - `podman machine start` で VM が起動しているか確認 (Windows / Mac)
- **`http://localhost:8080` が開かない**
  - `podman compose ps` で `wordpress` が `running` か確認
  - 他プロセスが 8080 を使っていないか確認 (`compose.yaml` の `ports` を `"8081:80"` 等に変更)
- **DB 接続エラーが出る**
  - `db` が `healthy` になる前に `wordpress` が起動している可能性。`podman compose restart wordpress` で再試行
- **`wordpress_data/` に何もコピーされない**
  - ディレクトリが空でない状態で起動するとコピーがスキップされます。空にしてから `podman compose up -d`
- **最初からやり直したい**
  - `podman compose down -v && rm -rf ./wordpress_data` で完全リセット
