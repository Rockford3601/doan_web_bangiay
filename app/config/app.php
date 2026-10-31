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
    'catalog' => [
        'title' => 'Danh sách giày | Tuyển Chọn',
        'css'   => 'catalog.css',
        'file'  => 'pages/catalog.php'
    ],

     'giaodien' => [
        'title' => 'TEST GIAO DIỆN | TEST',
        'css'   => 'giaodien1.css',
        'file'  => 'pages/giaodien.php'
    ],

    'cart' => [
        'title' => 'Giỏ hàng | Tuyển Chọn',
        'css'   => 'cart.css',
        'file'  => 'pages/cart.php'
    ]
];
