# WordPress カスタムプラグインの作り方

このドキュメントでは、本リポジトリ ([README.md](../README.md)) の Podman Compose 環境を前提に、自作プラグインを作る手順をまとめます。

- 編集対象: `wordpress_data/wp-content/plugins/<プラグイン名>/`
- 同期対象: [deploy.sh](../deploy.sh) の `TARGETS` に追加すれば本番に rsync される
- PHP は保存 = 即反映 (コンテナ再起動不要)

リポジトリには既に `wp-content/plugins/my-api-plugin/` という空ディレクトリが用意されています。名前のとおり「REST API を提供するプラグイン」を最終的なゴールに据えながら、汎用的なプラグインの作り方を順を追って説明します。

## テーマとプラグインの違い

| 観点               | テーマ                              | プラグイン                              |
| ------------------ | ----------------------------------- | --------------------------------------- |
| 主な責務           | **見た目** (HTML / CSS / 表示順)    | **機能** (DB / API / フック / ロジック) |
| 切り替え           | 1 サイトに **1 つだけ**有効         | **複数同時**に有効化できる              |
| テーマ変更時の影響 | 表示が変わる                        | 機能はそのまま残る                      |
| 配置場所           | `wp-content/themes/`                | `wp-content/plugins/`                   |

> **判断基準**
> - 「投稿一覧をカード表示にしたい」 → テーマ
> - 「特定の URL で JSON を返したい」「カスタム投稿タイプを追加したい」 → プラグイン

サイトを再構築してもロジックを残したいなら、それはプラグインに書くべきです。

---

## 1. 最小構成のプラグインを作る

プラグインに必須なファイルは **メイン PHP ファイル 1 つだけ**です。

### 1.1 ファイル配置

```text
wp-content/plugins/my-api-plugin/
└── my-api-plugin.php   ← メインファイル (フォルダ名と同名にするのが慣例)
```

### 1.2 メインファイルのプラグインヘッダ

```php
<?php
/**
 * Plugin Name:       My API Plugin
 * Plugin URI:        https://example.com/my-api-plugin
 * Description:       REST API エンドポイントを提供するカスタムプラグイン
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-api-plugin
 * Domain Path:       /languages
 */

// 直接アクセスを禁止 (定番のガード)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ここから機能を書いていく
add_action( 'init', function () {
    // 何かしらの初期化
} );
```

> [!IMPORTANT]
> `Plugin Name:` は必須。これがないと管理画面のプラグイン一覧に出てきません。

### 1.3 有効化

1. <http://localhost:8080/wp-admin> にログイン
2. **プラグイン → インストール済みプラグイン** に「My API Plugin」が現れる
3. 「有効化」をクリック

これで PHP ファイルが毎リクエスト読み込まれるようになります。

---

## 2. WordPress の "フック" を理解する

プラグインは基本的に **「フックに関数を登録して WordPress の処理に割り込む」** ことで動作します。フックには 2 種類あります。

| 種類          | 関数              | 役割                                      |
| ------------- | ----------------- | ----------------------------------------- |
| アクション    | `add_action()`    | 何かをする (DB に書く・ヘッダ出力 など) |
| フィルター    | `add_filter()`    | 値を**加工して返す** (タイトル整形 など) |

### 2.1 アクション例

```php
// init: WordPress 初期化時。多くの登録処理はここで行う
add_action( 'init', function () {
    // カスタム投稿タイプの登録など
} );

// wp_enqueue_scripts: フロントの CSS/JS 読み込み
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'my-plugin-js',
        plugins_url( '/assets/js/main.js', __FILE__ ),
        array(),
        '0.1.0',
        true
    );
} );

// admin_menu: 管理画面メニュー追加
add_action( 'admin_menu', function () {
    add_menu_page(
        'My API Plugin',                   // ページタイトル
        'My API',                          // メニュータイトル
        'manage_options',                  // 必要権限
        'my-api-plugin',                   // メニュースラッグ
        'my_api_plugin_render_admin_page', // 描画関数
        'dashicons-rest-api',              // アイコン
        80                                 // 表示位置
    );
} );

function my_api_plugin_render_admin_page() {
    echo '<div class="wrap"><h1>My API Plugin</h1><p>設定画面</p></div>';
}
```

