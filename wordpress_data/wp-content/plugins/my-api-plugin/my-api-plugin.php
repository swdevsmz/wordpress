<?php
/**
 * Plugin Name: My Weather Plugin
 * Description: 投稿ごとに指定した場所・日時の天気を Open-Meteo から取得し、本文冒頭に差し込む。
 * Version: 0.2.0
 *
 * 概要:
 *   - 投稿編集画面のサイドバーに「天気情報」メタボックスを追加する。
 *   - 管理者が「場所（都市名）」と「日時」を入力して投稿を保存すると、
 *     Open-Meteo の無料 API を使って気温・天気コードを自動取得し、
 *     投稿メタとして保存する。
 *   - フロントエンドの投稿表示時に、取得済みの天気情報を本文冒頭にボックスとして挿入する。
 */

// WordPress の外から直接アクセスされた場合は即座に終了する（セキュリティ対策）
if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------
// 定数定義
// -----------------------------------------------------------------------

/** 投稿メタキー: 場所（都市名） */
const MYW_META_LOCATION = '_myw_location';

/** 投稿メタキー: 指定日時 */
const MYW_META_DATETIME = '_myw_datetime';

/** 投稿メタキー: 取得した天気データ（配列をシリアライズして保存） */
const MYW_META_WEATHER  = '_myw_weather';

/** nonce 検証に使うアクション名（CSRF 対策） */
const MYW_NONCE_ACTION  = 'myw_save';

/** nonce フィールド名 */
const MYW_NONCE_NAME    = 'myw_nonce';

// -----------------------------------------------------------------------
// 投稿編集画面のメタボックス
// -----------------------------------------------------------------------

/**
 * 投稿編集画面のサイドバーに「天気情報」メタボックスを登録する。
 * add_meta_boxes アクションは投稿編集画面の読み込み時に WordPress が発火する。
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'myw_box',          // メタボックスの ID（HTML id 属性にも使われる）
        '天気情報',          // メタボックスのタイトル
        'myw_render_metabox', // コールバック関数
        'post',             // 表示対象の投稿タイプ（通常の「投稿」のみ）
        'side'              // 表示位置（サイドバー）
    );
});

/**
 * メタボックスの HTML を出力する。
 * 既存のメタ値を読み込んでフォームに初期値としてセットする。
 *
 * @param WP_Post $post 現在編集中の投稿オブジェクト
 */
function myw_render_metabox($post) {
    // 保存済みのメタ値を取得（未保存の場合は空文字列になる）
    $location = get_post_meta($post->ID, MYW_META_LOCATION, true);
    $datetime = get_post_meta($post->ID, MYW_META_DATETIME, true);
    $weather  = get_post_meta($post->ID, MYW_META_WEATHER, true);

    // nonce フィールドを出力する（保存時の CSRF 検証に使用）
    wp_nonce_field(MYW_NONCE_ACTION, MYW_NONCE_NAME);
    ?>
    <p>
      <label>場所 (都市名)<br>
        <input type="text" name="myw_location"
          value="<?php echo esc_attr($location); ?>"
          style="width:100%" placeholder="Tokyo / 東京">
      </label>
    </p>
    <p>
      <label>日時<br>
        <input type="datetime-local" name="myw_datetime"
          value="<?php echo esc_attr($datetime); ?>"
          style="width:100%">
      </label>
    </p>
    
    <?php
    // 天気データが正常に取得できている場合はサマリーを表示する
    if (is_array($weather) && !empty($weather['summary'])) : ?>
      <p><strong>取得結果:</strong><br>
        <?php echo esc_html($weather['summary']); ?></p>
    <?php
    // API 呼び出しでエラーが発生していた場合はエラーメッセージを表示する
    elseif (is_array($weather) && !empty($weather['error'])) : ?>
      <p style="color:#c00"><strong>エラー:</strong>
        <?php echo esc_html($weather['error']); ?></p>
    <?php endif;
}

// -----------------------------------------------------------------------
// 投稿保存時に天気データを取得・保存する
// -----------------------------------------------------------------------

/**
 * 投稿が保存されるたびに実行される処理。
 * 入力値を検証・サニタイズしてメタに保存し、
 * 場所と日時が揃っていれば Open-Meteo API を呼んで天気を取得する。
 *
 * save_post_post フックは投稿タイプ "post"（通常の投稿）の保存時のみ発火する。
 *
 * @param int $post_id 保存される投稿の ID
 */
