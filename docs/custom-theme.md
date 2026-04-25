# WordPress カスタムテーマの作り方

このドキュメントでは、本リポジトリ ([README.md](../README.md)) の Podman Compose 環境を前提に、自作テーマを作る手順をまとめます。

- 編集対象: `wordpress_data/wp-content/themes/<テーマ名>/`
- 同期対象: [deploy.sh](../deploy.sh) の `TARGETS` に追加すれば本番に rsync される
- PHP は保存 = 即反映 (コンテナ再起動不要)

## テーマの 3 つの選択肢

| 種類             | 用途                                                       | 学習コスト | 本ドキュメント |
| ---------------- | ---------------------------------------------------------- | ---------- | -------------- |
| 子テーマ         | 既存テーマ (例: Twenty Twenty-Five) を**少しだけ**カスタム | 低         | ★メイン        |
| クラシックテーマ | PHP テンプレートで**ガッツリ**自作                         | 中         | ○後半でカバー  |
| ブロックテーマ   | サイトエディタ (FSE) 前提、HTML + theme.json で構築        | 中〜高     | △触れる程度    |

> **どれを選ぶか**
> - 既存テーマの色・CSS・関数を上書きしたいだけ → **子テーマ**
> - 完全独自デザイン・固有の構造で作りたい → **クラシックテーマ** or **ブロックテーマ**
> - 管理画面の「サイトエディタ」でブロック編集したい → **ブロックテーマ**

---

## 1. 子テーマを作る (推奨スタート)

リポジトリには既に `wp-content/themes/my-child-theme/` という空ディレクトリが用意されています。ここに最小ファイルを置けば子テーマの完成です。

### 1.1 親テーマを決める

例として `twentytwentyfive` (WordPress に同梱) を親に使います。
親テーマのスラッグ (フォルダ名) を控えておきます。

```bash
ls wordpress_data/wp-content/themes/
# twentytwentyfive  twentytwentyfour  twentytwentythree  my-child-theme  index.php
```

### 1.2 必須 2 ファイルを作る

子テーマに**最低限必要な**のは `style.css` と `functions.php` の 2 つだけ。

#### `style.css` (テーマヘッダ)

```css
/*
Theme Name:   My Child Theme
Theme URI:    https://example.com/my-child-theme
Description:  Twenty Twenty-Five を親にしたカスタム子テーマ
Author:       Your Name
Author URI:   https://example.com
Template:     twentytwentyfive
Version:      0.1.0
License:      GNU General Public License v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  my-child-theme
*/

/* ここから自由に上書き CSS を書く */
.site-title {
  color: #c0392b;
}
```

> [!IMPORTANT]
> `Template:` の値は**親テーマのフォルダ名と完全一致**させてください。タイポがあると「親テーマが見つかりません」エラーで有効化できません。

#### `functions.php` (親テーマの CSS を読み込む)

```php
<?php
/**
 * My Child Theme functions
 */

add_action( 'wp_enqueue_scripts', function () {
    // 親テーマの style.css を先に読み込む
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );

    // 子テーマの style.css を後から読み込む (上書きできるように)
    wp_enqueue_style(
        'child-style',
        get_stylesheet_uri(),
        array( 'parent-style' ),
        wp_get_theme()->get( 'Version' )
    );
} );
```

> **`get_template_directory_uri()` と `get_stylesheet_directory_uri()` の違い**
> - `get_template_directory_uri()` → **親**テーマの URL
> - `get_stylesheet_directory_uri()` → **子**テーマの URL (有効化されているテーマ)
> 子テーマでは混同しがちなので注意。

### 1.3 有効化

1. <http://localhost:8080/wp-admin> にログイン
2. **外観 → テーマ** に「My Child Theme」が現れる
3. 「有効化」をクリック

ヘッダ画像なしで表示が崩れる場合は `screenshot.png` (1200×900px 推奨) を子テーマ直下に置くとサムネイルも出ます。

### 1.4 親テーマのテンプレートを上書きする

子テーマで `header.php` や `single.php` を**同名で**置くと、親より優先されます。
例: 投稿ページの構造だけ変えたい場合、

```bash
# 親テーマの single.php をコピーして編集
cp wordpress_data/wp-content/themes/twentytwentyfive/single.php \
   wordpress_data/wp-content/themes/my-child-theme/single.php
```

これでこのファイルが優先的に使われます。

---

## 2. クラシックテーマをスクラッチで作る

子テーマでは物足りない、構造から自作したい場合。

### 2.1 最低限のファイル

```text
my-theme/
├── style.css       … テーマヘッダ + CSS
├── index.php       … 最後の砦 (どのテンプレも該当しない時に使われる)
├── functions.php   … フック・スクリプト読み込み
└── screenshot.png  … 管理画面サムネイル (任意)
```

`Template:` 行を**書かなければ**親なしの独立テーマになります。

#### `style.css` (テーマヘッダ最小版)