### 2.2 フィルター例

```php
// 投稿タイトルの末尾に印を付ける
add_filter( 'the_title', function ( $title, $post_id ) {
    if ( get_post_type( $post_id ) === 'post' ) {
        $title .= ' [カスタム]';
    }
    return $title;
}, 10, 2 );

// 本文の最後に署名を追加
add_filter( 'the_content', function ( $content ) {
    if ( is_single() ) {
        $content .= '<p class="signature">— My API Plugin が生成</p>';
    }
    return $content;
} );
```

> **`add_action` / `add_filter` の引数**
> - 第 3 引数: 優先度 (デフォルト 10、小さいほど先)
> - 第 4 引数: コールバックが受け取る引数の数 (デフォルト 1)
> 引数が 2 つ以上必要なフィルター/アクションでは第 4 引数を**忘れると渡ってこない**ので注意。

公式フック一覧: <https://developer.wordpress.org/reference/hooks/>

---

## 3. よくある実装パターン

### 3.1 カスタム投稿タイプ (CPT) を登録

```php
add_action( 'init', function () {
    register_post_type( 'product', array(
        'labels' => array(
            'name'          => '商品',
            'singular_name' => '商品',
            'add_new_item'  => '商品を追加',
        ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,                              // ブロックエディタで編集可
        'menu_icon'    => 'dashicons-cart',
        'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'rewrite'      => array( 'slug' => 'products' ),     // URL: /products/...
    ) );
} );
```

> [!IMPORTANT]
> CPT を**追加・スラッグ変更した直後**は管理画面の **設定 → パーマリンク** を開く (= 保存ボタンを押すだけ) と rewrite ルールが再生成されて 404 が解消します。

### 3.2 カスタムタクソノミー

```php
add_action( 'init', function () {
    register_taxonomy( 'product_category', 'product', array(
        'label'        => '商品カテゴリー',
        'hierarchical' => true,            // true = カテゴリー型 / false = タグ型
        'show_in_rest' => true,
        'rewrite'      => array( 'slug' => 'product-category' ),
    ) );
} );
```

### 3.3 REST API エンドポイント (my-api-plugin の本命)

```php
add_action( 'rest_api_init', function () {
    register_rest_route( 'my-api/v1', '/hello', array(
        'methods'             => WP_REST_Server::READABLE,   // GET
        'callback'            => 'my_api_hello',
        'permission_callback' => '__return_true',            // 公開エンドポイント
    ) );

    register_rest_route( 'my-api/v1', '/products/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'my_api_get_product',
        'args'                => array(
            'id' => array(
                'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                'sanitize_callback' => 'absint',
            ),
        ),
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'my-api/v1', '/products', array(
        'methods'             => WP_REST_Server::CREATABLE,  // POST
        'callback'            => 'my_api_create_product',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );         // ★ 認可チェック必須
        },
    ) );
} );

function my_api_hello( WP_REST_Request $request ) {
    return rest_ensure_response( array(
        'message' => 'Hello from My API Plugin',
        'time'    => current_time( 'c' ),
    ) );
}

function my_api_get_product( WP_REST_Request $request ) {
    $id   = (int) $request['id'];
    $post = get_post( $id );

    if ( ! $post || $post->post_type !== 'product' ) {
        return new WP_Error( 'not_found', '商品が見つかりません', array( 'status' => 404 ) );
    }

    return rest_ensure_response( array(
        'id'      => $post->ID,
        'title'   => get_the_title( $post ),
        'content' => apply_filters( 'the_content', $post->post_content ),
        'link'    => get_permalink( $post ),
    ) );
}

function my_api_create_product( WP_REST_Request $request ) {
    $title   = sanitize_text_field( $request->get_param( 'title' ) );
    $content = wp_kses_post( $request->get_param( 'content' ) );

    if ( $title === '' ) {
        return new WP_Error( 'invalid_input', 'title は必須です', array( 'status' => 400 ) );
    }

    $post_id = wp_insert_post( array(
        'post_type'    => 'product',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $content,
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    return rest_ensure_response( array( 'id' => $post_id ) );
}
```

