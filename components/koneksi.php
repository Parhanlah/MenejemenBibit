<?php
date_default_timezone_set('Asia/Jakarta'); 
$host = "localhost";
$user = "root"; // Default username XAMPP/Laragon
$pass = "";     // Default password XAMPP/Laragon (kosong)
$db   = "db_poncotani";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}