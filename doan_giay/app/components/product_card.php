<?php
// Thẻ sản phẩm dùng chung (trang catalog, yêu thích, trang chủ).
// Cần biến $product có: product_id, name, base_price, thumbnail, category_name (tùy chọn: sale_price, badge)
if (!isset($product)) return;
$__fav = isset(favorite_ids()[(int)$product['product_id']]);
$__link = 'index.php?page=product&id=' . (int)$product['product_id'];

$__basePrice = (float)($product['base_price'] ?? 0);
$__salePrice = !empty($product['sale_price']) ? (float)$product['sale_price'] : 0.0;
$__badge = (string)($product['badge'] ?? 'none');
$__isSale = ($__salePrice > 0 && $__salePrice < $__basePrice);
$__pct = $__isSale ? (int)round((1 - $__salePrice / $__basePrice) * 100) : 0;
?>
<div class="product_cards">
    <div class="card_img_wrap">
        <a class="card_img" href="<?php echo e($__link); ?>">
            <img src="<?php echo e(img_url($product['thumbnail'] ?? '')); ?>"
                 alt="<?php echo e($product['name']); ?>"
                 onerror="this.onerror=null; this.src='public/assets/imgs/placeholder.svg';">
        </a>
        <div class="card_badges">
            <?php if ($__isSale || $__badge === 'sale'): ?>
                <span class="badge-tag badge-tag-sale">Sale <?php echo $__pct > 0 ? "-{$__pct}%" : ''; ?></span>
            <?php endif; ?>
            <?php if ($__badge === 'new'): ?>
                <span class="badge-tag badge-tag-new">MỚI</span>
            <?php elseif ($__badge === 'hot'): ?>
                <span class="badge-tag badge-tag-hot">HOT</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card_info">
        <small class="cat_title"><?php echo e($product['category_name'] ?? 'Giày'); ?></small>
        <h4 class="prod_name"><a href="<?php echo e($__link); ?>"><?php echo e($product['name']); ?></a></h4>
        
        <div class="price_tag <?php echo $__isSale ? 'price_tag_sale' : ''; ?>">
            <?php if ($__isSale): ?>
                <span class="price_current"><?php echo format_vnd($__salePrice); ?></span>
                <span class="price_original"><?php echo format_vnd($__basePrice); ?></span>
            <?php else: ?>
                <span class="price_current"><?php echo format_vnd($__basePrice); ?></span>
            <?php endif; ?>
        </div>

        <form method="post" action="index.php" class="fav-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="favorite_toggle">
            <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
            <input type="hidden" name="return" value="<?php echo e(current_relative_url()); ?>">
            <button type="submit" class="fav-btn <?php echo $__fav ? 'is-fav' : ''; ?>" aria-label="Yêu thích">
                <i class="<?php echo $__fav ? 'fa-solid' : 'fa-regular'; ?> fa-heart"></i>
                <?php echo $__fav ? 'Đã thích' : 'Yêu thích'; ?>
            </button>
        </form>
    </div>
</div>