動作確認:

```bash
# 公開エンドポイント
curl http://localhost:8080/wp-json/my-api/v1/hello

# 個別商品取得
curl http://localhost:8080/wp-json/my-api/v1/products/123

# POST (要認証 — 後述の Application Password 等で)
curl -u admin:xxxx -X POST \
     -H 'Content-Type: application/json' \
     -d '{"title":"テスト商品","content":"<p>本文</p>"}' \
     http://localhost:8080/wp-json/my-api/v1/products
```

> **REST API での認証**
> ブラウザ外から POST/PUT/DELETE する場合、Cookie 認証は使えないので **Application Passwords** (管理画面 → ユーザー → プロフィール → 下部) を発行して Basic 認証で投げるのが手軽です。

### 3.4 ショートコード

```php
// [latest_products count="5"] のように使える
add_shortcode( 'latest_products', function ( $atts ) {
    $atts = shortcode_atts( array( 'count' => 3 ), $atts, 'latest_products' );

    $query = new WP_Query( array(
        'post_type'      => 'product',
        'posts_per_page' => (int) $atts['count'],
    ) );

    if ( ! $query->have_posts() ) {
        return '<p>商品がありません</p>';
    }

    $html = '<ul class="latest-products">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $html .= sprintf(
            '<li><a href="%s">%s</a></li>',
            esc_url( get_permalink() ),
            esc_html( get_the_title() )
        );
    }
    wp_reset_postdata();
    $html .= '</ul>';

    return $html;
} );
```

### 3.5 設定画面 + オプション保存

```php
// 設定画面メニューを追加 (Settings の下)
add_action( 'admin_menu', function () {
    add_options_page(
        'My API Plugin 設定',
        'My API Plugin',
        'manage_options',
        'my-api-plugin',
        'my_api_plugin_settings_page'
    );
} );

// 設定項目を登録
add_action( 'admin_init', function () {
    register_setting( 'my_api_plugin_options', 'my_api_plugin_api_key', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
} );

function my_api_plugin_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
      <h1>My API Plugin 設定</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields( 'my_api_plugin_options' );
        $api_key = get_option( 'my_api_plugin_api_key', '' );
        ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="api_key">API キー</label></th>
            <td>
              <input type="text"
                     id="api_key"
                     name="my_api_plugin_api_key"
                     value="<?php echo esc_attr( $api_key ); ?>"
                     class="regular-text">
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
}

// 取り出し例
$api_key = get_option( 'my_api_plugin_api_key' );
```

### 3.6 有効化 / 無効化 / アンインストール時のフック

```php
// 有効化時 (テーブル作成・初期データ投入など 1 回だけ走らせたい処理)
register_activation_hook( __FILE__, function () {
    add_option( 'my_api_plugin_api_key', '' );
    flush_rewrite_rules();   // CPT 登録後に呼ぶと 404 を防げる
} );

// 無効化時 (cron 解除など)
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'my_api_plugin_daily_event' );
    flush_rewrite_rules();
} );

// アンインストール時 — 必ず uninstall.php に書く (下記参照)
```

`uninstall.php` をプラグインのルートに置くと、**プラグイン削除時**に実行されます。

```php
<?php
// uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'my_api_plugin_api_key' );
// 必要なら自前テーブルも DROP
```

### 3.7 独自 DB テーブル

組み込み投稿タイプで足りない場合だけ。たいていは CPT + メタで賄えるので、**最初は CPT を試してから**検討するのを推奨します。

