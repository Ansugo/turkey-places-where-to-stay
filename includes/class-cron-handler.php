<?php
class TPWS_Cron_Handler {
    
    public static function schedule_events() {
        if (!wp_next_scheduled('tpws_fetch_places_daily')) {
            wp_schedule_event(time(), 'weekly', 'tpws_fetch_places_daily');
        }
        
        add_action('tpws_fetch_places_daily', [__CLASS__, 'fetch_all_cities_places']);
    }
    
    public static function clear_scheduled_events() {
        wp_clear_scheduled_hook('tpws_fetch_places_daily');
    }
    
    public static function fetch_all_cities_places() {
        global $wpdb;
        
        $api_handler = new TPWS_API_Handler();
        $cities = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}tpws_cities WHERE is_active = 1"
        );
        
        foreach ($cities as $city) {
            $api_handler->fetch_places_for_city($city->id);
            // API rate limit için bekle
            sleep(2);
        }
    }
}