<?php
// app/config/database.php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "web_giaydep";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Kết nối CSDL thất bại: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");