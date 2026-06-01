<?php
error_reporting(0); // Matikan error sementara agar format JSON tidak rusak
include 'components/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }

    // 1. PROSES TAMBAH SUPPLIER
    if (isset($_POST['ajax_add_supplier'])) {
        $nama = mysqli_real_escape_string($conn, $_POST['nama_supplier']);
        $telepon = mysqli_real_escape_string($conn, $_POST['telepon_supplier']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat_supplier']);
        
        $query = "INSERT INTO supplier (nama, telepon, alamat) VALUES ('$nama', '$telepon', '$alamat')";
        if (mysqli_query($conn, $query)) {
            $id_baru = mysqli_insert_id($conn);
            echo json_encode(['status' => 'success', 'id' => $id_baru, 'nama' => $nama, 'telepon' => $telepon, 'alamat' => $alamat]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // 2. PROSES EDIT SUPPLIER
    if (isset($_POST['ajax_edit_supplier'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id_supplier']);
        $nama = mysqli_real_escape_string($conn, $_POST['nama_supplier']);
        $telepon = mysqli_real_escape_string($conn, $_POST['telepon_supplier']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat_supplier']);
        
        // Ambil nama lama sebelum diupdate (untuk sinkronisasi dropdown di frontend)
        $q_lama = mysqli_query($conn, "SELECT nama FROM supplier WHERE id='$id'");
        $nama_lama = mysqli_fetch_assoc($q_lama)['nama'];

        $query = "UPDATE supplier SET nama='$nama', telepon='$telepon', alamat='$alamat' WHERE id='$id'";
        if (mysqli_query($conn, $query)) {
            echo json_encode(['status' => 'success', 'id' => $id, 'nama' => $nama, 'nama_lama' => $nama_lama, 'telepon' => $telepon, 'alamat' => $alamat]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }

    // 3. PROSES HAPUS SUPPLIER
    if (isset($_POST['ajax_delete_supplier'])) {
        $id = mysqli_real_escape_string($conn, $_POST['id_supplier']);
        $nama = mysqli_real_escape_string($conn, $_POST['nama_supplier']);
        
        $query = "DELETE FROM supplier WHERE id='$id'";
        if (mysqli_query($conn, $query)) {
            echo json_encode(['status' => 'success', 'id' => $id, 'nama' => $nama]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
        exit;
    }
}
?>