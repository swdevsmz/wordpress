# My Classic Theme 仕様書

この仕様書は、`wordpress_data/wp-content/themes/my-classic-theme/` に作成した WordPress クラシックテーマの構成、目的、実装内容、検証結果をまとめたものです。

## 目的

`my-classic-theme` は、先に作成した `my-block-theme` と近い見た目を、PHP テンプレート中心のクラシックテーマとして再実装したものです。

初期実装では、次のことを目的にしています。

- WordPress 管理画面の「外観 > テーマ」から有効化できること
- トップページで投稿一覧を表示できること
- `index.php`、`header.php`、`footer.php` から表示構造を追いやすいこと
- Flutter アプリの WebView でも横スクロールせず表示できること
- WordPress コア、既存テーマ、既存子テーマ、既存ブロックテーマを変更しないこと

## ディレクトリ構成

```text
wordpress_data/wp-content/themes/my-classic-theme/
├── style.css
├── functions.php
├── header.php
├── footer.php
├── index.php
└── template-parts/
    └── content-card.php
```

## ファイル構成

### `style.css`

テーマヘッダと全体 CSS を持つファイルです。

役割:

- WordPress にテーマ情報を認識させる
- ブロックテーマ版に近い色、余白、カード風記事一覧を定義する
- スマホ WebView 向けに横スクロールを抑制する
- 700px 以下、380px 以下でモバイル表示を調整する

主な指定:

- テーマ名: `My Classic Theme`
- テキストドメイン: `my-classic-theme`
- 最低 WordPress: `6.5`
- PHP: `7.4`
- `body { overflow-x: hidden; }`
- `.article-card` を白背景のカード風に表示
- `.article-card__title a { overflow-wrap: anywhere; }`
- `@media (max-width: 700px)` と `@media (max-width: 380px)` でスマホ向けに調整

### `functions.php`

テーマ初期化と CSS 読み込みを行うファイルです。

主な処理:

- 翻訳ファイル読み込み用に `load_theme_textdomain()` を呼ぶ
- `title-tag` を有効化する
- アイキャッチ画像を有効化する
- HTML5 マークアップを有効化する
- カスタムロゴを有効化する
- responsive embeds を有効化する
- `primary` メニューを登録する
- `style.css` を `wp_enqueue_scripts` で読み込む

CSS 読み込み:

```php
wp_enqueue_style(
    'my-classic-theme-style',
    get_stylesheet_uri(),
    array(),
    wp_get_theme()->get( 'Version' )
);
```

### `header.php`

サイト共通ヘッダーを出力します。

役割:

- `<!DOCTYPE html>`、`<html>`、`<head>`、`<body>` を開始する
- `wp_head()` を呼び、WordPress とプラグインが CSS/JS を追加できるようにする
- `wp_body_open()` を呼ぶ
- サイトタイトルをトップページへのリンクとして表示する
- `primary` メニューがあれば `wp_nav_menu()` で表示する
- メニュー未設定の場合は `wp_page_menu()` で固定ページ一覧を表示する

### `footer.php`

サイト共通フッターを出力します。

役割:

- フッター領域を表示する
- `Built with My Classic Theme.` を表示する
- サイト名へのリンクを表示する
- `wp_footer()` を呼び、WordPress とプラグインがフッター用 JS を追加できるようにする
- `</body>` と `</html>` を閉じる

### `index.php`

トップページ兼、投稿一覧のメインテンプレートです。

構成:

1. `get_header()` でヘッダーを読み込む
2. ヒーロー領域に `Journal`、`最近の記事`、説明文を表示する
3. WordPress ループで投稿一覧を表示する
4. 各投稿は `template-parts/content-card.php` に分離して表示する
5. `the_posts_pagination()` でページネーションを表示する
6. 投稿がない場合はメッセージを表示する
7. `get_footer()` でフッターを読み込む

表示テキスト:

- ラベル: `Journal`
- 見出し: `最近の記事`
- 説明文: `更新情報、読みもの、制作メモを落ち着いて読める一覧です。`

### `template-parts/content-card.php`

