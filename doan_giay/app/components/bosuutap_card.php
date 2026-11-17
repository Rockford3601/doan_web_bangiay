<?php
if (!defined('SECURE_CHECK')) exit('Access denied');

/**
 * Component hiển thị 1 thẻ bộ sưu tập
 * Nhận biến $col gồm các trường:
 * - title, subtitle, desc, img, link, tag
 */
$col = $col ?? [
    'title'    => 'Bộ Sưu Tập Xuân Hè 2026',
    'subtitle' => 'XUÂN HÈ COLLECTION',
    'desc'     => 'Cảm hứng từ những tia nắng đầu mùa, kết hợp chất liệu mỏng nhẹ, thoáng khí và êm ái tối đa cho từng chuyển động hàng ngày.',
    'img'      => 'public/assets/imgs/bosuutap/anh_collect_xuan.png',
    'link'     => 'index.php?page=catalog',
    'tag'      => 'MỚI RA MẮT'
];
?>
<article class="bst-card">
    <div class="bst-card-img-wrap">
        <img src="<?php echo e($col['img']); ?>" alt="<?php echo e($col['title']); ?>" loading="lazy" />
        <?php if (!empty($col['tag'])): ?>
            <span class="bst-card-tag"><?php echo e($col['tag']); ?></span>
        <?php endif; ?>
    </div>
    <div class="bst-card-content">
        <div class="bst-card-header">
            <span class="bst-card-sub"><?php echo e($col['subtitle'] ?? 'BỘ SƯU TẬP'); ?></span>
            <h3 class="bst-card-title"><?php echo e($col['title']); ?></h3>
        </div>
        <p class="bst-card-desc"><?php echo e($col['desc']); ?></p>
        <div class="bst-card-action">
            <a href="<?php echo e($col['link']); ?>" class="bst-card-btn">
                <span>Khám phá ngay</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</article>
