<?php
include 'components/koneksi.php';

$tgl_hari_ini = date('Y-m-d');
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard'; 

// =========================================================================
// 0. AMBIL HARGA GLOBAL DARI PENGATURAN (Sebagai Cadangan)
// =========================================================================
$cfg_bibit_global = 800000; // Harga default jika tabel belum ada
$cek_cfg = mysqli_query($conn, "SHOW TABLES LIKE 'pengaturan_sistem'");
if(mysqli_num_rows($cek_cfg) > 0) {
    $q_cfg = mysqli_query($conn, "SELECT nilai FROM pengaturan_sistem WHERE kunci='harga_bibit_global'");
    if($res = mysqli_fetch_assoc($q_cfg)) {
        $cfg_bibit_global = (int)$res['nilai'];
    }
}

// =========================================================================
// 0. AUTO-CREATE TABEL ORDER & KUPON (Tetap dipertahankan)
// =========================================================================
$cek_tabel_order = mysqli_query($conn, "SHOW TABLES LIKE 'order_bibit'");
if(mysqli_num_rows($cek_tabel_order) == 0) {
    mysqli_query($conn, "CREATE TABLE `order_bibit` (
        `id` int(11) NOT NULL AUTO_INCREMENT, `nama_customer` varchar(100) DEFAULT NULL, `no_hp` varchar(20) DEFAULT NULL,
        `alamat` text DEFAULT NULL, `id_baris` int(11) DEFAULT NULL, `panjang_m` decimal(4,1) DEFAULT NULL,
        `posisi` varchar(50) DEFAULT NULL, `varietas` varchar(100) DEFAULT NULL, `tgl_booking` date DEFAULT NULL,
        `tgl_lunas` date DEFAULT NULL, `tgl_ambil` date DEFAULT NULL, `harga_dasar` int(11) DEFAULT 0,
        `diskon_persen` decimal(5,2) DEFAULT 0, `diskon_nominal` int(11) DEFAULT 0, `ongkir` int(11) DEFAULT 0,
        `dp_dibayar` int(11) DEFAULT 0, `total_harga` int(11) DEFAULT 0, `status` varchar(20) DEFAULT 'booking',
        PRIMARY KEY (`id`)
    )");
} else {
    $cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM `order_bibit` LIKE 'harga_dasar'");
    if(mysqli_num_rows($cek_kolom) == 0) {
        mysqli_query($conn, "ALTER TABLE `order_bibit` ADD `harga_dasar` int(11) DEFAULT 0 AFTER `tgl_ambil`, ADD `diskon_persen` decimal(5,2) DEFAULT 0 AFTER `harga_dasar`, ADD `diskon_nominal` int(11) DEFAULT 0 AFTER `diskon_persen`, ADD `ongkir` int(11) DEFAULT 0 AFTER `diskon_nominal`, ADD `dp_dibayar` int(11) DEFAULT 0 AFTER `ongkir`");
    }
}

$cek_kupon = mysqli_query($conn, "SHOW TABLES LIKE 'kupon_diskon'");
if(mysqli_num_rows($cek_kupon) == 0) {
    mysqli_query($conn, "CREATE TABLE `kupon_diskon` (`id` int(11) NOT NULL AUTO_INCREMENT, `kode` varchar(50) NOT NULL, `nama` varchar(100) NOT NULL, `tipe` varchar(20) NOT NULL, `nilai` int(11) NOT NULL, `berlaku` varchar(50) DEFAULT 'Semua', `periode` varchar(100) DEFAULT NULL, `kuota` varchar(20) DEFAULT 'Unlimited', `status` varchar(20) DEFAULT 'Aktif', PRIMARY KEY (`id`))");
    mysqli_query($conn, "INSERT INTO kupon_diskon (kode, nama, tipe, nilai, berlaku, periode, kuota, status) VALUES ('BIBIT50K', 'Potongan 50rb Order Bibit', 'Nominal', 50000, 'Order Bibit', '1/1/2026 - 31/12/2026', 'Unlimited', 'Aktif')");
}

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
    function formatTgl($tgl){ 
        if(!$tgl) return '-'; $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $d = explode('-', $tgl); return (int)$d[2] . ' ' . $bulan[(int)$d[1]-1] . ' ' . $d[0];
    }
}

// =========================================================================
// 1. PROSES AKSI DATABASE
// =========================================================================

// A. SIMPAN ORDER BARU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_order'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_customer']); $hp = mysqli_real_escape_string($conn, $_POST['no_hp']); $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $tgl_booking = mysqli_real_escape_string($conn, $_POST['tgl_booking']); $tgl_ambil = !empty($_POST['tgl_ambil']) ? "'".mysqli_real_escape_string($conn, $_POST['tgl_ambil'])."'" : "NULL";
    
    $is_diskon = isset($_POST['is_diskon']) ? true : false;
    $tipe_diskon = isset($_POST['tipe_diskon']) ? $_POST['tipe_diskon'] : 'persen'; 
    $nominal_diskon_p = isset($_POST['nominal_diskon_persen']) ? (float)$_POST['nominal_diskon_persen'] : 0;
    $nominal_diskon_n = isset($_POST['nominal_diskon_rp']) ? (float)str_replace('.', '', $_POST['nominal_diskon_rp']) : 0;
    
    $diskon_p = ($is_diskon && $tipe_diskon == 'persen') ? $nominal_diskon_p : 0; 
    $diskon_n = ($is_diskon && $tipe_diskon == 'nominal') ? $nominal_diskon_n : 0;
    
    // Proses Kupon
    $kode_dipakai = isset($_POST['kode_kupon_dipakai']) ? mysqli_real_escape_string($conn, $_POST['kode_kupon_dipakai']) : '';
    if($kode_dipakai != '') {
        $q_kup = mysqli_query($conn, "SELECT id, kuota, tipe, nilai FROM kupon_diskon WHERE kode='$kode_dipakai'");
        if($kup = mysqli_fetch_assoc($q_kup)) {
            if($kup['tipe'] == 'Persentase') { $diskon_p += (float)$kup['nilai']; } 
            else { $diskon_n += (float)$kup['nilai']; }
            
            $k_val = $kup['kuota'];
            if(strpos($k_val, '/') !== false) {
                list($terpakai, $max) = explode('/', $k_val);
                $terpakai = (int)$terpakai + 1; $status_k = ($terpakai >= (int)$max) ? 'Habis' : 'Aktif';
                mysqli_query($conn, "UPDATE kupon_diskon SET kuota='$terpakai/$max', status='$status_k' WHERE id='{$kup['id']}'");
            }
        }
    }

    $ongkir = (isset($_POST['ambil']) && $_POST['ambil'] == 'dikirim') ? (int)str_replace('.', '', $_POST['nominal_ongkir']) : 0;
    $dp = (isset($_POST['bayar']) && $_POST['bayar'] == 'dp') ? (int)str_replace('.', '', $_POST['nominal_dp']) : 0;

    $baris_full = isset($_POST['baris_full_list']) && $_POST['baris_full_list'] != '' ? explode(',', $_POST['baris_full_list']) : [];
    $meter_tambahan = isset($_POST['meter_tambahan']) ? (float)$_POST['meter_tambahan'] : 0;
    $id_baris_tambahan = isset($_POST['id_baris_tambahan']) ? (int)$_POST['id_baris_tambahan'] : 0;

    if(count($baris_full) == 0 && $meter_tambahan <= 0) { echo "<script>alert('Gagal! Pilih baris atau meter tambahan.'); window.history.back();</script>"; exit; }

    $total_harga_dasar_semua = 0; $items_to_insert = [];

    // Proses Baris Full
    foreach($baris_full as $id_b) {
        $q = mysqli_query($conn, "SELECT v.nama_varietas, v.harga_jual FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.id_baris='$id_b'");
        if($d = mysqli_fetch_assoc($q)) {
            $harga_spesifik = (int)$d['harga_jual'];
            $harga_dasar = ($harga_spesifik > 0) ? $harga_spesifik : $cfg_bibit_global;
            
            $total_harga_dasar_semua += $harga_dasar;
            $items_to_insert[] = ['id'=>$id_b, 'm'=>12.0, 'pos'=>'0m - 12m', 'var'=>$d['nama_varietas'], 'hd'=>$harga_dasar];
            mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m=0, status='habis' WHERE id_baris='$id_b'");
        }
    }
    
    // Proses Meter Tambahan
    if($meter_tambahan > 0 && $id_baris_tambahan > 0) {
        $q = mysqli_query($conn, "SELECT b.tersedia_m, v.nama_varietas, v.harga_jual FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id WHERE b.id_baris='$id_baris_tambahan'");
        if($d = mysqli_fetch_assoc($q)) {
            $harga_spesifik = (int)$d['harga_jual'];
            $harga_per_baris = ($harga_spesifik > 0) ? $harga_spesifik : $cfg_bibit_global;
            
            $harga_dasar = round(($meter_tambahan / 12) * $harga_per_baris);
            
            $start_m = 12.0 - (float)$d['tersedia_m']; $end_m = $start_m + $meter_tambahan;
            $total_harga_dasar_semua += $harga_dasar;
            $items_to_insert[] = ['id'=>$id_baris_tambahan, 'm'=>$meter_tambahan, 'pos'=>"{$start_m}m - {$end_m}m", 'var'=>$d['nama_varietas'], 'hd'=>$harga_dasar];
            $sisa = (float)$d['tersedia_m'] - $meter_tambahan; $st_upd = ($sisa <= 0) ? ", status='habis'" : "";
            mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m=$sisa $st_upd WHERE id_baris='$id_baris_tambahan'");
        }
    }

    foreach($items_to_insert as $it) {
        $rasio = ($total_harga_dasar_semua > 0) ? ($it['hd'] / $total_harga_dasar_semua) : 1;
        $d_persen = $diskon_p; $d_nom = round($diskon_n * $rasio); $ongk = round($ongkir * $rasio);
        
        $potongan_p = $it['hd'] * ($d_persen / 100);
        $final = $it['hd'] - $potongan_p - $d_nom + $ongk;
        if($final < 0) $final = 0;
        
        $dp_item = ($_POST['bayar'] == 'lunas') ? $final : round($dp * $rasio);
        if($dp_item >= $final) { $status_order = 'lunas'; $tgl_lunas = "'$tgl_hari_ini'"; } 
        else { $status_order = 'booking'; $tgl_lunas = "NULL"; }
        
        mysqli_query($conn, "INSERT INTO order_bibit (nama_customer, no_hp, alamat, id_baris, panjang_m, posisi, varietas, tgl_booking, tgl_lunas, tgl_ambil, harga_dasar, diskon_persen, diskon_nominal, ongkir, dp_dibayar, total_harga, status) 
        VALUES ('$nama', '$hp', '$alamat', '{$it['id']}', '{$it['m']}', '{$it['pos']}', '{$it['var']}', '$tgl_booking', $tgl_lunas, $tgl_ambil, '{$it['hd']}', '$d_persen', '$d_nom', '$ongk', '$dp_item', '$final', '$status_order')");
    }

    echo "<script>alert('Berhasil! Order telah disimpan.'); window.location.href='?page=order-bibit&tab=data';</script>"; exit;
}