```php
register_activation_hook( __FILE__, function () {
    global $wpdb;
    $table   = $wpdb->prefix . 'my_api_logs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        endpoint VARCHAR(191) NOT NULL,
        status_code SMALLINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY endpoint (endpoint)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );  // ★ CREATE/ALTER をいい感じにやってくれる

    add_option( 'my_api_plugin_db_version', '1.0' );
} );

// 書き込み (必ずプリペアド)
function my_api_log( string $endpoint, int $status ) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'my_api_logs',
        array(
            'endpoint'    => $endpoint,
            'status_code' => $status,
            'created_at'  => current_time( 'mysql' ),
        ),
        array( '%s', '%d', '%s' )
    );
}

// 読み出し (必ず prepare で SQL インジェクション対策)
function my_api_get_logs( string $endpoint ) {
    global $wpdb;
    $table = $wpdb->prefix . 'my_api_logs';
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE endpoint = %s ORDER BY id DESC LIMIT 20",
            $endpoint
        )
    );
}
```

> [!WARNING]
> SQL を直接書く時は**必ず** `$wpdb->prepare()` を通す。`"... WHERE id = {$_GET['id']}"` は SQL インジェクションの典型例。

### 3.8 cron で定期実行

```php
// スケジュール
register_activation_hook( __FILE__, function () {
    if ( ! wp_next_scheduled( 'my_api_plugin_daily_event' ) ) {
        wp_schedule_event( time(), 'daily', 'my_api_plugin_daily_event' );
    }
} );

// 解除
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'my_api_plugin_daily_event' );
} );

// 実体
add_action( 'my_api_plugin_daily_event', function () {
    error_log( 'daily cron fired at ' . current_time( 'c' ) );
} );
```

> WP-Cron は**サイトにアクセスがあった時に走る**疑似 cron です。本番でアクセスが少ないと遅延するので、必要なら OS の cron から `wp-cron.php` を叩く構成に切り替えます。

---

## 4. セキュリティの定石

### 4.1 入力のサニタイズ

| 用途              | 関数                          |
| ----------------- | ----------------------------- |
| テキスト 1 行     | `sanitize_text_field()`       |
| メール            | `sanitize_email()`            |
| URL               | `esc_url_raw()`               |
| スラッグ          | `sanitize_title()`            |
| ファイル名        | `sanitize_file_name()`        |
| 投稿本文相当 HTML | `wp_kses_post()`              |
| 整数              | `absint()` / `(int)`          |
| 配列の各要素      | `array_map( 'sanitize_text_field', $arr )` |

### 4.2 出力のエスケープ

| 用途           | 関数               |
| -------------- | ------------------ |
| HTML テキスト  | `esc_html()`       |
| 属性値         | `esc_attr()`       |
| URL            | `esc_url()`        |
| 投稿本文相当   | `wp_kses_post()`   |
| JS 文字列      | `esc_js()`         |
| translate + escape | `esc_html__()` / `esc_attr__()` |

### 4.3 権限チェック

```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( '権限がありません', 'my-api-plugin' ) );
}
```

REST API では `permission_callback` に必ず実装する。`__return_true` は**公開して問題ない時だけ**。

### 4.4 nonce で CSRF 対策

```php
// フォーム側
wp_nonce_field( 'my_api_plugin_save', 'my_api_plugin_nonce' );

// 受信側
if ( ! isset( $_POST['my_api_plugin_nonce'] ) ||
     ! wp_verify_nonce( $_POST['my_api_plugin_nonce'], 'my_api_plugin_save' ) ) {
    wp_die( 'Invalid nonce' );
}
```

REST API は `X-WP-Nonce` ヘッダで自動チェックされます (Cookie 認証時)。

### 4.5 直接アクセス禁止

すべての PHP ファイルの先頭に:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## 5. 開発の Tips

### 5.1 デバッグログ

`wordpress_data/wp-config.php` で:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

ログ確認:

```bash
podman compose exec wordpress tail -f /var/www/html/wp-content/debug.log
```

PHP 側からの出力:

```php
error_log( 'value = ' . print_r( $value, true ) );
```

### 5.2 WP-CLI でいろいろ

WordPress 公式コンテナには WP-CLI が同梱されています。

