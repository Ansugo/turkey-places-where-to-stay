<?php
if (!defined('ABSPATH')) {
    exit;
}

class TPWS_Shortcode {
    
    private $api_handler;
    
    public function __construct() {
        add_shortcode('turkey_places_stay', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_scripts'));
        
        $this->api_handler = new TPWS_API_Handler();
    }
    
    public function enqueue_shortcode_scripts() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'turkey_places_stay')) {
            wp_enqueue_style(
                'tpws-public-style',
                TPWS_PLUGIN_URL . 'public/css/public-style.css',
                array(),
                TPWS_VERSION
            );
        }
    }
    
    public function render_shortcode($atts, $content = null) {
        // DEBUG: Tüm parametreleri logla
        if (current_user_can('administrator')) {
            error_log('TPWS Shortcode Atts: ' . print_r($atts, true));
        }
        
        // Parametreleri al (city veya city_name kabul et)
        $atts = shortcode_atts(array(
            'city_id' => '',
            'city' => '',      // Yeni parametre
            'city_name' => '', // Eski parametre (geri uyumluluk)
            'max_items' => 10,
            'columns' => 3,
            'show_filter' => 'no',
            'show_sort' => 'no',
            'min_rating' => 4.0,
            'layout' => 'grid'
        ), $atts, 'turkey_places_stay');
        
        // DEBUG çıktısı (sadece adminler görür)
        $debug = '';
        if (current_user_can('administrator')) {
            $debug .= '<!-- TPWS DEBUG: ' . print_r($atts, true) . ' -->';
        }
        
        // Şehir adını belirle (city veya city_name)
        $city_name = '';
        if (!empty($atts['city'])) {
            $city_name = trim($atts['city']);
        } elseif (!empty($atts['city_name'])) {
            $city_name = trim($atts['city_name']);
        }
        
        // DEBUG: Hangi şehir adı kullanılıyor
        if (current_user_can('administrator')) {
            $debug .= '<!-- TPWS City Name: "' . $city_name . '" -->';
        }
        
        // Şehir ID'sini bul
        $city_id = $this->get_city_id($atts['city_id'], $city_name);
        
        if (!$city_id) {
            $error = '<div class="tpws-error" style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">';
            $error .= '<p><strong>Hata:</strong> Geçerli bir şehir belirtilmedi.</p>';
            
            if (current_user_can('administrator')) {
                $error .= '<div style="margin-top: 10px; padding: 10px; background: #fff; border-radius: 3px;">';
                $error .= '<p><strong>Admin Debug Bilgisi:</strong></p>';
                $error .= '<p>Alınan Parametreler:</p>';
                $error .= '<ul>';
                $error .= '<li>city_id: ' . ($atts['city_id'] ?: 'BOŞ') . '</li>';
                $error .= '<li>city: "' . ($atts['city'] ?: 'BOŞ') . '"</li>';
                $error .= '<li>city_name: "' . ($atts['city_name'] ?: 'BOŞ') . '"</li>';
                $error .= '<li>Kullanılan şehir adı: "' . $city_name . '"</li>';
                $error .= '</ul>';
                
                global $wpdb;
                $cities = $wpdb->get_results("SELECT id, city_name FROM {$wpdb->prefix}tpws_cities");
                if ($cities) {
                    $error .= '<p>Veritabanındaki şehirler:</p><ul>';
                    foreach ($cities as $city) {
                        $error .= '<li>' . esc_html($city->city_name) . ' (ID: ' . $city->id . ')</li>';
                    }
                    $error .= '</ul>';
                }
                $error .= '</div>';
            }
            
            $error .= '</div>';
            
            return $debug . $error;
        }
        
        // Geri kalan kod aynı...
        global $wpdb;
        
        // Şehir adını al
        $city = $wpdb->get_row($wpdb->prepare(
            "SELECT city_name FROM {$wpdb->prefix}tpws_cities WHERE id = %d",
            $city_id
        ));
        
        if (!$city) {
            return $debug . '<p class="tpws-error">Şehir bulunamadı.</p>';
        }
        
        // Mekanları getir
        $places = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpws_places 
             WHERE city_id = %d AND is_active = 1 AND rating >= %f
			 ORDER BY (rating * LOG10(GREATEST(total_ratings, 1))) DESC 
             LIMIT %d",
            $city_id,
            floatval($atts['min_rating']),
            intval($atts['max_items'])
        ));
        
        if (empty($places)) {
            return $debug . '<p class="tpws-no-results">For ' . esc_html($city->city_name) . ' found no places to stay.</p>';
        }
        
        // Shortcode ID
        $shortcode_id = 'tpws-' . uniqid();
        
        // Çıktıyı başlat
        ob_start();
        
        echo $debug;
        
        // Container
        echo '<div class="tpws-shortcode-container" id="' . $shortcode_id . '">';
        
        // Başlık
        echo '<h3 class="tpws-title">Where to stay in ' . esc_html($city->city_name) . '</h3>';
        
        // Grid container
        $grid_class = 'tpws-grid tpws-columns-' . min(intval($atts['columns']), 4);
        
        if ($atts['layout'] === 'list') {
            $grid_class = 'tpws-list';
        } elseif ($atts['layout'] === 'carousel') {
            $grid_class = 'tpws-carousel';
        }
        
        echo '<div class="' . $grid_class . '">';
        
        foreach ($places as $place) {
            $this->render_place_card($place, $atts['layout']);
        }
        
        echo '</div>'; // .tpws-grid
        
        // Footer
        echo '<div class="tpws-footer">';
        echo '<small>' . count($places) . ' place is shown. Data is taken from Google Places.</small>';
        echo '</div>';
        
        echo '</div>'; // .tpws-shortcode-container
        
        return ob_get_clean();
    }
    
    private function get_city_id($city_id_param, $city_name_param) {
        global $wpdb;
        
        // 1. Önce city_id ile dene
        if (!empty($city_id_param)) {
            $city_id = intval($city_id_param);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tpws_cities WHERE id = %d",
                $city_id
            ));
            
            if ($exists) {
                return $city_id;
            }
        }
        
        // 2. city_name ile ara
        if (!empty($city_name_param)) {
            // Tam eşleşme
            $city_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tpws_cities 
                 WHERE city_name = %s",
                $city_name_param
            ));
            
            if ($city_id) {
                return $city_id;
            }
            
            // Büyük/küçük harf duyarsız
            $city_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tpws_cities 
                 WHERE LOWER(city_name) = LOWER(%s)",
                $city_name_param
            ));
            
            if ($city_id) {
                return $city_id;
            }
            
            // LIKE ile ara
            $city_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}tpws_cities 
                 WHERE city_name LIKE %s
                 LIMIT 1",
                '%' . $wpdb->esc_like($city_name_param) . '%'
            ));
            
            return $city_id;
        }
        
        return false;
    }
    
    private function render_place_card($place, $layout = 'grid') {
        ?>
        <div class="tpws-place-card">
            <div class="tpws-image">
                <?php if (!empty($place->photo_reference)): ?>
                    <?php 
                    $photo_url = $this->api_handler->get_place_photo_url(
                        $place->photo_reference, 
                        400
                    );
                    ?>
                    <img src="<?php echo esc_url($photo_url); ?>" 
                         alt="<?php echo esc_attr($place->name); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="tpws-no-image">
                        <span>📷</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tpws-content">
                <h4 class="tpws-name">
                    <a href="<?php echo esc_url($place->place_url); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html($place->name); ?>
                    </a>
                </h4>
                
                <div class="tpws-rating">
                    <div class="tpws-stars">
                        <?php
                        $rating = floatval($place->rating);
                        for ($i = 1; $i <= 5; $i++):
                            $class = '';
                            if ($i <= floor($rating)) {
                                $class = 'filled';
                            } elseif ($i - 0.5 <= $rating) {
                                $class = 'half';
                            }
                        ?>
                            <span class="star <?php echo $class; ?>">★</span>
                        <?php endfor; ?>
                        
                        <span class="tpws-rating-value"><?php echo number_format($rating, 1); ?></span>
                        <span class="tpws-review-count">(<?php echo number_format($place->total_ratings); ?>)</span>
                    </div>
                </div>
                
                <?php if ($place->price_level): ?>
                    <div class="tpws-price">
                        <?php echo str_repeat('₺', $place->price_level); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($layout === 'list' && !empty($place->address)): ?>
                    <div class="tpws-address">
                        <small>📍 <?php echo esc_html($place->address); ?></small>
                    </div>
                <?php endif; ?>
                
                <div class="tpws-actions">
                    <a href="<?php echo esc_url($place->place_url); ?>" 
                       target="_blank" 
                       rel="noopener"
                       class="tpws-button">
                        See on Google
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}

new TPWS_Shortcode();