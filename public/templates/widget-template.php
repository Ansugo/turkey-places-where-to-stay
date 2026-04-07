<?php
$api_handler = new TPWS_API_Handler();
?>

<div class="tpws-widget">
    <div class="tpws-grid-container">
        <?php foreach ($places as $place): ?>
            <div class="tpws-place-card">
                <div class="tpws-place-image">
                    <?php if (!empty($place->photo_reference)): ?>
                        <img src="<?php echo esc_url($api_handler->get_place_photo_url($place->photo_reference, 300)); ?>" 
                             alt="<?php echo esc_attr($place->name); ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="tpws-no-image">Resim Yok</div>
                    <?php endif; ?>
                </div>
                
                <div class="tpws-place-info">
                    <h4 class="tpws-place-title">
                        <a href="<?php echo esc_url($place->place_url); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($place->name); ?>
                        </a>
                    </h4>
                    
                    <div class="tpws-place-rating">
                        <div class="tpws-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?php echo $i <= floor($place->rating) ? 'filled' : ''; ?>">★</span>
                            <?php endfor; ?>
                            <span class="rating-value"><?php echo number_format($place->rating, 1); ?></span>
                            <span class="total-ratings">(<?php echo number_format($place->total_ratings); ?>)</span>
                        </div>
                    </div>
                    
                    <?php if ($place->price_level): ?>
                        <div class="tpws-price-level">
                            <?php echo str_repeat('$', $place->price_level); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($place->address)): ?>
                        <div class="tpws-place-address">
                            📍 <?php echo esc_html($place->address); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tpws-actions">
                        <a href="<?php echo esc_url($place->place_url); ?>" 
                           target="_blank" 
                           rel="noopener"
                           class="tpws-button">
                            Google'da Gör
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="tpws-footer">
        <small>Veriler Google Places'dan alınmıştır. Son güncelleme: <?php echo date_i18n('d F Y'); ?></small>
    </div>
</div>