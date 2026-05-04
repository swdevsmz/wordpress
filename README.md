# WordPress 開発環境

Windows 11 WSL2 (Ubuntu 24.04) 上の Docker Compose で WordPress + MariaDB を動かす構成です。

## 構成

| サービス    | イメージ                                 | ポート                  |
| ----------- | ---------------------------------------- | ----------------------- |
| `wordpress` | `wordpress:latest` ベース（Xdebug 追加） | `http://localhost:8080` |
| `db`        | `mariadb:10.11`                          | コンテナ内のみ (3306)   |

- WordPress ファイル: `./wordpress_data`（ホスト bind mount → PHP を直接編集可）
- DB データ: named volume `db_data`
- Xdebug 設定: `xdebug.ini` はイメージビルド時にコピーする
- Xdebug ポート: `9003`

## 事前準備

### 1. WSL2 + Ubuntu 24.04 のインストール

PowerShell（管理者）で実行します。

```powershell
wsl --install -d Ubuntu-24.04
```

インストール後、Ubuntu を起動してユーザー名・パスワードを設定してください。

### 2. Docker の準備

Ubuntu 24.04 のターミナルで実行します。

```bash
# Windows 側で Docker Desktop を起動しておく
docker version
```

### 3. VS Code の準備

1. [Visual Studio Code](https://code.visualstudio.com/) をホスト（Windows）にインストール
2. 必要なら **PHP Debug** (`xdebug.php-debug`) を入れる
3. Ubuntu上の任意の場所に当リポジトリをクローン
4. VS Codeからクローンしたリポジトリを開く

## 起動手順

### 1. `.env` を用意する

```bash
cp .env.example .env
# .env を開いてパスワード等を設定する
```

### 2. Compose で起動する

Ubuntu のターミナルでリポジトリを開き、次を実行します。

```bash
docker compose up --build
```

初回はイメージのビルドに数分かかります。`wordpress_data` が空なら、公式 entrypoint が WordPress 本体をそこへコピーします。あとはブラウザで初期セットアップ画面を開いてください。

管理画面: <http://localhost:8080/wp-admin>

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
