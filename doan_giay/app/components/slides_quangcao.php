<?php if (!defined('SECURE_CHECK')) exit('Access denied'); ?>

<!-- Component Slideshow quảng cáo sản phẩm -->
<div class="khung_quangcao">
    <!-- Slide 1 -->
    <div class="slide_item active">
        <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1200&q=80" alt="Nike Air Force 1" class="slide_img">
        <div class="noidung_quangcao">
            <span class="slide_tag">BỘ SƯU TẬP MỚI</span>
            <h2 class="slide_title">NIKE AIR FORCE 1 &rsquo;07</h2>
            <p class="slide_desc">Biểu tượng phong cách đường phố với độ êm ái vượt thời gian. Nâng tầm từng bước chuyển động của bạn.</p>
            <a href="index.php?page=catalog" class="slide_btn_cta">Khám Phá Ngay &rarr;</a>
        </div>
    </div>

    <!-- Slide 2 -->
    <div class="slide_item">
        <img src="https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=1200&q=80" alt="Nike Pegasus Running" class="slide_img">
        <div class="noidung_quangcao">
            <span class="slide_tag">DÒNG CHẠY BỘ ĐỈNH CAO</span>
            <h2 class="slide_title">NIKE PEGASUS 41</h2>
            <p class="slide_desc">Trợ lực đàn hồi tối đa, thoáng khí và siêu nhẹ. Sẵn sàng bứt phá trên mọi cung đường.</p>
            <a href="index.php?page=catalog" class="slide_btn_cta">Xem Sản Phẩm &rarr;</a>
        </div>
    </div>

    <!-- Slide 3 -->
    <div class="slide_item">
        <img src="https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1200&q=80" alt="Nike Dunk Low" class="slide_img">
        <div class="noidung_quangcao">
            <span class="slide_tag">ƯU ĐÃI ĐẶC BIỆT</span>
            <h2 class="slide_title">NIKE DUNK RETRO SALE</h2>
            <p class="slide_desc">Thiết kế kinh điển phối màu trẻ trung, giảm giá đặc biệt cho thành viên trong tuần lễ mua sắm.</p>
            <a href="index.php?page=catalog" class="slide_btn_cta">Săn Ưu Đãi &rarr;</a>
        </div>
    </div>

    <!-- Nút bấm chuyển slide trước / sau -->
    <button type="button" class="nut_chuyen nut_truoc" onclick="chuyenSlide(-1)" aria-label="Slide trước">&#10094;</button>
    <button type="button" class="nut_chuyen nut_sau" onclick="chuyenSlide(1)" aria-label="Slide tiếp theo">&#10095;</button>

    <!-- Các chấm tròn chuyển slide -->
    <div class="slide_dots">
        <span class="dot active" onclick="chonSlide(0)"></span>
        <span class="dot" onclick="chonSlide(1)"></span>
        <span class="dot" onclick="chonSlide(2)"></span>
    </div>
</div>

<script>
// Đoạn mã JavaScript đơn giản điều khiển slideshow quảng cáo
let chiSoSlideHienTai = 0;
const danhSachSlide = document.querySelectorAll('.slide_item');
const danhSachDots  = document.querySelectorAll('.slide_dots .dot');
const khungSlide    = document.querySelector('.khung_quangcao');

// Hiển thị slide theo vị trí chỉ số
function capNhatSlide(viTri) {
    if (!danhSachSlide.length) return;

    // Vòng lặp chỉ số: vượt cuối quay về đầu, vượt đầu ra cuối
    if (viTri >= danhSachSlide.length) {
        chiSoSlideHienTai = 0;
    } else if (viTri < 0) {
        chiSoSlideHienTai = danhSachSlide.length - 1;
    } else {
        chiSoSlideHienTai = viTri;
    }

    // Ẩn tất cả slide và tắt active chấm tròn
    for (let i = 0; i < danhSachSlide.length; i++) {
        danhSachSlide[i].classList.remove('active');
        if (danhSachDots[i]) danhSachDots[i].classList.remove('active');
    }

    // Hiển thị slide được chọn
    danhSachSlide[chiSoSlideHienTai].classList.add('active');
    if (danhSachDots[chiSoSlideHienTai]) {
        danhSachDots[chiSoSlideHienTai].classList.add('active');
    }
}

// Chuyển slide khi bấm nút trước / sau
function chuyenSlide(buoc) {
    capNhatSlide(chiSoSlideHienTai + buoc);
}

// Chuyển slide khi bấm vào chấm tròn
function chonSlide(index) {
    capNhatSlide(index);
}

// Tự động chuyển slide sau mỗi 4 giây — dừng khi di chuột vào, resume khi rời
let boHenGio = setInterval(function() { chuyenSlide(1); }, 4000);

if (khungSlide) {
    // Dừng auto-play khi hover vào slideshow
    khungSlide.addEventListener('mouseenter', function() {
        clearInterval(boHenGio);
    });
    // Tiếp tục auto-play khi rời khỏi slideshow
    khungSlide.addEventListener('mouseleave', function() {
        boHenGio = setInterval(function() { chuyenSlide(1); }, 4000);
    });
}
</script>