```css
/*
Theme Name:  My Theme
Author:      Your Name
Description: 自作テーマ
Version:     0.1.0
Text Domain: my-theme
*/
```

#### `index.php` (最小)

```php
<?php get_header(); ?>

<main>
  <?php
  if ( have_posts() ) :
      while ( have_posts() ) :
          the_post();
          ?>
          <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="entry-content"><?php the_content(); ?></div>
          </article>
          <?php
      endwhile;
  else :
      echo '<p>記事がありません</p>';
  endif;
  ?>
</main>

<?php get_footer(); ?>
```

このパターン (`if have_posts() / while have_posts() / the_post()`) が **WordPress の "ループ"** で、ほぼ全テンプレで使う基本構造です。

#### `header.php` / `footer.php`

`get_header()` / `get_footer()` から呼ばれる共通パーツ。

```php
<?php // header.php ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>  <!-- ★ 必須: プラグイン・CSS が刺さるフック -->
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
  <h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
</header>
```

```php
<?php // footer.php ?>
<footer class="site-footer">
  <small>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></small>
</footer>
<?php wp_footer(); ?>  <!-- ★ 必須: プラグインの JS が刺さるフック -->
</body>
</html>
```

> [!WARNING]
> `wp_head()` と `wp_footer()` は**省略禁止**。これがないと WordPress の管理バー・ブロックエディタの CSS・プラグインの JS が一切読み込まれません。

### 2.2 テンプレート階層 (要点だけ)

WordPress は URL に応じて、**ファイル名で**テンプレートを自動選択します。優先度の高い順に探し、見つからなければ次を探します。

| ページ種別           | 探す順 (上が優先)                                                |
| -------------------- | ---------------------------------------------------------------- |
| 個別投稿             | `single-{post-type}-{slug}.php` → `single-{post-type}.php` → `single.php` → `singular.php` → `index.php` |
| 固定ページ           | `page-{slug}.php` → `page-{id}.php` → `page.php` → `singular.php` → `index.php` |
| カテゴリーアーカイブ | `category-{slug}.php` → `category-{id}.php` → `category.php` → `archive.php` → `index.php` |
| トップページ         | `front-page.php` → `home.php` → `index.php`                      |
| 検索結果             | `search.php` → `index.php`                                       |
| 404                  | `404.php` → `index.php`                                          |

**ルール**: 名前を一致させるだけで読み込まれる。`if ( is_page() )` のような分岐は最終手段。

公式リファレンス: <https://developer.wordpress.org/themes/basics/template-hierarchy/>

### 2.3 functions.php の典型パターン

```php
<?php
/**
 * My Theme functions
 */

// テーマサポートを有効化
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );          // <title> を自動生成
    add_theme_support( 'post-thumbnails' );    // アイキャッチ画像
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
    add_theme_support( 'custom-logo' );

    register_nav_menus( array(
        'primary' => __( 'メインメニュー', 'my-theme' ),
        'footer'  => __( 'フッターメニュー', 'my-theme' ),
    ) );
} );

// CSS / JS の読み込み
add_action( 'wp_enqueue_scripts', function () {
    $version = wp_get_theme()->get( 'Version' );

    wp_enqueue_style(
        'my-theme-style',
        get_stylesheet_uri(),
        array(),
        $version
    );

    wp_enqueue_script(
        'my-theme-main',
        get_theme_file_uri( '/assets/js/main.js' ),
        array(),
        $version,
        true  // </body> 直前で読み込む
    );
} );

// ウィジェットエリア登録
add_action( 'widgets_init', function () {
    register_sidebar( array(
        'name'          => __( 'サイドバー', 'my-theme' ),
        'id'            => 'sidebar-1',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );
} );
```

### 2.4 よく使うテンプレートタグ

| 関数                          | 用途                          |
| ----------------------------- | ----------------------------- |
| `the_title()` / `get_the_title()` | 投稿タイトル              |
| `the_content()`               | 本文                          |
| `the_permalink()`             | パーマリンク URL              |
| `the_post_thumbnail()`        | アイキャッチ画像              |
| `the_excerpt()`               | 抜粋                          |
| `bloginfo( 'name' )`          | サイト名                      |
| `home_url( '/path' )`         | サイト URL                    |
| `wp_nav_menu()`               | ナビゲーションメニュー描画    |
| `get_search_form()`           | 検索フォーム                  |
| `get_template_part( 'parts/card' )` | テンプレ断片の取り込み |

### 2.5 セキュリティ: エスケープを徹底する

ユーザー入力・DB から取り出した値は**必ず**エスケープしてから出力します。

```php
echo esc_html( $title );          // テキスト
echo esc_attr( $css_class );      // 属性値
echo esc_url( $url );             // URL
echo wp_kses_post( $html );       // 投稿本文相当の HTML を許可
echo esc_js( $js_string );        // JS 内文字列
```

