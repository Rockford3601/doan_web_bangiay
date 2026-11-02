<?php
// Ngăn truy cập trực tiếp
if (!defined('SECURE_CHECK')) {
    exit('Access denied');
}

// Cấu hình danh sách trang hợp lệ (Whitelist)
$pages = [
    'home' => [
        'title' => 'Tuyển Chọn | Nike Style',
        'css'   => 'home.css',
        'file'  => 'pages/home.php'
    ],
    'catalog_loi' => [
        'title' => 'Danh sách giày | Tuyển Chọn',
        'css'   => 'catalog_loi.css',
        'file'  => 'pages/catalog_loi.php'
    ],

     'giaodien' => [
        'title' => 'TEST GIAO DIỆN | TEST',
        'css'   => 'giaodien.css',
        'file'  => 'pages/giaodien.php'
    ],


    'catalog' => [
        'title' => 'TEST SANPHAM | TEST2',
        'css'   => 'catalog.css',
        'file'  => 'pages/catalog.php'
    ],

    'cart' => [
        'title' => 'Giỏ hàng | Tuyển Chọn',
        'css'   => 'cart.css',
        'file'  => 'pages/cart.php'
    ]
];