add_action('save_post_post', function ($post_id) {
    // 自動保存（Autosave）の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // リビジョン保存の場合は何もしない
    if (wp_is_post_revision($post_id)) return;

    // 現在のユーザーに投稿の編集権限がない場合は何もしない
    if (!current_user_can('edit_post', $post_id)) return;

    // nonce フィールドが POST データに存在しない場合は何もしない
    // （このプラグインのメタボックスを経由した保存ではないと判断する）
    if (!isset($_POST[MYW_NONCE_NAME])) return;

    // nonce を検証して CSRF 攻撃を防ぐ
    if (!wp_verify_nonce(
        sanitize_text_field(wp_unslash($_POST[MYW_NONCE_NAME])),
        MYW_NONCE_ACTION
    )) return;

    // POST データから場所と日時を取得してサニタイズする
    $location = isset($_POST['myw_location'])
        ? sanitize_text_field(wp_unslash($_POST['myw_location'])) : '';
    $datetime = isset($_POST['myw_datetime'])
        ? sanitize_text_field(wp_unslash($_POST['myw_datetime'])) : '';

    // サニタイズ済みの値をメタとして保存する
    update_post_meta($post_id, MYW_META_LOCATION, $location);
    update_post_meta($post_id, MYW_META_DATETIME, $datetime);

    // 場所または日時が空の場合は天気メタを削除して終了する
    if ($location === '' || $datetime === '') {
        delete_post_meta($post_id, MYW_META_WEATHER);
        return;
    }

    // 場所と日時が揃っていれば API を呼び出し、結果をメタに保存する
    update_post_meta($post_id, MYW_META_WEATHER, myw_fetch_weather($location, $datetime));
});

// -----------------------------------------------------------------------
// 本文冒頭に天気情報ボックスを挿入する
// -----------------------------------------------------------------------

/**
 * the_content フィルターを使って、投稿本文の冒頭に天気情報ボックスを挿入する。
 * シングル投稿ページかつメインクエリのループ内でのみ動作し、
 * アーカイブやウィジェットなど他の場所での誤挿入を防ぐ。
 *
 * @param string $content 元の投稿本文 HTML
 * @return string 天気ボックスを冒頭に付加した投稿本文 HTML
 */
add_filter('the_content', function ($content) {
    // シングル投稿ページ・ループ内・メインクエリのみ対象にする
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;

    // 現在の投稿の天気メタを取得する
    $weather = get_post_meta(get_the_ID(), MYW_META_WEATHER, true);

    // 有効な天気サマリーがなければ本文をそのまま返す
    if (!is_array($weather) || empty($weather['summary'])) return $content;

    // 天気情報を表示する HTML ブロックを組み立てる
    $block = '<div class="myw-weather" style="padding:1em;border:1px solid #ddd;'
           . 'margin-bottom:1em;border-radius:4px">'
           . '<strong>この記事の天気</strong><br>'
           . esc_html($weather['summary'])
           . '</div>';

    // 天気ブロックを本文の前に付加して返す
    return $block . $content;
});

// -----------------------------------------------------------------------
// Open-Meteo API 連携
// -----------------------------------------------------------------------

/**
 * 指定した場所・日時の天気を Open-Meteo の無料 API から取得する。
 *
 * 処理の流れ:
 *   1. Geocoding API で都市名を緯度・経度に変換する
 *   2. 指定日時を WordPress タイムゾーンで解釈し、対象の日付と時間（時）を求める
 *   3. 日時が過去 92 日〜未来 16 日以内なら forecast エンドポイント、
 *      それより古い場合は archive エンドポイントを使う
 *   4. Forecast/Archive API から 1 時間ごとの気温・天気コードを取得し、
 *      指定時刻のデータを抽出して返す
 *
 * @param string $location 都市名（例: "Tokyo", "東京"）
 * @param string $datetime datetime-local 形式の文字列（例: "2025-08-15T14:00"）
 * @return array{summary:string, location:string, temperature:float, code:int, label:string, datetime:string}
 *              成功時は天気データの連想配列。失敗時は ['error' => string]。
 */
