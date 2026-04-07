<?php
/**
 * Plugin Name: Turkey Places - Where to Stay
 * Plugin URI: https://farukenes.com.tr
 * Description: Fetches and displays 4-5 star restaurants from Google Places API for Turkish cities
 * Version: 1.0.0
 * Author: Haci Faruk Enes
 * License: GPL v2 or later
 */

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('TPWS_VERSION', '1.0.0');
define('TPWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPWS_CRON_INTERVAL', 604800); // 7 gün

// Otomatik yükleme sınıfları
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'TPWS_') === 0) {
        $file = TPWS_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Tüm gerekli dosyaları include et
$required_files = [
    'class-database.php',
    'class-api-handler.php',
    'class-cron-handler.php',
    'class-admin-page.php',
    'class-ajax-handler.php',
    'class-shortcode.php',
    'class-cache-handler.php'
];

foreach ($required_files as $file) {
    $file_path = TPWS_PLUGIN_DIR . 'includes/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// Eklentiyi başlat
function tpws_init() {
    // Veritabanı tablolarını oluştur
    TPWS_Database::create_tables();
    
    // Cron job'ları ayarla
    TPWS_Cron_Handler::schedule_events();
    
    // Admin sayfasını yükle
    if (is_admin()) {
        new TPWS_Admin_Page();
    }
    
    // Widget'ı kaydet
// Widget'ı kaydet
/*
add_action('widgets_init', 'tpws_register_widgets');
function tpws_register_widgets() {
    if (class_exists('TPWS_Widget')) {
        register_widget('TPWS_Widget');
    } else {
        // Debug için
        error_log('TPWS_Widget sınıfı bulunamadı');
    }
}
    */
    // AJAX handler'ı başlat
    new TPWS_Ajax_Handler();
    
    // Shortcode'u başlat
    new TPWS_Shortcode();
    
    // Cache handler'ı başlat
    new TPWS_Cache_Handler();
}
add_action('plugins_loaded', 'tpws_init');

// Eklenti aktivasyonu
register_activation_hook(__FILE__, 'tpws_activate');
function tpws_activate() {
    TPWS_Database::create_tables();
    TPWS_Cron_Handler::schedule_events();
    
    // Default seçenekleri ekle
    add_option('tpws_google_api_key', '');
    add_option('tpws_cache_duration', 604800);
    add_option('tpws_max_places_per_city', 20);
    add_option('tpws_min_rating', 4.0);
}

// Eklenti deaktivasyonu
register_deactivation_hook(__FILE__, 'tpws_deactivate');
function tpws_deactivate() {
    TPWS_Cron_Handler::clear_scheduled_events();
    wp_clear_scheduled_hook('tpws_fetch_places_daily');
}

// Eklenti silinmesi
register_uninstall_hook(__FILE__, 'tpws_uninstall');
function tpws_uninstall() {
    global $wpdb;
    
    // Tabloları sil
    $tables = [
        $wpdb->prefix . 'tpws_places',
        $wpdb->prefix . 'tpws_cities'
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Seçenekleri sil
    delete_option('tpws_google_api_key');
    delete_option('tpws_cache_duration');
    delete_option('tpws_max_places_per_city');
    delete_option('tpws_min_rating');
    
    // Transient'leri temizle
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_tpws_%'");
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_timeout_tpws_%'");
    
    // Cache'leri temizle
    wp_cache_flush();
}

// Dil dosyaları yükleme
add_action('init', 'tpws_load_textdomain');
function tpws_load_textdomain() {
    load_plugin_textdomain(
        'tpws',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

// Admin bildirimleri
add_action('admin_notices', 'tpws_admin_notices');
function tpws_admin_notices() {
    if (!get_option('tpws_google_api_key')) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Turkey Places:</strong> 
                Google Places API key'iniz yapılandırılmamış. 
                <a href="<?php echo admin_url('admin.php?page=turkey-places'); ?>">
                    Ayarlar sayfasından API key ekleyin
                </a>.
            </p>
        </div>
        <?php
    }
}

// Widget cache temizleme
/*
add_action('save_post', 'tpws_clear_widget_cache');
function tpws_clear_widget_cache() {
    wp_cache_delete('widget_tpws_widget', 'widget');
}*/