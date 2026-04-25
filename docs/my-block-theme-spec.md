# My Block Theme 仕様書

この仕様書は、`wordpress_data/wp-content/themes/my-block-theme/` に作成した WordPress ブロックテーマの構成、目的、実装内容、検証結果をまとめたものです。

## 目的

`my-block-theme` は、WordPress の投稿一覧を「読み物メディア」風に表示するための最小ブロックテーマです。

初期実装では、次のことを目的にしています。

- WordPress 管理画面の「外観 > テーマ」から有効化できること
- トップページで投稿一覧を表示できること
- Site Editor で扱いやすい `theme.json` ベースのブロックテーマであること
- Flutter アプリの WebView でも横スクロールせず表示できること
- WordPress コア、既存テーマ、既存子テーマを変更しないこと

## ディレクトリ構成

```text
wordpress_data/wp-content/themes/my-block-theme/
├── style.css
├── functions.php
├── theme.json
├── templates/
│   └── index.html
└── parts/
    ├── header.html
    └── footer.html
```

### `style.css`

テーマヘッダと追加 CSS を持つファイルです。

役割:

- WordPress にテーマ情報を認識させる
- 記事一覧カード、ヘッダー、フッター、ページネーションの見た目を調整する
- スマホ幅での余白、見出しサイズ、記事タイトルサイズを調整する
- 長い投稿タイトルでも横にはみ出しにくいようにする

主な指定:

- テーマ名: `My Block Theme`
- テキストドメイン: `my-block-theme`
- 最低 WordPress: `6.5`
- PHP: `7.4`
- `body { overflow-x: hidden; }`
- `.article-list .wp-block-post` を白背景のカード風に表示
- `@media (max-width: 700px)` と `@media (max-width: 380px)` でスマホ向けに調整

### `functions.php`

テーマ CSS をフロント側に読み込ませるための最小 PHP ファイルです。

```php
wp_enqueue_style(
    'my-block-theme-style',
    get_stylesheet_uri(),
    array(),
    wp_get_theme()->get( 'Version' )
);
```

ブロックテーマでは `theme.json` のスタイルが中心になりますが、今回のカード風デザインや WebView 向け調整は `style.css` に書いているため、`wp_enqueue_scripts` で明示的に読み込んでいます。

### `theme.json`

ブロックテーマのグローバル設定とデフォルトスタイルを定義します。

役割:

- Site Editor に公開する色、余白、フォントサイズを定義する
- テーマ全体の背景色、本文色、見出し、リンク、ボタンの初期スタイルを決める
- `Header` と `Footer` の template part を登録する

主な設定:

| 項目 | 内容 |
| --- | --- |
| `version` | `3` |
| `appearanceTools` | `true` |
| `layout.contentSize` | `720px` |
| `layout.wideSize` | `1120px` |
| 本文フォント | system sans-serif |
| 見出しフォント | Georgia 系 serif |
| 背景色 | `base` = `#fbfaf7` |
| 記事カード背景 | `surface` = `#ffffff` |
| アクセント色 | `accent` = `#0b6f6a` |
| 補助アクセント | `highlight` = `#a94b2b` |

## テンプレート構成

### `templates/index.html`

トップページ兼、投稿一覧のメインテンプレートです。

構成:

1. `parts/header.html` を読み込む
2. `main` 内に記事一覧の見出しエリアを表示する
3. Query Loop で投稿一覧を表示する
4. 投稿がない場合のメッセージを表示する
5. ページネーションを表示する
6. `parts/footer.html` を読み込む

記事一覧で表示する情報:

- カテゴリー
- 投稿タイトル
- 投稿日
- 抜粋
- `Read more` リンク

Query Loop の条件:

- 投稿タイプ: `post`
- 1ページあたり: `10`
- 並び順: 新しい順
- `inherit: true`

### `parts/header.html`

サイトヘッダー用の template part です。

表示内容:

- サイトタイトル
- WordPress の Navigation ブロック

特徴:

- 横幅は `alignwide`
- PC ではサイトタイトルとナビを左右に配置
- モバイルでは Navigation ブロックの overlay menu を使う
- スマホ幅では `style.css` 側で sticky header にしている

### `parts/footer.html`

サイトフッター用の template part です。

表示内容:

- `Built with My Block Theme.`
- WordPress.org への Social Link

特徴:

- ヘッダーと同じく `alignwide`
- フッター上部に区切り線を表示
- スマホでも折り返せる flex レイアウト

## デザイン仕様

このテーマは、派手なランディングページではなく、Flutter WebView 内でも読みやすい「記事一覧ビュー」を優先しています。

### 全体

- 背景は少し暖かい白 (`#fbfaf7`)
- 本文は濃いグレー (`#1f2428`)
- 補助テキストは muted グレー (`#687076`)
- アクセントは緑寄り (`#0b6f6a`) と茶赤 (`#a94b2b`)
- カード角丸は `8px`

### 記事一覧

`.article-list` 配下の投稿はカード風に表示します。

- 白背景
- 1px border
- 左にアクセント線
- 軽い shadow
- カテゴリーは pill 型のラベル表示
- タイトルは hover 時にアクセント色へ変更
- 長いタイトルは `overflow-wrap: anywhere` で折り返し

### レスポンシブ

スマホ向けに次の調整を入れています。

- ルート左右余白を `1rem` に調整
- ヘッダーを sticky 表示
- ヒーロー見出しを `2rem`、380px 以下では `1.75rem` に縮小
- 記事カードの余白を縮小
- 投稿タイトルを `1.35rem` に縮小
- ページネーションの gap を調整

## 検証結果

実装後に次を確認済みです。

- WordPress が `My Block Theme 0.1.0` としてテーマを認識
- `theme.json` が JSON として正常
- `functions.php` の PHP 構文が正常
- `http://localhost:8080/` が HTTP 200 を返す
- テーマ CSS が読み込まれる
- `body` に `wp-theme-my-block-theme` が付与される
- 投稿一覧に `最近の記事` と投稿カードが表示される

スマホ幅チェック:

| 幅 | 横スクロール | 見出し | 投稿カード |
| --- | --- | --- | --- |
| 375px | なし | 表示OK | 表示OK |
| 390px | なし | 表示OK | 表示OK |
| 430px | なし | 表示OK | 表示OK |
| 768px | なし | 表示OK | 表示OK |

## 今後の拡張候補

初期実装では最小構成に絞っているため、次のテンプレートや機能は未実装です。

- `templates/single.html`: 個別記事ページ
- `templates/page.html`: 固定ページ
- `templates/archive.html`: アーカイブページ
- `templates/404.html`: 404 ページ
- `patterns/`: Site Editor で挿入できるテーマ独自パターン
- `styles/`: カラーバリエーション
- `screenshot.png`: 管理画面のテーマサムネイル

次に作るなら、記事一覧からの導線を自然にするため `single.html` を追加するのがよいです。
