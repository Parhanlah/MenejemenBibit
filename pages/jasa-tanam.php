<?php
include 'components/koneksi.php';

$tgl_hari_ini = date('Y-m-d');
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard'; 

// =========================================================================
// 0. AUTO DB MIGRATION
// =========================================================================
$cek_kolom_1 = mysqli_query($conn, "SHOW COLUMNS FROM `order_bibit` LIKE 'biaya_tambahan'");
if(mysqli_num_rows($cek_kolom_1) == 0) mysqli_query($conn, "ALTER TABLE `order_bibit` ADD `biaya_tambahan` INT DEFAULT 0");
$cek_kolom_2 = mysqli_query($conn, "SHOW COLUMNS FROM `order_bibit` LIKE 'ket_biaya_tambahan'");
if(mysqli_num_rows($cek_kolom_2) == 0) mysqli_query($conn, "ALTER TABLE `order_bibit` ADD `ket_biaya_tambahan` VARCHAR(255) DEFAULT ''");
$cek_kolom_3 = mysqli_query($conn, "SHOW COLUMNS FROM `order_bibit` LIKE 'progres_tanam'");
if(mysqli_num_rows($cek_kolom_3) == 0) mysqli_query($conn, "ALTER TABLE `order_bibit` ADD `progres_tanam` VARCHAR(255) DEFAULT ''");

// =========================================================================
// 1. ENGINE UPDATE STATUS (METODE AUTO-REFRESH SUPER STABIL)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['no_order']) && isset($_GET['status_baru'])) {
    
    $no_order = mysqli_real_escape_string($conn, $_GET['no_order']);
    $status_baru = mysqli_real_escape_string($conn, $_GET['status_baru']);

    $q_old = mysqli_query($conn, "SELECT id_baris, panjang_m, total_harga, status FROM order_bibit WHERE no_order='$no_order'");
    $order_lama = mysqli_fetch_assoc($q_old);

    if ($order_lama) {
        $id_baris = $order_lama['id_baris'];
        $panjang_m = (float)$order_lama['panjang_m'];
        $status_sebelumnya = $order_lama['status'];

        // HUBUNGAN OTOMATIS STATUS & KEUANGAN:
        if (in_array($status_baru, ['Lunas', 'Persiapan', 'Tanam', 'Selesai'])) {
            mysqli_query($conn, "UPDATE order_bibit SET status='$status_baru', dp_dibayar=total_harga WHERE no_order='$no_order'");
        } 
        // HUBUNGAN OTOMATIS STATUS & OPERASIONAL LAHAN (PEMBATALAN):
        else if ($status_baru == 'Batal') {
            if ($status_sebelumnya != 'Batal') { 
                mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m = LEAST(tersedia_m + $panjang_m, 12.0), status='tumbuh' WHERE id_baris='$id_baris'");
            }
            mysqli_query($conn, "UPDATE order_bibit SET status='Batal' WHERE no_order='$no_order'");
        } 
        // Jika dikembalikan ke Booking / DP dari Batal
        else {
            if ($status_sebelumnya == 'Batal') {
                mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m = GREATEST(tersedia_m - $panjang_m, 0.0) WHERE id_baris='$id_baris'");
            }
            mysqli_query($conn, "UPDATE order_bibit SET status='$status_baru' WHERE no_order='$no_order'");
        }

        // Langsung Auto-Refresh ke halaman tab Data dan nyalakan efek berkedip
        echo "<script>window.location.href='?page=jasa-tanam&tab=data&highlight=$no_order';</script>";
        exit;
    }
}

// =========================================================================
// 1.5. ENGINE UPDATE PROGRES PENANAMAN
// =========================================================================
if (isset($_POST['update_progres'])) {
    $no_order = mysqli_real_escape_string($conn, $_POST['no_order']);
    $progres = mysqli_real_escape_string($conn, $_POST['progres_tanam']);
    
    $cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM `order_bibit` LIKE 'progres_tanam'");
    if(mysqli_num_rows($cek_kolom) == 0) {
        mysqli_query($conn, "ALTER TABLE `order_bibit` ADD `progres_tanam` VARCHAR(255) DEFAULT ''");
    }
    
    mysqli_query($conn, "UPDATE order_bibit SET progres_tanam='$progres' WHERE no_order='$no_order'");
    echo "<script>alert('Progres penanaman berhasil diperbarui!'); window.location.href='?page=jasa-tanam&tab=aktif';</script>";
    exit;
}

// =========================================================================
// 2. CONFIG ACUAN HARGA DASAR GLOBAL DARI DATABASE
// =========================================================================
$cfg_bibit = 800000; $cfg_jasa = 1200000;
$cek_cfg = mysqli_query($conn, "SHOW TABLES LIKE 'pengaturan_sistem'");
if(mysqli_num_rows($cek_cfg) > 0) {
    $q_b = mysqli_query($conn, "SELECT nilai FROM pengaturan_sistem WHERE kunci='harga_bibit_global'");
    if($r_b = mysqli_fetch_assoc($q_b)) $cfg_bibit = (int)$r_b['nilai'];
    $q_j = mysqli_query($conn, "SELECT nilai FROM pengaturan_sistem WHERE kunci='harga_jasa_tanam_global'");
    if($r_j = mysqli_fetch_assoc($q_j)) $cfg_jasa = (int)$r_j['nilai'];
}
$total_paket_global = $cfg_bibit + $cfg_jasa;

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
    function formatTgl($tgl){ 
        if(!$tgl || $tgl == '0000-00-00') return '-'; $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $d = explode('-', $tgl); return (int)$d[2] . ' ' . $bulan[(int)$d[1]-1] . ' ' . $d[0];
    }
}