**やってはいけない**:
```php
echo $_GET['name'];               // ❌ XSS の温床
echo $post->post_title;           // ❌ エスケープなし
```

---

## 3. ブロックテーマ (FSE) を作る (要点)

WordPress 5.9+ の「サイトエディタ」前提のテーマ。PHP テンプレの代わりに HTML ファイルでテンプレを書きます。

### 必須構成

```text
my-block-theme/
├── style.css           … テーマヘッダ
├── theme.json          … ★ デザイン設定 (色・タイポ・スペース)
├── templates/
│   ├── index.html      … トップ
│   ├── single.html     … 個別投稿
│   └── page.html       … 固定ページ
└── parts/
    ├── header.html
    └── footer.html
```

### `theme.json` 最小例

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary", "color": "#c0392b", "name": "Primary" },
        { "slug": "base",    "color": "#ffffff", "name": "Base" }
      ]
    },
    "typography": {
      "fontSizes": [
        { "slug": "small",  "size": "14px", "name": "Small" },
        { "slug": "medium", "size": "16px", "name": "Medium" },
        { "slug": "large",  "size": "20px", "name": "Large" }
      ]
    }
  },
  "styles": {
    "color": { "background": "var(--wp--preset--color--base)" }
  }
}
```

### `templates/index.html` 最小例

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:query {"query":{"perPage":10}} -->
  <div class="wp-block-query">
    <!-- wp:post-template -->
      <!-- wp:post-title {"isLink":true} /-->
      <!-- wp:post-excerpt /-->
    <!-- /wp:post-template -->
  </div>
  <!-- /wp:query -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

公式: <https://developer.wordpress.org/themes/block-themes/>

---

## 4. 開発の Tips

### 4.1 デバッグを有効化する

`wordpress_data/wp-config.php` で:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );      // wp-content/debug.log に出力
define( 'WP_DEBUG_DISPLAY', false ); // 画面には出さない
@ini_set( 'display_errors', 0 );
```

ログ確認:

```bash
podman compose exec wordpress tail -f /var/www/html/wp-content/debug.log
```

PHP コードからログを吐く:

```php
error_log( print_r( $variable, true ) );
```

### 4.2 ループ内のクエリ書き換え

```php
add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
        $query->set( 'posts_per_page', 5 );
    }
} );
```

### 4.3 親テーマファイルの場所を素早く確認

```bash
podman compose exec wordpress ls /var/www/html/wp-content/themes/twentytwentyfive/
```

### 4.4 テーマ有効化後にブラウザに反映されない時

- ブラウザのキャッシュ (Ctrl + F5 / Cmd + Shift + R)
- `style.css` の `Version:` を上げる (cache busting)
- プラグイン由来のキャッシュがあれば一時的に無効化

### 4.5 本番にデプロイする

リポジトリの仕組みを使う ([README の「デプロイ」セクション](../README.md#サーバーへのデプロイ) 参照):

1. [deploy.sh](../deploy.sh) の `TARGETS` 配列に `themes/my-child-theme` が含まれていることを確認
2. 含まれていなければ追記
3. [compose.prod.yaml](../compose.prod.yaml) の `volumes` にも同名の bind mount 行を追加
4. `SERVER_HOST=user@example.com ./deploy.sh` で同期
5. 本番管理画面でテーマを「有効化」 (初回のみ)

---

## 5. ディレクトリ構成のおすすめ

ある程度規模が大きくなる場合の目安:

```text
my-theme/
├── style.css
├── functions.php
├── index.php
├── header.php
├── footer.php
├── sidebar.php
├── front-page.php
├── single.php
├── page.php
├── archive.php
├── search.php
├── 404.php
├── searchform.php
│
├── inc/                     … functions.php から require_once する分割ファイル
│   ├── setup.php            …   add_theme_support 系
│   ├── enqueue.php          …   CSS/JS 読み込み
│   ├── menus.php            …   メニュー登録
│   ├── widgets.php          …   ウィジェット登録
│   └── customizer.php       …   カスタマイザ設定
│
├── template-parts/          … get_template_part() で呼ぶ部品
│   ├── content.php
│   ├── content-single.php
│   └── card.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
└── languages/               … 翻訳 .pot / .po / .mo
```

`functions.php` は薄く保ち、機能ごとに `inc/` 配下に分けると保守しやすくなります。

```php
// functions.php
require_once get_theme_file_path( '/inc/setup.php' );
require_once get_theme_file_path( '/inc/enqueue.php' );
require_once get_theme_file_path( '/inc/menus.php' );
```

---

## 6. 参考リンク

- [Theme Handbook](https://developer.wordpress.org/themes/) (公式)
- [Template Hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/)
- [Block Theme Handbook](https://developer.wordpress.org/themes/block-themes/)
- [theme.json リファレンス](https://developer.wordpress.org/themes/global-settings-and-styles/)
- [Plugin/Theme Security](https://developer.wordpress.org/apis/security/)
- [Coding Standards (PHP)](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
