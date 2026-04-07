<?php
class TPWS_Ajax_Handler {
    
    public function __construct() {
        // Admin AJAX işlemleri
        add_action('wp_ajax_tpws_fetch_selected_cities', [$this, 'fetch_selected_cities']);
        add_action('wp_ajax_tpws_toggle_city_status', [$this, 'toggle_city_status']);
        add_action('wp_ajax_tpws_get_city_stats', [$this, 'get_city_stats']);
        
        // Public AJAX işlemleri
        add_action('wp_ajax_tpws_get_place_details', [$this, 'get_place_details']);
        add_action('wp_ajax_nopriv_tpws_get_place_details', [$this, 'get_place_details']);
        
        // AJAX script localization
        add_action('admin_enqueue_scripts', [$this, 'localize_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'localize_public_scripts']);
    }
    
    public function localize_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_turkey-places') {
            return;
        }
        
        wp_localize_script('tpws-admin-script', 'tpws_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpws_admin_nonce'),
            'text_fetching' => __('Veriler çekiliyor...', 'tpws'),
            'text_success' => __('Başarıyla güncellendi', 'tpws'),
            'text_error' => __('Bir hata oluştu', 'tpws')
        ]);
    }
    
    public function localize_public_scripts() {
        if (!is_active_widget(false, false, 'tpws_widget', true)) {
            return;
        }
        
        wp_localize_script('tpws-public-script', 'tpws_public', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpws_public_nonce')
        ]);
    }
    
    public function fetch_selected_cities() {
        check_ajax_referer('tpws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        $city_ids = $_POST['city_ids'] ?? [];
        
        if (empty($city_ids)) {
            wp_send_json_error('Şehir seçilmedi.');
        }
        
        $api_handler = new TPWS_API_Handler();
        $results = [];
        
        foreach ($city_ids as $city_id) {
            $success = $api_handler->fetch_places_for_city(intval($city_id));
            $results[] = [
                'city_id' => $city_id,
                'success' => $success
            ];
            
            // Rate limiting için bekle
            sleep(1);
        }
        
        wp_send_json_success([
            'message' => count($city_ids) . ' şehir için veriler güncellendi.',
            'results' => $results
        ]);
    }
    
    public function toggle_city_status() {
        check_ajax_referer('tpws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        $city_id = intval($_POST['city_id']);
        $status = intval($_POST['status']);
        
        global $wpdb;
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'tpws_cities',
            ['is_active' => $status],
            ['id' => $city_id]
        );
        
        if ($updated !== false) {
            wp_send_json_success('Durum güncellendi.');
        } else {
            wp_send_json_error('Güncelleme başarısız.');
        }
    }
    
    public function get_city_stats() {
        check_ajax_referer('tpws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Yetkiniz yok.');
        }
        
        $city_id = intval($_POST['city_id']);
        
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_places,
                AVG(rating) as avg_rating,
                MAX(rating) as max_rating,
                MIN(rating) as min_rating,
                SUM(total_ratings) as total_reviews
             FROM {$wpdb->prefix}tpws_places 
             WHERE city_id = %d AND is_active = 1",
            $city_id
        ));
        
        wp_send_json_success($stats);
    }
    
    public function get_place_details() {
        check_ajax_referer('tpws_public_nonce', 'nonce');
        
        $place_id = intval($_POST['place_id']);
        
        global $wpdb;
        
        $place = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, c.city_name 
             FROM {$wpdb->prefix}tpws_places p
             LEFT JOIN {$wpdb->prefix}tpws_cities c ON p.city_id = c.id
             WHERE p.id = %d",
            $place_id
        ));
        
        if (!$place) {
            wp_send_json_error('Mekan bulunamadı.');
        }
        
        $api_handler = new TPWS_API_Handler();
        
        ob_start();
        ?>
        <div class="tpws-modal-details">
            <div class="tpws-modal-header">
                <h3><?php echo esc_html($place->name); ?></h3>
                <div class="tpws-modal-location">
                    <span class="city"><?php echo esc_html($place->city_name); ?></span>
                </div>
            </div>
            
            <div class="tpws-modal-image">
                <?php if (!empty($place->photo_reference)): ?>
                    <img src="<?php echo esc_url($api_handler->get_place_photo_url($place->photo_reference, 600)); ?>" 
                         alt="<?php echo esc_attr($place->name); ?>">
                <?php endif; ?>
            </div>
            
            <div class="tpws-modal-rating">
                <div class="tpws-stars-large">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?php echo $i <= floor($place->rating) ? 'filled' : ''; ?>">★</span>
                    <?php endfor; ?>
                    <span class="rating-value"><?php echo number_format($place->rating, 1); ?></span>
                    <span class="total-ratings">(<?php echo number_format($place->total_ratings); ?> değerlendirme)</span>
                </div>
                
                <?php if ($place->price_level): ?>
                    <div class="tpws-price-level-large">
                        Fiyat aralığı: <?php echo str_repeat('₺', $place->price_level); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($place->address)): ?>
                <div class="tpws-modal-address">
                    <h4>Adres</h4>
                    <p><?php echo esc_html($place->address); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($place->phone)): ?>
                <div class="tpws-modal-phone">
                    <h4>Telefon</h4>
                    <p><a href="tel:<?php echo esc_attr($place->phone); ?>"><?php echo esc_html($place->phone); ?></a></p>
                </div>
            <?php endif; ?>
            
            <div class="tpws-modal-actions">
                <a href="<?php echo esc_url($place->place_url); ?>" 
                   target="_blank" 
                   rel="noopener"
                   class="tpws-button-large">
                    📍 Google Haritalar'da Aç
                </a>
                
                <?php if (!empty($place->website)): ?>
                    <a href="<?php echo esc_url($place->website); ?>" 
                       target="_blank" 
                       rel="noopener"
                       class="tpws-button-large tpws-button-secondary">
                        🌐 Web Sitesini Ziyaret Et
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="tpws-modal-footer">
                <small>Son güncelleme: <?php echo date_i18n('d F Y H:i', strtotime($place->last_updated)); ?></small>
            </div>
        </div>
        <?php
        
        $content = ob_get_clean();
        wp_send_json_success($content);
    }
}

// AJAX handler'ı başlat
new TPWS_Ajax_Handler();