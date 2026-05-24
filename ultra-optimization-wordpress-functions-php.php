<?php
/**
 * БЛОК 1: ОПТИМИЗАЦИЯ СКОРОСТИ И ОЧИСТКА HTML-КОДА
 */

add_action('init', function() {
    // 1. Полное удаление Emoji (эмодзи скриптов и стилей)
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    
    // Удаляем dns-prefetch к серверам s.w.org (куда стучались эмодзи)
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/');
            $urls = array_diff($urls, array($emoji_svg_url));
        }
        return $urls;
    }, 100, 2);

    // 2. Удаляем ссылки на RSS-ленты из шапки (если у тебя не новостной блог)
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'feed_links', 2);
});

// 3. Отключаем Dashicons для неавторизованных пользователей (экономит целый CSS-файл)
add_action('wp_enqueue_scripts', function() {
    if (!is_user_logged_in()) {
        wp_dequeue_style('dashicons');
    }
}, 100);

// 4. Отключаем загрузку стилей встроенного редактора Gutenberg (так как работаем в Elementor)
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('wp-block-library');       // Стандартные блоки
    wp_dequeue_style('wp-block-library-theme'); // Стили темы блоков
    wp_dequeue_style('wc-blocks-style');        // Блоки WooCommerce (если не используешь новые Woo-блоки)
}, 100);

// 5. Отключаем глобальные стили (wp-blocks-json-inline-css), которые WP 6.0+ сует на каждую страницу
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('global-styles');
}, 100);


/**
 * БЛОК 2: ЖЕСТКАЯ БЕЗОПАСНОСТЬ
 */

// 1. Полное отключение XML-RPC (защита от брутфорса и DDoS через пингбэки)
add_filter('xmlrpc_enabled', '__return_false');

// 2. Скрытие логинов авторов/админов через REST API (то, что мы проверяли)
add_filter('rest_endpoints', function($endpoints) {
    if (isset($endpoints['/wp/v2/users'])) {
        unset($endpoints['/wp/v2/users']);
    }
    if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    return $endpoints;
});

// 3. Отключение сканирования авторов через URL-запрос (?author=1)
add_action('template_redirect', function() {
    if (is_author()) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        locate_template('404.php', true, true);
        exit;
    }
});

// 4. Удаление мета-тегов из <head>, выдающих техническую информацию
remove_action('wp_head', 'wp_generator');                // Версия WordPress
remove_action('wp_head', 'rsd_link');                    // Ссылка для внешних сервисов публикации
remove_action('wp_head', 'wlwmanifest_link');            // Ссылка для Windows Live Writer
remove_action('wp_head', 'wp_shortlink_wp_head');        // Короткая ссылка на страницу
remove_action('wp_head', 'rest_output_link_wp_head');    // Ссылка на REST API в шапке
remove_action('wp_head', 'wp_oembed_add_discovery_links'); // Ссылки oEmbed (встраивание контента)

// 5. Изменение сообщения об ошибке при входе (не говорит, что именно неверно — логин или пароль)
add_filter('login_errors', function() {
    return 'Ошибка: Неверные данные для входа.';
});


/**
 * БЛОК 3: ОПТИМИЗАЦИЯ WOOCOMMERCE
 */

// 1. Отключаем скрипты и стили WooCommerce везде, кроме страниц магазина
add_action('wp_enqueue_scripts', function() {
    // Если WooCommerce не установлен, выходим
    if (!class_exists('WooCommerce')) return;

    // Список страниц, где WooCommerce ДОЛЖЕН работать
    if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
        return; 
    }

    // Отменяем загрузку стилей
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');
    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('generic-woocommerce-styles');

    // Отменяем загрузку скриптов (включая скрипт корзины)
    wp_dequeue_script('wc-add-to-cart');
    wp_dequeue_script('woocommerce');
    wp_dequeue_script('wc-cart-fragments');
}, 99);

// 2. Отключаем генератор WooCommerce (скрывает версию плагина в коде)
add_action('get_header', function() {
    if (class_exists('WooCommerce')) {
        remove_action('wp_head', array($GLOBALS['woocommerce'], 'generator'));
    }
});

/**
 * БЛОК 4: ПОЛНОЕ УНИЧТОЖЕНИЕ КОММЕНТАРИЕВ И СВЯЗАННОГО ХЛАМА
 * (Использовать только на бизнес-сайте, на шопе НЕЛЬЗЯ)
 */

add_action('admin_init', function () {
    // Перенаправляем любого, кто пытается зайти на страницу комментариев в админке
    global $pagenow;
    if ($pagenow === 'edit-comments.php') {
        wp_redirect(admin_url());
        exit;
    }
    // Удаляем секцию метабокса комментариев из кастомных полей страниц/записей
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
});

// Закрываем комментарии на фронтенде для абсолютно всех типов записей
add_filter('comments_open', '__return_false', 20);
add_filter('pings_open', '__return_false', 20);

// Скрываем существующие комментарии, если они успели записаться в базу
add_filter('comments_array', '__return_empty_array', 10, 2);

// Удаляем пункт «Комментарии» из бокового меню админки
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
});

// Удаляем комментарии из верхнего меню (Admin Bar)
add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
});

// Отключаем загрузку скрипта comment-reply.js на страницах сайта
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_script('comment-reply');
}, 100);