```bash
# プラグイン一覧
podman compose exec wordpress wp plugin list --allow-root

# 有効化
podman compose exec wordpress wp plugin activate my-api-plugin --allow-root

# REST ルート一覧
podman compose exec wordpress wp rest route list --allow-root

# クエリのデバッグ
podman compose exec wordpress wp eval 'var_dump( get_option( "my_api_plugin_api_key" ) );' --allow-root
```

### 5.3 翻訳対応

```php
__( 'こんにちは', 'my-api-plugin' )      // 文字列を返す
_e( 'こんにちは', 'my-api-plugin' )      // 直接 echo
esc_html__( 'こんにちは', 'my-api-plugin' )  // エスケープ + 翻訳
```

`Text Domain:` ヘッダと `languages/<text-domain>-<locale>.mo` を揃えれば翻訳が当たります。

### 5.4 本番にデプロイする

リポジトリの仕組みを使う ([README の「デプロイ」セクション](../README.md#サーバーへのデプロイ) 参照):

1. [deploy.sh](../deploy.sh) の `TARGETS` 配列に `plugins/my-api-plugin` が含まれていることを確認
2. 含まれていなければ追記
3. [compose.prod.yaml](../compose.prod.yaml) の `volumes` にも同名の bind mount 行を追加
4. `SERVER_HOST=user@example.com ./deploy.sh` で同期
5. 本番管理画面でプラグインを「有効化」 (初回のみ)

---

## 6. ある程度大きくなった時のディレクトリ構成

メイン PHP 1 枚で書ききれなくなってきたら分割します。

```text
my-api-plugin/
├── my-api-plugin.php          … プラグインヘッダ + 各種 require
├── uninstall.php              … 削除時のクリーンアップ
├── readme.txt                 … wp.org 公開時のメタデータ (任意)
│
├── includes/                  … メインロジック (PSR-4 で名前空間化推奨)
│   ├── class-plugin.php       …   ブートストラップクラス
│   ├── class-rest.php         …   REST ルート登録
│   ├── class-cpt.php          …   カスタム投稿タイプ
│   ├── class-cron.php         …   定期実行
│   └── class-settings.php     …   設定画面
│
├── admin/                     … 管理画面専用
│   ├── views/
│   └── assets/
│
├── public/                    … フロント側
│   └── assets/
│
├── languages/                 … 翻訳ファイル
└── vendor/                    … Composer 依存 (使う場合)
```

メイン PHP は **「require して bootstrap するだけ」** に薄く保つ:

```php
<?php
/**
 * Plugin Name: My API Plugin
 * ...
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MY_API_PLUGIN_FILE', __FILE__ );
define( 'MY_API_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MY_API_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'MY_API_PLUGIN_VER',  '0.1.0' );

require_once MY_API_PLUGIN_DIR . 'includes/class-plugin.php';
require_once MY_API_PLUGIN_DIR . 'includes/class-rest.php';
require_once MY_API_PLUGIN_DIR . 'includes/class-cpt.php';

add_action( 'plugins_loaded', array( My_Api_Plugin::class, 'init' ) );
```

クラス側で名前空間を切り、各機能を独立してテスト可能にしておくと保守が楽になります。

### Composer を使う場合

```bash
podman compose exec wordpress bash -lc "cd /var/www/html/wp-content/plugins/my-api-plugin && composer init"
```

メイン PHP の冒頭で:

```php
if ( file_exists( MY_API_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once MY_API_PLUGIN_DIR . 'vendor/autoload.php';
}
```

ただし**他プラグインとの autoload 衝突**を避けるため、依存パッケージは Mozart や PHP-Scoper でプレフィックスする運用が安全です。

---

## 7. 参考リンク

- [Plugin Handbook](https://developer.wordpress.org/plugins/) (公式)
- [Hooks Reference](https://developer.wordpress.org/reference/hooks/)
- [REST API Handbook](https://developer.wordpress.org/rest-api/)
- [Plugin Security](https://developer.wordpress.org/apis/security/)
- [WordPress Coding Standards (PHP)](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [WP-CLI Commands](https://developer.wordpress.org/cli/commands/)
- [$wpdb クラス](https://developer.wordpress.org/reference/classes/wpdb/)
