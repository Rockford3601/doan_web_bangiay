<?php if (!isset($product)) return; ?>
<div class="product_cards">
    <div class="card_img">
        <img src="<?php echo htmlspecialchars(!empty($product['thumbnail']) ? $product['thumbnail'] : 'public/assets/images/default.jpg'); ?>" 
             alt="<?php echo htmlspecialchars($product['name']); ?>"
             onerror="this.onerror=null; this.src='https://via.placeholder.com/200x150?text=No+Image';">
    </div>
    <div class="card_info">
        <small class="cat_title"><?php echo htmlspecialchars($product['category_name'] ?? 'Giày'); ?></small>
        <h4 class="prod_name"><?php echo htmlspecialchars($product['name']); ?></h4>
        <div class="rating"><i class="fa-solid fa-star"></i> <?php echo number_format($product['avg_rating'] ?? 5.0, 1); ?></div>
        <div class="price_tag"><?php echo number_format($product['base_price'], 0, ',', '.'); ?> VNĐ</div>
    </div>
</div>