// =========================================================================
// 3. PROSES SIMPAN & EDIT DATABASE KONTRAK JASA TANAM
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jasa_tanam'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_customer']); $hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']); $sawah = mysqli_real_escape_string($conn, $_POST['lokasi_sawah']);
    $tgl_b = mysqli_real_escape_string($conn, $_POST['tgl_booking']); $tgl_t = mysqli_real_escape_string($conn, $_POST['tgl_tanam']);
    $ket = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    $baris_array = isset($_POST['baris_tanam_list']) && $_POST['baris_tanam_list'] != '' ? explode(',', $_POST['baris_tanam_list']) : [];
    $jml_baris = count($baris_array);
    $meter_tambahan = isset($_POST['meter_tambahan']) ? (float)$_POST['meter_tambahan'] : 0;
    $id_baris_tambahan = isset($_POST['id_baris_tambahan']) ? (int)$_POST['id_baris_tambahan'] : 0;
    
    if ($jml_baris == 0 && $meter_tambahan <= 0) { echo "<script>alert('Gagal! Pilih minimal 1 baris lahan atau meter tambahan.'); window.history.back();</script>"; exit; }

    $tahun = date('Y', strtotime($tgl_b));
    $q_max = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING_INDEX(no_order, '-', -1) AS UNSIGNED)) as max_id FROM order_bibit WHERE no_order LIKE 'JT-%'");
    $next_id = mysqli_fetch_assoc($q_max)['max_id'] + 1;
    $no_order = "JT-" . $tahun . "-" . str_pad($next_id, 4, '0', STR_PAD_LEFT);

    $subtotal_gabungan = ($cfg_bibit + $cfg_jasa) * $jml_baris;
    if($meter_tambahan > 0 && $id_baris_tambahan > 0) {
        $subtotal_gabungan += round(($meter_tambahan / 12) * ($cfg_bibit + $cfg_jasa));
    }
    
    $diskon_p = 0; $diskon_n = 0;
    if (isset($_POST['is_diskon'])) {
        if ($_POST['tipe_diskon'] == 'persen') { $diskon_p = (float)$_POST['nominal_diskon_persen']; } 
        else { $diskon_n = (int)str_replace('.', '', $_POST['nominal_diskon_rp']); }
    }
    
    $kode_dipakai = isset($_POST['kode_kupon_dipakai']) ? mysqli_real_escape_string($conn, $_POST['kode_kupon_dipakai']) : '';
    if($kode_dipakai != '') {
        $q_kup = mysqli_query($conn, "SELECT id, kuota, tipe, nilai FROM kupon_diskon WHERE kode='$kode_dipakai'");
        if($kup = mysqli_fetch_assoc($q_kup)) {
            if($kup['tipe'] == 'Persentase') { $diskon_p += (float)$kup['nilai']; } else { $diskon_n += (float)$kup['nilai']; }
        }
    }

    $total_diskon = ($subtotal_gabungan * ($diskon_p / 100)) + $diskon_n;
    $ongkir = isset($_POST['ambil']) && $_POST['ambil'] == 'dikirim' ? (int)str_replace('.', '', $_POST['nominal_ongkir']) : 0;
    $final_total = $subtotal_gabungan - $total_diskon + $ongkir;
    $dp_total = isset($_POST['bayar']) && $_POST['bayar'] == 'dp' ? (int)str_replace('.', '', $_POST['nominal_dp']) : $final_total;
    $status_init = ($dp_total >= $final_total) ? 'Lunas' : 'Booking';

    $items_to_insert = [];
    foreach ($baris_array as $no_b) {
        $q_v = mysqli_query($conn, "SELECT v.nama_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.id_baris='$no_b'");
        $var_nama = mysqli_fetch_assoc($q_v)['nama_varietas'] ?: 'Ciherang';
        $items_to_insert[] = [ 'id_b' => $no_b, 'm' => 12.0, 'pos' => '0m - 12m', 'var' => $var_nama, 'h_dasar' => $cfg_bibit, 'h_jasa' => $cfg_jasa ];
        mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m=0, status='habis' WHERE id_baris='$no_b'");
    }

    if($meter_tambahan > 0 && $id_baris_tambahan > 0) {
        $q_v = mysqli_query($conn, "SELECT b.tersedia_m, v.nama_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.id_baris='$id_baris_tambahan'");
        $d_bt = mysqli_fetch_assoc($q_v); $var_nama = $d_bt['nama_varietas'] ?: 'Ciherang';
        $start_m = 12.0 - (float)$d_bt['tersedia_m']; $end_m = $start_m + $meter_tambahan;
        $items_to_insert[] = [ 'id_b' => $id_baris_tambahan, 'm' => $meter_tambahan, 'pos' => "{$start_m}m - {$end_m}m", 'var' => $var_nama, 'h_dasar' => round(($meter_tambahan/12)*$cfg_bibit), 'h_jasa' => round(($meter_tambahan/12)*$cfg_jasa) ];
        $sisa = (float)$d_bt['tersedia_m'] - $meter_tambahan; $st_upd = ($sisa <= 0) ? ", status='habis'" : "";
        mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m=$sisa $st_upd WHERE id_baris='$id_baris_tambahan'");
    }

    foreach($items_to_insert as $it) {
        $sub_item = $it['h_dasar'] + $it['h_jasa'];
        $rasio = ($subtotal_gabungan > 0) ? ($sub_item / $subtotal_gabungan) : 1;
        $d_p = $diskon_p; $d_n = round($diskon_n * $rasio); $ong = round($ongkir * $rasio);
        $final_item = $sub_item - ($sub_item * ($d_p/100)) - $d_n + $ong;
        $dp_item = ($dp_total >= $final_total) ? $final_item : round($dp_total * $rasio);

        mysqli_query($conn, "INSERT INTO order_bibit (no_order, nama_customer, no_hp, alamat, id_baris, panjang_m, posisi, varietas, tgl_booking, harga_dasar, diskon_persen, diskon_nominal, ongkir, dp_dibayar, total_harga, status, tipe_order, lokasi_sawah, tgl_tanam, biaya_jasa, keterangan) 
        VALUES ('$no_order', '$nama', '$hp', '$alamat', '{$it['id_b']}', '{$it['m']}', '{$it['pos']}', '{$it['var']}', '$tgl_b', '{$it['h_dasar']}', '$d_p', '$d_n', '$ong', '$dp_item', '$final_item', '$status_init', 'Jasa Tanam', '$sawah', '$tgl_t', '{$it['h_jasa']}', '$ket')");
    }

    echo "<script>alert('Berhasil! Kontrak Jasa Tanam $no_order tersimpan.'); window.location.href='?page=jasa-tanam&tab=data';</script>"; exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_jasa_tanam'])) {
    $no_order = mysqli_real_escape_string($conn, $_POST['edit_no_order']);
    $nama = mysqli_real_escape_string($conn, $_POST['edit_nama']);
    $hp = mysqli_real_escape_string($conn, $_POST['edit_hp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['edit_alamat']);
    $sawah = mysqli_real_escape_string($conn, $_POST['edit_sawah']);
    $tgl_t = mysqli_real_escape_string($conn, $_POST['edit_tgl_tanam']);
    $ket = mysqli_real_escape_string($conn, $_POST['edit_keterangan']);

    // Fitur Tambahan Biaya
    $biaya_tambahan = isset($_POST['edit_biaya_tambahan']) ? (int)str_replace('.', '', $_POST['edit_biaya_tambahan']) : 0;
    $ket_biaya_tambahan = mysqli_real_escape_string($conn, $_POST['edit_ket_biaya_tambahan'] ?? '');

    // Hitung ulang harga jika ada biaya tambahan yang diinput
    $q_rows = mysqli_query($conn, "SELECT id, harga_dasar, biaya_jasa, diskon_persen, diskon_nominal, ongkir FROM order_bibit WHERE no_order='$no_order'");
    $total_base = 0; $rows = [];
    while($r = mysqli_fetch_assoc($q_rows)) {
        $total_base += ($r['harga_dasar'] + $r['biaya_jasa']);
        $rows[] = $r;
    }
    
    foreach($rows as $r) {
        $sub = $r['harga_dasar'] + $r['biaya_jasa'];
        $rasio = ($total_base > 0) ? ($sub / $total_base) : 1;
        $bt_item = round($biaya_tambahan * $rasio);
        
        $new_total = $sub - ($sub * ($r['diskon_persen']/100)) - $r['diskon_nominal'] + $r['ongkir'] + $bt_item;
        mysqli_query($conn, "UPDATE order_bibit SET biaya_tambahan=$bt_item, ket_biaya_tambahan='$ket_biaya_tambahan', total_harga=$new_total WHERE id={$r['id']}");
    }

    mysqli_query($conn, "UPDATE order_bibit SET nama_customer='$nama', no_hp='$hp', alamat='$alamat', lokasi_sawah='$sawah', tgl_tanam='$tgl_t', keterangan='$ket' WHERE no_order='$no_order'");
    echo "<script>alert('Kontrak Jasa Tanam berhasil diperbarui!'); window.location.href='?page=jasa-tanam&tab=data';</script>"; exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tukar_baris') {
    $no_order = mysqli_real_escape_string($conn, $_POST['no_order']);
    $old_id = mysqli_real_escape_string($conn, $_POST['old_id_baris']);
    $new_id = mysqli_real_escape_string($conn, $_POST['new_id_baris']);

    $q_old = mysqli_query($conn, "SELECT id, panjang_m, varietas FROM order_bibit WHERE no_order='$no_order' AND id_baris='$old_id' LIMIT 1");
    if($old = mysqli_fetch_assoc($q_old)) {
        $panjang_m = (float)$old['panjang_m'];
        $order_id = $old['id'];

        $q_new_var = mysqli_query($conn, "SELECT v.nama_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.id_baris='$new_id'");
        $new_var_nama = mysqli_fetch_assoc($q_new_var)['nama_varietas'] ?: 'Ciherang';

        mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m = LEAST(tersedia_m + $panjang_m, 12.0), status='tumbuh' WHERE id_baris='$old_id'");
        mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m = GREATEST(tersedia_m - $panjang_m, 0.0) WHERE id_baris='$new_id'");
        mysqli_query($conn, "UPDATE bibit_baris SET status='habis' WHERE id_baris='$new_id' AND tersedia_m <= 0");
        mysqli_query($conn, "UPDATE order_bibit SET id_baris='$new_id', varietas='$new_var_nama' WHERE id='$order_id'");

        echo "<script>alert('Berhasil! Baris #$old_id telah ditukar dengan baris #$new_id.'); window.location.href='?page=jasa-tanam&tab=data&highlight=$no_order';</script>"; exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'ubah_volume') {
    $no_order = mysqli_real_escape_string($conn, $_POST['no_order']);
    $id_baris = mysqli_real_escape_string($conn, $_POST['id_baris']);
    $new_m = (float)$_POST['volume_baru'];

    if ($new_m <= 0 || $new_m > 12) {
        echo "<script>alert('Gagal! Volume baru harus di antara 0.1 hingga 12 meter.'); window.history.back();</script>"; exit;
    }

    $q_old = mysqli_query($conn, "SELECT id, panjang_m, harga_dasar, biaya_jasa FROM order_bibit WHERE no_order='$no_order' AND id_baris='$id_baris' LIMIT 1");
    if($old = mysqli_fetch_assoc($q_old)) {
        $old_m = (float)$old['panjang_m'];
        $diff = $new_m - $old_m;

        $q_inv = mysqli_query($conn, "SELECT tersedia_m FROM bibit_baris WHERE id_baris='$id_baris'");
        $inv = mysqli_fetch_assoc($q_inv);
        $tersedia_sekarang = (float)$inv['tersedia_m'];

        if ($diff > 0 && $tersedia_sekarang < $diff) {
            echo "<script>alert('Gagal! Lahan tidak cukup. Hanya tersisa $tersedia_sekarang m di baris ini.'); window.history.back();</script>"; exit;
        }

        $new_tersedia = $tersedia_sekarang - $diff;
        $status_baru = ($new_tersedia <= 0) ? 'habis' : 'tumbuh';
        mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m=$new_tersedia, status='$status_baru' WHERE id_baris='$id_baris'");

        $new_h_dasar = round(($new_m / 12) * $cfg_bibit);
        $new_h_jasa = round(($new_m / 12) * $cfg_jasa);
        mysqli_query($conn, "UPDATE order_bibit SET panjang_m=$new_m, harga_dasar=$new_h_dasar, biaya_jasa=$new_h_jasa WHERE id={$old['id']}");

        // Recalculate whole order
        $q_rows = mysqli_query($conn, "SELECT id, harga_dasar, biaya_jasa, diskon_persen, diskon_nominal, ongkir, biaya_tambahan, dp_dibayar FROM order_bibit WHERE no_order='$no_order'");
        $total_h_dasar = 0; $total_h_jasa = 0; $rows = [];
        $first_diskon_p = 0; $first_diskon_n = 0; $first_ongkir = 0; $first_bt = 0; $first_dp = 0;
        
        while($r = mysqli_fetch_assoc($q_rows)) {
            if(empty($rows)) {
                $first_diskon_p = (float)$r['diskon_persen'];
                $first_diskon_n = (int)$r['diskon_nominal'];
                $first_ongkir = (int)$r['ongkir'];
                $first_bt = (int)$r['biaya_tambahan'];
                $first_dp = (int)$r['dp_dibayar'];
            }
            $total_h_dasar += (int)$r['harga_dasar'];
            $total_h_jasa += (int)$r['biaya_jasa'];
            $rows[] = $r;
        }

        $total_sub = $total_h_dasar + $total_h_jasa;
        foreach($rows as $r) {
            $prop = ($r['harga_dasar'] + $r['biaya_jasa']) / $total_sub;
            $new_dn = round($first_diskon_n * $prop);
            $new_ok = round($first_ongkir * $prop);
            $new_bt = round($first_bt * $prop);
            $new_dp = round($first_dp * $prop);
            
            $th = ($r['harga_dasar'] + $r['biaya_jasa']) - (($r['harga_dasar'] * ($first_diskon_p/100)) + $new_dn) + $new_ok + $new_bt;
            mysqli_query($conn, "UPDATE order_bibit SET diskon_persen=$first_diskon_p, diskon_nominal=$new_dn, ongkir=$new_ok, biaya_tambahan=$new_bt, dp_dibayar=$new_dp, total_harga=$th WHERE id={$r['id']}");
        }

        echo "<script>alert('Berhasil! Volume baris #$id_baris telah diubah menjadi $new_m meter.'); window.location.href='?page=jasa-tanam&tab=data&highlight=$no_order';</script>"; exit;
    }
}



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_progres_modal') {
    $no_order = mysqli_real_escape_string($conn, $_POST['up_no_order']);
    $new_progres = mysqli_real_escape_string($conn, $_POST['up_progres']);
    
    // Update progres_tanam for all rows in the order
    mysqli_query($conn, "UPDATE order_bibit SET progres_tanam='$new_progres' WHERE no_order='$no_order'");
    
    echo "<script>alert('Berhasil! Progres penanaman telah diperbarui.'); window.location.href='?page=jasa-tanam&tab=aktif&highlight=$no_order';</script>"; exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah_baris') {
    $no_order = mysqli_real_escape_string($conn, $_POST['no_order']);
    $new_id = mysqli_real_escape_string($conn, $_POST['new_id_baris']);
    $new_m = (float)$_POST['meter_tambahan'];

    if ($new_m <= 0 || $new_m > 12) {
        echo "<script>alert('Gagal! Meter tambahan harus di antara 0.1 hingga 12 meter.'); window.history.back();</script>"; exit;
    }

    $q_inv = mysqli_query($conn, "SELECT b.tersedia_m, v.nama_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.id_baris='$new_id'");
    $inv = mysqli_fetch_assoc($q_inv);
    $tersedia_sekarang = (float)$inv['tersedia_m'];
    $var_nama = $inv['nama_varietas'] ?: 'Ciherang';

    if ($tersedia_sekarang < $new_m) {
        echo "<script>alert('Gagal! Lahan tidak cukup. Hanya tersisa $tersedia_sekarang m di baris #$new_id.'); window.history.back();</script>"; exit;
    }

    $q_parent = mysqli_query($conn, "SELECT nama_customer, no_hp, alamat, lokasi_sawah, tgl_tanam, tgl_booking, keterangan, status, diskon_persen, tipe_order, dp_dibayar FROM order_bibit WHERE no_order='$no_order' LIMIT 1");
    if ($parent = mysqli_fetch_assoc($q_parent)) {
        // Update inventory
        $new_tersedia = $tersedia_sekarang - $new_m;
        $status_baru = ($new_tersedia <= 0) ? 'habis' : 'tumbuh';
        mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m=$new_tersedia, status='$status_baru' WHERE id_baris='$new_id'");

        // Check if this row already exists in the order
        $q_existing = mysqli_query($conn, "SELECT id, panjang_m FROM order_bibit WHERE no_order='$no_order' AND id_baris='$new_id'");
        if (mysqli_num_rows($q_existing) > 0) {
            $existing = mysqli_fetch_assoc($q_existing);
            $total_m = (float)$existing['panjang_m'] + $new_m;
            $new_h_dasar = round(($total_m / 12) * $cfg_bibit);
            $new_h_jasa = round(($total_m / 12) * $cfg_jasa);
            $start_m = 12.0 - $tersedia_sekarang; $end_m = $start_m + $new_m;
            $pos_tambahan = " + {$start_m}m - {$end_m}m";
            mysqli_query($conn, "UPDATE order_bibit SET panjang_m=$total_m, posisi=CONCAT(posisi, '$pos_tambahan'), harga_dasar=$new_h_dasar, biaya_jasa=$new_h_jasa WHERE id={$existing['id']}");
        } else {
            // Insert new row
            $new_h_dasar = round(($new_m / 12) * $cfg_bibit);
            $new_h_jasa = round(($new_m / 12) * $cfg_jasa);
            $start_m = 12.0 - $tersedia_sekarang; $end_m = $start_m + $new_m;
            $pos = "{$start_m}m - {$end_m}m";
            
            mysqli_query($conn, "INSERT INTO order_bibit (no_order, nama_customer, no_hp, alamat, lokasi_sawah, tgl_tanam, tgl_booking, keterangan, status, diskon_persen, tipe_order, id_baris, varietas, panjang_m, posisi, harga_dasar, biaya_jasa, diskon_nominal, ongkir, biaya_tambahan, dp_dibayar, total_harga) 
            VALUES ('$no_order', '{$parent['nama_customer']}', '{$parent['no_hp']}', '{$parent['alamat']}', '{$parent['lokasi_sawah']}', '{$parent['tgl_tanam']}', '{$parent['tgl_booking']}', '{$parent['keterangan']}', '{$parent['status']}', '{$parent['diskon_persen']}', '{$parent['tipe_order']}', '$new_id', '$var_nama', $new_m, '$pos', $new_h_dasar, $new_h_jasa, 0, 0, 0, 0, 0)");
        }

        // Recalculate whole order to redistribute diskon_persen, ongkir, biaya_tambahan, and DP
        $q_rows = mysqli_query($conn, "SELECT id, harga_dasar, biaya_jasa, diskon_persen, diskon_nominal, ongkir, biaya_tambahan, dp_dibayar FROM order_bibit WHERE no_order='$no_order'");
        $total_base = 0; $rows = [];
        $total_bt = 0; $total_dp = 0;
        while($r = mysqli_fetch_assoc($q_rows)) {
            $total_base += ($r['harga_dasar'] + $r['biaya_jasa']);
            $total_bt += $r['biaya_tambahan'];
            $total_dp += $r['dp_dibayar'];
            $rows[] = $r;
        }
        
        foreach($rows as $r) {
            $sub = $r['harga_dasar'] + $r['biaya_jasa'];
            $rasio = ($total_base > 0) ? ($sub / $total_base) : 1;
            $bt_item = round($total_bt * $rasio);
            $dp_item = round($total_dp * $rasio); 
            
            $new_total = $sub - ($sub * ($r['diskon_persen']/100)) - $r['diskon_nominal'] + $r['ongkir'] + $bt_item;
            mysqli_query($conn, "UPDATE order_bibit SET total_harga=$new_total, biaya_tambahan=$bt_item, dp_dibayar=$dp_item WHERE id={$r['id']}");
        }

        echo "<script>alert('Berhasil! Baris #$new_id ($new_m meter) telah ditambahkan ke kontrak.'); window.location.href='?page=jasa-tanam&tab=data&highlight=$no_order';</script>"; exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'hapus_permanen' && isset($_GET['no_order'])) {
    $no_order = mysqli_real_escape_string($conn, $_GET['no_order']);
    
    // Kembalikan stok lahan sebelum dihapus
    $q_o = mysqli_query($conn, "SELECT id_baris, panjang_m, status FROM order_bibit WHERE no_order='$no_order'");
    while($o = mysqli_fetch_assoc($q_o)) {
        if ($o['status'] != 'Batal') {
            mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m = LEAST(tersedia_m + {$o['panjang_m']}, 12.0), status='tumbuh' WHERE id_baris='{$o['id_baris']}'");
        }
    }
    mysqli_query($conn, "DELETE FROM order_bibit WHERE no_order='$no_order'");
    echo "<script>alert('Kontrak Jasa Tanam dihapus permanen!'); window.location.href='?page=jasa-tanam&tab=data';</script>"; exit;
}

// =========================================================================
// 4. PREPARASI DATA RADAR DASHBOARD & FORM ACUAN
// =========================================================================
$q_lahan = mysqli_query($conn, "SELECT b.*, v.nama_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id ORDER BY b.id_baris ASC");
$q_active_orders = mysqli_query($conn, "SELECT * FROM order_bibit WHERE status NOT IN ('diambil', 'Selesai', 'Batal')");
$active_orders_map = [];
while($ao = mysqli_fetch_assoc($q_active_orders)) { $active_orders_map[$ao['id_baris']][] = $ao; }

$data_baris = []; $lahan_siap_borong = []; $lahan_sumber_tambahan = [];

while($r = mysqli_fetch_assoc($q_lahan)) {
    $display_tersedia = (float)$r['tersedia_m']; $status_db = $r['status'];
    if($status_db == 'kosong' || $status_db == 'persiapan') { $display_tersedia = 0; }
    
    if($status_db != 'kosong') {
        if($display_tersedia == 12.0) { $lahan_siap_borong[] = $r; }
        if($display_tersedia > 0) { $lahan_sumber_tambahan[] = $r; }
    }

    $m_booking = 0; $m_lunas = 0;
    if(isset($active_orders_map[$r['id_baris']])) {
        foreach($active_orders_map[$r['id_baris']] as $ao) {
            $st = strtolower($ao['status']);
            if($st == 'booking' || $st == 'dp') { $m_booking += (float)$ao['panjang_m']; }
            else if($st == 'lunas' || $st == 'persiapan' || $st == 'tanam' || $st == 'selesai') { $m_lunas += (float)$ao['panjang_m']; }
        }
    }
    
    $m_free = $display_tersedia;
    if ($status_db == 'kosong' || $status_db == 'persiapan') {
        $m_kosong = 12.0; $m_lunas = 0; $m_booking = 0; $m_free = 0;
    } else {
        $m_kosong = 12.0 - ($m_lunas + $m_booking + $m_free);
        if($m_kosong < 0) $m_kosong = 0;
    }

    $data_baris[] = [ 'no' => $r['id_baris'], 'varietas' => $r['nama_varietas'] ?: '-', 'm_kosong' => $m_kosong, 'm_lunas' => $m_lunas, 'm_booking' => $m_booking, 'm_free' => $m_free ];
}
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    <div class="flex items-center gap-3 mb-6">
        <div>
            <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]"><i class="fa-solid fa-person-digging text-[#3fb950] mr-3"></i> Jasa Tanam Padi</h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-0.5 ml-8">Kelola order jasa tanam padi terintegrasi</p>
        </div>
    </div>

    <div class="flex gap-6 border-b border-gray-200 dark:border-[#30363d] mb-6 px-2 overflow-x-auto">
        <a href="?page=jasa-tanam&tab=dashboard" class="border-b-2 <?= $tab=='dashboard' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-chart-simple mr-1.5"></i> Dashboard</a>
        <a href="?page=jasa-tanam&tab=baru" class="border-b-2 <?= $tab=='baru' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-plus mr-1.5"></i> Order Baru</a>
        <a href="?page=jasa-tanam&tab=aktif" class="border-b-2 <?= $tab=='aktif' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-users mr-1.5"></i> Transaksi Aktif</a>
        <a href="?page=jasa-tanam&tab=data" class="border-b-2 <?= $tab=='data' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-regular fa-folder-open mr-1.5"></i> Data Order</a>
    </div>

    <div id="ajax-toast" class="fixed bottom-5 right-5 z-[999] bg-[#238636] text-white px-5 py-3 rounded-lg shadow-xl font-bold text-[13px] translate-y-20 opacity-0 transition-all duration-300 pointer-events-none flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-base"></i> <span id="ajax-toast-msg">Status Aman Tersimpan!</span>
    </div>

    <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-4 rounded-xl shadow-sm mb-6">
        <h4 class="text-[12px] font-bold text-gray-500 uppercase mb-3 flex items-center"><i class="fa-solid fa-circle-dollar-to-slot text-yellow-500 mr-2"></i> Informasi Acuan Harga Jasa Tanam Padi</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] p-4 rounded-lg"><p class="text-[11px] text-gray-500">Harga Dasar Bibit / Baris</p><h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1">Rp <?= number_format($cfg_bibit, 0, ',', '.') ?></h3></div>
            <div class="bg-white dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] p-4 rounded-lg"><p class="text-[11px] text-gray-500">Harga Jasa Tanam / Baris</p><h3 class="text-xl font-bold text-gray-900 dark:text-white mt-1">Rp <?= number_format($cfg_jasa, 0, ',', '.') ?></h3></div>
            <div class="bg-green-500/10 border border-green-500/30 p-4 rounded-lg"><p class="text-[11px] text-green-600 dark:text-green-400">Total Paket per Baris (Bibit + Jasa)</p><h3 class="text-xl font-bold text-[#3fb950] mt-1">Rp <?= number_format($total_paket_global, 0, ',', '.') ?></h3></div>
        </div>
    </div>

    <?php if($tab == 'dashboard'): ?>
    <div>
        <div class="flex flex-wrap items-center gap-4 mb-6 bg-gray-50 dark:bg-[#161b22] p-3 rounded-lg border border-gray-200 dark:border-[#30363d]">
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-[#3fb950] mr-2"></div> Free (Tersedia)</div>
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-[#d29922] mr-2"></div> Booking / DP</div>
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-[#f85149] mr-2"></div> Lunas / Proses Tanam</div>
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-gray-200 dark:bg-[#30363d] mr-2 border border-gray-300 dark:border-transparent"></div> Kosong (Selesai/Diambil)</div>
        </div>

        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg pb-10">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="border-b border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#161b22]">
                    <tr class="text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase">
                        <th class="py-3 px-4 w-16 text-center">NO</th><th class="py-3 px-4 w-40">VARIETAS BIBIT</th><th class="py-3 px-4 min-w-[400px]">VISUALISASI SEGMEN PENJUALAN (12M)</th><th class="py-3 px-4 text-right w-16">SISA SLOT</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d]">
                    <?php foreach($data_baris as $b): 
                        $czones = []; $c_pos = 0;
                        if($b['m_kosong'] > 0) { $czones[] = ['c'=>'#e5e7eb', 'd'=>'#30363d', 's'=>$c_pos, 'e'=>$c_pos+$b['m_kosong'], 'n'=>'Kosong']; $c_pos+=$b['m_kosong']; }
                        if($b['m_lunas'] > 0)  { $czones[] = ['c'=>'#f85149', 'd'=>'#f85149', 's'=>$c_pos, 'e'=>$c_pos+$b['m_lunas'], 'n'=>'Lunas']; $c_pos+=$b['m_lunas']; }
                        if($b['m_booking'] > 0){ $czones[] = ['c'=>'#d29922', 'd'=>'#d29922', 's'=>$c_pos, 'e'=>$c_pos+$b['m_booking'], 'n'=>'Booking']; $c_pos+=$b['m_booking']; }
                        if($b['m_free'] > 0)   { $czones[] = ['c'=>'#2ea043', 'd'=>'#2ea043', 's'=>$c_pos, 'e'=>$c_pos+$b['m_free'], 'n'=>'Free']; $c_pos+=$b['m_free']; }
                    ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]">
                        <td class="py-3 px-4 text-center"><div class="w-6 h-6 mx-auto rounded-full flex items-center justify-center text-[11px] font-bold text-white <?= $b['m_free']==0 && $b['m_kosong']<12 ? 'bg-[#f85149]' : 'bg-[#238636]' ?>"><?= $b['no'] ?></div></td>
                        <td class="py-3 px-4 text-[13px] text-gray-900 dark:text-[#c9d1d9] font-bold"><?= htmlspecialchars($b['varietas']) ?></td>
                        <td class="py-3 px-4">
                            <div class="grid grid-cols-8 gap-1 w-full">
                                <?php 
                                for($i=1; $i<=8; $i++) {
                                    $s_st = ($i - 1) * 1.5; $s_en = $i * 1.5; $intersect = [];
                                    foreach($czones as $z) {
                                        $i_st = max($z['s'], $s_st); $i_en = min($z['e'], $s_en);
                                        if($i_st < $i_en + 0.001 && round($i_en - $i_st, 3) > 0) { $intersect[] = ['c' => $z['c'], 'd' => $z['d'], 'n' => $z['n'], 'p' => (($i_en - $i_st) / 1.5) * 100]; }
                                    }
                                    if(count($intersect) == 0) { echo '<div class="bg-gray-200 dark:bg-[#30363d] text-gray-500 text-[9px] font-bold py-1.5 px-1 rounded-[2px]">Err</div>'; }
                                    else if(count($intersect) == 1) {
                                        $iz = $intersect[0];
                                        if($iz['n'] == 'Kosong') { echo '<div class="bg-gray-200 dark:bg-[#30363d] text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-transparent text-[9px] font-bold py-1.5 text-center rounded-[2px] truncate px-1 cursor-default">Kosong</div>'; } 
                                        else { echo '<div style="background-color:'.$iz['d'].';" class="text-white text-[9px] font-bold py-1.5 text-center rounded-[2px] truncate px-1 cursor-default">'.$iz['n'].'</div>'; }
                                    } else {
                                        $stops = []; $cum = 0; $names = []; $total_iz = count($intersect);
                                        foreach($intersect as $idx => $iz) {
                                            $st_p = round($cum, 2); $cum += $iz['p']; $en_p = ($idx === $total_iz - 1) ? 100 : round($cum, 2);
                                            $stops[] = "{$iz['d']} {$st_p}%"; $stops[] = "{$iz['d']} {$en_p}%"; $names[] = $iz['n'];
                                        }
                                        $grad = "linear-gradient(to right, ".implode(', ', $stops).")";
                                        echo '<div style="background: '.$grad.' no-repeat padding-box; text-shadow: 1px 1px 2px rgba(0,0,0,0.6);" title="Campur: '.implode(', ', $names).'" class="text-white text-[9px] font-bold py-1.5 text-center rounded-[2px] truncate px-1 cursor-help">Campur</div>';
                                    }
                                }
                                ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-[13px] font-bold text-right <?= $b['m_free'] > 0 ? 'text-[#3fb950]' : 'text-[#f85149]' ?>"><?= $b['m_free'] ?>m</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif($tab == 'baru'): ?>
    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-5 rounded-xl shadow-sm">
        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-5"><i class="fa-solid fa-file-signature text-[#58a6ff] mr-2"></i> Input Formulir Kontrak Jasa Tanam Baru</h2>
        <form method="POST" action="">
            <input type="hidden" name="baris_tanam_list" id="h_baris_tanam_list" value="">
            <input type="hidden" name="kode_kupon_dipakai" id="h_kode_kupon" value="">
            <input type="hidden" id="h_nilai_kupon" value="0">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-4">
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Nama Customer <span class="text-red-500">*</span></label><input type="text" name="nama_customer" placeholder="Nama lengkap" required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">No. HP <span class="text-red-500">*</span></label><input type="text" name="no_hp" placeholder="08xxxxxxxxxx" required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
            </div>
            <div class="mb-4"><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Alamat Rumah Customer <span class="text-red-500">*</span></label><input type="text" name="alamat" placeholder="Alamat lengkap tinggal" required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
            <div class="mb-4"><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Lokasi Sawah Area Tanam <span class="text-red-500">*</span></label><input type="text" name="lokasi_sawah" placeholder="Sawah di daerah..." required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Tanggal Booking <span class="text-red-500">*</span></label><input type="date" name="tgl_booking" value="<?= $tgl_hari_ini ?>" required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff] [color-scheme:light] dark:[color-scheme:dark]"></div>
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Tanggal Rencana Tanam <span class="text-red-500">*</span></label><input type="date" name="tgl_tanam" required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff] [color-scheme:light] dark:[color-scheme:dark]"></div>
            </div>

            <div class="mb-5">
                <label class="block text-[12px] font-bold text-gray-500 uppercase mb-3">PILIH BARIS BIBIT UTUH (1 Baris = 12 Meter) <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-5 sm:grid-cols-8 lg:grid-cols-10 gap-2 bg-gray-50 dark:bg-[#0d1117] p-4 border border-gray-200 dark:border-[#30363d] rounded-lg max-h-48 overflow-y-auto custom-scrollbar">
                    <?php if(count($lahan_siap_borong) > 0): ?>
                        <?php foreach($lahan_siap_borong as $lb): ?>
                            <div onclick="selectBarisTanam(this, <?= $lb['id_baris'] ?>)" class="relative border border-gray-300 dark:border-[#30363d] bg-white dark:bg-[#161b22] rounded p-2 text-center cursor-pointer select-none transition-all hover:border-[#58a6ff]">
                                <div class="text-[13px] font-bold text-gray-800 dark:text-white">#<?= $lb['id_baris'] ?></div>
                                <div class="text-[10px] text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($lb['nama_varietas']) ?></div>
                                <div class="check-overlay absolute inset-0 bg-blue-500/10 border-2 border-[#58a6ff] rounded hidden items-center justify-center"><div class="bg-[#1f6feb] text-white rounded-full w-4 h-4 flex items-center justify-center absolute -top-1.5 -right-1.5 shadow-md"><i class="fa-solid fa-check text-[9px]"></i></div></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center text-gray-500 py-4 text-[12px]">Tidak ada baris utuh berbenih yang tersedia.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5 bg-gray-50 dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] p-4 rounded-lg">
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Meter Tambahan (Opsional)</label><input type="number" name="meter_tambahan" id="in_meter_tambahan" step="0.5" min="0" value="0" class="w-full bg-white dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                <div id="cont_baris_tambahan" class="hidden"><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Pilih Baris Sumber Tambahan <span class="text-red-500">*</span></label><select name="id_baris_tambahan" id="sel_baris_tambahan" class="w-full bg-white dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"><option value="0" data-max="0">Pilih baris...</option><?php foreach($lahan_sumber_tambahan as $bt): ?><option value="<?= $bt['id_baris'] ?>" data-max="<?= $bt['tersedia_m'] ?>">#<?= $bt['id_baris'] ?> - (Sisa: <?= $bt['tersedia_m'] ?>m)</option><?php endforeach; ?></select></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <div class="flex items-center justify-between mb-3"><label class="text-[12px] font-bold text-gray-500 uppercase">Diskon Manual</label><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="tog_diskon" name="is_diskon" class="sr-only peer"><div class="w-8 h-4 bg-gray-300 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all dark:border-gray-600 peer-checked:bg-[#3fb950]"></div></label></div>
                    <div id="cont_diskon" class="hidden border-t border-gray-200 dark:border-[#30363d] pt-3">
                        <div class="flex gap-4 mb-3"><label class="text-[12px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="tipe_diskon" value="persen" checked class="mr-1"> Persen (%)</label><label class="text-[12px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="tipe_diskon" value="nominal" class="mr-1"> Nominal (Rp)</label></div>
                        <div id="w_diskon_p" class="relative"><input type="number" name="nominal_diskon_persen" id="in_diskon_p" value="0" min="0" max="100" class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] pr-8 text-gray-900 dark:text-white"><span class="absolute right-3 top-2 text-gray-500 text-[12px] font-bold">%</span></div>
                        <div id="w_diskon_n" class="relative hidden"><span class="absolute left-3 top-2 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="nominal_diskon_rp" id="in_diskon_n" class="format-rupiah w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] pl-8 text-gray-900 dark:text-white"></div>
                    </div>
                </div>
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <label class="block text-[12px] font-bold text-gray-500 uppercase mb-3">Kode Kupon (Opsional)</label>
                    <div class="flex gap-2 mb-1.5"><input type="text" id="in_kupon" placeholder="Masukkan kode" class="uppercase w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] text-gray-900 dark:text-white"><button type="button" id="btn_kupon" class="bg-[#1f6feb] text-white px-3 rounded text-[12px] font-bold">Cek</button></div>
                    <p id="msg_kupon" class="text-[11px] hidden"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Metode Ambil</label>
                    <div class="flex flex-col gap-2 text-[13px] mb-2"><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="ambil" value="diambil" checked class="mr-2"> Ambil Mandiri (Gratis)</label><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="ambil" value="dikirim" class="mr-2"> Antar ke Sawah</label></div>
                    <div id="cont_ongkir" class="hidden relative pt-2 border-t border-gray-200 dark:border-[#30363d]"><span class="absolute left-3 top-4 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="nominal_ongkir" id="in_ongkir" placeholder="Biaya Ongkir" class="format-rupiah w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] pl-8 text-gray-900 dark:text-white"></div>
                </div>
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Sistem Bayar</label>
                    <div class="flex flex-col gap-2 text-[13px] mb-2"><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="bayar" value="lunas" checked class="mr-2"> Bayar Lunas</label><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="bayar" value="dp" class="mr-2"> Bayar DP (Uang Muka)</label></div>
                    <div id="cont_dp" class="hidden relative pt-2 border-t border-gray-200 dark:border-[#30363d]"><span class="absolute left-3 top-4 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="nominal_dp" id="in_dp" placeholder="Nominal Uang Muka" class="format-rupiah w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] pl-8 text-gray-900 dark:text-white"></div>
                </div>
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Catatan Keterangan</label>
                    <textarea name="keterangan" rows="3" placeholder="Catatan pekerjaan..." class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded p-2 text-[12px] focus:outline-none resize-none"></textarea>
                </div>
            </div>

            <div class="border-t-4 border-green-500 bg-white dark:bg-[#161b22] border-x border-b border-gray-200 dark:border-[#30363d] p-5 rounded-b-lg mb-6 text-[13px]">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4">Ringkasan Faktur Perhitungan Jasa</h3>
                <div class="flex justify-between mb-2 text-gray-600 dark:text-[#c9d1d9]"><span>Jumlah Baris Utuh:</span> <span id="r_qty" class="font-bold">0 baris</span></div>
                <div class="flex justify-between mb-2 text-gray-600 dark:text-[#c9d1d9]"><span>Meter Tambahan:</span> <span id="r_mtr" class="font-bold">0m</span></div>
                <div class="flex justify-between mb-2 text-gray-600 dark:text-[#c9d1d9] pt-2 border-t border-gray-300 dark:border-[#30363d]"><span>Biaya Bibit Padi:</span> <span id="r_bibit">Rp 0</span></div>
                <div class="flex justify-between mb-2 text-gray-600 dark:text-[#c9d1d9]"><span>Biaya Pekerja Tanam:</span> <span id="r_jasa">Rp 0</span></div>
                <div class="flex justify-between mb-2 text-gray-800 dark:text-white font-bold pt-2 border-t border-gray-300 dark:border-[#30363d]"><span>Subtotal Paket:</span> <span id="r_sub">Rp 0</span></div>
                <div class="flex justify-between mb-2 text-red-500 hidden" id="r_row_dis"><span>Potongan Diskon:</span> <span id="r_dis">- Rp 0</span></div>
                <div class="flex justify-between mb-2 text-yellow-500 hidden" id="r_row_ong"><span>Biaya Ongkir:</span> <span id="r_ong">+ Rp 0</span></div>
                
                <div class="flex justify-between pt-3 border-t border-gray-300 dark:border-[#30363d] text-base font-bold text-green-500"><span>TOTAL HARGA AKHIR:</span> <span id="r_final">Rp 0</span></div>
                
                <div class="flex justify-between items-center text-[13px] text-[#1f6feb] dark:text-[#58a6ff] mt-2 pt-2 border-t border-gray-300 dark:border-[#30363d] hidden" id="r_row_dp"><span>DP Dibayar:</span> <span id="r_dp">Rp 0</span></div>
                <div class="flex justify-between items-center text-[13px] text-[#f85149] mt-2 hidden" id="r_row_sisa"><span>Sisa Bayar:</span> <span id="r_sisa" class="font-bold">Rp 0</span></div>
            </div>

            <button type="submit" name="simpan_jasa_tanam" class="w-full bg-[#238636] hover:bg-[#2ea043] text-white py-3 rounded-lg font-bold text-[14px] transition-colors shadow">Simpan & Konfirmasi Kontrak Kerja</button>
        </form>
    </div>

    <script>
        const fmtRp = (a) => "Rp " + new Intl.NumberFormat('id-ID').format(a);
        const unfRp = (s) => parseInt(s.toString().replace(/[^0-9]/g, '')) || 0;

        document.querySelectorAll('.format-rupiah').forEach(inp => {
            inp.addEventListener('input', function() {
                let v = this.value.replace(/[^0-9]/g, ''); this.value = v !== '' ? new Intl.NumberFormat('id-ID').format(parseInt(v)) : ''; calcJasaTanam();
            });
        });

        let setBarisTanam = new Set();
        const cfgBibit = <?= $cfg_bibit ?>; const cfgJasa = <?= $cfg_jasa ?>;
        
        const inMtr = document.getElementById('in_meter_tambahan'); const selMtr = document.getElementById('sel_baris_tambahan'); const contMtr = document.getElementById('cont_baris_tambahan');
        const togDis = document.getElementById('tog_diskon'); const contDis = document.getElementById('cont_diskon'); const rTipeDis = document.querySelectorAll('input[name="tipe_diskon"]');
        const wpDisP = document.getElementById('w_diskon_p'); const wpDisN = document.getElementById('w_diskon_n'); const inDisP = document.getElementById('in_diskon_p'); const inDisN = document.getElementById('in_diskon_n');
        const rAmbil = document.querySelectorAll('input[name="ambil"]'); const contOng = document.getElementById('cont_ongkir'); const inOng = document.getElementById('in_ongkir');
        const rBayar = document.querySelectorAll('input[name="bayar"]'); const contDp = document.getElementById('cont_dp'); const inDp = document.getElementById('in_dp');
        
        <?php $q_k = mysqli_query($conn, "SELECT * FROM kupon_diskon WHERE status='Aktif' AND (berlaku='Jasa Tanam' OR berlaku='Semua Order')"); $arr_k = []; while($ka = mysqli_fetch_assoc($q_k)) { $arr_k[] = $ka; } ?>
        const listKupon = <?= json_encode($arr_k) ?>;
        const btnKup = document.getElementById('btn_kupon'); const inKup = document.getElementById('in_kupon'); const msgKup = document.getElementById('msg_kupon');
        const hNilaiKup = document.getElementById('h_nilai_kupon'); const hKodeKup = document.getElementById('h_kode_kupon');

        btnKup.addEventListener('click', () => {
            let k = inKup.value.trim().toUpperCase(); let f = listKupon.find(x => x.kode.toUpperCase() === k);
            if(f) {
                let h = false; if(f.kuota.includes('/')){ let p=f.kuota.split('/'); if(parseInt(p[0])>=parseInt(p[1])) h=true; }
                if(h) { msgKup.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Kuota habis.'; msgKup.className='text-[11px] mt-1 text-[#f85149] block font-bold'; hNilaiKup.value=0; hKodeKup.value=''; }
                else { msgKup.innerHTML = '<i class="fa-solid fa-circle-check"></i> Diskon: ' + (f.tipe==='Nominal'?fmtRp(f.nilai):f.nilai+'%'); msgKup.className='text-[11px] mt-1 text-[#3fb950] block font-bold'; hNilaiKup.value=f.nilai; hNilaiKup.setAttribute('data-tipe', f.tipe); hKodeKup.value=f.kode; }
            } else if(k===''){ msgKup.className='hidden'; hNilaiKup.value=0; hKodeKup.value=''; } 
            else { msgKup.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Kode invalid.'; msgKup.className='text-[11px] mt-1 text-[#f85149] block font-bold'; hNilaiKup.value=0; hKodeKup.value=''; }
            calcJasaTanam();
        });

        inMtr.addEventListener('input', function(){ let v = parseFloat(this.value)||0; if(v>0) contMtr.classList.remove('hidden'); else contMtr.classList.add('hidden'); calcJasaTanam(); });
        selMtr.addEventListener('change', calcJasaTanam);
        togDis.addEventListener('change', function(){ if(this.checked) contDis.classList.remove('hidden'); else { contDis.classList.add('hidden'); inDisP.value=0; inDisN.value=0; } calcJasaTanam(); });
        rTipeDis.forEach(r => r.addEventListener('change', function(){ if(this.value==='persen'){ wpDisP.classList.remove('hidden'); wpDisN.classList.add('hidden'); inDisN.value=0; } else { wpDisP.classList.add('hidden'); wpDisN.classList.remove('hidden'); inDisP.value=0; } calcJasaTanam(); }));
        inDisP.addEventListener('input', function(){ let v=parseFloat(this.value)||0; if(v>100)this.value=100; calcJasaTanam(); });
        
        rAmbil.forEach(r => r.addEventListener('change', function(){ if(this.value==='dikirim') contOng.classList.remove('hidden'); else { contOng.classList.add('hidden'); inOng.value=0; } calcJasaTanam(); }));
        rBayar.forEach(r => r.addEventListener('change', function(){ if(this.value==='dp') contDp.classList.remove('hidden'); else { contDp.classList.add('hidden'); inDp.value=0; } calcJasaTanam(); }));

        function selectBarisTanam(el, id_b) {
            let ov = el.querySelector('.check-overlay');
            if(setBarisTanam.has(id_b)) { setBarisTanam.delete(id_b); el.classList.remove('border-[#58a6ff]'); ov.classList.remove('flex'); ov.classList.add('hidden'); } 
            else { setBarisTanam.add(id_b); el.classList.add('border-[#58a6ff]'); ov.classList.remove('hidden'); ov.classList.add('flex'); }
            calcJasaTanam();
        }

        function calcJasaTanam() {
            let qFull = setBarisTanam.size; document.getElementById('h_baris_tanam_list').value = Array.from(setBarisTanam).join(',');
            let vMtr = parseFloat(inMtr.value)||0; let opt = selMtr.options[selMtr.selectedIndex]; let maxM = parseFloat(opt.getAttribute('data-max'))||0;
            if(vMtr > maxM && maxM > 0) { inMtr.value = maxM; vMtr = maxM; }

            let tBibitF = qFull * cfgBibit; let tJasaF = qFull * cfgJasa;
            let tBibitM = (vMtr/12) * cfgBibit; let tJasaM = (vMtr/12) * cfgJasa;
            let tBibit = tBibitF + tBibitM; let tJasa = tJasaF + tJasaM; let sub = tBibit + tJasa;

            document.getElementById('r_qty').innerText = qFull + ' baris'; document.getElementById('r_mtr').innerText = vMtr + 'm';
            document.getElementById('r_bibit').innerText = fmtRp(tBibit); document.getElementById('r_jasa').innerText = fmtRp(tJasa);
            document.getElementById('r_sub').innerText = fmtRp(sub);

            let dMan = 0; if(togDis.checked) dMan = (document.querySelector('input[name="tipe_diskon"]:checked').value==='persen') ? sub * ((parseFloat(inDisP.value)||0)/100) : unfRp(inDisN.value);
            let dKup = 0; let valK = parseFloat(hNilaiKup.value)||0; if(valK>0) dKup = (hNilaiKup.getAttribute('data-tipe')==='Persentase') ? sub * (valK/100) : valK;
            let tDis = dMan + dKup;
            if(tDis>0){ document.getElementById('r_row_dis').classList.remove('hidden'); document.getElementById('r_dis').innerText = '- ' + fmtRp(tDis); } else document.getElementById('r_row_dis').classList.add('hidden');

            let o = unfRp(inOng.value);
            if(o>0){ document.getElementById('r_row_ong').classList.remove('hidden'); document.getElementById('r_ong').innerText = '+ ' + fmtRp(o); } else document.getElementById('r_row_ong').classList.add('hidden');

            let fnl = sub - tDis + o; if(fnl<0) fnl = 0; document.getElementById('r_final').innerText = fmtRp(fnl);
            
            let tipeB = document.querySelector('input[name="bayar"]:checked').value;
            if(tipeB === 'dp') { 
                let vDp = unfRp(inDp.value); 
                if(vDp > fnl) { inDp.value = new Intl.NumberFormat('id-ID').format(fnl); vDp = fnl; }
                document.getElementById('r_row_dp').classList.remove('hidden'); document.getElementById('r_row_sisa').classList.remove('hidden');
                document.getElementById('r_dp').innerText = fmtRp(vDp); document.getElementById('r_sisa').innerText = fmtRp(fnl - vDp);
            } else {
                document.getElementById('r_row_dp').classList.add('hidden'); document.getElementById('r_row_sisa').classList.add('hidden');
            }
        }
    </script>

    <?php elseif($tab == 'data'): ?>
    <?php
        $q_data = mysqli_query($conn, "SELECT no_order, nama_customer, no_hp, lokasi_sawah, tgl_tanam, status, alamat, keterangan,
            SUM(harga_dasar) as total_bibit, SUM(biaya_jasa) as total_jasa, SUM(total_harga) as total_f,
            SUM(dp_dibayar) as total_dp, SUM(ongkir) as total_ongkir, SUM(panjang_m) as total_meter,
            SUM((harga_dasar + biaya_jasa) * (diskon_persen/100) + diskon_nominal) as total_diskon,
            SUM(biaya_tambahan) as total_biaya_tambahan, MAX(ket_biaya_tambahan) as max_ket_biaya_tambahan,
            GROUP_CONCAT(id_baris SEPARATOR ', ') as baris_gabung, MIN(tgl_booking) as tgl_booking_min
            FROM order_bibit WHERE tipe_order='Jasa Tanam' GROUP BY no_order ORDER BY id DESC");
            
        $js_order_data = [];
    ?>
    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-5 rounded-xl shadow-sm overflow-hidden">
        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-5">Data Order Jasa Tanam Padi</h2>
        
            <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchOrderJasa" placeholder="Cari nama atau nomor HP customer..." class="w-full pl-9 pr-4 py-2 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#58a6ff] text-[13px] shadow-sm">
            </div>
            <div class="w-full md:w-64 relative">
                <select id="filterStatusJasa" class="w-full appearance-none px-4 py-2 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#58a6ff] cursor-pointer text-[13px] shadow-sm font-medium">
                    <option value="all">Semua Status</option>
                    <option value="Booking">Booking</option>
                    <option value="DP">DP</option>
                    <option value="Lunas">Lunas</option>
                    <option value="Persiapan">Persiapan</option>
                    <option value="Tanam">Tanam</option>
                    <option value="Selesai">Selesai</option>
                    <option value="Batal">Batal</option>
                </select>
                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
            </div>
        </div>

        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="bg-gray-50 dark:bg-[#0d1117] border-b border-gray-200 dark:border-[#30363d]">
                    <tr class="text-[10px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">
                        <th class="py-3.5 px-3 w-16 text-center">NO. ORDER</th><th class="py-3.5 px-3">CUSTOMER</th>
                        <th class="py-3.5 px-3">LOKASI SAWAH</th><th class="py-3.5 px-3 text-center">BARIS</th>
                        <th class="py-3.5 px-3">TGL TANAM</th><th class="py-3.5 px-3 text-right">BIAYA BIBIT</th>
                        <th class="py-3.5 px-3 text-right">BIAYA JASA</th><th class="py-3.5 px-3 text-right">DISKON/KUPON</th>
                        <th class="py-3.5 px-3 text-right">ONGKIR</th><th class="py-3.5 px-3 text-center">TOTAL</th>
                        <th class="py-3.5 px-3 text-center">DP</th><th class="py-3.5 px-3 text-center">SISA BAYAR</th>
                        <th class="py-3.5 px-3 text-center">STATUS</th><th class="py-3.5 px-3 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-[12px] bg-white dark:bg-[#161b22]">
                    <?php if(mysqli_num_rows($q_data) > 0): ?>
                        <?php while($jt = mysqli_fetch_assoc($q_data)): 
                            $is_highlight = (isset($_GET['highlight']) && $_GET['highlight'] == $jt['no_order']);
                            $kelas_blink = $is_highlight ? 'border-l-4 border-[#58a6ff] efek-kedip-biru' : '';
                            
                            $no_order_parts = explode('-', $jt['no_order']);
                            $no_order_html = $no_order_parts[0].'-<br>'.$no_order_parts[1].'-<br>'.$no_order_parts[2];
                            
                            $tgl = strtotime($jt['tgl_tanam']);
                            $tgl_html = date('d M', $tgl).'<br><span class="text-gray-500 dark:text-[#8b949e]">'.date('Y', $tgl).'</span>';
                            
                            $qty_baris = round($jt['total_meter'] / 12, 1);
                            $b_bibit_k = number_format($cfg_bibit / 1000, 0).'k';
                            $b_jasa_k = number_format($cfg_jasa / 1000000, 1).'jt';
                            
                            $diskon = (int)$jt['total_diskon']; $ongkir = (int)$jt['total_ongkir'];
                            $dp = (int)$jt['total_dp']; $sisa = $jt['total_f'] - $dp;
                            
                            $txt_diskon = $diskon > 0 ? '<span class="text-[#f85149] font-bold">-Rp '.number_format($diskon,0,',','.').'</span>' : '-';
                            $txt_ongkir = $ongkir > 0 ? '<span class="text-[#d29922] font-bold">+Rp '.number_format($ongkir,0,',','.').'</span>' : '-';
                            $txt_dp = $dp > 0 ? 'Rp '.number_format($dp,0,',','.') : '-';
                            $txt_sisa = $sisa <= 0 ? '<span class="text-gray-400">Lunas</span>' : 'Rp '.number_format($sisa,0,',','.');
                            
                            $st = strtolower($jt['status']);
                            $st_color = 'text-gray-500 border-gray-500'; 
                            if($st=='booking'||$st=='dp') $st_color = 'text-[#d29922] border-[#d29922]/30 bg-[#d29922]/10';
                            if($st=='lunas'||$st=='tanam'||$st=='persiapan') $st_color = 'text-[#f85149] border-[#f85149]/30 bg-[#f85149]/10';
                            if($st=='selesai'||$st=='diambil') $st_color = 'text-[#3fb950] border-[#3fb950]/30 bg-[#3fb950]/10';

                            // Map Data JSON untuk Konsumsi Modal
                            $js_order_data[$jt['no_order']] = [
                                'no_order' => $jt['no_order'], 'nama' => $jt['nama_customer'], 'hp' => $jt['no_hp'],
                                'alamat' => $jt['alamat'] ?: '-', 'sawah' => $jt['lokasi_sawah'], 'baris' => $qty_baris . " (Baris: #".$jt['baris_gabung'].")",
                                'tgl_b' => date('d M Y', strtotime($jt['tgl_booking_min'])),
                                'tgl_p' => date('d M Y', strtotime($jt['tgl_tanam'])),
                                'tgl_t' => date('d M Y', strtotime($jt['tgl_tanam'])),
                                'total' => 'Rp ' . number_format($jt['total_f'], 0, ',', '.'),
                                'keterangan' => $jt['keterangan'] ?: '-',
                                'biaya_tambahan' => (int)$jt['max_biaya_tambahan'],
                                'ket_biaya_tambahan' => $jt['max_ket_biaya_tambahan'],
                                'baris_asli' => explode(', ', $jt['baris_gabung'])
                            ];
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors baris-order-jasa <?= $kelas_blink ?>" data-name="<?= strtolower(htmlspecialchars($jt['nama_customer'])) ?>" data-phone="<?= htmlspecialchars($jt['no_hp']) ?>" data-status="<?= $jt['status'] ?>">
                            <td class="py-3.5 px-3 text-center font-bold text-gray-500 dark:text-[#8b949e] text-[10px] leading-tight"><?= $no_order_html ?></td>
                            <td class="py-3.5 px-3"><p class="font-bold text-gray-900 dark:text-white mb-0.5 whitespace-nowrap"><?= htmlspecialchars($jt['nama_customer']) ?></p><p class="text-[10px] text-gray-500 dark:text-[#8b949e]"><?= htmlspecialchars($jt['no_hp']) ?></p></td>
                            <td class="py-3.5 px-3 text-gray-700 dark:text-[#c9d1d9] max-w-[120px] truncate" title="<?= htmlspecialchars($jt['lokasi_sawah']) ?>"><?= htmlspecialchars($jt['lokasi_sawah']) ?></td>
                            <td class="py-3.5 px-3 text-center"><span class="bg-[#2ea043] text-white font-bold px-2 py-1 rounded text-[11px]"><?= str_replace('.0', '', $qty_baris) ?></span></td>
                            <td class="py-3.5 px-3 font-medium text-gray-700 dark:text-[#c9d1d9] text-[11px] leading-tight"><?= $tgl_html ?></td>
                            
                            <td class="py-3.5 px-3 text-right leading-tight">
                                <span class="text-gray-500 dark:text-[#8b949e] text-[10px] block mb-0.5"><?= $qty_baris ?> &times; Rp <?= $b_bibit_k ?></span>
                                <span class="font-bold text-gray-900 dark:text-white whitespace-nowrap">Rp <?= number_format($jt['total_bibit'], 0, ',', '.') ?></span>
                            </td>
                            <td class="py-3.5 px-3 text-right leading-tight">
                                <span class="text-gray-500 dark:text-[#8b949e] text-[10px] block mb-0.5"><?= $qty_baris ?> &times; Rp <?= $b_jasa_k ?></span>
                                <span class="font-bold text-gray-900 dark:text-white whitespace-nowrap">Rp <?= number_format($jt['total_jasa'], 0, ',', '.') ?></span>
                            </td>
                            
                            <td class="py-3.5 px-3 text-right whitespace-nowrap"><?= $txt_diskon ?></td>
                            <td class="py-3.5 px-3 text-right whitespace-nowrap"><?= $txt_ongkir ?></td>
                            
                            <td class="py-3.5 px-3 text-center leading-tight">
                                <span class="text-gray-900 dark:text-white font-bold block mb-0.5 text-[10px]">Rp</span>
                                <span class="font-bold text-gray-900 dark:text-white text-[13px] whitespace-nowrap"><?= number_format($jt['total_f'], 0, ',', '.') ?></span>
                            </td>
                            
                            <td class="py-3.5 px-3 text-center text-gray-700 dark:text-[#c9d1d9] whitespace-nowrap"><?= $txt_dp ?></td>
                            <td class="py-3.5 px-3 text-center font-bold text-gray-900 dark:text-white whitespace-nowrap"><?= $txt_sisa ?></td>
                            
                            <td class="py-3.5 px-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize whitespace-nowrap <?= $st_color ?>"><?= $jt['status'] ?></span>
                            </td>
                            
                            <td class="py-3.5 px-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="bukaModalDetailJasa('<?= $jt['no_order'] ?>')" class="w-6 h-6 rounded flex items-center justify-center text-[#58a6ff] hover:bg-[#58a6ff]/10 transition-colors" title="Lihat Detail"><i class="fa-regular fa-eye"></i></button>
                                    <a href="cetak/invoice-jasa-tanam.php?no_order=<?= $jt['no_order'] ?>" target="_blank" class="w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#30363d] transition-colors" title="Cetak Invoice"><i class="fa-solid fa-print"></i></a>
                                    <?php
                                        $st_aktif = $jt['status'];
                                        $warna_teks_dd = 'text-gray-900 dark:text-white';
                                        if($st_aktif=='Booking'||$st_aktif=='DP') $warna_teks_dd = 'text-[#d29922] font-bold';
                                        if($st_aktif=='Lunas'||$st_aktif=='Tanam'||$st_aktif=='Persiapan') $warna_teks_dd = 'text-[#f85149] font-bold';
                                        if($st_aktif=='Selesai') $warna_teks_dd = 'text-[#3fb950] font-bold';
                                        if($st_aktif=='Batal') $warna_teks_dd = 'text-[#f85149] font-bold';

                                        // LOGIKA FLEKSIBEL: Buka semua opsi operasional setelah Lunas
                                        $opt_html = '<option value="'.$st_aktif.'" selected class="'.$warna_teks_dd.'">'.$st_aktif.'</option>';
                                        
                                        if ($st_aktif == 'Booking' || $st_aktif == 'DP') {
                                            $opt_html .= '<option value="Lunas" class="text-gray-900 dark:text-white">Lunas</option>';
                                            $opt_html .= '<option value="Batal" class="text-[#f85149] font-bold">Batal</option>';
                                        } 
                                        // JIKA SUDAH LUNAS / TAHAP OPERASIONAL: Bebas pilih Persiapan, Tanam, atau Selesai
                                        elseif (in_array($st_aktif, ['Lunas', 'Persiapan', 'Tanam'])) {
                                            if ($st_aktif != 'Lunas') $opt_html .= '<option value="Lunas" class="text-gray-900 dark:text-white">Lunas</option>';
                                            if ($st_aktif != 'Persiapan') $opt_html .= '<option value="Persiapan" class="text-gray-900 dark:text-white">Persiapan</option>';
                                            if ($st_aktif != 'Tanam') $opt_html .= '<option value="Tanam" class="text-gray-900 dark:text-white">Tanam</option>';
                                            if ($st_aktif != 'Selesai') $opt_html .= '<option value="Selesai" class="text-[#3fb950] font-bold">Selesai</option>';
                                            $opt_html .= '<option value="Batal" class="text-[#f85149] font-bold">Batal</option>';
                                        }
                                    ?>
                                    <div class="relative group">
                                        <select onchange="konfirmasiUbahStatus(this, '<?= $jt['no_order'] ?>', '<?= $jt['status'] ?>')" class="appearance-none bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] <?= $warna_teks_dd ?> text-[11px] rounded px-2 py-1 pr-6 focus:outline-none focus:border-[#58a6ff] cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-[#161b22]" <?= ($st_aktif == 'Selesai' || $st_aktif == 'Batal') ? 'disabled' : '' ?>>
                                            <?= $opt_html ?>
                                        </select>
                                        <?php if($st_aktif != 'Selesai' && $st_aktif != 'Batal'): ?>
                                        <i class="fa-solid fa-chevron-down absolute right-1.5 top-1/2 transform -translate-y-1/2 text-[9px] text-gray-500 pointer-events-none"></i>
                                        <?php endif; ?>
                                    </div>

                                    <a href="?page=jasa-tanam&tab=data&action=hapus_permanen&no_order=<?= $jt['no_order'] ?>" onclick="return confirm('Hapus Kontrak Kerja ini secara permanen? Stok meteran lahan yang aktif akan dilepas kembali.')" class="w-6 h-6 rounded flex items-center justify-center text-[#f85149] hover:bg-[#f85149]/10 transition-colors" title="Hapus Order"><i class="fa-regular fa-trash-can"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="14" class="text-center py-10 text-gray-500 italic">Belum ada kontrak Jasa Tanam di dalam tabel gabungan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if($tab == 'data' || $tab == 'aktif'): ?>
    <div id="modal-detail-jasa" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm">
        <div class="bg-[#161b22] border border-[#30363d] rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden flex flex-col text-white">
            <div class="px-5 py-4 border-b border-[#30363d] flex justify-between items-center bg-[#0d1117]">
                <h3 class="text-[15px] font-bold flex items-center"><i class="fa-regular fa-eye mr-2 text-[#58a6ff]"></i> Detail Order - <span id="m_no_order" class="text-[#58a6ff] ml-1"></span></h3>
                <button onclick="tutupModalDetailJasa()" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
                
                <!-- Action Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <button onclick="bukaTambahBarisModal()" class="bg-[#21262d] border border-[#30363d] text-[#1f6feb] px-3 py-2.5 rounded-lg text-[12px] font-bold flex justify-center items-center gap-2 hover:bg-[#30363d] transition-colors"><i class="fa-solid fa-plus"></i> Tambah Lahan</button>
                    <button onclick="bukaUbahVolumeModal()" class="bg-[#21262d] border border-[#30363d] text-[#3fb950] px-3 py-2.5 rounded-lg text-[12px] font-bold flex justify-center items-center gap-2 hover:bg-[#30363d] transition-colors"><i class="fa-solid fa-arrows-up-down"></i> Ubah Volume</button>
                    <button onclick="bukaGantiBarisModal()" class="bg-[#21262d] border border-[#30363d] text-[#d29922] px-3 py-2.5 rounded-lg text-[12px] font-bold flex justify-center items-center gap-2 hover:bg-[#30363d] transition-colors"><i class="fa-solid fa-rotate"></i> Tukar Baris</button>
                    <button onclick="bukaEditJasaTanamModal()" class="bg-[#21262d] border border-[#30363d] text-[#58a6ff] px-3 py-2.5 rounded-lg text-[12px] font-bold flex justify-center items-center gap-2 hover:bg-[#30363d] transition-colors"><i class="fa-regular fa-pen-to-square"></i> Edit Order</button>
                </div>

                <div class="bg-[#0d1117] border border-[#30363d] p-4 rounded-lg space-y-3">
                    <h4 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Informasi Customer</h4>
                    <div class="grid grid-cols-2 gap-3 text-[13px]">
                        <div><p class="text-gray-500 text-[10px]">Nama</p><p id="m_nama" class="font-bold"></p></div>
                        <div><p class="text-gray-500 text-[10px]">No. HP</p><p id="m_hp" class="font-bold"></p></div>
                        <div class="col-span-2"><p class="text-gray-500 text-[10px]">Alamat Tinggal</p><p id="m_alamat" class="text-gray-300"></p></div>
                        <div class="col-span-2"><p class="text-gray-500 text-[10px]">Lokasi Area Sawah</p><p id="m_sawah" class="text-gray-300 font-semibold text-[#58a6ff]"></p></div>
                    </div>
                </div>
                <div class="bg-[#0d1117] border border-[#30363d] p-4 rounded-lg">
                    <div class="grid grid-cols-4 gap-2 text-center text-[11px]">
                        <div><p class="text-gray-500 text-[10px] mb-1">Vol Lahan</p><p id="m_baris" class="font-bold text-[13px] text-[#3fb950]"></p></div>
                        <div><p class="text-gray-500 text-[10px] mb-1">Tgl Booking</p><p id="m_tgl_b" class="font-medium text-gray-300"></p></div>
                        <div><p class="text-gray-500 text-[10px] mb-1">Tgl Persiapan</p><p id="m_tgl_p" class="font-medium text-gray-300"></p></div>
                        <div><p class="text-gray-500 text-[10px] mb-1">Rencana Tanam</p><p id="m_tgl_t" class="font-medium text-gray-300 font-bold text-[#d29922]"></p></div>
                    </div>
                </div>
                <div class="bg-[#0d1117] border border-[#30363d] p-4 rounded-lg"><p class="text-gray-500 text-[10px] mb-1">Catatan Tambahan Pekerjaan</p><p id="m_keterangan" class="text-[12px] text-gray-300 italic"></p></div>
                <div class="border border-green-500/30 rounded-lg overflow-hidden">
                    <div class="bg-green-500/10 p-3 flex justify-between items-center text-[13px] border-b border-green-500/20"><span class="font-bold text-gray-300">Rincian Faktur Keuangan</span></div>
                    <div class="p-4 bg-[#0d1117] flex justify-between items-center"><span class="text-[12px] text-gray-400">Subtotal Terpadu</span><span id="m_subtotal" class="text-[13px] font-bold text-gray-300"></span></div>
                    <div class="p-4 bg-[#0d1117] border-t border-[#30363d] flex justify-between items-center"><span class="text-[13px] font-bold text-green-400">Total Akhir</span><span id="m_total" class="text-base font-bold text-[#3fb950]"></span></div>
                </div>
            </div>
            <div class="p-4 bg-[#0d1117] border-t border-[#30363d] flex justify-end"><button onclick="tutupModalDetailJasa()" class="w-full bg-[#21262d] hover:bg-[#30363d] text-white py-2 rounded-md text-[13px] font-bold transition-colors">Tutup</button></div>
        </div>
    </div>

    <div id="modal-edit-jasa" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col text-gray-800 dark:text-gray-300">
            <form method="POST" action="">
                <input type="hidden" name="edit_no_order" id="eo_no_order">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117]"><h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-pen-to-square mr-2 text-[#58a6ff]"></i> Edit Kontrak Jasa Tanam</h3><button type="button" onclick="tutupEditJasaTanamModal()" class="text-gray-500 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button></div>
                <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Nama Customer *</label><input type="text" name="edit_nama" id="form_eo_nama" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none"></div>
                    <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">No. HP *</label><input type="text" name="edit_hp" id="form_eo_hp" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none"></div>
                    <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Alamat Rumah *</label><input type="text" name="edit_alamat" id="form_eo_alamat" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none"></div>
                    <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Lokasi Sawah *</label><input type="text" name="edit_sawah" id="form_eo_sawah" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none"></div>
                    <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Tanggal Rencana Tanam *</label><input type="date" name="edit_tgl_tanam" id="form_eo_tgl_t" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none [color-scheme:light] dark:[color-scheme:dark]"></div>
                    <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Catatan Keterangan</label><textarea name="edit_keterangan" id="form_eo_ket" rows="2" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none resize-none"></textarea></div>
                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200 dark:border-[#30363d]">
                        <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1 text-[#d29922]">Biaya Tambahan</label><input type="text" name="edit_biaya_tambahan" id="form_eo_biaya_tambahan" placeholder="Cth: 50000" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none"></div>
                        <div><label class="block text-[11px] font-bold uppercase text-gray-500 mb-1 text-[#d29922]">Keterangan Biaya</label><input type="text" name="edit_ket_biaya_tambahan" id="form_eo_ket_biaya_tambahan" placeholder="Cth: Pupuk Ekstra" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none"></div>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex gap-3"><button type="button" onclick="tutupEditJasaTanamModal()" class="w-1/3 bg-gray-200 dark:bg-[#21262d] text-gray-700 dark:text-white py-2 rounded text-[13px] font-bold">Batal</button><button type="submit" name="update_jasa_tanam" class="w-2/3 bg-[#1f6feb] text-white py-2 rounded text-[13px] font-bold shadow">Simpan Perubahan</button></div>
            </form>
        </div>
    </div>

    <div id="modal-ganti-baris" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col text-gray-800 dark:text-gray-300">
            <form method="POST" action="">
                <input type="hidden" name="action" value="tukar_baris">
                <input type="hidden" name="no_order" id="gb_no_order">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117]">
                    <h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-rotate mr-2 text-[#d29922]"></i> Ganti Baris Bibit</h3>
                    <button type="button" onclick="tutupGantiBarisModal()" class="text-gray-500 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Baris Lama (Saat ini)</label>
                        <select name="old_id_baris" id="gb_old_id" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none">
                        </select>
                    </div>
                    <div class="flex justify-center text-gray-400"><i class="fa-solid fa-arrow-down"></i></div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Baris Baru (Tujuan)</label>
                        <select name="new_id_baris" id="gb_new_id" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none">
                        </select>
                    </div>
                    <p class="text-[10px] text-gray-500 italic mt-2">*Pergantian baris ini otomatis me-refund stok baris lama dan memotong stok baris baru. Pastikan memilih baris dengan kapasitas yang sesuai!</p>
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex gap-3">
                    <button type="button" onclick="tutupGantiBarisModal()" class="w-1/3 bg-gray-200 dark:bg-[#21262d] text-gray-700 dark:text-white py-2 rounded text-[13px] font-bold">Batal</button>
                    <button type="submit" class="w-2/3 bg-[#d29922] text-white py-2 rounded text-[13px] font-bold shadow">Simpan Tukar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-ubah-volume" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col text-gray-800 dark:text-gray-300">
            <form method="POST" action="">
                <input type="hidden" name="action" value="ubah_volume">
                <input type="hidden" name="no_order" id="uv_no_order">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117]">
                    <h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-arrows-up-down mr-2 text-[#3fb950]"></i> Ubah Volume Lahan</h3>
                    <button type="button" onclick="tutupUbahVolumeModal()" class="text-gray-500 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Pilih Baris Lahan</label>
                        <select name="id_baris" id="uv_id_baris" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none">
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1 text-[#3fb950]">Volume Baru (Meter)</label>
                        <input type="number" step="0.1" min="0.1" max="12" name="volume_baru" id="uv_volume_baru" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none" placeholder="Cth: 6">
                    </div>
                    <p class="text-[10px] text-gray-500 italic mt-2">*Masukkan sisa meteran mutlak yang baru. Jika ingin mengembalikan/mengurangi, ketik meteran yang tersisa. Sistem otomatis menambah/memotong stok asli.</p>
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex gap-3">
                    <button type="button" onclick="tutupUbahVolumeModal()" class="w-1/3 bg-gray-200 dark:bg-[#21262d] text-gray-700 dark:text-white py-2 rounded text-[13px] font-bold">Batal</button>
                    <button type="submit" class="w-2/3 bg-[#3fb950] text-white py-2 rounded text-[13px] font-bold shadow">Ubah Volume</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-tambah-baris" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col text-gray-800 dark:text-gray-300">
            <form method="POST" action="">
                <input type="hidden" name="action" value="tambah_baris">
                <input type="hidden" name="no_order" id="tb_no_order">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117]">
                    <h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-plus mr-2 text-[#1f6feb]"></i> Tambah Baris Lahan</h3>
                    <button type="button" onclick="tutupTambahBarisModal()" class="text-gray-500 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Pilih Baris Kosong</label>
                        <select name="new_id_baris" id="tb_id_baris" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none">
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1 text-[#1f6feb]">Meter Tambahan</label>
                        <input type="number" step="0.1" min="0.1" max="12" name="meter_tambahan" id="tb_meter_tambahan" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none" placeholder="Cth: 5">
                    </div>
                    <p class="text-[10px] text-gray-500 italic mt-2">*Penambahan ini akan disisipkan ke dalam nota pesanan ini. Ongkir & biaya tambahan akan dihitung ulang secara proporsional otomatis.</p>
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex gap-3">
                    <button type="button" onclick="tutupTambahBarisModal()" class="w-1/3 bg-gray-200 dark:bg-[#21262d] text-gray-700 dark:text-white py-2 rounded text-[13px] font-bold">Batal</button>
                    <button type="submit" class="w-2/3 bg-[#1f6feb] text-white py-2 rounded text-[13px] font-bold shadow">Tambah Baris</button>
                </div>
            </form>
        </div>
    </div>
    <div id="modal-update-progres" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col text-gray-800 dark:text-gray-300">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_progres_modal">
                <input type="hidden" name="up_no_order" id="up_no_order">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117]"><h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-chart-line mr-2 text-[#8957e5]"></i> Update Progres Penanaman</h3><button type="button" onclick="tutupUpdateProgresModal()" class="text-gray-500 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button></div>
                <div class="p-5 space-y-4">
                    <div class="bg-[#0d1117] p-3 rounded-lg border border-[#30363d] mb-2">
                        <p class="text-[11px] text-gray-400 text-center">Total Target Penanaman:</p>
                        <p id="up_total_meter" class="text-center font-bold text-[#3fb950] text-[16px] mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Meter Selesai Ditanam *</label>
                        <div class="relative flex items-center">
                            <input type="number" step="any" min="0" name="up_progres" id="up_progres_input" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-3 py-2 pr-12 text-[13px] focus:border-[#58a6ff] outline-none">
                            <span class="absolute right-3 text-[11px] font-bold text-gray-500 pointer-events-none">Meter</span>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex gap-3">
                    <button type="button" onclick="tutupUpdateProgresModal()" class="w-1/3 bg-gray-200 dark:bg-[#21262d] text-gray-700 dark:text-white py-2 rounded text-[13px] font-bold">Batal</button>
                    <button type="submit" class="w-2/3 bg-[#238636] hover:bg-[#2ea043] text-white py-2 rounded text-[13px] font-bold shadow transition-colors">Simpan Progres</button>
                </div>
            </form>
        </div>
    </div>


    <?php endif; ?>

    <?php if($tab == 'aktif'): ?>
    <?php
        $q_rekap = mysqli_query($conn, "
            SELECT 
                no_order, nama_customer, no_hp, lokasi_sawah, tgl_tanam, alamat, keterangan,
                MIN(tgl_booking) as tgl_booking_min, SUM(panjang_m) as total_meter, GROUP_CONCAT(id_baris SEPARATOR ', ') as baris_gabung,
                MAX(progres_tanam) as progres,
                SUM(biaya_tambahan) as total_biaya_tambahan, MAX(ket_biaya_tambahan) as max_ket_biaya_tambahan,
                SUM(total_harga) as total_belanja, 
                SUM(dp_dibayar) as total_dibayar,
                SUM(total_harga - dp_dibayar) as sisa_tagihan,
                GROUP_CONCAT(CONCAT('&bull; <span class=\"text-[#58a6ff] font-bold\">#', id_baris, '</span> <b>', varietas, '</b> (', (panjang_m + 0), 'm) <span class=\"text-[9px] uppercase font-bold ml-1 ', IF(status='lunas' OR status='Tanam' OR status='Persiapan', 'text-[#f85149]', IF(status='Selesai', 'text-[#3fb950]', 'text-[#d29922]')), '\">[', status, ']</span>') SEPARATOR '<br>') as detail_barang
            FROM order_bibit 
            WHERE tipe_order = 'Jasa Tanam' AND status NOT IN ('Selesai', 'Batal')
            GROUP BY no_order, nama_customer, no_hp 
            ORDER BY id DESC
        ");
        
        $js_order_data = [];
    ?>
    <div class="bg-[#161b22] border border-[#30363d] p-5 rounded-xl shadow-sm">
        <div class="flex justify-between items-center mb-5">
            <div>
                <h2 class="text-base font-bold text-white flex items-center"><i class="fa-solid fa-users text-[#3fb950] mr-2"></i> Daftar Transaksi Aktif</h2>
            </div>
            <div class="relative w-64">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-[13px]"></i>
                <input type="text" id="searchAktif" placeholder="Cari pelanggan..." class="w-full pl-8 pr-3 py-2 bg-[#0d1117] border border-[#30363d] text-white rounded text-[13px] focus:outline-none focus:border-[#58a6ff]">
            </div>
        </div>

        <div class="overflow-x-auto border border-[#30363d] rounded-lg">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="bg-[#0d1117] border-b border-[#30363d]">
                    <tr class="text-[10px] font-bold text-[#8b949e] uppercase tracking-wider">
                        <th class="py-4 px-4 w-12 text-center">NO</th>
                        <th class="py-4 px-4 min-w-[150px]">DATA PELANGGAN</th>
                        <th class="py-4 px-4 min-w-[200px]">BARIS BIBIT YANG DIGUNAKAN</th>
                        <th class="py-4 px-4 min-w-[150px]">PROGRES PENANAMAN</th>
                        <th class="py-4 px-4 text-center">AKUMULASI BELANJA</th>
                        <th class="py-4 px-4 text-center">TOTAL TERBAYAR</th>
                        <th class="py-4 px-4 text-center">SISA BAYAR</th>
                        <th class="py-4 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#21262d]" id="bodyAktif">
                    <?php if(mysqli_num_rows($q_rekap) > 0): $no=1; while($r = mysqli_fetch_assoc($q_rekap)): 
                        $max_baris = round($r['total_meter']/12, 1);
                        $js_order_data[$r['no_order']] = [
                            'no_order' => $r['no_order'], 'nama' => $r['nama_customer'], 'hp' => $r['no_hp'],
                            'alamat' => $r['alamat'] ?: '-', 'sawah' => $r['lokasi_sawah'], 'baris' => $max_baris . " (Baris: #".$r['baris_gabung'].")",
                            'tgl_b' => date('d M Y', strtotime($r['tgl_booking_min'])),
                            'tgl_p' => date('d M Y', strtotime($r['tgl_tanam'])),
                            'tgl_t' => date('d M Y', strtotime($r['tgl_tanam'])),
                            'total' => 'Rp ' . number_format($r['total_belanja'], 0, ',', '.'),
                            'keterangan' => $r['keterangan'] ?: '-',
                            'biaya_tambahan' => (int)$r['total_biaya_tambahan'],
                            'ket_biaya_tambahan' => $r['max_ket_biaya_tambahan'],
                            'baris_asli' => explode(', ', $r['baris_gabung'])
                        ];
                    ?>
                    <tr class="hover:bg-[#21262d] transition-colors tr-aktif cursor-pointer" onclick="if(event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && event.target.tagName !== 'A' && event.target.tagName !== 'FORM') bukaModalDetailJasa('<?= $r['no_order'] ?>')" data-search="<?= strtolower($r['nama_customer'].' '.$r['no_hp'].' '.$r['no_order']) ?>">
                        <td class="py-4 px-4 text-center text-[12px] font-bold text-[#8b949e]"><?= $no++ ?></td>
                        <td class="py-4 px-4">
                            <a href="?page=jasa-tanam&tab=data&highlight=<?= $r['no_order'] ?>" class="text-[13px] font-bold text-[#58a6ff] hover:underline mb-1 inline-block" title="Klik untuk melihat rincian di Data Order"><?= htmlspecialchars($r['nama_customer']) ?></a>
                            <p class="text-[11px] text-[#8b949e] flex items-center"><i class="fa-solid fa-phone mr-1.5 text-gray-500"></i> <?= htmlspecialchars($r['no_hp']) ?></p>
                            <p class="text-[10px] text-[#8b949e] mt-1">Tgl Tanam: <span class="font-bold text-[#d29922]"><?= formatTgl($r['tgl_tanam']) ?></span></p>
                        </td>
                        <td class="py-4 px-4 text-[11px] leading-relaxed text-[#c9d1d9] relative z-10"><?= $r['detail_barang'] ?></td>
                        
                        <td class="py-4 px-4 text-center relative z-10" onclick="event.stopPropagation()">
                            <?php 
                                $prog_m = (float)($r['progres'] ?? 0);
                                $total_m = (float)$r['total_meter'];
                                $persen = ($total_m > 0) ? round(($prog_m / $total_m) * 100) : 0;
                                if($persen > 100) $persen = 100;
                            ?>
                            <div class="flex items-center justify-center gap-3">
                                <div class="flex flex-col gap-1 items-center justify-center w-full max-w-[90px]">
                                    <span class="text-[11px] font-bold text-[#c9d1d9]"><?= $prog_m ?>m <span class="text-gray-500 font-normal">/ <?= $total_m ?>m</span></span>
                                    <div class="w-full bg-[#0d1117] rounded-full h-1.5 border border-[#30363d] overflow-hidden">
                                        <div class="bg-[#3fb950] h-1.5 rounded-full" style="width: <?= $persen ?>%"></div>
                                    </div>
                                    <span class="text-[10px] font-bold text-[#3fb950] mt-0.5"><?= $persen ?>%</span>
                                </div>
                                <button type="button" onclick="bukaUpdateProgresTable('<?= $r['no_order'] ?>', <?= $prog_m ?>, <?= $total_m ?>)" class="w-7 h-7 flex-shrink-0 bg-[#21262d] border border-[#30363d] text-[#8957e5] rounded flex justify-center items-center hover:bg-[#30363d] transition-colors" title="Update Progres">
                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                </button>
                            </div>
                        </td>
                        
                        <td class="py-4 px-4 text-center font-bold text-white text-[12px]"><?= formatRp($r['total_belanja']) ?></td>
                        <td class="py-4 px-4 text-center text-[#8b949e] text-[12px]"><?= formatRp($r['total_dibayar']) ?></td>
                        
                        <?php if($r['sisa_tagihan'] <= 0): ?>
                        <td class="py-4 px-4 text-center font-bold text-[#3fb950] text-[12px]">Lunas</td>
                        <?php else: ?>
                        <td class="py-4 px-4 text-center font-bold text-[#f85149] text-[12px]"><?= formatRp($r['sisa_tagihan']) ?></td>
                        <?php endif; ?>
                        
                        <td class="py-4 px-4 text-center">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <?php if($r['sisa_tagihan'] > 0): ?>
                                <a href="?page=jasa-tanam&action=update_status&no_order=<?= $r['no_order'] ?>&status_baru=Lunas" onclick="return confirm('Lunasi transaksi ini?')" class="w-full bg-[#d29922] hover:bg-yellow-600 text-white px-3 py-1.5 rounded-md text-[11px] font-bold shadow transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-money-bill-wave"></i> Lunasi</a>
                                <?php else: ?>
                                <a href="?page=jasa-tanam&action=update_status&no_order=<?= $r['no_order'] ?>&status_baru=Selesai" onclick="return confirm('Selesaikan/Arsipkan transaksi ini?')" class="w-full bg-[#238636] hover:bg-[#2ea043] text-white px-3 py-1.5 rounded-md text-[11px] font-bold shadow transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-check-double"></i> Selesaikan</a>
                                <?php endif; ?>
                                
                                <a href="cetak/invoice-jasa-tanam.php?no_order=<?= $r['no_order'] ?>" target="_blank" class="w-full bg-[#21262d] hover:bg-[#30363d] border border-[#30363d] text-[#c9d1d9] px-3 py-1.5 rounded-md text-[11px] font-bold shadow transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-print"></i> Cetak</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center py-8 text-[#8b949e] text-[12px]">Tidak ada transaksi aktif yang perlu diselesaikan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        const sAktif = document.getElementById('searchAktif');
        const trAktif = document.querySelectorAll('.tr-aktif');
        if(sAktif) {
            sAktif.addEventListener('input', function() {
                let v = this.value.toLowerCase();
                trAktif.forEach(tr => {
                    if(tr.getAttribute('data-search').includes(v)) tr.style.display = '';
                    else tr.style.display = 'none';
                });
            });
        }
    </script>
    <?php endif; ?>
</div>

<?php
    // Fetch ketersediaan baris kosong untuk dropdown Tukar Baris (Dibutuhkan di tab data & aktif)
    $q_baris_kosong = mysqli_query($conn, "SELECT b.id_baris, b.tersedia_m, v.nama_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.tersedia_m > 0 ORDER BY b.id_baris ASC");
    $js_baris_kosong = [];
    if($q_baris_kosong) {
        while($rb = mysqli_fetch_assoc($q_baris_kosong)) {
            $js_baris_kosong[] = ['id' => $rb['id_baris'], 'm' => (float)$rb['tersedia_m'], 'var' => $rb['nama_varietas'] ?: 'Bibit'];
        }
    }
?>
<script>
    const databaseJasaMap = <?= json_encode($js_order_data ?? []) ?>;
    const barisKosongData = <?= json_encode($js_baris_kosong) ?>;

    function bukaModalDetailJasa(noOrder) {
        let data = databaseJasaMap[noOrder];
        if(data) {
            document.getElementById('m_no_order').innerText = data.no_order;
            document.getElementById('m_nama').innerText = data.nama;
            document.getElementById('m_hp').innerText = data.hp;
            document.getElementById('m_alamat').innerText = data.alamat;
            document.getElementById('m_sawah').innerText = data.sawah;
            document.getElementById('m_baris').innerText = data.baris;
            document.getElementById('m_tgl_b').innerText = data.tgl_b;
            document.getElementById('m_tgl_p').innerText = data.tgl_p;
            document.getElementById('m_tgl_t').innerText = data.tgl_t;
            document.getElementById('m_keterangan').innerText = data.keterangan;
            document.getElementById('m_subtotal').innerText = data.total;
            document.getElementById('m_total').innerText = data.total;
            
            document.getElementById('modal-detail-jasa').classList.remove('hidden');
        }
    }
    function tutupModalDetailJasa() { document.getElementById('modal-detail-jasa').classList.add('hidden'); }

    function bukaEditJasaTanamModal() {
        let noOrder = document.getElementById('m_no_order').innerText;
        let data = databaseJasaMap[noOrder];
        if(data) {
            tutupModalDetailJasa();
            document.getElementById('eo_no_order').value = data.no_order;
            document.getElementById('form_eo_nama').value = data.nama;
            document.getElementById('form_eo_hp').value = data.hp;
            document.getElementById('form_eo_alamat').value = data.alamat;
            document.getElementById('form_eo_sawah').value = data.sawah;
            
            // Format konversi kembali ke objek tanggal input HTML
            let parts = data.tgl_p.split(' ');
            const m_map = {'Jan':'01','Feb':'02','Mar':'03','Apr':'04','Mei':'05','Jun':'06','Jul':'07','Agu':'08','Sep':'09','Okt':'10','Nov':'11','Des':'12'};
            let format_iso = `${parts[2]}-${m_map[parts[1]]}-${parts[0].padStart(2,'0')}`;
            document.getElementById('form_eo_tgl_t').value = format_iso;
            document.getElementById('form_eo_ket').value = data.keterangan === '-' ? '' : data.keterangan;
            let biaya = data.biaya_tambahan ? data.biaya_tambahan.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") : "";
            document.getElementById('form_eo_biaya_tambahan').value = biaya;
            document.getElementById('form_eo_ket_biaya_tambahan').value = data.ket_biaya_tambahan || '';
            
            document.getElementById('modal-edit-jasa').classList.remove('hidden');
        }
    }
    function tutupEditJasaTanamModal() { document.getElementById('modal-edit-jasa').classList.add('hidden'); }

    function bukaGantiBarisModal() {
        let noOrder = document.getElementById('m_no_order').innerText;
        let data = databaseJasaMap[noOrder];
        if(data) {
            tutupModalDetailJasa();
            document.getElementById('gb_no_order').value = data.no_order;
            
            let selOld = document.getElementById('gb_old_id');
            selOld.innerHTML = '';
            data.baris_asli.forEach(b => {
                let opt = document.createElement('option');
                opt.value = b; opt.innerText = "Baris #" + b;
                selOld.appendChild(opt);
            });

            let selNew = document.getElementById('gb_new_id');
            selNew.innerHTML = '<option value="">-- Pilih Baris Tujuan --</option>';
            barisKosongData.forEach(b => {
                let opt = document.createElement('option');
                opt.value = b.id; opt.innerText = "Baris #" + b.id + " (" + b.var + " - Sisa " + b.m + "m)";
                selNew.appendChild(opt);
            });

            document.getElementById('modal-ganti-baris').classList.remove('hidden');
        }
    }
    function tutupGantiBarisModal() { document.getElementById('modal-ganti-baris').classList.add('hidden'); }

    function konfirmasiUbahStatus(selectElement, noOrder, statusLama) {
            let statusBaru = selectElement.value;
            
            // Jika user memilih status yang sama, tidak perlu melakukan apa-apa
            if (statusBaru === statusLama) return;

            let pesanDialog = "Ubah status nota " + noOrder + " menjadi " + statusBaru + "?";
            
            // Berikan peringatan lebih keras untuk aksi kritikal
            if (statusBaru === 'Batal') {
                pesanDialog = "⚠️ PERHATIAN!\n\nMembatalkan nota " + noOrder + " akan me-refund (mengembalikan) meteran lahan ini ke sistem agar bisa dipesan orang lain.\n\nYakin ingin membatalkan?";
            } else if (statusBaru === 'Lunas') {
                pesanDialog = "Tandai nota " + noOrder + " sebagai Lunas? (Sisa bayar akan otomatis di-nol-kan)";
            }

            // Jika admin menekan "OK"
            if (confirm(pesanDialog)) {
                window.location.href = '?page=jasa-tanam&action=update_status&no_order=' + noOrder + '&status_baru=' + statusBaru;
            } 
            // Jika admin menekan "Cancel"
            else {
                selectElement.value = statusLama; // Kembalikan ke posisi semula
            }
        }

    // Logic Highlighter saat halaman mendarat dari radar luar
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get('highlight');
        if (highlightId) {
            window.history.replaceState({}, document.title, "?page=jasa-tanam&tab=data");
        }
    });

    // =========================================================
    // LOGIKA PENCARIAN & FILTER STATUS JASA TANAM
    // =========================================================
    const searchInputJasa = document.getElementById('searchOrderJasa'); 
    const statusFilterJasa = document.getElementById('filterStatusJasa'); 
    const rowsJasa = document.querySelectorAll('.baris-order-jasa');
    
    function bukaUbahVolumeModal() {
        let noOrder = document.getElementById('m_no_order').innerText;
        let data = databaseJasaMap[noOrder];
        if(data) {
            tutupModalDetailJasa();
            document.getElementById('uv_no_order').value = data.no_order;
            
            let selBaris = document.getElementById('uv_id_baris');
            selBaris.innerHTML = '';
            data.baris_asli.forEach(b => {
                let opt = document.createElement('option');
                opt.value = b; 
                opt.text = "Baris #" + b;
                selBaris.appendChild(opt);
            });
            document.getElementById('uv_volume_baru').value = '';
            document.getElementById('modal-ubah-volume').classList.remove('hidden');
        }
    }
    function tutupUbahVolumeModal() { document.getElementById('modal-ubah-volume').classList.add('hidden'); }

    function bukaTambahBarisModal() {
        let noOrder = document.getElementById('m_no_order').innerText;
        let data = databaseJasaMap[noOrder];
        if(data) {
            tutupModalDetailJasa();
            document.getElementById('tb_no_order').value = data.no_order;
            
            let selBaris = document.getElementById('tb_id_baris');
            selBaris.innerHTML = '<option value="">-- Pilih Baris Kosong --</option>';
            barisKosongData.forEach(b => {
                let opt = document.createElement('option');
                opt.value = b.id; opt.innerText = "Baris #" + b.id + " (" + b.var + " - Sisa " + b.m + "m)";
                selBaris.appendChild(opt);
            });
            document.getElementById('tb_meter_tambahan').value = '';
            document.getElementById('modal-tambah-baris').classList.remove('hidden');
        }
    }
    function tutupTambahBarisModal() { document.getElementById('modal-tambah-baris').classList.add('hidden'); }



    function filterTableJasa() { 
        let searchVal = searchInputJasa.value.toLowerCase(); 
        let statusVal = statusFilterJasa.value; 
        
        rowsJasa.forEach(row => { 
            let name = row.getAttribute('data-name'); 
            let phone = row.getAttribute('data-phone'); 
            let status = row.getAttribute('data-status'); 
            
            let matchSearch = name.includes(searchVal) || phone.includes(searchVal); 
            let matchStatus = (statusVal === 'all') || (status === statusVal); 
            
            if(matchSearch && matchStatus) { 
                row.style.display = ''; 
            } else { 
                row.style.display = 'none'; 
            } 
        }); 
    }
    function bukaUpdateProgresTable(noOrder, currentM, maxM) {
        document.getElementById('up_no_order').value = noOrder;
        document.getElementById('up_total_meter').innerText = maxM + " Meter";
        document.getElementById('up_progres_input').max = maxM;
        document.getElementById('up_progres_input').value = currentM;
        document.getElementById('modal-update-progres').classList.remove('hidden');
    }
    function tutupUpdateProgresModal() { document.getElementById('modal-update-progres').classList.add('hidden'); }
    

    if(searchInputJasa) searchInputJasa.addEventListener('input', filterTableJasa); 
    if(statusFilterJasa) statusFilterJasa.addEventListener('change', filterTableJasa);

    const inputBiaya = document.getElementById('form_eo_biaya_tambahan');
    if (inputBiaya) {
        inputBiaya.addEventListener('input', function(e) {
            let val = this.value.replace(/[^0-9]/g, '');
            if (val !== '') {
                this.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            } else {
                this.value = '';
            }
        });
    }
</script>

<style>
    @keyframes kedipBiru { 0%, 100% { background-color: transparent; } 50% { background-color: rgba(88, 166, 255, 0.3); } }
    .efek-kedip-biru { animation: kedipBiru 0.6s ease-in-out 3; }
    .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; } 
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
</style>