// B. EDIT ORDER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    $id_order = (int)$_POST['id_order'];
    $nama = mysqli_real_escape_string($conn, $_POST['edit_nama']); $hp = mysqli_real_escape_string($conn, $_POST['edit_hp']); $alamat = mysqli_real_escape_string($conn, $_POST['edit_alamat']);
    $tgl_booking = mysqli_real_escape_string($conn, $_POST['edit_tgl_booking']); $tgl_ambil = !empty($_POST['edit_tgl_ambil']) ? "'".mysqli_real_escape_string($conn, $_POST['edit_tgl_ambil'])."'" : "NULL";
    
    $tipe_diskon = $_POST['edit_tipe_diskon']; 
    $nominal_diskon_p = isset($_POST['edit_nominal_diskon_persen']) ? (float)$_POST['edit_nominal_diskon_persen'] : 0;
    $nominal_diskon_n = isset($_POST['edit_nominal_diskon_rp']) ? (float)str_replace('.', '', $_POST['edit_nominal_diskon_rp']) : 0;
    $diskon_p = ($tipe_diskon == 'persen') ? $nominal_diskon_p : 0; $diskon_n = ($tipe_diskon == 'nominal') ? $nominal_diskon_n : 0;
    
    $ongkir = ($_POST['edit_ambil'] == 'dikirim') ? (int)str_replace('.', '', $_POST['edit_nominal_ongkir']) : 0;
    $hd = (int)$_POST['edit_harga_dasar'];
    $final = $hd - ($hd * ($diskon_p/100)) - $diskon_n + $ongkir;
    $dp = ($_POST['edit_bayar'] == 'dp') ? (int)str_replace('.', '', $_POST['edit_nominal_dp']) : $final;
    $status_order = ($dp >= $final) ? 'lunas' : 'booking';
    $tgl_lunas = ($status_order == 'lunas') ? "'$tgl_hari_ini'" : "NULL";

    mysqli_query($conn, "UPDATE order_bibit SET nama_customer='$nama', no_hp='$hp', alamat='$alamat', tgl_booking='$tgl_booking', tgl_ambil=$tgl_ambil, diskon_persen='$diskon_p', diskon_nominal='$diskon_n', ongkir='$ongkir', dp_dibayar='$dp', total_harga='$final', status='$status_order', tgl_lunas=$tgl_lunas WHERE id='$id_order'");
    echo "<script>alert('Perubahan berhasil disimpan!'); window.location.href='?page=order-bibit&tab=data';</script>"; exit;
}

// C. AKSI DROPDOWN & SAPU LAHAN
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action']; $id = (int)$_GET['id'];
    if ($action == 'lunas') {
        mysqli_query($conn, "UPDATE order_bibit SET status='lunas', tgl_lunas='$tgl_hari_ini' WHERE id='$id'");
        echo "<script>window.location.href='?page=order-bibit&tab=data';</script>"; exit;
    } 
    elseif ($action == 'diambil') {
        $cek_status = mysqli_query($conn, "SELECT status FROM order_bibit WHERE id='$id'");
        $d_status = mysqli_fetch_assoc($cek_status);
        if ($d_status['status'] == 'booking') {
            echo "<script>alert('Gagal! Pesanan belum lunas, tidak bisa diambil. Harap selesaikan pelunasan pembayaran terlebih dahulu.'); window.location.href='?page=order-bibit&tab=data';</script>";
            exit;
        }
        mysqli_query($conn, "UPDATE order_bibit SET status='diambil', tgl_ambil='$tgl_hari_ini' WHERE id='$id'");
        echo "<script>alert('Order Diambil! Lahan order ini akan menjadi Kosong/Putih di Dashboard.'); window.location.href='?page=order-bibit&tab=data';</script>"; exit;
    } 
    elseif ($action == 'cancel') {
        $id_b = isset($_GET['id_b']) ? (int)$_GET['id_b'] : 0; $pjg = isset($_GET['pjg']) ? (float)$_GET['pjg'] : 0;
        if($id_b > 0) {
            mysqli_query($conn, "UPDATE bibit_baris SET tersedia_m = LEAST(tersedia_m + $pjg, 12.0) WHERE id_baris='$id_b'");
            mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh' WHERE id_baris='$id_b' AND status='habis'");
        }
        mysqli_query($conn, "DELETE FROM order_bibit WHERE id='$id'");
        echo "<script>alert('Order Dibatalkan! Meteran lahan dikembalikan.'); window.location.href='?page=order-bibit&tab=data';</script>"; exit;
    }
}

if(isset($_GET['bersihkan_baris'])) {
    $id_b = (int)$_GET['bersihkan_baris'];
    mysqli_query($conn, "UPDATE bibit_baris SET status='kosong', id_varietas=NULL, tgl_persiapan=NULL, tgl_sebar=NULL, tersedia_m=12.0 WHERE id_baris='$id_b'");
    mysqli_query($conn, "UPDATE order_bibit SET status='diambil', tgl_ambil='$tgl_hari_ini' WHERE id_baris='$id_b' AND status NOT IN ('diambil', 'Selesai', 'Batal')");
    echo "<script>alert('Lahan baris #$id_b berhasil disapu bersih menjadi 12m Kosong!'); window.location.href='?page=order-bibit&tab=dashboard';</script>"; exit;
}

// D. AKSI LUNASI DARI TRANSAKSI AKTIF (TAHAP 1)
if (isset($_GET['action']) && $_GET['action'] == 'lunas_aktif') {
    $hp = mysqli_real_escape_string($conn, $_GET['hp']);
    $nama = mysqli_real_escape_string($conn, $_GET['nama']);
    
    // UPDATE SEMUA pesanan DP milik pelanggan ini menjadi LUNAS, tapi TIDAK Dihilangkan dari Transaksi Aktif
    mysqli_query($conn, "UPDATE order_bibit SET dp_dibayar = total_harga, status = 'lunas', tgl_lunas = '$tgl_hari_ini' WHERE nama_customer = '$nama' AND no_hp = '$hp' AND status NOT IN ('Selesai', 'Batal')");
    
    echo "<script>alert('Pelunasan Berhasil! Status berubah menjadi LUNAS SEMUA.\\n\\nSilakan klik tombol CETAK untuk memberikan bukti lunas kepada pelanggan, lalu klik tombol SELESAIKAN untuk membersihkan nama ini dari layar.'); window.location.href='?page=order-bibit&tab=aktif';</script>"; exit;
}

// E. AKSI SELESAIKAN/ARSIPKAN DARI TRANSAKSI AKTIF (TAHAP 2)
if (isset($_GET['action']) && $_GET['action'] == 'arsipkan_aktif') {
    $hp = mysqli_real_escape_string($conn, $_GET['hp']);
    $nama = mysqli_real_escape_string($conn, $_GET['nama']);
    
    // UBAH status menjadi Selesai (Disembunyikan dari Transaksi Aktif, Pindah ke Data Order)
    mysqli_query($conn, "UPDATE order_bibit SET status = 'Selesai' WHERE nama_customer = '$nama' AND no_hp = '$hp' AND status NOT IN ('Selesai', 'Batal')");
    
    echo "<script>alert('Mantap! Seluruh pesanan atas nama $nama telah Selesai dan dipindahkan ke Arsip (Data Order).'); window.location.href='?page=order-bibit&tab=aktif';</script>"; exit;
}


// =========================================================================
// 2. AMBIL DATA BARIS & HITUNG LOGIKA DASHBOARD
// =========================================================================
$query_baris = mysqli_query($conn, "SELECT b.*, v.nama_varietas, v.kode_varietas, v.harga_jual FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id ORDER BY b.id_baris ASC");

$q_active_orders = mysqli_query($conn, "SELECT * FROM order_bibit WHERE status NOT IN ('diambil', 'Selesai', 'Batal') ORDER BY id ASC");
$active_orders_map = [];
while($ao = mysqli_fetch_assoc($q_active_orders)) { $active_orders_map[$ao['id_baris']][] = $ao; }

$tot_tersedia = 0; $tot_terjual_sebagian = 0; $tot_proses_kosong = 0; $tot_meter_global = 0; 
$data_baris = []; $baris_full = []; $baris_tersedia = [];

