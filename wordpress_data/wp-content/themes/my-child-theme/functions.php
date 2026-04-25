<?php
// 子テーマの functions.php

// 直接アクセスを防止
if (!defined('ABSPATH')) exit;


// 親テーマと子テーマのスタイルシートを読み込む
add_action('wp_enqueue_scripts', function () {

    // 親テーマの style.css を登録
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );
    
    // 子テーマの style.css を登録（親テーマに依存）
    wp_enqueue_style(
        'child-style',
        get_stylesheet_uri(),
        ['parent-style'],
        wp_get_theme()->get('Version') // キャッシュバスティング用にテーマバージョンを付与
    );
});