投稿カード 1 件分を表示するテンプレートパーツです。

表示する情報:

- カテゴリー
- 投稿タイトル
- 投稿日
- 抜粋
- `Read more` リンク

主な WordPress 関数:

- `the_ID()`
- `post_class()`
- `get_the_category_list()`
- `the_permalink()`
- `the_title()`
- `get_the_date()`
- `the_excerpt()`

出力時の安全性:

- 投稿日の `datetime` 属性は `esc_attr()` でエスケープ
- 日付表示は `esc_html()` でエスケープ
- カテゴリーリンクは WordPress が生成した HTML を `wp_kses_post()` で許可
- 固定文言は `esc_html_e()` を使用

## デザイン仕様

このテーマは、`my-block-theme` と同じく「読み物メディア」風の記事一覧を目指しています。ただし、ブロックコメントや `theme.json` に依存せず、PHP と CSS で追いやすい構成にしています。

### 全体

- 背景は少し暖かい白 (`#fbfaf7`)
- 本文は濃いグレー (`#1f2428`)
- 補助テキストは muted グレー (`#687076`)
- アクセントは緑寄り (`#0b6f6a`) と茶赤 (`#a94b2b`)
- 本文フォントは system sans-serif
- 見出しフォントは Georgia 系 serif

### 記事一覧

`.article-list` の中に `.article-card` を並べます。

- 白背景
- 1px border
- 左にアクセント線
- 8px 角丸
- 軽い shadow
- カテゴリーは pill 型のラベル表示
- タイトル hover 時にアクセント色へ変更
- 長いタイトルは `overflow-wrap: anywhere` で折り返し

### レスポンシブ

スマホ向けに次の調整を入れています。

- 700px 以下でヘッダーを sticky 表示
- ヘッダーとフッターを縦並びにする
- メイン上下余白を縮小する
- ヒーロー見出しを `2rem`、380px 以下では `1.75rem` に縮小する
- 投稿カードの余白を縮小する
- 投稿タイトルを `1.35rem` に縮小する

## ブロックテーマ版との違い

| 項目 | `my-block-theme` | `my-classic-theme` |
| --- | --- | --- |
| テンプレート | `templates/*.html` | `*.php` |
| 共通部品 | `parts/*.html` | `header.php`, `footer.php`, `template-parts/*.php` |
| デザイン設定 | `theme.json` + CSS | CSS |
| Site Editor | 扱いやすい | 主対象ではない |
| プログラマの追いやすさ | ブロックコメントを読む必要あり | PHP テンプレートとして追いやすい |
| WebView 向け調整 | CSS で対応 | CSS で対応 |

## 検証結果

実装後に次を確認済みです。

- WordPress が `My Classic Theme 0.1.0` としてテーマを認識
- すべての PHP ファイルで構文エラーなし
- ローカル WordPress の有効テーマを `my-classic-theme` に切り替え可能
- `http://localhost:8080/` が HTTP 200 を返す
- テーマ CSS が読み込まれる
- 投稿一覧に `最近の記事` と投稿カードが表示される
- 投稿カードにカテゴリー、タイトル、日付、抜粋、`Read more` が表示される

スマホ幅チェック:

| 幅 | 横スクロール | 見出し | 投稿カード |
| --- | --- | --- | --- |
| 375px | なし | 表示OK | 表示OK |
| 390px | なし | 表示OK | 表示OK |
| 430px | なし | 表示OK | 表示OK |
| 768px | なし | 表示OK | 表示OK |

## 今後の拡張候補

初期実装では記事一覧に絞っているため、次のテンプレートや機能は未実装です。

- `single.php`: 個別記事ページ
- `page.php`: 固定ページ
- `archive.php`: アーカイブページ
- `search.php`: 検索結果ページ
- `404.php`: 404 ページ
- `template-parts/content-single.php`: 個別記事用テンプレートパーツ
- `screenshot.png`: 管理画面のテーマサムネイル

次に作るなら、記事一覧から遷移した先の体験を整えるため `single.php` を追加するのがよいです。