while($r = mysqli_fetch_assoc($query_baris)) {
    $tersedia = (float)$r['tersedia_m']; $status_db = $r['status'];
    $umur = '-';
    if ($status_db != 'kosong' && $status_db != 'persiapan') {
        $diff = strtotime($tgl_hari_ini) - strtotime($r['tgl_sebar']);
        $umur = (floor($diff / 86400) >= 0 ? floor($diff / 86400) : 0) . 'h';
    }
    $display_tersedia = ($status_db == 'kosong' || $status_db == 'persiapan') ? 0 : $tersedia;
    $tot_meter_global += $display_tersedia;

    if ($status_db == 'kosong' || $status_db == 'persiapan' || $display_tersedia <= 0) { $tot_proses_kosong++; } 
    else { if ($display_tersedia == 12.0) { $tot_tersedia++; } else { $tot_terjual_sebagian++; } }

    $harga_spesifik = (int)$r['harga_jual'];
    $harga_baris = ($harga_spesifik > 0) ? $harga_spesifik : $cfg_bibit_global;
    
    $m_booking = 0; $m_lunas = 0;
    if(isset($active_orders_map[$r['id_baris']])) {
        foreach($active_orders_map[$r['id_baris']] as $ao) {
            $st = strtolower($ao['status']);
            if($st == 'booking' || $st == 'dp') { $m_booking += (float)$ao['panjang_m']; }
            if($st == 'lunas' || $st == 'persiapan' || $st == 'tanam') { $m_lunas += (float)$ao['panjang_m']; }
        }
    }
    $m_free = $display_tersedia;
    
    if ($status_db == 'kosong' || $status_db == 'persiapan') {
        $m_kosong = 12.0; $m_lunas = 0; $m_booking = 0; $m_free = 0;
    } else {
        $m_kosong = 12.0 - ($m_lunas + $m_booking + $m_free);
        if($m_kosong < 0) $m_kosong = 0;
    }

    $item = [ 'no' => $r['id_baris'], 'varietas' => $r['kode_varietas'] ? $r['kode_varietas'] : '-', 'nama_var' => $r['nama_varietas'], 'umur' => $umur, 'status_db' => $status_db, 'display_tersedia' => $display_tersedia, 'harga_baris' => $harga_baris, 'm_kosong' => $m_kosong, 'm_lunas' => $m_lunas, 'm_booking' => $m_booking, 'm_free' => $m_free ];
    $data_baris[] = $item;
    if($display_tersedia == 12.0) { $baris_full[] = $item; }
    if($display_tersedia > 0) { $baris_tersedia[] = $item; }
}
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    <div class="flex items-center gap-3 mb-4">
        <a href="?page=dashboard" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors"><i class="fa-solid fa-arrow-left text-sm md:text-base"></i></a>
        <div>
            <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]"><i class="fa-solid fa-seedling text-[#d29922] mr-3"></i> Order Bibit Padi</h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-0.5 ml-8">Kelola order bibit padi - 85 Baris @ 12 meter (8 segmen x 1,5m)</p>
        </div>
    </div>

    <div class="flex gap-6 border-b border-gray-200 dark:border-[#30363d] mb-6 px-2 overflow-x-auto">
        <a href="?page=order-bibit&tab=dashboard" class="border-b-2 <?= $tab=='dashboard' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-chart-simple mr-1.5"></i> Dashboard</a>
        <a href="?page=order-bibit&tab=baru" class="border-b-2 <?= $tab=='baru' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-plus mr-1.5"></i> Order Baru</a>
        <a href="?page=order-bibit&tab=data" class="border-b-2 <?= $tab=='data' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-regular fa-folder-open mr-1.5"></i> Data Order</a>
        <a href="?page=order-bibit&tab=aktif" class="border-b-2 <?= $tab=='aktif' ? 'border-[#3fb950] text-[#3fb950]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-users mr-1.5"></i> Transaksi Aktif</a>
    </div>

    <?php if($tab == 'dashboard'): ?>
    <div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex items-center gap-4"><div class="w-10 h-10 rounded-md bg-[#238636]/20 flex items-center justify-center text-[#3fb950] shrink-0"><i class="fa-solid fa-seedling text-lg"></i></div><div><p class="text-[11px] text-gray-500 dark:text-[#8b949e] mb-0.5">Tersedia</p><h3 class="text-xl font-bold text-[#3fb950] leading-none"><?= $tot_tersedia ?></h3><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mt-1">baris siap jual</p></div></div>
            <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex items-center gap-4"><div class="w-10 h-10 rounded-md bg-[#d29922]/20 flex items-center justify-center text-[#d29922] shrink-0"><i class="fa-solid fa-arrow-trend-up text-lg"></i></div><div><p class="text-[11px] text-gray-500 dark:text-[#8b949e] mb-0.5">Terjual</p><h3 class="text-xl font-bold text-[#d29922] leading-none"><?= $tot_terjual_sebagian ?></h3><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mt-1">baris sebagian</p></div></div>
            <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex items-center gap-4"><div class="w-10 h-10 rounded-md bg-[#f85149]/20 flex items-center justify-center text-[#f85149] shrink-0"><i class="fa-solid fa-circle-exclamation text-lg"></i></div><div><p class="text-[11px] text-gray-500 dark:text-[#8b949e] mb-0.5">Proses/Kosong</p><h3 class="text-xl font-bold text-[#f85149] leading-none"><?= $tot_proses_kosong ?></h3><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mt-1">belum siap</p></div></div>
            <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex items-center gap-4"><div class="w-10 h-10 rounded-md bg-[#58a6ff]/20 flex items-center justify-center text-[#58a6ff] shrink-0"><i class="fa-solid fa-arrows-spin text-lg"></i></div><div><p class="text-[11px] text-gray-500 dark:text-[#8b949e] mb-0.5">Total Meter</p><h3 class="text-xl font-bold text-[#58a6ff] leading-none"><?= number_format($tot_meter_global, 0) ?>m</h3><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mt-1">tersedia</p></div></div>
        </div>

        <div class="flex flex-wrap items-center gap-4 mb-6 bg-gray-50 dark:bg-[#161b22] p-3 rounded-lg border border-gray-200 dark:border-[#30363d]">
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-[#3fb950] mr-2"></div> Free (Tersedia)</div>
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-[#d29922] mr-2"></div> Booking (DP)</div>
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-[#f85149] mr-2"></div> Lunas (Belum diambil)</div>
            <div class="flex items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] font-medium"><div class="w-4 h-3 rounded bg-gray-200 dark:bg-[#30363d] mr-2 border border-gray-300 dark:border-transparent"></div> Kosong (Sudah diambil)</div>
        </div>

        <h2 class="text-[14px] font-bold text-gray-900 dark:text-white mb-3 flex justify-between items-center">
            <span>Status Baris 1 - 85 <span class="font-normal text-gray-500 text-[12px] ml-2">(Klik segmen warna untuk Ringkasan Baris)</span></span>
        </h2>

        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg pb-10">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="border-b border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#161b22]">
                    <tr class="text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase">
                        <th class="py-3 px-4 w-16 text-center">NO</th><th class="py-3 px-4 w-40">VARIETAS</th><th class="py-3 px-4 w-20">UMUR</th><th class="py-3 px-4 min-w-[400px]">SEGMEN (8 X 1,5M = 12M)</th><th class="py-3 px-4 text-right w-16">SISA</th><th class="py-3 px-4 text-center w-16">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d]">
                    <?php foreach($data_baris as $b): 
                        $has_order = 'onclick="bukaDetailPemesan('.$b['no'].', '.$b['m_free'].', '.$b['m_kosong'].')"';
                        
                        $czones = []; $c_pos = 0;
                        if($b['m_kosong'] > 0) { $czones[] = ['c'=>'#e5e7eb', 'd'=>'#30363d', 's'=>$c_pos, 'e'=>$c_pos+$b['m_kosong'], 'n'=>'Kosong']; $c_pos+=$b['m_kosong']; }
                        if($b['m_lunas'] > 0)  { $czones[] = ['c'=>'#f85149', 'd'=>'#f85149', 's'=>$c_pos, 'e'=>$c_pos+$b['m_lunas'], 'n'=>'Lunas']; $c_pos+=$b['m_lunas']; }
                        if($b['m_booking'] > 0){ $czones[] = ['c'=>'#d29922', 'd'=>'#d29922', 's'=>$c_pos, 'e'=>$c_pos+$b['m_booking'], 'n'=>'Booking']; $c_pos+=$b['m_booking']; }
                        if($b['m_free'] > 0)   { $czones[] = ['c'=>'#2ea043', 'd'=>'#2ea043', 's'=>$c_pos, 'e'=>$c_pos+$b['m_free'], 'n'=>'Free']; $c_pos+=$b['m_free']; }
                    ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]">
                        <td class="py-3 px-4 text-center"><div class="w-6 h-6 mx-auto rounded-full flex items-center justify-center text-[11px] font-bold text-white <?= $b['m_free']==0 && $b['m_kosong']<12 ? 'bg-[#f85149]' : 'bg-[#238636]' ?>"><?= $b['no'] ?></div></td>
                        <td class="py-3 px-4 text-[13px] text-gray-900 dark:text-[#c9d1d9] font-bold"><?= htmlspecialchars($b['varietas']) ?></td>
                        <td class="py-3 px-4 text-[13px] text-gray-500 dark:text-[#8b949e]"><?= $b['umur'] ?></td>
                        <td class="py-3 px-4">
                            <div class="grid grid-cols-8 gap-1 w-full" <?= $has_order ?>>
                                <?php 
                                for($i=1; $i<=8; $i++) {
                                    $s_st = ($i - 1) * 1.5; $s_en = $i * 1.5;
                                    $intersect = [];
                                    
                                    foreach($czones as $z) {
                                        $i_st = max($z['s'], $s_st); 
                                        $i_en = min($z['e'], $s_en);
                                        if($i_st < $i_en + 0.001 && round($i_en - $i_st, 3) > 0) {
                                            $intersect[] = ['c' => $z['c'], 'd' => $z['d'], 'n' => $z['n'], 'p' => (($i_en - $i_st) / 1.5) * 100];
                                        }
                                    }

                                    if(count($intersect) == 0) {
                                        echo '<div class="bg-gray-200 dark:bg-[#30363d] text-gray-500 text-[9px] font-bold py-1.5 px-1 rounded-[2px]">Err</div>';
                                    } else if(count($intersect) == 1) {
                                        $iz = $intersect[0];
                                        if($iz['n'] == 'Kosong') {
                                            echo '<div class="bg-gray-200 dark:bg-[#30363d] text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-transparent text-[9px] font-bold py-1.5 text-center rounded-[2px] truncate px-1 cursor-pointer hover:opacity-80 transition-opacity">Kosong</div>';
                                        } else {
                                            echo '<div style="background-color:'.$iz['d'].';" class="text-white text-[9px] font-bold py-1.5 text-center rounded-[2px] truncate px-1 cursor-pointer hover:opacity-80 transition-opacity">'.$iz['n'].'</div>';
                                        }
                                    } else {
                                        $stops = []; $cum = 0; $names = [];
                                        $total_iz = count($intersect);
                                        foreach($intersect as $idx => $iz) {
                                            $st_p = round($cum, 2); $cum += $iz['p']; $en_p = ($idx === $total_iz - 1) ? 100 : round($cum, 2);
                                            $stops[] = "{$iz['d']} {$st_p}%"; $stops[] = "{$iz['d']} {$en_p}%"; $names[] = $iz['n'];
                                        }
                                        $grad = "linear-gradient(to right, ".implode(', ', $stops).")";
                                        echo '<div style="background: '.$grad.' no-repeat padding-box; border: none; outline: none; text-shadow: 1px 1px 2px rgba(0,0,0,0.6);" title="Campur: '.implode(', ', $names).'" class="text-white text-[9px] font-bold py-1.5 text-center rounded-[2px] truncate px-1 cursor-pointer hover:opacity-80 transition-opacity">Campur</div>';
                                    }
                                }
                                ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-[13px] font-bold text-right <?= $b['m_free'] > 0 ? 'text-[#3fb950]' : 'text-[#f85149]' ?>"><?= $b['m_free'] ?>m</td>
                        <td class="py-3 px-4 text-center">
                            <a href="?page=order-bibit&tab=dashboard&bersihkan_baris=<?= $b['no'] ?>" onclick="return confirm('BERSIHKAN LAHAN (Sapu)? Status baris akan diset kembali 12m Kosong/Putih.')" class="text-[#58a6ff] hover:text-blue-500 transition-colors" title="Sapu Lahan (Jadikan Kosong 12m)"><i class="fa-solid fa-broom"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php elseif($tab == 'baru'): ?>
    <div>
        <h2 class="text-[16px] font-bold text-gray-900 dark:text-white mb-5">Form Order Bibit Padi</h2>
        <form id="formOrderBaru" method="POST" action="">
            <input type="hidden" name="kode_kupon_dipakai" id="form_kode_kupon_dipakai" value="">
            <input type="hidden" name="baris_full_list" id="form_baris_full_list" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Nama Customer <span class="text-red-500">*</span></label><input type="text" name="nama_customer" placeholder="Nama lengkap" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                <div><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">No. HP <span class="text-red-500">*</span></label><input type="text" name="no_hp" placeholder="08xxxxxxxxxx" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
            </div>
            <div class="mb-5"><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Alamat <span class="text-red-500">*</span></label><textarea name="alamat" rows="2" placeholder="Alamat lengkap" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff] resize-none"></textarea></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
                <div><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Tanggal Booking <span class="text-red-500">*</span></label><input type="date" name="tgl_booking" value="<?= $tgl_hari_ini ?>" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff] [color-scheme:light] dark:[color-scheme:dark]"></div>
                <div><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Tanggal Ambil (Rencana)</label><input type="date" name="tgl_ambil" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff] [color-scheme:light] dark:[color-scheme:dark]"></div>
            </div>

            <div class="mb-6">
                <label class="block text-[13px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Pilih Baris (Full 12m) - dari stock tersedia</label>
                <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-2 mb-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar border border-gray-200 dark:border-[#30363d] p-3 rounded-lg bg-gray-50 dark:bg-[#161b22]">
                    <?php if(count($baris_full) > 0): ?>
                        <?php foreach($baris_full as $bf): ?>
                            <div class="box-baris relative border border-gray-300 dark:border-[#30363d] bg-white dark:bg-[#0d1117] rounded p-2 text-center cursor-pointer transition-all hover:border-[#58a6ff] select-none" data-id="<?= $bf['no'] ?>" data-price="<?= $bf['harga_baris'] ?>">
                                <div class="text-[13px] font-bold text-gray-900 dark:text-white mb-0.5">#<?= $bf['no'] ?></div>
                                <div class="text-[10px] text-gray-500 dark:text-[#8b949e] truncate leading-tight"><?= htmlspecialchars($bf['varietas']) ?></div>
                                <div class="text-[10px] font-bold text-[#3fb950] mt-0.5">12m</div>
                                <div class="check-overlay absolute inset-0 bg-blue-500/10 border-2 border-[#58a6ff] rounded hidden items-center justify-center"><div class="bg-[#1f6feb] text-white rounded-full w-4 h-4 flex items-center justify-center absolute -top-1.5 -right-1.5 shadow-md"><i class="fa-solid fa-check text-[9px]"></i></div></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-[12px] text-gray-500 text-center py-4">Tidak ada baris full 12m yang tersedia.</div>
                    <?php endif; ?>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-[#8b949e]">Dipilih: <span id="text-jml-baris" class="font-bold">0</span> baris (<span id="text-jml-meter">0</span>m)</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6 bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-4 rounded-lg">
                <div><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Meter Tambahan (opsional)</label><input type="number" name="meter_tambahan" id="input_meter_tambahan" step="0.5" min="0" value="0" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                <div id="container_baris_tambahan" class="hidden"><label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Pilih baris sumber tambahan <span class="text-red-500">*</span></label><select name="id_baris_tambahan" id="select_baris_tambahan" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"><option value="0" data-price="0" class="text-gray-900 dark:text-white">Pilih baris...</option><?php foreach($baris_tersedia as $bt): ?><option value="<?= $bt['no'] ?>" data-max="<?= $bt['display_tersedia'] ?>" data-price="<?= $bt['harga_baris'] ?>" class="text-gray-900 dark:text-white">#<?= $bt['no'] ?> - <?= htmlspecialchars($bt['nama_var']) ?> (Tersedia: <?= $bt['display_tersedia'] ?>m)</option><?php endforeach; ?></select></div>
            </div>

            <div class="space-y-4 mb-6">
                <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                    <label class="block text-[13px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Pembayaran</label>
                    <div class="flex gap-4 mb-3"><label class="flex items-center text-[13px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="bayar" value="lunas" checked class="mr-2"> Lunas</label><label class="flex items-center text-[13px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="bayar" value="dp" class="mr-2"> DP (Uang Muka)</label></div>
                    <div id="container_dp" class="hidden mt-3 pt-3 border-t border-gray-200 dark:border-[#30363d]">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Nominal DP <span class="text-red-500">*</span></label>
                        <div class="relative"><span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[13px] font-bold">Rp</span><input type="text" name="nominal_dp" id="input_dp" class="format-rupiah w-full pl-8 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                    </div>
                </div>

                <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <label class="text-[13px] font-bold text-gray-700 dark:text-[#c9d1d9]">Diskon Manual</label>
                        <label class="relative inline-flex items-center cursor-pointer">
                          <input type="checkbox" id="toggle_diskon" name="is_diskon" class="sr-only peer">
                          <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-[#3fb950]"></div>
                        </label>
                    </div>
                    <div id="container_diskon" class="hidden mt-3 pt-3 border-t border-gray-200 dark:border-[#30363d]">
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center text-[13px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="tipe_diskon" value="persen" checked class="mr-2"> Persentase (%)</label>
                            <label class="flex items-center text-[13px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="tipe_diskon" value="nominal" class="mr-2"> Nominal (Rp)</label>
                        </div>
                        <div id="wrap_diskon_persen" class="relative">
                            <input type="number" name="nominal_diskon_persen" id="input_diskon_p" value="0" max="100" min="0" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md pr-8 px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]">
                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[13px] font-bold">%</span>
                        </div>
                        <div id="wrap_diskon_nominal" class="relative hidden">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[13px] font-bold">Rp</span>
                            <input type="text" name="nominal_diskon_rp" id="input_diskon_n" class="format-rupiah w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md pl-8 px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]">
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                    <label class="block text-[13px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Kode Kupon (Opsional)</label>
                    <div class="flex gap-2">
                        <input type="text" id="input_kode_kupon" placeholder="Masukkan kode kupon" class="uppercase flex-1 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]">
                        <button type="button" id="btn_cek_kupon" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-4 py-2 rounded-md text-[13px] font-medium transition-colors">Cek</button>
                    </div>
                    <p id="msg_kupon" class="text-[11px] mt-1.5 hidden"></p>
                    <input type="hidden" name="nilai_kupon" id="val_kupon" value="0">
                </div>

                <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                    <label class="block text-[13px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Metode Pengambilan</label>
                    <div class="flex gap-4 mb-3"><label class="flex items-center text-[13px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="ambil" value="diambil" checked class="mr-2"> Diambil di Tempat (Gratis)</label><label class="flex items-center text-[13px] text-gray-700 dark:text-white cursor-pointer"><input type="radio" name="ambil" value="dikirim" class="mr-2"> Dikirim (Ada Ongkir)</label></div>
                    <div id="container_ongkir" class="hidden mt-3 pt-3 border-t border-gray-200 dark:border-[#30363d]">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Biaya Ongkir <span class="text-red-500">*</span></label>
                        <div class="relative"><span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[13px] font-bold">Rp</span><input type="text" name="nominal_ongkir" id="input_ongkir" class="format-rupiah w-full pl-8 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                    </div>
                </div>
            </div>

            <div class="border-t-4 border-[#3fb950] bg-gray-50 dark:bg-[#161b22] border-x border-b border-gray-200 dark:border-[#30363d] rounded-b-lg p-5 mb-6">
                <h3 class="text-[14px] font-bold text-gray-900 dark:text-white mb-4">Ringkasan Order</h3>
                <div class="flex justify-between items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] mb-2"><span>Baris Full:</span> <span id="ringkasan_full">0 x 12m = 0m</span></div>
                <div class="flex justify-between items-center text-[12px] text-gray-700 dark:text-[#c9d1d9] mb-2"><span>Tambahan:</span> <span id="ringkasan_tambahan">0m</span></div>
                <div class="flex justify-between items-center text-[13px] font-bold text-gray-900 dark:text-white mb-4 pb-4 border-b border-gray-300 dark:border-[#30363d]"><span>Total Meter:</span> <span id="ringkasan_total_m">0m</span></div>
                
                <div class="flex justify-between items-center text-[13px] text-gray-700 dark:text-[#c9d1d9] mb-2"><span>Subtotal:</span> <span id="ringkasan_subtotal">Rp 0</span></div>
                <div class="flex justify-between items-center text-[13px] text-red-500 mb-2 hidden" id="row_diskon"><span>Diskon:</span> <span id="ringkasan_diskon">- Rp 0</span></div>
                <div class="flex justify-between items-center text-[13px] text-yellow-600 dark:text-yellow-400 mb-2 hidden" id="row_ongkir"><span>Biaya Ongkir:</span> <span id="ringkasan_ongkir">+ Rp 0</span></div>
                
                <div class="flex justify-between items-center text-[16px] font-bold text-[#3fb950] mt-2 pt-2 border-t border-gray-300 dark:border-[#30363d]"><span>Harga Final:</span> <span id="ringkasan_final">Rp 0</span></div>
                
                <div class="flex justify-between items-center text-[13px] text-[#1f6feb] dark:text-[#58a6ff] mt-2 pt-2 border-t border-gray-300 dark:border-[#30363d] hidden" id="row_dp_summary"><span>DP Dibayar:</span> <span id="ringkasan_dp">Rp 0</span></div>
                <div class="flex justify-between items-center text-[13px] text-[#f85149] mt-2 hidden" id="row_sisa_summary"><span>Sisa Bayar:</span> <span id="ringkasan_sisa">Rp 0</span></div>
            </div>

            <div class="flex gap-4">
                <button type="reset" class="w-1/3 bg-gray-200 hover:bg-gray-300 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] py-3 rounded-lg text-[13px] font-bold transition-colors border border-gray-300 dark:border-transparent">Reset</button>
                <button type="submit" name="simpan_order" class="w-2/3 bg-[#238636] hover:bg-[#2ea043] text-white py-3 rounded-lg text-[14px] font-bold transition-colors shadow-lg">Simpan Order</button>
            </div>
        </form>
    </div>

    <script>
        const formatRupiahStr = (angka) => "Rp " + new Intl.NumberFormat('id-ID').format(angka);
        const unformatRupiah = (str) => parseInt(str.toString().replace(/[^0-9]/g, '')) || 0;

        document.querySelectorAll('.format-rupiah').forEach(inp => {
            inp.addEventListener('focus', function() { if(this.value === '0') this.value = ''; });
            inp.addEventListener('blur', function() { if(this.value === '') this.value = '0'; });
            inp.addEventListener('input', function() {
                let val = this.value.replace(/[^0-9]/g, '');
                if(val !== '') { this.value = new Intl.NumberFormat('id-ID').format(parseInt(val)); } else { this.value = ''; }
                if(typeof calculateAll === "function") calculateAll();
            });
        });

        const boxes = document.querySelectorAll('.box-baris'), inputHiddenBaris = document.getElementById('form_baris_full_list'), inputTambahan = document.getElementById('input_meter_tambahan'), contTambahan = document.getElementById('container_baris_tambahan'), selectTambahan = document.getElementById('select_baris_tambahan'), radiosBayar = document.querySelectorAll('input[name="bayar"]'), contDP = document.getElementById('container_dp'), inputDP = document.getElementById('input_dp'), radiosAmbil = document.querySelectorAll('input[name="ambil"]'), contOngkir = document.getElementById('container_ongkir'), inputOngkir = document.getElementById('input_ongkir');
        
        let selectedBoxes = new Set(); let priceFull = 0; 
        
        const toggleDiskon = document.getElementById('toggle_diskon');
        const contDiskon = document.getElementById('container_diskon');
        const rDiskon = document.querySelectorAll('input[name="tipe_diskon"]');
        const wrapDPersen = document.getElementById('wrap_diskon_persen');
        const wrapDNominal = document.getElementById('wrap_diskon_nominal');
        const inpDiskonP = document.getElementById('input_diskon_p');
        const inpDiskonN = document.getElementById('input_diskon_n');

        toggleDiskon.addEventListener('change', function() {
            if(this.checked) { contDiskon.classList.remove('hidden'); } else { contDiskon.classList.add('hidden'); inpDiskonP.value=0; inpDiskonN.value=0; } calculateAll();
        });

        rDiskon.forEach(r => r.addEventListener('change', function() {
            if(this.value === 'persen') { wrapDPersen.classList.remove('hidden'); wrapDNominal.classList.add('hidden'); inpDiskonN.value=0; } 
            else { wrapDPersen.classList.add('hidden'); wrapDNominal.classList.remove('hidden'); inpDiskonP.value=0; } calculateAll();
        }));

        inpDiskonP.addEventListener('input', function() {
            let val = parseFloat(this.value) || 0;
            if(val > 100) this.value = 100; if(val < 0) this.value = 0; calculateAll();
        });

        <?php
            $q_aktif = mysqli_query($conn, "SELECT * FROM kupon_diskon WHERE status='Aktif'");
            $arr_aktif = []; while($ka = mysqli_fetch_assoc($q_aktif)) { $arr_aktif[] = $ka; }
        ?>
        const listKuponAktif = <?= json_encode($arr_aktif) ?>;
        const btnCekKupon = document.getElementById('btn_cek_kupon');
        const inKupon = document.getElementById('input_kode_kupon');
        const msgKupon = document.getElementById('msg_kupon');
        const valKupon = document.getElementById('val_kupon');
        const inHKodeDipakai = document.getElementById('form_kode_kupon_dipakai');

        btnCekKupon.addEventListener('click', function() {
            let kode = inKupon.value.trim().toUpperCase();
            let found = listKuponAktif.find(k => k.kode.toUpperCase() === kode);
            if(found) {
                let isHabis = false;
                if(found.kuota.includes('/')) {
                    let parts = found.kuota.split('/');
                    if(parseInt(parts[0]) >= parseInt(parts[1])) isHabis = true;
                }
                if(isHabis) {
                    msgKupon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Kuota kupon habis.'; msgKupon.className = 'text-[11px] mt-1.5 block text-[#f85149] font-bold'; valKupon.value = 0; inHKodeDipakai.value = '';
                } else {
                    let txtDiskon = found.tipe === 'Nominal' ? formatRupiahStr(found.nilai) : found.nilai + '%';
                    msgKupon.innerHTML = '<i class="fa-solid fa-circle-check"></i> Kupon valid! Diskon ' + txtDiskon; msgKupon.className = 'text-[11px] mt-1.5 block text-[#3fb950] font-bold'; valKupon.value = found.nilai; valKupon.setAttribute('data-tipe', found.tipe); inHKodeDipakai.value = found.kode;
                }
            } else if(kode === '') {
                msgKupon.className = 'hidden'; valKupon.value = 0; inHKodeDipakai.value = '';
            } else {
                msgKupon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Kode tidak ditemukan.'; msgKupon.className = 'text-[11px] mt-1.5 block text-[#f85149] font-bold'; valKupon.value = 0; inHKodeDipakai.value = '';
            }
            calculateAll();
        });

        inputTambahan.addEventListener('input', function() { let val = parseFloat(this.value) || 0; if(val > 0) contTambahan.classList.remove('hidden'); else contTambahan.classList.add('hidden'); calculateAll(); });
        radiosBayar.forEach(r => r.addEventListener('change', function() { if(this.value === 'dp') contDP.classList.remove('hidden'); else contDP.classList.add('hidden'); calculateAll(); }));
        radiosAmbil.forEach(r => r.addEventListener('change', function() { if(this.value === 'dikirim') contOngkir.classList.remove('hidden'); else contOngkir.classList.add('hidden'); calculateAll(); }));
        
        function calculateAll() {
            let countFull = selectedBoxes.size; let meterFull = countFull * 12; inputHiddenBaris.value = Array.from(selectedBoxes).join(',');
            
            // =============================================================
            // KODE SUNTIKAN BARU: AUTO-LOCK BARIS TAMBAHAN
            // =============================================================
            for (let i = 0; i < selectTambahan.options.length; i++) {
                let opt = selectTambahan.options[i];
                if (opt.value !== "0") {
                    if (selectedBoxes.has(opt.value)) {
                        // Jika baris diklik Full, matikan dari dropdown eceran
                        opt.disabled = true;
                        opt.style.display = 'none'; 
                        
                        // Jika terlanjur dipilih di dropdown, batalkan otomatis
                        if (selectTambahan.value === opt.value) {
                            selectTambahan.value = "0";
                            inputTambahan.value = "0";
                            contTambahan.classList.add('hidden');
                            alert("Peringatan: Baris #" + opt.value + " baru saja Anda pilih sebagai Baris Full. Baris ini otomatis dikunci dan tidak bisa digunakan untuk meteran tambahan.");
                        }
                    } else {
                        // Jika baris Full dilepas, nyalakan kembali di dropdown
                        opt.disabled = false;
                        opt.style.display = ''; 
                    }
                }
            }
            // =============================================================

            let valTambahan = parseFloat(inputTambahan.value) || 0; let optSelected = selectTambahan.options[selectTambahan.selectedIndex]; let maxM = parseFloat(optSelected.getAttribute('data-max')) || 0; 
            
            let priceBaris = parseFloat(optSelected.getAttribute('data-price')) || 0; let pricePerM = priceBaris / 12;
            if(valTambahan > maxM && maxM > 0) { alert("Melebihi sisa ("+maxM+"m)!"); inputTambahan.value = maxM; valTambahan = maxM; }
            let priceTambahan = valTambahan * pricePerM;

            document.getElementById('text-jml-baris').innerText = countFull; document.getElementById('ringkasan_full').innerText = `${countFull} x 12m = ${meterFull}m`; document.getElementById('ringkasan_tambahan').innerText = `${valTambahan}m`; document.getElementById('ringkasan_total_m').innerText = `${meterFull + valTambahan}m`;

            let subtotal = priceFull + priceTambahan; document.getElementById('ringkasan_subtotal').innerText = formatRupiahStr(subtotal);

            let valDiskonP = parseFloat(inpDiskonP.value) || 0; let valDiskonN = unformatRupiah(inpDiskonN.value);
            let potongManual = 0;
            if(toggleDiskon.checked) {
                potongManual = (document.querySelector('input[name="tipe_diskon"]:checked').value === 'persen') ? subtotal * (valDiskonP / 100) : valDiskonN;
            }
            
            let potongKupon = 0; let valK = parseFloat(valKupon.value) || 0;
            if(valK > 0) { potongKupon = (valKupon.getAttribute('data-tipe') === 'Persentase') ? subtotal * (valK / 100) : valK; }
            
            let totalDiskon = potongManual + potongKupon;

            if(totalDiskon > 0) { document.getElementById('row_diskon').classList.remove('hidden'); document.getElementById('ringkasan_diskon').innerText = "- " + formatRupiahStr(totalDiskon); } 
            else { document.getElementById('row_diskon').classList.add('hidden'); }

            let valOngkir = unformatRupiah(inputOngkir.value);
            if(valOngkir > 0) { document.getElementById('row_ongkir').classList.remove('hidden'); document.getElementById('ringkasan_ongkir').innerText = "+ " + formatRupiahStr(valOngkir); } 
            else { document.getElementById('row_ongkir').classList.add('hidden'); }

            let finalPrice = subtotal - totalDiskon + valOngkir; if(finalPrice < 0) finalPrice = 0; document.getElementById('ringkasan_final').innerText = formatRupiahStr(finalPrice);

            let dpDibayar = 0; let tipeBayar = document.querySelector('input[name="bayar"]:checked').value;
            if(tipeBayar === 'lunas') { 
                dpDibayar = finalPrice; document.getElementById('row_dp_summary').classList.add('hidden'); document.getElementById('row_sisa_summary').classList.add('hidden');
            } else { 
                dpDibayar = unformatRupiah(inputDP.value); 
                if(dpDibayar > finalPrice) { dpDibayar = finalPrice; inputDP.value = new Intl.NumberFormat('id-ID').format(dpDibayar); }
                document.getElementById('row_dp_summary').classList.remove('hidden'); document.getElementById('row_sisa_summary').classList.remove('hidden');
            }
            
            let sisaBayar = finalPrice - dpDibayar; 
            document.getElementById('ringkasan_dp').innerText = formatRupiahStr(dpDibayar); document.getElementById('ringkasan_sisa').innerText = formatRupiahStr(sisaBayar);
        }

        boxes.forEach(box => { box.addEventListener('click', function() { let id = this.getAttribute('data-id'); let price = parseFloat(this.getAttribute('data-price')) || 0; let overlay = this.querySelector('.check-overlay');
            if(selectedBoxes.has(id)) { selectedBoxes.delete(id); priceFull -= price; overlay.classList.remove('flex'); overlay.classList.add('hidden'); this.classList.remove('border-[#58a6ff]'); this.classList.add('dark:border-[#30363d]', 'border-gray-300'); } 
            else { selectedBoxes.add(id); priceFull += price; overlay.classList.remove('hidden'); overlay.classList.add('flex'); this.classList.remove('dark:border-[#30363d]', 'border-gray-300'); this.classList.add('border-[#58a6ff]'); }
            calculateAll();
        });});
        selectTambahan.addEventListener('change', calculateAll); calculateAll();
    </script>
    <style>.custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; } .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }</style>

    <?php elseif($tab == 'data'): ?>
    <?php
        // SORTING OPTIMASI: Yang 'Selesai' otomatis tenggelam ke paling bawah, yang aktif ada di atas
        $q_orders = mysqli_query($conn, "SELECT * FROM order_bibit WHERE tipe_order='Reguler' OR tipe_order IS NULL ORDER BY CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END ASC, id DESC");
        $orders = []; while($o = mysqli_fetch_assoc($q_orders)) { $orders[] = $o; }
    ?>
    <style>
        @keyframes kedip-biru { 0% { background-color: rgba(31, 111, 235, 0.4); } 50% { background-color: transparent; } 100% { background-color: rgba(31, 111, 235, 0.4); } }
        .animasi-kedip { animation: kedip-biru 0.8s ease-in-out 3; }
    </style>
    <div class="dropdown-container">
        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchOrder" placeholder="Cari nama atau nomor HP..." class="w-full pl-9 pr-4 py-2 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#58a6ff] text-[13px]">
            </div>
            <div class="w-full md:w-64 relative">
                <select id="filterStatus" class="w-full appearance-none px-4 py-2 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#58a6ff] cursor-pointer text-[13px]">
                    <option value="all">Semua Status</option><option value="booking">Booking</option><option value="lunas">Lunas</option><option value="diambil">Diambil</option><option value="Selesai">Selesai (Arsip)</option>
                </select>
                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
            </div>
        </div>

        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="border-b border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#161b22]">
                    <tr class="text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase">
                        <th class="py-3 px-4 min-w-[150px]">CUSTOMER</th>
                        <th class="py-3 px-4">BARIS</th>
                        <th class="py-3 px-4">TGL BOOKING</th>
                        <th class="py-3 px-4">TGL AMBIL</th>
                        <th class="py-3 px-4">TOTAL</th>
                        <th class="py-3 px-4">DISKON</th>
                        <th class="py-3 px-4">ONGKIR</th>
                        <th class="py-3 px-4">DP</th>
                        <th class="py-3 px-4">SISA</th>
                        <th class="py-3 px-4 text-center">STATUS</th>
                        <th class="py-3 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d]" id="tableOrderBody">
                    <?php if(count($orders) > 0): ?>
                    <?php foreach($orders as $o): 
                        $potongan_p = $o['harga_dasar'] * ((float)$o['diskon_persen']/100);
                        $potongan_total = $potongan_p + (float)$o['diskon_nominal'];
                        
                        $txt_diskon = '-';
                        if ($potongan_total > 0) {
                            if ($o['diskon_persen'] > 0) {
                                $txt_diskon = (float)$o['diskon_persen'].'% (' . formatRp($potongan_total) . ')';
                            } else {
                                $txt_diskon = formatRp($potongan_total);
                            }
                        }

                        $txt_ongkir = $o['ongkir'] > 0 ? formatRp($o['ongkir']) : '-';
                        
                        $sisa = $o['total_harga'] - $o['dp_dibayar'];
                        $is_lunas = ($sisa <= 0 || $o['status'] == 'lunas' || $o['status'] == 'diambil' || $o['status'] == 'Selesai');
                        
                        $txt_dp = $is_lunas ? '-' : ($o['dp_dibayar'] > 0 ? '<span class="text-[#58a6ff]">'.formatRp($o['dp_dibayar']).'</span>' : '-');
                        $txt_sisa = $is_lunas ? '<span class="text-gray-400">Lunas</span>' : '<span class="text-[#f85149] font-bold">'.formatRp($sisa).'</span>';

                        $badgeDiambil = '';
                        if($o['status'] == 'Selesai') { $badgeDiambil = '<span class="bg-[#238636]/20 text-[#2ea043] px-3 py-1 rounded-full text-[10px] font-bold border border-[#238636]/30"><i class="fa-solid fa-check-double"></i> Selesai</span>'; }
                        elseif($o['status'] == 'diambil') { $badgeDiambil = '<span class="bg-gray-200 dark:bg-purple-500/20 text-gray-600 dark:text-purple-400 px-3 py-1 rounded-full text-[10px] font-bold border border-gray-300 dark:border-transparent">diambil</span>'; } 
                        elseif($o['status'] == 'lunas') { $badgeDiambil = '<span class="bg-[#f85149]/20 text-[#f85149] px-3 py-1 rounded-full text-[10px] font-bold border border-[#f85149]/30">lunas</span>'; } 
                        elseif($o['status'] == 'booking') { $badgeDiambil = '<span class="bg-[#d29922]/20 text-[#d29922] px-3 py-1 rounded-full text-[10px] font-bold border border-[#d29922]/30">booking</span>'; }
                        
                        $json = htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr id="baris-order-<?= $o['id'] ?>" data-order='<?= $json ?>' onclick="bukaDetailOrderDariBaris(this)" class="hover:bg-gray-50 dark:hover:bg-[#21262d] order-row transition-all duration-300 cursor-pointer group" data-name="<?= strtolower($o['nama_customer']) ?>" data-phone="<?= $o['no_hp'] ?>" data-status="<?= $o['status'] ?>" title="Klik untuk lihat Detail Order">
                        
                        <td class="py-3 px-4">
                            <p class="text-[13px] font-bold text-gray-900 dark:text-white mb-0.5 transition-colors"><i class="fa-regular fa-user mr-1 text-gray-400"></i> <?= htmlspecialchars($o['nama_customer']) ?></p>
                            <p class="text-[11px] text-gray-500 dark:text-[#8b949e] ml-4"><?= htmlspecialchars($o['no_hp']) ?></p>
                        </td>
                        
                        <td class="py-3 px-4"><span class="bg-[#3fb950]/20 text-[#3fb950] font-bold text-[11px] px-1.5 py-0.5 rounded border border-[#3fb950]/30">#<?= $o['id_baris'] ?></span><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mt-1.5"><?= (float)$o['panjang_m'] ?>m</p></td>
                        <td class="py-3 px-4 text-[12px] text-gray-700 dark:text-[#c9d1d9]"><?= formatTgl($o['tgl_booking']) ?></td>
                        <td class="py-3 px-4 text-[12px] text-gray-700 dark:text-[#c9d1d9]"><?= formatTgl($o['tgl_ambil']) ?></td>
                        <td class="py-3 px-4 text-[12px] font-bold text-gray-900 dark:text-white"><?= formatRp($o['total_harga']) ?></td>
                        
                        <td class="py-3 px-4 text-[12px] text-[#f85149]"><?= $txt_diskon ?></td>
                        <td class="py-3 px-4 text-[12px] text-gray-700 dark:text-gray-400"><?= $txt_ongkir ?></td>
                        <td class="py-3 px-4 text-[12px]"><?= $txt_dp ?></td>
                        <td class="py-3 px-4 text-[12px]"><?= $txt_sisa ?></td>

                        <td class="py-3 px-4 text-center"><div class="flex flex-col items-center justify-center gap-1"><?= $badgeDiambil ?></div></td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-3 relative">
                                <button type="button" onclick="event.stopPropagation(); bukaDetailOrder(JSON.parse(this.closest('tr').getAttribute('data-order')))" class="text-[#58a6ff] hover:text-blue-500 transition-colors" title="Lihat Detail"><i class="fa-regular fa-eye"></i></button>
                                
                                <?php if($o['status'] != 'Selesai'): ?>
                                <div class="relative inline-block text-left">
                                    <button type="button" onclick="event.stopPropagation(); toggleDropdown(<?= $o['id'] ?>)" class="text-[#3fb950] hover:text-green-500 transition-colors bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] px-1.5 py-0.5 rounded shadow-sm flex items-center gap-1">
                                        <i class="fa-solid fa-check text-[11px]"></i> <i class="fa-solid fa-chevron-down text-[9px]"></i>
                                    </button>
                                    <div id="dropdown-<?= $o['id'] ?>" class="action-dropdown hidden absolute right-0 mt-1 w-28 bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-md shadow-lg z-50 overflow-hidden">
                                        <?php if($o['status'] == 'booking'): ?>
                                        <a href="?page=order-bibit&tab=data&action=lunas&id=<?= $o['id'] ?>" onclick="event.stopPropagation(); return confirm('Tandai sebagai Lunas?')" class="block px-4 py-2 text-[12px] text-gray-700 dark:text-[#c9d1d9] hover:bg-gray-100 dark:hover:bg-[#30363d] text-left"><i class="fa-solid fa-check mr-2"></i> Lunas</a>
                                        <?php endif; ?>
                                        
                                        <?php if($o['status'] == 'lunas'): ?>
                                        <a href="?page=order-bibit&tab=data&action=diambil&id=<?= $o['id'] ?>&id_b=<?= $o['id_baris'] ?>" onclick="event.stopPropagation(); return confirm('Tandai Diambil? Jatah meteran baris akan diubah menjadi Kosong (Putih) di Dashboard.')" class="block px-4 py-2 text-[12px] text-gray-700 dark:text-[#c9d1d9] hover:bg-gray-100 dark:hover:bg-[#30363d] text-left"><i class="fa-solid fa-box-open mr-2"></i> Diambil</a>
                                        <?php endif; ?>
                                        
                                        <a href="?page=order-bibit&tab=data&action=cancel&id=<?= $o['id'] ?>&id_b=<?= $o['id_baris'] ?>&pjg=<?= $o['panjang_m'] ?>" onclick="event.stopPropagation(); return confirm('Cancel Order? Stok akan dikembalikan.')" class="block px-4 py-2 text-[12px] text-[#f85149] hover:bg-gray-100 dark:hover:bg-[#30363d] text-left border-t border-gray-100 dark:border-[#30363d]"><i class="fa-solid fa-xmark mr-2"></i> Cancel</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <a href="?page=order-bibit&tab=data&action=cancel&id=<?= $o['id'] ?>&id_b=<?= $o['id_baris'] ?>&pjg=<?= $o['panjang_m'] ?>" onclick="event.stopPropagation(); return confirm('Hapus order permanen?')" class="text-[#f85149] hover:text-red-500 transition-colors" title="Hapus"><i class="fa-regular fa-trash-can"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="11" class="py-8 text-center text-gray-500 text-[13px]">Belum ada data order. Silakan input dari Order Baru!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modal-detail-order" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
        <div class="bg-[#161b22] rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-[#30363d] flex flex-col max-h-[95vh]">
            <div class="px-5 py-4 flex justify-between items-center shrink-0 border-b border-[#30363d]">
                <h3 class="text-[16px] font-bold text-white">Detail Order</h3>
                <button type="button" onclick="tutupDetailOrder()" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="p-5 overflow-y-auto custom-scrollbar space-y-4">
                
                <div class="bg-[#1c2128] rounded-lg p-4 border border-[#30363d]">
                    <h4 class="text-[13px] text-gray-500 mb-3">Informasi Customer</h4>
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Nama</p><p class="text-[14px] font-bold text-white" id="det_nama">-</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">No. HP</p><p class="text-[14px] font-bold text-white" id="det_hp">-</p></div>
                    </div>
                    <div><p class="text-[12px] text-gray-500 mb-0.5">Alamat</p><p class="text-[14px] font-bold text-white" id="det_alamat">-</p></div>
                </div>

                <div class="bg-[#1c2128] rounded-lg p-4 border border-[#30363d]">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-[12px] text-gray-500">Informasi Order</h4>
                        <button type="button" onclick="bukaEditOrder()" class="text-[#58a6ff] hover:text-blue-500 text-[11px] font-bold transition-colors"><i class="fa-solid fa-pen mr-1"></i> Edit</button>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Baris</p><p class="text-[14px] font-bold text-[#3fb950]" id="det_baris">-</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Varietas</p><p class="text-[14px] font-bold text-white" id="det_var">-</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Panjang</p><p class="text-[14px] font-bold text-white" id="det_panjang">-</p></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Posisi</p><p class="text-[14px] font-bold text-white" id="det_posisi">-</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Status</p><div id="det_status_pill" class="mt-0.5"></div></div>
                    </div>
                </div>

                <div class="bg-[#1c2128] rounded-lg p-4 border border-[#30363d]">
                    <h4 class="text-[13px] text-gray-500 mb-3">Tanggal</h4>
                    <div class="grid grid-cols-3 gap-4">
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Booking</p><p class="text-[13px] font-bold text-white" id="det_tgl_booking">-</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Lunas</p><p class="text-[13px] font-bold text-white" id="det_tgl_lunas">-</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Diambil</p><p class="text-[13px] font-bold text-white" id="det_tgl_ambil">-</p></div>
                    </div>
                </div>

                <div class="bg-[#1c2128] rounded-lg p-4 border border-[#30363d]">
                    <h4 class="text-[13px] text-gray-500 mb-3">Pembayaran</h4>
                    <div class="flex justify-between items-center text-[13px] text-gray-400 mb-3"><span id="det_calc_text">Subtotal</span> <span id="det_subtotal" class="text-white">Rp 0</span></div>
                    
                    <div class="flex justify-between items-center text-[13px] text-red-400 mb-3 hidden" id="det_row_diskon"><span>Diskon</span> <span id="det_val_diskon">-Rp 0</span></div>
                    <div class="flex justify-between items-center text-[13px] text-yellow-500 mb-3 hidden" id="det_row_ongkir"><span>Ongkir</span> <span id="det_val_ongkir">+Rp 0</span></div>
                    
                    <div class="flex justify-between items-center text-[14px] pt-3 border-t border-[#30363d]">
                        <span class="font-bold text-white">Harga Final</span> 
                        <span class="font-bold text-[#3fb950]" id="det_final">Rp 0</span>
                    </div>
                    
                    <div id="det_wrap_kredit" class="hidden">
                        <div class="flex justify-between items-center text-[13px] text-[#58a6ff] mt-4 pt-3 border-t border-[#30363d] mb-2"><span>DP Dibayar</span> <span class="font-bold" id="det_val_dp">Rp 0</span></div>
                        <div class="flex justify-between items-center text-[13px] text-gray-400"><span>Sisa Bayar</span> <span class="font-bold text-[#f85149]" id="det_val_sisa">Rp 0</span></div>
                    </div>
                </div>
            </div>
            <div class="p-4 shrink-0 bg-[#161b22] border-t border-[#30363d]">
                <button onclick="tutupDetailOrder()" class="w-full bg-[#2d333b] hover:bg-[#3b434d] text-gray-300 hover:text-white py-2.5 rounded-md text-[13px] font-bold transition-colors">Tutup</button>
            </div>
        </div>
    </div>

    <div id="modal-edit-order" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col max-h-[95vh]">
            <form method="POST" action="">
                <input type="hidden" name="id_order" id="edit_id_order">
                <input type="hidden" name="edit_harga_dasar" id="edit_harga_dasar">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117] shrink-0"><h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-pen mr-2 text-[#d2a878]"></i> Edit Order - Baris <span id="edit_title_baris"></span></h3><button type="button" onclick="tutupEditOrder()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><i class="fa-solid fa-xmark text-lg"></i></button></div>
                <div class="p-5 overflow-y-auto custom-scrollbar space-y-5">
                    <div class="bg-[#1f6feb]/10 border border-[#1f6feb]/30 rounded-lg p-3"><h4 class="text-[11px] font-bold text-[#1f6feb] mb-2"><i class="fa-solid fa-file-invoice mr-1"></i> Info Order (Tidak Bisa Diubah)</h4><div class="grid grid-cols-4 gap-2"><div><p class="text-[10px] text-gray-500">Baris</p><p class="text-[12px] font-bold text-gray-900 dark:text-white" id="eo_baris">-</p></div><div><p class="text-[10px] text-gray-500">Varietas</p><p class="text-[12px] font-bold text-gray-900 dark:text-white" id="eo_var">-</p></div><div><p class="text-[10px] text-gray-500">Panjang</p><p class="text-[12px] font-bold text-gray-900 dark:text-white" id="eo_pjg">-</p></div><div><p class="text-[10px] text-gray-500">Harga Dasar</p><p class="text-[12px] font-bold text-gray-900 dark:text-white" id="eo_hd">-</p></div></div></div>
                    <div class="grid grid-cols-2 gap-4"><div><label class="block text-[11px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-1.5">Nama Customer *</label><input type="text" name="edit_nama" id="eo_nama" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none"></div><div><label class="block text-[11px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-1.5">No. HP *</label><input type="text" name="edit_hp" id="eo_hp" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none"></div></div>
                    <div><label class="block text-[11px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-1.5">Alamat Customer</label><input type="text" name="edit_alamat" id="eo_alamat" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none"></div>
                    <div class="grid grid-cols-2 gap-4"><div><label class="block text-[11px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-1.5">Tanggal Booking</label><input type="date" name="edit_tgl_booking" id="eo_tgl_b" required class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none [color-scheme:light] dark:[color-scheme:dark]"></div><div><label class="block text-[11px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-1.5">Tanggal Ambil</label><input type="date" name="edit_tgl_ambil" id="eo_tgl_a" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none [color-scheme:light] dark:[color-scheme:dark]"></div></div>
                    
                    <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Diskon</label>
                        <div class="flex gap-4 mb-2"><label class="flex items-center text-[12px] text-gray-900 dark:text-white cursor-pointer"><input type="radio" name="edit_tipe_diskon" value="persen" id="ed_tipe_p" class="mr-2"> Diskon (%)</label><label class="flex items-center text-[12px] text-gray-900 dark:text-white cursor-pointer"><input type="radio" name="edit_tipe_diskon" value="nominal" id="ed_tipe_n" class="mr-2"> Diskon (Rp)</label></div>
                        <div id="e_wrap_dp" class="relative hidden"><input type="number" name="edit_nominal_diskon_persen" id="eo_diskon_p" min="0" max="100" class="w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md pr-8 px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none"><span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[12px] font-bold">%</span></div>
                        <div id="e_wrap_dn" class="relative hidden"><span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="edit_nominal_diskon_rp" id="eo_diskon_n" class="format-rupiah w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md pl-8 px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none"></div>
                    </div>
                    <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Metode Pengambilan</label>
                        <div class="flex gap-4 mb-2"><label class="flex items-center text-[12px] text-gray-900 dark:text-white cursor-pointer"><input type="radio" name="edit_ambil" value="diambil" id="ea_ambil" class="mr-2"> Diambil (Gratis)</label><label class="flex items-center text-[12px] text-gray-900 dark:text-white cursor-pointer"><input type="radio" name="edit_ambil" value="dikirim" id="ea_kirim" class="mr-2"> Dikirim (Ongkir)</label></div>
                        <div id="eo_cont_ongkir" class="hidden relative"><span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="edit_nominal_ongkir" id="eo_ongkir" class="format-rupiah w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md pl-8 px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none mt-2"></div>
                    </div>
                    <div class="border border-gray-200 dark:border-[#30363d] rounded-lg p-4">
                        <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-3">Pembayaran <span class="font-normal text-gray-500">(Final: <span id="eo_calc_final" class="text-[#3fb950] font-bold">Rp 0</span>)</span></label>
                        <div class="flex gap-4 mb-2"><label class="flex items-center text-[12px] text-gray-900 dark:text-white cursor-pointer"><input type="radio" name="edit_bayar" value="lunas" id="eb_lunas" class="mr-2"> Lunas</label><label class="flex items-center text-[12px] text-gray-900 dark:text-white cursor-pointer"><input type="radio" name="edit_bayar" value="dp" id="eb_dp" class="mr-2"> DP</label></div>
                        <div id="eo_cont_dp" class="hidden relative"><span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="edit_nominal_dp" id="eo_dp" class="format-rupiah w-full bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md pl-8 px-3 py-2 text-[12px] focus:border-[#58a6ff] outline-none mt-2"></div>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex justify-between gap-3 shrink-0"><button type="submit" name="update_order" class="flex-1 bg-[#238636] hover:bg-[#2ea043] text-white py-2.5 rounded-md text-[13px] font-bold shadow-sm flex items-center justify-center transition-colors"><i class="fa-regular fa-floppy-disk mr-2"></i> Simpan Perubahan</button><button type="button" onclick="tutupEditOrder()" class="w-1/3 bg-gray-200 hover:bg-gray-300 dark:bg-[#30363d] dark:hover:bg-[#3b434d] text-gray-800 dark:text-white py-2.5 rounded-md text-[13px] font-bold transition-colors">Batal</button></div>
            </form>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchOrder'); const statusFilter = document.getElementById('filterStatus'); const rows = document.querySelectorAll('.order-row');
        function filterTable() { let searchVal = searchInput.value.toLowerCase(); let statusVal = statusFilter.value; rows.forEach(row => { let name = row.getAttribute('data-name'); let phone = row.getAttribute('data-phone'); let status = row.getAttribute('data-status'); let matchSearch = name.includes(searchVal) || phone.includes(searchVal); let matchStatus = (statusVal === 'all') || (status === statusVal); if(matchSearch && matchStatus) { row.style.display = ''; } else { row.style.display = 'none'; } }); }
        if(searchInput) searchInput.addEventListener('input', filterTable); if(statusFilter) statusFilter.addEventListener('change', filterTable);

        function toggleDropdown(id) { document.querySelectorAll('.action-dropdown').forEach(el => { if(el.id !== 'dropdown-'+id) el.classList.add('hidden'); }); document.getElementById('dropdown-'+id).classList.toggle('hidden'); }
        window.onclick = function(e) { if (!e.target.closest('.dropdown-container') && !e.target.closest('button[onclick^="toggleDropdown"]')) { document.querySelectorAll('.action-dropdown').forEach(el => el.classList.add('hidden')); } }

        function fTgl(dateStr) {
            if(!dateStr || dateStr === 'null' || dateStr === '0000-00-00') return '-';
            const bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
            let d = dateStr.split('-');
            if(d.length !== 3) return dateStr;
            return `${parseInt(d[2])} ${bln[parseInt(d[1])-1]} ${d[0]}`;
        }
        function fmtRp(angka) {
            return "Rp " + new Intl.NumberFormat('id-ID').format(angka);
        }

        function bukaDetailOrderDariBaris(trElement) {
            let dataRaw = trElement.getAttribute('data-order');
            if(dataRaw) {
                let order = JSON.parse(dataRaw);
                bukaDetailOrder(order);
            }
        }

        let currentOrderData = null;
        function bukaDetailOrder(order) {
            currentOrderData = order; 
            
            document.getElementById('det_nama').innerText = order.nama_customer; 
            document.getElementById('det_hp').innerText = order.no_hp; 
            document.getElementById('det_alamat').innerText = order.alamat || '-';
            
            document.getElementById('det_baris').innerText = '#' + order.id_baris; 
            document.getElementById('det_var').innerText = order.varietas; 
            document.getElementById('det_panjang').innerText = parseFloat(order.panjang_m) + 'm'; 
            document.getElementById('det_posisi').innerText = order.posisi;
            
            let pillHtml = '';
            if(order.status == 'Selesai') pillHtml = '<span class="bg-[#238636]/20 text-[#2ea043] px-3 py-1 rounded-full text-[11px] font-bold capitalize"><i class="fa-solid fa-check-double"></i> Selesai</span>';
            else if(order.status == 'diambil') pillHtml = '<span class="bg-purple-500/20 text-purple-400 px-3 py-1 rounded-full text-[11px] font-bold capitalize">diambil</span>';
            else if(order.status == 'lunas') pillHtml = '<span class="bg-[#f85149]/20 text-[#f85149] px-3 py-1 rounded-full text-[11px] font-bold capitalize">lunas</span>';
            else pillHtml = '<span class="bg-[#d29922]/20 text-[#d29922] px-3 py-1 rounded-full text-[11px] font-bold capitalize">booking</span>';
            document.getElementById('det_status_pill').innerHTML = pillHtml;
            
            document.getElementById('det_tgl_booking').innerText = fTgl(order.tgl_booking); 
            document.getElementById('det_tgl_lunas').innerText = fTgl(order.tgl_lunas); 
            document.getElementById('det_tgl_ambil').innerText = fTgl(order.tgl_ambil);
            
            let hd = parseFloat(order.harga_dasar) || 0;
            let pjg = parseFloat(order.panjang_m) || 1;
            let hrgPermeter = Math.round(hd / pjg);
            
            document.getElementById('det_calc_text').innerText = `Subtotal (${pjg}m x ${fmtRp(hrgPermeter)})`; 
            document.getElementById('det_subtotal').innerText = fmtRp(hd);
            
            let dp = parseFloat(order.diskon_persen) || 0; 
            let dn = parseFloat(order.diskon_nominal) || 0; 
            let potP = hd * (dp/100);
            let potTotal = potP + dn;
            
            if(potTotal > 0){ 
                document.getElementById('det_row_diskon').classList.remove('hidden'); 
                let txtDiskon = dp > 0 ? `${dp}% (${fmtRp(potTotal)})` : fmtRp(potTotal);
                document.getElementById('det_val_diskon').innerText = "-" + txtDiskon; 
            } else { 
                document.getElementById('det_row_diskon').classList.add('hidden'); 
            }
            
            let ok = parseFloat(order.ongkir)||0;
            if(ok>0){ document.getElementById('det_row_ongkir').classList.remove('hidden'); document.getElementById('det_val_ongkir').innerText = "+"+fmtRp(ok); } else { document.getElementById('det_row_ongkir').classList.add('hidden'); }

            let totalHarga = parseFloat(order.total_harga) || 0;
            let dpDibayar = parseFloat(order.dp_dibayar) || 0;
            let sisaBayar = totalHarga - dpDibayar;

            document.getElementById('det_final').innerText = fmtRp(totalHarga);
            
            if(sisaBayar <= 0 || order.status === 'lunas' || order.status === 'diambil' || order.status === 'Selesai') {
                document.getElementById('det_wrap_kredit').classList.add('hidden');
            } else {
                document.getElementById('det_wrap_kredit').classList.remove('hidden');
                document.getElementById('det_val_dp').innerText = fmtRp(dpDibayar);
                document.getElementById('det_val_sisa').innerText = fmtRp(sisaBayar);
            }

            document.getElementById('modal-detail-order').classList.remove('hidden');
        }
        function tutupDetailOrder() { document.getElementById('modal-detail-order').classList.add('hidden'); }

        const eRadiosDiskon = document.querySelectorAll('input[name="edit_tipe_diskon"]');
        const ewDPersen = document.getElementById('e_wrap_dp'); const ewDNominal = document.getElementById('e_wrap_dn');
        const eoDiskonP = document.getElementById('eo_diskon_p'); const eoDiskonN = document.getElementById('eo_diskon_n');

        function bukaEditOrder() {
            tutupDetailOrder(); let o = currentOrderData;
            document.getElementById('edit_id_order').value = o.id; document.getElementById('edit_harga_dasar').value = o.harga_dasar; document.getElementById('edit_title_baris').innerText = '#' + o.id_baris;
            document.getElementById('eo_baris').innerText = '#' + o.id_baris; document.getElementById('eo_var').innerText = o.varietas; document.getElementById('eo_pjg').innerText = parseFloat(o.panjang_m)+'m ('+o.posisi+')'; document.getElementById('eo_hd').innerText = fmtRp(o.harga_dasar);
            document.getElementById('eo_nama').value = o.nama_customer; document.getElementById('eo_hp').value = o.no_hp; document.getElementById('eo_alamat').value = o.alamat; document.getElementById('eo_tgl_b').value = o.tgl_booking; document.getElementById('eo_tgl_a').value = o.tgl_ambil || '';
            
            if(parseFloat(o.diskon_persen) > 0) { document.getElementById('ed_tipe_p').checked = true; eoDiskonP.value = o.diskon_persen; ewDPersen.classList.remove('hidden'); ewDNominal.classList.add('hidden'); } 
            else { document.getElementById('ed_tipe_n').checked = true; eoDiskonN.value = new Intl.NumberFormat('id-ID').format(o.diskon_nominal); ewDPersen.classList.add('hidden'); ewDNominal.classList.remove('hidden'); }
            
            if(parseFloat(o.ongkir) > 0) { document.getElementById('ea_kirim').checked = true; document.getElementById('eo_ongkir').value = new Intl.NumberFormat('id-ID').format(o.ongkir); document.getElementById('eo_cont_ongkir').classList.remove('hidden'); } 
            else { document.getElementById('ea_ambil').checked = true; document.getElementById('eo_ongkir').value = 0; document.getElementById('eo_cont_ongkir').classList.add('hidden'); }
            
            if(parseFloat(o.dp_dibayar) >= parseFloat(o.total_harga)) { document.getElementById('eb_lunas').checked = true; document.getElementById('eo_cont_dp').classList.add('hidden'); document.getElementById('eo_dp').value = 0; } 
            else { document.getElementById('eb_dp').checked = true; document.getElementById('eo_cont_dp').classList.remove('hidden'); document.getElementById('eo_dp').value = new Intl.NumberFormat('id-ID').format(o.dp_dibayar); }

            calcEditRealtime(); document.getElementById('modal-edit-order').classList.remove('hidden');
        }
        function tutupEditOrder() { document.getElementById('modal-edit-order').classList.add('hidden'); }

        const eRadiosAmbil = document.querySelectorAll('input[name="edit_ambil"]');
        eRadiosAmbil.forEach(r => r.addEventListener('change', function() { if(this.value === 'dikirim') document.getElementById('eo_cont_ongkir').classList.remove('hidden'); else { document.getElementById('eo_cont_ongkir').classList.add('hidden'); document.getElementById('eo_ongkir').value = 0; } calcEditRealtime(); }));
        
        const eRadiosBayar = document.querySelectorAll('input[name="edit_bayar"]');
        eRadiosBayar.forEach(r => r.addEventListener('change', function() { if(this.value === 'dp') document.getElementById('eo_cont_dp').classList.remove('hidden'); else { document.getElementById('eo_cont_dp').classList.add('hidden'); } calcEditRealtime(); }));

        eRadiosDiskon.forEach(r => r.addEventListener('change', function() {
            if(this.value === 'persen') { ewDPersen.classList.remove('hidden'); ewDNominal.classList.add('hidden'); eoDiskonN.value = 0; }
            else { ewDPersen.classList.add('hidden'); ewDNominal.classList.remove('hidden'); eoDiskonP.value = 0; } calcEditRealtime();
        }));

        eoDiskonP.addEventListener('input', function() { let v = parseFloat(this.value)||0; if(v>100) this.value=100; if(v<0) this.value=0; calcEditRealtime(); });
        document.getElementById('eo_diskon_n').addEventListener('input', calcEditRealtime);
        document.getElementById('eo_ongkir').addEventListener('input', calcEditRealtime);

        function calcEditRealtime() {
            let hd = parseFloat(document.getElementById('edit_harga_dasar').value) || 0;
            let tipe_d = document.querySelector('input[name="edit_tipe_diskon"]:checked').value;
            let pot = (tipe_d === 'persen') ? hd*(parseFloat(eoDiskonP.value||0)/100) : parseInt(eoDiskonN.value.toString().replace(/[^0-9]/g, '')) || 0;
            let ok = parseInt(document.getElementById('eo_ongkir').value.toString().replace(/[^0-9]/g, '')) || 0;
            let final = hd - pot + ok; if(final < 0) final = 0; document.getElementById('eo_calc_final').innerText = fmtRp(final);
        }

        window.addEventListener('DOMContentLoaded', (event) => {
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('highlight');
            if (highlightId) {
                let targetRow = document.getElementById('baris-order-' + highlightId);
                if (targetRow) {
                    document.getElementById('searchOrder').value = ''; document.getElementById('filterStatus').value = 'all'; if(typeof filterTable === "function") filterTable();
                    targetRow.scrollIntoView({ behavior: "smooth", block: "center" });
                    targetRow.classList.add('animasi-kedip');
                    window.history.replaceState({}, document.title, "?page=order-bibit&tab=data");
                }
            }
        });
    </script>

    <?php elseif($tab == 'aktif'): ?>
    <!-- ==================== TABEL TRANSAKSI AKTIF ==================== -->
    <?php
        $q_rekap = mysqli_query($conn, "
            SELECT 
                nama_customer, 
                no_hp, 
                alamat,
                COUNT(id) as jml_transaksi, 
                SUM(total_harga) as total_belanja, 
                SUM(dp_dibayar) as total_dibayar,
                SUM(total_harga - dp_dibayar) as sisa_tagihan,
                GROUP_CONCAT(CONCAT('&bull; <span class=\"text-[#58a6ff] font-bold\">#', id_baris, '</span> <b>', varietas, '</b> (', (panjang_m + 0), 'm)') SEPARATOR '<br>') as detail_barang
            FROM order_bibit 
            WHERE status NOT IN ('Selesai', 'Batal')
            GROUP BY nama_customer, no_hp 
            ORDER BY nama_customer ASC
        ");
    ?>
    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-5 rounded-xl shadow-sm overflow-hidden animate-in fade-in zoom-in duration-300">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-users text-[#3fb950] mr-2"></i> Daftar Transaksi Aktif</h2>
                <p class="text-[12px] text-gray-500 mt-1">Rangkuman transaksi yang belum diselesaikan (diarsipkan).</p>
            </div>
            
            <div class="relative w-full md:w-64 shrink-0">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchAktif" placeholder="Cari pelanggan..." class="w-full pl-9 pr-4 py-2 bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#3fb950] text-[13px] shadow-sm">
            </div>
        </div>
        
        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="bg-gray-50 dark:bg-[#0d1117] border-b border-gray-200 dark:border-[#30363d]">
                    <tr class="text-[10px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-12 text-center">NO</th>
                        <th class="py-3.5 px-4">DATA PELANGGAN</th>
                        <th class="py-3.5 px-4">DETAIL BARANG BELANJA</th>
                        <th class="py-3.5 px-4 text-center">TOTAL NOTA</th>
                        <th class="py-3.5 px-4 text-right">AKUMULASI BELANJA</th>
                        <th class="py-3.5 px-4 text-right">TOTAL TERBAYAR</th>
                        <th class="py-3.5 px-4 text-right">SISA BAYAR</th>
                        <th class="py-3.5 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-[12px] bg-white dark:bg-[#161b22]">
                    <?php if(mysqli_num_rows($q_rekap) > 0): ?>
                        <?php $no = 1; while($rk = mysqli_fetch_assoc($q_rekap)): 
                            $hutang = $rk['sisa_tagihan'];
                            $warna_hutang = $hutang > 0 ? 'text-[#f85149] font-bold' : 'text-[#3fb950] font-bold';
                            $teks_hutang = $hutang > 0 ? formatRp($hutang) : '<i class="fa-solid fa-check-circle"></i> LUNAS SEMUA';
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors baris-aktif" data-nama="<?= strtolower(htmlspecialchars($rk['nama_customer'])) ?>">
                            <td class="py-4 px-4 text-center text-gray-500 font-bold" style="vertical-align: top; padding-top: 20px;"><?= $no++ ?></td>
                            <td class="py-4 px-4" style="vertical-align: top; padding-top: 18px;">
                                <p class="font-bold text-gray-900 dark:text-white text-[13px] mb-0.5"><?= ucwords(htmlspecialchars($rk['nama_customer'])) ?></p>
                                <p class="text-[10px] text-gray-500"><i class="fa-solid fa-phone mr-1"></i> <?= htmlspecialchars($rk['no_hp']) ?: 'Tidak ada no HP' ?></p>
                            </td>
                            <td class="py-4 px-4 text-[11px] text-gray-700 dark:text-[#c9d1d9] leading-relaxed" style="vertical-align: top; padding-top: 18px;">
                                <?= $rk['detail_barang'] ?>
                            </td>
                            <td class="py-4 px-4 text-center" style="vertical-align: top; padding-top: 18px;">
                                <span class="bg-[#3fb950]/10 text-[#3fb950] border border-[#3fb950]/20 px-2 py-1 rounded font-bold"><?= $rk['jml_transaksi'] ?> Transaksi</span>
                            </td>
                            <td class="py-4 px-4 text-right font-bold text-gray-800 dark:text-white" style="vertical-align: top; padding-top: 18px;">
                                <?= formatRp($rk['total_belanja']) ?>
                            </td>
                            <td class="py-4 px-4 text-right text-gray-600 dark:text-gray-400" style="vertical-align: top; padding-top: 18px;">
                                <?= formatRp($rk['total_dibayar']) ?>
                            </td>
                            <td class="py-4 px-4 text-right <?= $warna_hutang ?> text-[13px]" style="vertical-align: top; padding-top: 18px;">
                                <?= $teks_hutang ?>
                            </td>
                            <td class="py-4 px-4 text-center" style="vertical-align: top; padding-top: 16px;">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if($hutang > 0): ?>
                                        <a href="?page=order-bibit&action=lunas_aktif&hp=<?= urlencode($rk['no_hp']) ?>&nama=<?= urlencode($rk['nama_customer']) ?>" onclick="return confirm('PENTING: Yakin ingin melunasi sisa tagihan <?= ucwords(htmlspecialchars($rk['nama_customer'])) ?> sejumlah Rp <?= formatRp($hutang) ?>?')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#d29922] hover:bg-[#b0801b] text-white rounded text-[11px] font-bold transition-colors shadow-sm">
                                            <i class="fa-solid fa-money-bill-wave"></i> Lunasi
                                        </a>
                                    <?php else: ?>
                                        <a href="?page=order-bibit&action=arsipkan_aktif&hp=<?= urlencode($rk['no_hp']) ?>&nama=<?= urlencode($rk['nama_customer']) ?>" onclick="return confirm('PENTING: Apakah Anda sudah MENCETAK struk bukti lunas? \n\nKlik OK untuk membersihkan data ini dari layar Transaksi Aktif dan memindahkannya ke Arsip (Data Order).')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#238636] hover:bg-[#2ea043] text-white rounded text-[11px] font-bold transition-colors shadow-sm">
                                            <i class="fa-solid fa-check-double"></i> Selesaikan
                                        </a>
                                    <?php endif; ?>
                                    <a href="cetak/invoice-bibit.php?hp=<?= urlencode($rk['no_hp']) ?>&nama=<?= urlencode($rk['nama_customer']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#0F314F] hover:bg-[#1a4a75] text-white rounded text-[11px] font-bold transition-colors shadow-sm">
                                        <i class="fa-solid fa-print"></i> Cetak
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-10 text-gray-500 italic">Belum ada riwayat transaksi aktif.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        const searchAktif = document.getElementById('searchAktif');
        const barisAktif = document.querySelectorAll('.baris-aktif');
        if(searchAktif) {
            searchAktif.addEventListener('input', function() {
                let val = this.value.toLowerCase();
                barisAktif.forEach(row => {
                    let nama = row.getAttribute('data-nama');
                    row.style.display = nama.includes(val) ? '' : 'none';
                });
            });
        }
    </script>
    
    <?php endif; ?>

    <div id="modal-detail-pemesan" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
        <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col max-h-[90vh]">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117] shrink-0">
                <h3 class="text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-layer-group text-[#58a6ff] mr-2"></i> Status Baris <span id="dp_title_baris"></span></h3>
                <button type="button" onclick="document.getElementById('modal-detail-pemesan').classList.add('hidden')" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="p-5 overflow-y-auto custom-scrollbar space-y-4">
                <div class="bg-gray-50 dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex justify-between items-center">
                    <div>
                        <p class="text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase mb-1">Sisa Lahan Free</p>
                        <p class="text-[18px] font-bold text-[#3fb950]" id="dp_sisa_free">0m</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase mb-1">Lahan Kosong</p>
                        <p class="text-[15px] font-bold text-gray-700 dark:text-gray-400" id="dp_sisa_kosong">0m</p>
                    </div>
                </div>
                <div>
                    <h4 class="text-[13px] font-bold text-gray-900 dark:text-white mb-3 flex items-center"><i class="fa-solid fa-users mr-2 text-gray-400"></i> Daftar Pemesan Aktif</h4>
                    <div id="dp_buyer_list" class="space-y-3"></div>
                </div>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex justify-end shrink-0">
                <button type="button" onclick="document.getElementById('modal-detail-pemesan').classList.add('hidden')" class="w-full bg-gray-200 hover:bg-gray-300 dark:bg-[#30363d] dark:hover:bg-[#3b434d] text-gray-800 dark:text-white py-2.5 rounded-md text-[13px] font-bold transition-colors">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        const activeOrdersMap = <?= json_encode($active_orders_map ?? []) ?>;

        function bukaDetailPemesan(id_baris, m_free, m_kosong) {
            document.getElementById('dp_title_baris').innerText = '#' + id_baris;
            document.getElementById('dp_sisa_free').innerText = m_free + 'm';
            document.getElementById('dp_sisa_kosong').innerText = m_kosong + 'm';
            
            let orders = activeOrdersMap[id_baris] || [];
            let listContainer = document.getElementById('dp_buyer_list');
            let html = '';

            if(orders.length === 0) {
                html = '<p class="text-[12px] text-gray-500 dark:text-[#8b949e] italic text-center py-4 border border-dashed border-gray-300 dark:border-[#30363d] rounded-lg">Belum ada pemesan di baris ini.</p>';
            } else {
                orders.forEach(o => {
                    let st = o.status.toLowerCase();
                    let badgeStatus = (st === 'lunas' || st === 'persiapan' || st === 'tanam')
                        ? '<span class="bg-[#f85149]/10 text-[#f85149] border border-[#f85149]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">'+o.status+'</span>' 
                        : '<span class="bg-[#d29922]/10 text-[#d29922] border border-[#d29922]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">'+o.status+'</span>';
                    
                    let isJasaTanam = (o.tipe_order === 'Jasa Tanam');
                    
                    let badgeTipe = isJasaTanam 
                        ? '<span class="bg-[#1f6feb]/10 text-[#58a6ff] border border-[#1f6feb]/30 px-2 py-0.5 rounded text-[10px] font-bold"><i class="fa-solid fa-person-digging"></i> Jasa Tanam</span>' 
                        : '<span class="bg-gray-500/10 text-gray-400 border border-gray-500/30 px-2 py-0.5 rounded text-[10px] font-bold"><i class="fa-solid fa-box"></i> Reguler</span>';
                    
                    let linkDetail = isJasaTanam 
                        ? '?page=jasa-tanam&tab=data&highlight=' + o.no_order 
                        : '?page=order-bibit&tab=data&highlight=' + o.id;

                    html += `
                    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-3.5 rounded-lg flex justify-between items-center shadow-sm">
                        <div>
                            <div class="flex items-center gap-2 mb-1"><p class="text-[13px] font-bold text-gray-900 dark:text-white">${o.nama_customer}</p> ${badgeTipe}</div>
                            <p class="text-[11px] text-gray-600 dark:text-gray-400 flex items-center gap-2"><span><i class="fa-solid fa-ruler-horizontal mr-1"></i> ${parseFloat(o.panjang_m)}m</span> ${badgeStatus}</p>
                        </div>
                        <a href="${linkDetail}" class="text-[#58a6ff] hover:text-blue-500 text-[11px] font-bold transition-colors shrink-0"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Detail</a>
                    </div>`;
                });
            }
            listContainer.innerHTML = html;
            document.getElementById('modal-detail-pemesan').classList.remove('hidden');
        }

        function loncatKeData(id_order) {
            window.location.href = '?page=order-bibit&tab=data&highlight=' + id_order;
        }
    </script>
</div>