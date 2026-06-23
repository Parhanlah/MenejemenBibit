<?php
date_default_timezone_set('Asia/Jakarta'); 

$host = "mysql-1bdbc819-farhanadi18032002-bdfb.h.aivencloud.com";
$user = "avnadmin";
$pass = getenv("DB_PASS"); // Coba ambil password dari variabel environment (Vercel)
if (!$pass && file_exists(__DIR__ . '/password.php')) {
    include __DIR__ . '/password.php'; // Ambil password dari file rahasia jika dijalankan di komputer lokal
}
$db   = "defaultdb"; 
$port = 15608;

// Menggunakan koneksi SSL wajib untuk Aiven
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}
?>