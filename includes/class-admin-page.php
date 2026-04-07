<?php
class TPWS_Admin_Page {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_tpws_save_city', [$this, 'save_city']);
        add_action('admin_post_tpws_delete_city', [$this, 'delete_city']);
        add_action('admin_post_tpws_manual_fetch', [$this, 'manual_fetch']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Turkey Places',
            'Turkey Places',
            'manage_options',
            'turkey-places',
            [$this, 'render_admin_page'],
            'dashicons-admin-multisite',
            30
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_turkey-places') {
            return;
        }
        
        wp_enqueue_style(
            'tpws-admin-style',
            TPWS_PLUGIN_URL . 'admin/css/admin-style.css',
            [],
            TPWS_VERSION
        );
        
        wp_enqueue_script(
            'tpws-admin-script',
            TPWS_PLUGIN_URL . 'admin/js/admin-script.js',
            ['jquery'],
            TPWS_VERSION,
            true
        );
    }
    
    public function render_admin_page() {
        global $wpdb;
        
        // API key ayarı
        if (isset($_POST['save_api_key'])) {
            update_option('tpws_google_api_key', sanitize_text_field($_POST['api_key']));
            echo '<div class="notice notice-success"><p>API key kaydedildi.</p></div>';
        }
        
        $api_key = get_option('tpws_google_api_key', '');
        $cities = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tpws_cities ORDER BY city_name");
        ?>
        
        <div class="wrap tpws-admin">
            <h1>Turkey Places - Where to Stay</h1>
            
            <div class="tpws-settings-section">
                <h2>Google API Ayarları</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('tpws_api_key_nonce', '_wpnonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="api_key">Google Places API Key</label></th>
                            <td>
                                <input type="text" id="api_key" name="api_key" 
                                       value="<?php echo esc_attr($api_key); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    <a href="https://console.cloud.google.com/" target="_blank">
                                        Google Cloud Console'dan alın
                                    </a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <input type="submit" name="save_api_key" class="button button-primary" value="Kaydet">
                </form>
            </div>
            
            <div class="tpws-cities-section">
                <h2>Şehir Yönetimi</h2>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="tpws-add-city">
                    <input type="hidden" name="action" value="tpws_save_city">
                    <?php wp_nonce_field('tpws_add_city', 'tpws_nonce'); ?>
                    
                    <h3>Yeni Şehir Ekle</h3>
                    <div class="form-row">
                        <input type="text" name="city_name" placeholder="Şehir Adı (örn: Manavgat)" required>
                        <input type="text" name="latitude" placeholder="Enlem (örn: 36.7867)" required>
                        <input type="text" name="longitude" placeholder="Boylam (örn: 31.4430)" required>
                        <input type="submit" class="button button-primary" value="Şehir Ekle">
                    </div>
                </form>
                
                <h3>Mevcut Şehirler</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Şehir Adı</th>
                            <th>Koordinatlar</th>
                            <th>Son Güncelleme</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cities): ?>
                            <?php foreach ($cities as $city): ?>
                                <tr>
                                    <td><?php echo $city->id; ?></td>
                                    <td><?php echo esc_html($city->city_name); ?></td>
                                    <td><?php echo $city->latitude . ', ' . $city->longitude; ?></td>
                                    <td><?php echo $city->last_updated ?: 'Henüz çekilmedi'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $city->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $city->is_active ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="tpws_manual_fetch">
                                            <input type="hidden" name="city_id" value="<?php echo $city->id; ?>">
                                            <?php wp_nonce_field('tpws_manual_fetch_' . $city->id, '_wpnonce'); ?>
                                            <button type="submit" class="button button-small">Şimdi Çek</button>
                                        </form>
                                        
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="tpws_delete_city">
                                            <input type="hidden" name="city_id" value="<?php echo $city->id; ?>">
                                            <?php wp_nonce_field('tpws_delete_city_' . $city->id, '_wpnonce'); ?>
                                            <button type="submit" class="button button-small button-danger" 
                                                    onclick="return confirm('Bu şehri silmek istediğinize emin misiniz?')">
                                                Sil
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Henüz şehir eklenmemiş.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="tpws-actions">
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="tpws_manual_fetch">
                        <input type="hidden" name="city_id" value="all">
                        <?php wp_nonce_field('tpws_manual_fetch_all', '_wpnonce'); ?>
                        <button type="submit" class="button button-large button-primary">
                            Tüm Şehirleri Şimdi Çek
                        </button>
                        <p class="description">Not: Bu işlem biraz zaman alabilir ve API limitinizi kullanır.</p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function save_city() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['tpws_nonce'], 'tpws_add_city')) {
            wp_die('Yetkiniz yok.');
        }
        
        global $wpdb;
        
        $data = [
            'city_name' => sanitize_text_field($_POST['city_name']),
            'latitude' => floatval($_POST['latitude']),
            'longitude' => floatval($_POST['longitude']),
            'is_active' => 1
        ];
        
        $wpdb->insert($wpdb->prefix . 'tpws_cities', $data);
        
        wp_redirect(admin_url('admin.php?page=turkey-places&message=saved'));
        exit;
    }
    
    public function delete_city() {
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        global $wpdb;
        
        $city_id = intval($_POST['city_id']);
        $wpdb->delete($wpdb->prefix . 'tpws_cities', ['id' => $city_id]);
        
        wp_redirect(admin_url('admin.php?page=turkey-places&message=deleted'));
        exit;
    }
    
    public function manual_fetch() {
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        $city_id = $_POST['city_id'];
        $api_handler = new TPWS_API_Handler();
        
        if ($city_id === 'all') {
            TPWS_Cron_Handler::fetch_all_cities_places();
        } else {
            $api_handler->fetch_places_for_city(intval($city_id));
        }
        
        wp_redirect(admin_url('admin.php?page=turkey-places&message=fetched'));
        exit;
    }
}