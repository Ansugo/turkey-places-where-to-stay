<?php
class TPWS_API_Handler {
    
    private $api_key;
    private $base_url = 'https://maps.googleapis.com/maps/api/place';
    
    public function __construct() {
        $this->api_key = get_option('tpws_google_api_key', '');
    }
    
    public function fetch_places_for_city($city_id) {
        global $wpdb;
        
        if (empty($this->api_key)) {
            error_log('TPWS: API key bulunamadı');
            return false;
        }
        
        // Şehir bilgilerini al
        $city = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpws_cities WHERE id = %d",
            $city_id
        ));
        
        if (!$city) {
            return false;
        }
        
        // Google'dan veri çek
        $places = $this->search_nearby_lodging($city->latitude, $city->longitude);
        
        if ($places && is_array($places)) {
            $this->save_places_to_database($places, $city_id);
            return true;
        }
        
        return false;
    }
    
	private function search_nearby_lodging($lat, $lng, $radius = 20000) {
		$url = $this->base_url . '/nearbysearch/json';
    
		$params = [
			'key' => $this->api_key,
			'location' => "$lat,$lng",
			'radius' => $radius,
			'type' => 'lodging',
			'rankby' => 'prominence'
		];
    
		$response = wp_remote_get(add_query_arg($params, $url));
    
		if (is_wp_error($response)) {
			error_log('TPWS API Error: ' . $response->get_error_message());
			return false;
		}
    
		$body = json_decode(wp_remote_retrieve_body($response), true);
    
		if ($body['status'] !== 'OK') {
			error_log('TPWS API Status: ' . $body['status']);
			return false;
		}
    
		$all_places = [];
    
		// TÜM 4+ RATING'Lİ OTELLERİ TOPLA
		foreach ($body['results'] as $place) {
			if (isset($place['rating']) && $place['rating'] >= 4.0) {
				$all_places[] = $place;
			}
		}
    
		// EĞER 4+ RATING'Lİ OTEL YOKSA, 3.5+ OLANLARI AL (OPSİYONEL)
		if (empty($all_places)) {
			foreach ($body['results'] as $place) {
				if (isset($place['rating']) && $place['rating'] >= 3.5) {
					$all_places[] = $place;
				}
			}
		}
    
		// YORUM SAYISINA GÖRE SIRALA (AZALAN - EN ÇOK YORUM EN ÜSTTE)
		usort($all_places, function($a, $b) {
			$a_reviews = isset($a['user_ratings_total']) ? $a['user_ratings_total'] : 0;
			$b_reviews = isset($b['user_ratings_total']) ? $b['user_ratings_total'] : 0;
			return $b_reviews - $a_reviews; // Azalan sıralama
		});
    
		// İLK 20'Yİ AL (EN ÇOK YORUMA SAHİP OLANLAR)
		$filtered_places = array_slice($all_places, 0, 20);
    
		return $filtered_places;
	}    
	
	private function save_places_to_database($places, $city_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'tpws_places';
    
		// SADECE İLK 20 KAYDI İŞLE (zaten API'den 20 geliyor ama yine de)
		$places = array_slice($places, 0, 20);
    
		// Mevcut place ID'leri topla (temizleme için)
		$current_place_ids = [];
    
		foreach ($places as $place) {
			$current_place_ids[] = $place['place_id'];
        
			$data = [
				'city_id' => $city_id,
				'google_place_id' => $place['place_id'],
				'name' => $place['name'],
				'address' => isset($place['vicinity']) ? $place['vicinity'] : '',
				'rating' => isset($place['rating']) ? $place['rating'] : null,
				'total_ratings' => isset($place['user_ratings_total']) ? $place['user_ratings_total'] : 0,
				'price_level' => isset($place['price_level']) ? $place['price_level'] : null,
				'photo_reference' => isset($place['photos'][0]['photo_reference']) ? $place['photos'][0]['photo_reference'] : '',
				'place_url' => 'https://www.google.com/maps/place/?q=place_id:' . $place['place_id'],
				'last_updated' => current_time('mysql')
			];
        
			// Var olan kaydı güncelle veya yeni ekle
			$existing = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM $table_name WHERE google_place_id = %s",
				$place['place_id']
			));
        
			if ($existing) {
				$wpdb->update($table_name, $data, ['id' => $existing]);
			} else {
				$wpdb->insert($table_name, $data);
			}
		}
    
		// ESKİ KAYITLARI TEMİZLE (opsiyonel)
		$this->clean_old_places($city_id, $current_place_ids);
    
		// Şehirin güncelleme tarihini güncelle
		$wpdb->update(
			$wpdb->prefix . 'tpws_cities',
			['last_updated' => current_time('mysql')],
			['id' => $city_id]
		);
	}

	// Eski/aktif olmayan kayıtları temizleme fonksiyonu (ekleyin)
	private function clean_old_places($city_id, $current_place_ids) {
		global $wpdb;
    
		if (empty($current_place_ids)) {
			return;
		}
    
		// Placeholder oluştur
		$placeholders = implode(',', array_fill(0, count($current_place_ids), '%s'));
    
		// 1 günden eski ve şu anki listede olmayan kayıtları sil
		$query = $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}tpws_places 
			WHERE city_id = %d 
			AND google_place_id NOT IN ($placeholders)
			AND last_updated < DATE_SUB(NOW(), INTERVAL 1 DAY)",
			array_merge([$city_id], $current_place_ids)
		);
    
		$wpdb->query($query);
	}
    
   public function get_place_photo_url($photo_reference, $max_width = 400) {
    if (empty($photo_reference)) {
        // Varsayılan resim için SVG oluştur
        return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="' . $max_width . '" height="' . ($max_width * 0.75) . '" viewBox="0 0 400 300">
                    <rect width="400" height="300" fill="#f0f0f0"/>
                    <rect x="50" y="50" width="300" height="200" fill="#ffffff" stroke="#cccccc" stroke-width="2"/>
                    <rect x="100" y="80" width="200" height="20" fill="#e0e0e0"/>
                    <rect x="100" y="110" width="150" height="15" fill="#e0e0e0"/>
                    <rect x="100" y="130" width="100" height="15" fill="#e0e0e0"/>
                    <circle cx="300" cy="150" r="40" fill="#4a90e2" opacity="0.3"/>
                    <text x="200" y="220" text-anchor="middle" font-family="Arial" font-size="14" fill="#666666">Konaklama Yeri</text>
                </svg>');
    }
    
    $url = $this->base_url . '/photo';
    $params = [
        'key' => $this->api_key,
        'photoreference' => $photo_reference,
        'maxwidth' => $max_width
    ];
    
    return add_query_arg($params, $url);
	}
}