function myw_fetch_weather($location, $datetime) {

    // ------------------------------------------------------------------
    // 1) 地名 -> 緯度経度の変換
    // ------------------------------------------------------------------

    // Open-Meteo の Geocoding API に都市名を投げて緯度・経度を取得する
    $geo_url = add_query_arg([
        'name'     => $location, // 検索する都市名
        'count'    => 1,         // 最も一致する 1 件だけ取得
        'language' => 'ja',      // レスポンスの言語（日本語）
        'format'   => 'json',    // JSON 形式で受け取る
    ], 'https://geocoding-api.open-meteo.com/v1/search');

    $geo = wp_remote_get($geo_url, ['timeout' => 10]);

    // HTTP リクエスト自体が失敗した場合（ネットワークエラーなど）
    if (is_wp_error($geo)) {
        return ['error' => '地名検索失敗: ' . $geo->get_error_message()];
    }

    $geo_body = json_decode(wp_remote_retrieve_body($geo), true);

    // 該当する都市が見つからなかった場合
    if (empty($geo_body['results'][0])) {
        return ['error' => '地名が見つかりません: ' . $location];
    }

    // 最初にヒットした都市の緯度・経度・表示名を取り出す
    $hit = $geo_body['results'][0];
    $lat = $hit['latitude'];
    $lon = $hit['longitude'];
    $name_resolved = $hit['name'] . (isset($hit['country']) ? ', ' . $hit['country'] : '');

    // ------------------------------------------------------------------
    // 2) 日時パース
    // ------------------------------------------------------------------

    // datetime-local の値は "YYYY-MM-DDTHH:MM" 形式で送られてくる
    // WordPress のサイトタイムゾーンを基準に DateTimeImmutable を生成する
    try {
        $dt  = new DateTimeImmutable($datetime, wp_timezone()); // 指定日時
        $now = new DateTimeImmutable('now', wp_timezone());      // 現在日時（比較用）
    } catch (Exception $e) {
        return ['error' => '日時が不正: ' . $datetime];
    }

    $date_str = $dt->format('Y-m-d'); // API に渡す日付文字列（例: "2025-08-15"）
    $hour     = (int) $dt->format('H'); // 取得対象の時間インデックス（0〜23）

    // ------------------------------------------------------------------
    // 3) 過去 / 未来によって使用する API エンドポイントを選択
    // ------------------------------------------------------------------

    // 指定日と今日の日付差（日数）を計算する
    // Open-Meteo Forecast API は過去 92 日〜未来 16 日をカバーする
    // それより古いデータは Archive API（ERA5 再解析データ）を使う
    $days_diff = (int) (($dt->setTime(0, 0)->getTimestamp()
                       - $now->setTime(0, 0)->getTimestamp()) / 86400);

    $endpoint = ($days_diff >= -92 && $days_diff <= 16)
        ? 'https://api.open-meteo.com/v1/forecast'          // 通常の予報・直近過去
        : 'https://archive-api.open-meteo.com/v1/archive';  // 92 日以上前の過去データ

    // ------------------------------------------------------------------
    // 4) 天気データの取得
    // ------------------------------------------------------------------

    // 緯度・経度・日付などのパラメータを組み立てて API URL を生成する
    $url = add_query_arg([
        'latitude'   => $lat,
        'longitude'  => $lon,
        'hourly'     => 'temperature_2m,weather_code', // 取得するフィールド: 気温と天気コード
        'start_date' => $date_str,                      // 取得開始日
        'end_date'   => $date_str,                      // 取得終了日（同日なので 1 日分のみ）
        'timezone'   => wp_timezone_string(),           // WordPress のタイムゾーン文字列（例: "Asia/Tokyo"）
    ], $endpoint);

    $res = wp_remote_get($url, ['timeout' => 10]);

    // HTTP リクエスト自体が失敗した場合
    if (is_wp_error($res)) {
        return ['error' => '天気取得失敗: ' . $res->get_error_message()];
    }

    $body = json_decode(wp_remote_retrieve_body($res), true);

    // 指定時刻（$hour）のデータが存在しない場合（API の仕様変更や無効な時刻など）
    if (!isset($body['hourly']['temperature_2m'][$hour], $body['hourly']['weather_code'][$hour])) {
        return ['error' => '指定時刻の天気データがありません'];
    }

    // 指定時刻の気温（°C）と WMO 天気コードを取り出す
    $temp  = $body['hourly']['temperature_2m'][$hour];
    $code  = (int) $body['hourly']['weather_code'][$hour];
    $label = myw_weather_label($code); // 天気コードを日本語ラベルに変換

    // 取得した天気データを連想配列にまとめて返す
    return [
        'summary'     => sprintf('%s %s — %s / %s°C',
                                 $dt->format('Y-m-d H:i'), $name_resolved, $label, $temp),
        'location'    => $name_resolved, // 解決された場所名（例: "Tokyo, Japan"）
        'temperature' => $temp,          // 気温（°C）
        'code'        => $code,          // WMO 天気コード
        'label'       => $label,         // 天気コードの日本語ラベル
        'datetime'    => $dt->format('c'), // ISO 8601 形式の日時文字列
    ];
}

// -----------------------------------------------------------------------
// WMO 天気コードの日本語ラベル変換
// -----------------------------------------------------------------------

/**
 * WMO（世界気象機関）が定義する天気コードを日本語のラベルに変換する。
 * Open-Meteo API が返す weather_code はこの WMO コードに準拠している。
 *
 * @param int $code WMO 天気コード
 * @return string 対応する日本語の天気ラベル。未定義コードの場合は "天気コード {$code}"。
 */
function myw_weather_label($code) {
    $map = [
        // 晴れ系
        0 => '快晴',         // 雲量ほぼ 0
        1 => '晴れ',         // 主に晴れ
        2 => '一部曇り',     // 晴れときどき曇り
        3 => '曇り',         // 全天曇り
        // 霧系
        45 => '霧',
        48 => '霧氷',        // 霧による着氷
        // 霧雨（Drizzle）系
        51 => '霧雨(弱)', 53 => '霧雨(中)', 55 => '霧雨(強)',
        // 雨（Rain）系
        61 => '雨(弱)',   63 => '雨(中)',   65 => '雨(強)',
        // 雪（Snow）系
        71 => '雪(弱)',   73 => '雪(中)',   75 => '雪(強)',
        // にわか雨（Showers）系
        80 => 'にわか雨(弱)', 81 => 'にわか雨(中)', 82 => 'にわか雨(強)',
        // 雷雨系
        95 => '雷雨',
        96 => '雷雨+雹(弱)',
        99 => '雷雨+雹(強)',
    ];

    // マップにない未知のコードはコード番号をそのまま表示する
    return $map[$code] ?? "天気コード {$code}";
}
