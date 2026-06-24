<?php
include __DIR__ . '/../components/koneksi.php';

$tgl_hari_ini = date('Y-m-d');
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'baru'; 

// =========================================================================
// FUNGSI BANTUAN GLOBAL
// =========================================================================
if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}
function getRomawi($bulan){
    $map = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'];
    return $map[(int)$bulan];
}

// =========================================================================
// 1. ENGINE UPDATE STATUS & STOK (AUTO-REFRESH) - SUDAH FIX MULTI-ITEM
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['no_order']) && isset($_GET['status_baru'])) {
    $no_order = mysqli_real_escape_string($conn, $_GET['no_order']);
    $status_baru = mysqli_real_escape_string($conn, $_GET['status_baru']);

    // Cek status saat ini dari salah satu baris
    $q_old = mysqli_query($conn, "SELECT status FROM order_pupuk WHERE no_order='$no_order' LIMIT 1");
    $order_lama = mysqli_fetch_assoc($q_old);

    if ($order_lama) {
        $status_sebelumnya = $order_lama['status'];

        if (in_array($status_baru, ['Lunas', 'Selesai'])) {
            mysqli_query($conn, "UPDATE order_pupuk SET status='$status_baru', nominal_pelunasan=(total_harga - dp_dibayar), tgl_lunas='$tgl_hari_ini' WHERE no_order='$no_order'");
        } 
        else if ($status_baru == 'Batal') {
            if ($status_sebelumnya != 'Batal') { 
                // Kembalikan SEMUA stok ke gudang jika dibatalkan
                $q_items = mysqli_query($conn, "SELECT id_barang, tipe_item, qty FROM order_pupuk WHERE no_order='$no_order'");
                while($itm = mysqli_fetch_assoc($q_items)){
                    if ($itm['tipe_item'] == 'Paket') {
                        $q_pi = mysqli_query($conn, "SELECT id_barang, qty FROM paket_pupuk_item WHERE id_paket='{$itm['id_barang']}'");
                        while($pi = mysqli_fetch_assoc($q_pi)) {
                            $jml_balik = $pi['qty'] * $itm['qty'];
                            mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok + $jml_balik WHERE id='{$pi['id_barang']}'");
                        }
                    } else {
                        mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok + {$itm['qty']} WHERE id='{$itm['id_barang']}'");
                    }
                }
            }
            mysqli_query($conn, "UPDATE order_pupuk SET status='Batal' WHERE no_order='$no_order'");
        } 
        else {
            if ($status_sebelumnya == 'Batal') {
                // Tarik kembali SEMUA stok dari gudang jika tadinya batal lalu dihidupkan lagi
                $q_items = mysqli_query($conn, "SELECT id_barang, tipe_item, qty FROM order_pupuk WHERE no_order='$no_order'");
                while($itm = mysqli_fetch_assoc($q_items)){
                    if ($itm['tipe_item'] == 'Paket') {
                        $q_pi = mysqli_query($conn, "SELECT id_barang, qty FROM paket_pupuk_item WHERE id_paket='{$itm['id_barang']}'");
                        while($pi = mysqli_fetch_assoc($q_pi)) {
                            $jml_potong = $pi['qty'] * $itm['qty'];
                            mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok - $jml_potong WHERE id='{$pi['id_barang']}'");
                        }
                    } else {
                        mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok - {$itm['qty']} WHERE id='{$itm['id_barang']}'");
                    }
                }
            }
            mysqli_query($conn, "UPDATE order_pupuk SET status='$status_baru' WHERE no_order='$no_order'");
        }
        echo "<script>window.location.href='?page=order-pupuk&tab=data&highlight=$no_order';</script>";
        exit;
    }
}

// =========================================================================
// 1.5 AUTO-CREATE KOLOM BARU (DP & PELUNASAN)
// =========================================================================
$cek_kolom_pupuk = mysqli_query($conn, "SHOW COLUMNS FROM `order_pupuk` LIKE 'nominal_pelunasan'");
if(mysqli_num_rows($cek_kolom_pupuk) == 0) {
    mysqli_query($conn, "ALTER TABLE `order_pupuk` ADD `nominal_pelunasan` int(11) DEFAULT 0 AFTER `dp_dibayar`, ADD `tgl_lunas` date DEFAULT NULL AFTER `nominal_pelunasan`");
}
$cek_kolom_tipe = mysqli_query($conn, "SHOW COLUMNS FROM `order_pupuk` LIKE 'tipe_item'");
if(mysqli_num_rows($cek_kolom_tipe) == 0) {
    mysqli_query($conn, "ALTER TABLE `order_pupuk` ADD `tipe_item` varchar(20) DEFAULT 'Satuan' AFTER `id_barang`");
}

// =========================================================================
// 2. PROSES SIMPAN ORDER PUPUK & OBAT BARU (SISTEM KERANJANG)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_order_pupuk'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_customer']); 
    $hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']); 
    $ket = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Array dari keranjang belanja
    $id_barangs = $_POST['id_barang'];
    $qtys = $_POST['qty'];

    if (empty($id_barangs) || $id_barangs[0] == "") { 
        echo "<script>alert('Gagal! Keranjang masih kosong.'); window.history.back();</script>"; exit; 
    }

    $items_to_insert = [];
    $grand_subtotal = 0;

    // Validasi Stok dan Kumpulkan Data Barang
    for($i = 0; $i < count($id_barangs); $i++) {
        $val_raw = $id_barangs[$i];
        $qty_b = (int)$qtys[$i];

        if($val_raw != "" && $qty_b > 0) {
            if (strpos($val_raw, 'P_') === 0) {
                // Tipe Paket
                $id_p = (int)str_replace('P_', '', $val_raw);
                $q_pkt = mysqli_query($conn, "SELECT nama, harga FROM paket_pupuk WHERE id='$id_p'");
                $pkt = mysqli_fetch_assoc($q_pkt);
                
                // Cek stok isi paket
                $q_items = mysqli_query($conn, "SELECT ppi.qty as pkt_qty, sp.stok, sp.nama FROM paket_pupuk_item ppi JOIN stok_pupuk sp ON ppi.id_barang = sp.id WHERE ppi.id_paket='$id_p'");
                $items_in_paket = [];
                while($itm = mysqli_fetch_assoc($q_items)) {
                    $butuh_stok = $itm['pkt_qty'] * $qty_b;
                    if($itm['stok'] < $butuh_stok) {
                        echo "<script>alert('Gagal! Stok ".$itm['nama']." tidak mencukupi untuk meracik paket ini.'); window.history.back();</script>"; exit; 
                    }
                    $items_in_paket[] = ['id_barang' => $itm['id_barang'], 'butuh_qty' => $butuh_stok];
                }

                $harga_satuan = (float)$pkt['harga'];
                $sub = $harga_satuan * $qty_b;
                $grand_subtotal += $sub;

                $items_to_insert[] = [
                    'tipe_item' => 'Paket',
                    'id' => $id_p,
                    'nama' => mysqli_real_escape_string($conn, $pkt['nama']),
                    'qty' => $qty_b,
                    'harga' => $harga_satuan,
                    'subtotal' => $sub
                ];
            } else {
                // Tipe Satuan
                $id_b = (int)str_replace('S_', '', $val_raw);
                $q_brg = mysqli_query($conn, "SELECT nama, harga, stok FROM stok_pupuk WHERE id='$id_b'");
                $brg = mysqli_fetch_assoc($q_brg);
                
                if($brg['stok'] < $qty_b) { 
                    echo "<script>alert('Gagal! Stok ".$brg['nama']." tersisa hanya ".$brg['stok'].".'); window.history.back();</script>"; exit; 
                }

                $harga_satuan = (float)$brg['harga'];
                $sub = $harga_satuan * $qty_b;
                $grand_subtotal += $sub;

                $items_to_insert[] = [
                    'tipe_item' => 'Satuan',
                    'id' => $id_b,
                    'nama' => mysqli_real_escape_string($conn, $brg['nama']),
                    'qty' => $qty_b,
                    'harga' => $harga_satuan,
                    'subtotal' => $sub
                ];
            }
        }
    }

    if(count($items_to_insert) == 0) { echo "<script>alert('Gagal! Tidak ada barang valid.'); window.history.back();</script>"; exit; }

    // GENERATOR INVOICE OTOMATIS
    $tahun = date('Y');
    $bulan_romawi = getRomawi(date('m'));
    
    $q_max = mysqli_query($conn, "SELECT no_order FROM order_pupuk ORDER BY id DESC LIMIT 1");
    if(mysqli_num_rows($q_max) > 0) {
        $last_order = mysqli_fetch_assoc($q_max)['no_order'];
        $parts = explode('/', $last_order);
        $next_id = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
    } else {
        $next_id = 1;
    }
    $nomor_urut = str_pad($next_id, 4, '0', STR_PAD_LEFT);
    
    $no_order = "INV-PUPUKDANOBAT/PCT/$nomor_urut/$bulan_romawi/$tahun";
    $tgl_order = date('Y-m-d H:i:s');
    
    // Perhitungan Diskon Global
    $diskon_p = 0; $diskon_n = 0;
    if (isset($_POST['is_diskon'])) {
        if ($_POST['tipe_diskon'] == 'persen') { $diskon_p = (float)$_POST['nominal_diskon_persen']; } 
        else { $diskon_n = (int)str_replace('.', '', $_POST['nominal_diskon_rp']); }
    }
    
    $kode_dipakai = isset($_POST['kode_kupon_dipakai']) ? mysqli_real_escape_string($conn, $_POST['kode_kupon_dipakai']) : '';
    if($kode_dipakai != '') {
        $q_kup = mysqli_query($conn, "SELECT id, tipe, nilai FROM kupon_diskon WHERE kode='$kode_dipakai'");
        if($kup = mysqli_fetch_assoc($q_kup)) {
            if($kup['tipe'] == 'Persentase') { $diskon_p += (float)$kup['nilai']; } else { $diskon_n += (float)$kup['nilai']; }
        }
    }

    $total_diskon = ($grand_subtotal * ($diskon_p / 100)) + $diskon_n;
    $ongkir = isset($_POST['ambil']) && $_POST['ambil'] == 'dikirim' ? (int)str_replace('.', '', $_POST['nominal_ongkir']) : 0;
    
    $final_total = $grand_subtotal - $total_diskon + $ongkir;
    if($final_total < 0) $final_total = 0;

    $dp_total = isset($_POST['bayar']) && $_POST['bayar'] == 'dp' ? (int)str_replace('.', '', $_POST['nominal_dp']) : $final_total;
    $status_init = ($dp_total >= $final_total) ? 'Lunas' : 'DP';

    // Variabel sisa untuk last item fix rounding
    $sisa_diskon_n = $diskon_n;
    $sisa_ongkir = $ongkir;
    $sisa_dp = $dp_total;
    $sisa_final = $final_total;
    
    $count_items = count($items_to_insert);
    $idx = 0;

    // Insert Database (Looping per barang di keranjang)
    foreach($items_to_insert as $itm) {
        $idx++;
        if ($idx == $count_items) {
            $item_diskon_n = $sisa_diskon_n;
            $item_ongkir = $sisa_ongkir;
            $item_dp = $sisa_dp;
            $item_final = $sisa_final;
        } else {
            $rasio = ($grand_subtotal > 0) ? ($itm['subtotal'] / $grand_subtotal) : 1;
            
            $item_diskon_n = round($diskon_n * $rasio);
            $item_ongkir = round($ongkir * $rasio);
            $item_dp = round($dp_total * $rasio);
            
            $item_final = $itm['subtotal'] - ($itm['subtotal'] * ($diskon_p / 100)) - $item_diskon_n + $item_ongkir;
            if($item_final < 0) $item_final = 0;
            
            $sisa_diskon_n -= $item_diskon_n;
            $sisa_ongkir -= $item_ongkir;
            $sisa_dp -= $item_dp;
            $sisa_final -= $item_final;
        }

        mysqli_query($conn, "INSERT INTO order_pupuk 
            (no_order, tgl_order, nama_customer, no_hp, alamat, id_barang, tipe_item, nama_barang, qty, harga_satuan, diskon_persen, diskon_nominal, ongkir, total_harga, dp_dibayar, status, keterangan) 
            VALUES ('$no_order', '$tgl_order', '$nama', '$hp', '$alamat', '{$itm['id']}', '{$itm['tipe_item']}', '{$itm['nama']}', '{$itm['qty']}', '{$itm['harga']}', '$diskon_p', '$item_diskon_n', '$item_ongkir', '$item_final', '$item_dp', '$status_init', '$ket')");

        // Kurangi Stok
        if ($itm['tipe_item'] == 'Paket') {
            $q_items = mysqli_query($conn, "SELECT id_barang, qty FROM paket_pupuk_item WHERE id_paket='{$itm['id']}'");
            while($pi = mysqli_fetch_assoc($q_items)) {
                $jml_potong = $pi['qty'] * $itm['qty'];
                mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok - $jml_potong WHERE id='{$pi['id_barang']}'");
            }
        } else {
            mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok - {$itm['qty']} WHERE id='{$itm['id']}'");
        }
    }

    echo "<script>alert('Berhasil! Order Pupuk & Obat $no_order tersimpan.'); window.location.href='?page=order-pupuk&tab=data';</script>"; exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'hapus_permanen' && isset($_GET['no_order'])) {
    $no_order = mysqli_real_escape_string($conn, $_GET['no_order']);
    
    // Kembalikan stok gudang (Semua Barang)
    $q_o = mysqli_query($conn, "SELECT id_barang, tipe_item, qty, status FROM order_pupuk WHERE no_order='$no_order'");
    while($o = mysqli_fetch_assoc($q_o)) {
        if ($o['status'] != 'Batal') {
            if ($o['tipe_item'] == 'Paket') {
                $q_items = mysqli_query($conn, "SELECT id_barang, qty FROM paket_pupuk_item WHERE id_paket='{$o['id_barang']}'");
                while($pi = mysqli_fetch_assoc($q_items)) {
                    $jml_balik = $pi['qty'] * $o['qty'];
                    mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok + $jml_balik WHERE id='{$pi['id_barang']}'");
                }
            } else {
                mysqli_query($conn, "UPDATE stok_pupuk SET stok = stok + {$o['qty']} WHERE id='{$o['id_barang']}'");
            }
        }
    }
    mysqli_query($conn, "DELETE FROM order_pupuk WHERE no_order='$no_order'");
    echo "<script>alert('Nota penjualan dihapus permanen!'); window.location.href='?page=order-pupuk&tab=data';</script>"; exit;
}

// Data Barang untuk Dropdown
$q_stok = mysqli_query($conn, "SELECT * FROM stok_pupuk WHERE stok > 0 ORDER BY nama ASC");
$opsi_html = '<option value="" data-harga="0" data-stok="0" data-tipe="none">-- Pilih Produk / Paket --</option>';

$opsi_html .= '<optgroup label="Paket Bundling">';
$q_paket = mysqli_query($conn, "SELECT * FROM paket_pupuk ORDER BY nama ASC");
if($q_paket) {
    while($rp = mysqli_fetch_assoc($q_paket)) {
        // Asumsikan stok paket "unlimited" di UI, tervalidasi di backend
        $opsi_html .= '<option value="P_'.$rp['id'].'" data-harga="'.$rp['harga'].'" data-stok="99999" data-tipe="paket">[PAKET] '.$rp['nama'].'</option>';
    }
}
$opsi_html .= '</optgroup>';

$opsi_html .= '<optgroup label="Barang Satuan">';
while($r = mysqli_fetch_assoc($q_stok)) { 
    $opsi_html .= '<option value="S_'.$r['id'].'" data-harga="'.$r['harga'].'" data-stok="'.$r['stok'].'" data-tipe="satuan">'.$r['nama'].' (Stok: '.$r['stok'].' '.$r['satuan'].')</option>'; 
}
$opsi_html .= '</optgroup>';
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    <div class="flex items-center gap-3 mb-6">
        <div>
            <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]"><i class="fa-solid fa-flask text-orange-500 mr-3"></i> Transaksi Pupuk & Obat</h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-0.5 ml-8">Kasir cepat penjualan barang non-benih</p>
        </div>
    </div>

    <div class="flex gap-6 border-b border-gray-200 dark:border-[#30363d] mb-6 px-2 overflow-x-auto">
        <a href="?page=order-pupuk&tab=baru" class="border-b-2 <?= $tab=='baru' ? 'border-[#58a6ff] text-[#58a6ff]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-solid fa-plus mr-1.5"></i> Order Baru</a>
        <a href="?page=order-pupuk&tab=data" class="border-b-2 <?= $tab=='data' ? 'border-[#58a6ff] text-[#58a6ff]' : 'border-transparent text-gray-500 dark:text-[#8b949e] hover:text-gray-700 dark:hover:text-gray-200' ?> pb-3 text-[13px] font-bold whitespace-nowrap transition-colors"><i class="fa-regular fa-folder-open mr-1.5"></i> Data Order</a>
    </div>

    <?php if($tab == 'baru'): ?>
    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-5 rounded-xl shadow-sm">
        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-5"><i class="fa-solid fa-file-signature text-[#58a6ff] mr-2"></i> Input Formulir Order Barang</h2>
        <form method="POST" action="">
            <input type="hidden" name="kode_kupon_dipakai" id="h_kode_kupon" value="">
            <input type="hidden" id="h_nilai_kupon" value="0">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-4">
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Nama Customer <span class="text-red-500">*</span></label><input type="text" name="nama_customer" placeholder="Nama lengkap" required class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                <div><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">No. HP / WA</label><input type="text" name="no_hp" placeholder="08xxxxxxxxxx" class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
            </div>
            <div class="mb-5"><label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Alamat Pengiriman / Customer</label><input type="text" name="alamat" placeholder="Alamat lengkap tinggal" class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
            
            <div class="col-span-full border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-gray-50 dark:bg-[#0d1117] mb-5">
                <label class="block text-[12px] font-bold text-gray-500 uppercase mb-3"><i class="fa-solid fa-box-open text-[#58a6ff] mr-1"></i> Keranjang Belanja Produk <span class="text-red-500">*</span></label>
                
                <div id="cart-container" class="space-y-3">
                    <div class="cart-row flex items-center gap-3">
                        <div class="flex-1">
                            <select name="id_barang[]" required class="produk-select w-full bg-white dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]">
                                <?= $opsi_html ?>
                            </select>
                        </div>
                        <div class="w-24">
                            <input type="number" name="qty[]" min="1" value="1" required class="qty-input w-full bg-white dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#58a6ff]">
                        </div>
                        <div class="w-8 flex justify-center">
                            </div>
                    </div>
                </div>
                
                <button type="button" onclick="tambahBaris()" class="mt-4 text-[12px] font-bold text-[#58a6ff] hover:text-blue-600 transition-colors flex items-center gap-1">
                    <i class="fa-solid fa-circle-plus"></i> Tambah Produk Lain
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <div class="flex items-center justify-between mb-3"><label class="text-[12px] font-bold text-gray-500 uppercase">Diskon Manual</label><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="tog_diskon" name="is_diskon" class="sr-only peer"><div class="w-8 h-4 bg-gray-300 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all dark:border-gray-600 peer-checked:bg-[#58a6ff]"></div></label></div>
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
                    <div class="flex flex-col gap-2 text-[13px] mb-2"><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="ambil" value="diambil" checked class="mr-2"> Ambil / Bawa Sendiri</label><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="ambil" value="dikirim" class="mr-2"> Dikirim Kurir</label></div>
                    <div id="cont_ongkir" class="hidden relative pt-2 border-t border-gray-200 dark:border-[#30363d]"><span class="absolute left-3 top-4 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="nominal_ongkir" id="in_ongkir" placeholder="Biaya Ongkir" class="format-rupiah w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] pl-8 text-gray-900 dark:text-white"></div>
                </div>
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Sistem Bayar</label>
                    <div class="flex flex-col gap-2 text-[13px] mb-2"><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="bayar" value="lunas" checked class="mr-2"> Bayar Lunas</label><label class="cursor-pointer text-gray-900 dark:text-white"><input type="radio" name="bayar" value="dp" class="mr-2"> Uang Muka </label></div>
                    <div id="cont_dp" class="hidden relative pt-2 border-t border-gray-200 dark:border-[#30363d]"><span class="absolute left-3 top-4 text-gray-500 text-[12px] font-bold">Rp</span><input type="text" name="nominal_dp" id="in_dp" placeholder="Nominal Uang Muka" class="format-rupiah w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] rounded p-2 text-[12px] pl-8 text-gray-900 dark:text-white"></div>
                </div>
                <div class="border border-gray-200 dark:border-[#30363d] p-4 rounded-lg bg-white dark:bg-[#161b22]">
                    <label class="block text-[12px] font-bold text-gray-500 uppercase mb-2">Catatan Keterangan</label>
                    <textarea name="keterangan" rows="3" placeholder="Catatan opsional..." class="w-full bg-gray-50 dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded p-2 text-[12px] focus:outline-none resize-none"></textarea>
                </div>
            </div>

            <div class="border-t-4 border-orange-500 bg-white dark:bg-[#161b22] border-x border-b border-gray-200 dark:border-[#30363d] p-5 rounded-b-lg mb-6 text-[13px]">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4">Ringkasan Faktur Belanja</h3>
                <div class="flex justify-between mb-2 text-gray-600 dark:text-[#c9d1d9]"><span>Barang Terpilih:</span> <span id="r_nama_barang" class="font-medium text-right w-2/3 truncate">-</span></div>
                <div class="flex justify-between mb-2 text-gray-600 dark:text-[#c9d1d9]"><span>Total Barang:</span> <span id="r_qty" class="font-bold">1 Macam</span></div>
                
                <div class="flex justify-between mb-2 text-gray-800 dark:text-white font-bold pt-2 border-t border-gray-300 dark:border-[#30363d]"><span>Subtotal:</span> <span id="r_sub">Rp 0</span></div>
                <div class="flex justify-between mb-2 text-red-500 hidden" id="r_row_dis"><span>Potongan Diskon:</span> <span id="r_dis">- Rp 0</span></div>
                <div class="flex justify-between mb-2 text-yellow-500 hidden" id="r_row_ong"><span>Biaya Ongkir:</span> <span id="r_ong">+ Rp 0</span></div>
                
                <div class="flex justify-between pt-3 border-t border-gray-300 dark:border-[#30363d] text-base font-bold text-orange-500"><span>TOTAL HARGA AKHIR:</span> <span id="r_final">Rp 0</span></div>
                
                <div class="flex justify-between items-center text-[13px] text-[#1f6feb] dark:text-[#58a6ff] mt-2 pt-2 border-t border-gray-300 dark:border-[#30363d] hidden" id="r_row_dp"><span>Telah Dibayar:</span> <span id="r_dp">Rp 0</span></div>
                <div class="flex justify-between items-center text-[13px] text-[#f85149] mt-2 hidden" id="r_row_sisa"><span>Sisa:</span> <span id="r_sisa" class="font-bold">Rp 0</span></div>
            </div>

            <button type="submit" name="simpan_order_pupuk" class="w-full bg-[#238636] hover:bg-[#2ea043] text-white py-3 rounded-lg font-bold text-[14px] transition-colors shadow">Simpan & Cetak Transaksi</button>
        </form>
    </div>

    <script>
        const fmtRp = (a) => "Rp " + new Intl.NumberFormat('id-ID').format(a);
        const unfRp = (s) => parseInt(s.toString().replace(/[^0-9]/g, '')) || 0;

        document.addEventListener('input', function(e) {
            if(e.target.classList.contains('format-rupiah')) {
                let v = e.target.value.replace(/[^0-9]/g, ''); 
                e.target.value = v !== '' ? new Intl.NumberFormat('id-ID').format(parseInt(v)) : ''; 
                calcPupuk();
            }
        });

        const htmlOpsi = `<?= $opsi_html ?>`;
        function tambahBaris() {
            let html = `
            <div class="cart-row flex items-center gap-3 mt-3">
                <div class="flex-1">
                    <select name="id_barang[]" required class="produk-select w-full bg-white dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]">
                        ${htmlOpsi}
                    </select>
                </div>
                <div class="w-24">
                    <input type="number" name="qty[]" min="1" value="1" required class="qty-input w-full bg-white dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] text-center focus:outline-none focus:border-[#58a6ff]">
                </div>
                <div class="w-8 flex justify-center">
                    <button type="button" onclick="this.closest('.cart-row').remove(); calcPupuk();" class="text-red-500 hover:text-red-700 transition-colors"><i class="fa-solid fa-trash-can"></i></button>
                </div>
            </div>`;
            document.getElementById('cart-container').insertAdjacentHTML('beforeend', html);
            attachListeners();
        }

        function attachListeners() {
            document.querySelectorAll('.produk-select').forEach(el => {
                el.removeEventListener('change', calcPupuk);
                el.addEventListener('change', calcPupuk);
            });
            document.querySelectorAll('.qty-input').forEach(el => {
                el.removeEventListener('input', validasiQty);
                el.addEventListener('input', validasiQty);
            });
        }

        function validasiQty(e) {
            let row = e.target.closest('.cart-row');
            let sel = row.querySelector('.produk-select');
            if(sel.selectedIndex > 0) {
                let max = parseInt(sel.options[sel.selectedIndex].getAttribute('data-stok'));
                let val = parseInt(e.target.value) || 1;
                if(val > max) { e.target.value = max; alert("Stok gudang hanya tersisa " + max); }
            }
            calcPupuk();
        }
        
        attachListeners();

        const togDis = document.getElementById('tog_diskon'); const contDis = document.getElementById('cont_diskon'); const rTipeDis = document.querySelectorAll('input[name="tipe_diskon"]');
        const wpDisP = document.getElementById('w_diskon_p'); const wpDisN = document.getElementById('w_diskon_n'); const inDisP = document.getElementById('in_diskon_p'); const inDisN = document.getElementById('in_diskon_n');
        const rAmbil = document.querySelectorAll('input[name="ambil"]'); const contOng = document.getElementById('cont_ongkir'); const inOng = document.getElementById('in_ongkir');
        const rBayar = document.querySelectorAll('input[name="bayar"]'); const contDp = document.getElementById('cont_dp'); const inDp = document.getElementById('in_dp');
        
        <?php $q_k = mysqli_query($conn, "SELECT * FROM kupon_diskon WHERE status='Aktif' AND (berlaku='Semua Order' OR berlaku='Pupuk & Obat')"); $arr_k = []; while($ka = mysqli_fetch_assoc($q_k)) { $arr_k[] = $ka; } ?>
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
            calcPupuk();
        });

        togDis.addEventListener('change', function(){ if(this.checked) contDis.classList.remove('hidden'); else { contDis.classList.add('hidden'); inDisP.value=0; inDisN.value=0; } calcPupuk(); });
        rTipeDis.forEach(r => r.addEventListener('change', function(){ if(this.value==='persen'){ wpDisP.classList.remove('hidden'); wpDisN.classList.add('hidden'); inDisN.value=0; } else { wpDisP.classList.add('hidden'); wpDisN.classList.remove('hidden'); inDisP.value=0; } calcPupuk(); }));
        inDisP.addEventListener('input', function(){ let v=parseFloat(this.value)||0; if(v>100)this.value=100; calcPupuk(); });
        
        rAmbil.forEach(r => r.addEventListener('change', function(){ if(this.value==='dikirim') contOng.classList.remove('hidden'); else { contOng.classList.add('hidden'); inOng.value=0; } calcPupuk(); }));
        rBayar.forEach(r => r.addEventListener('change', function(){ if(this.value==='dp') contDp.classList.remove('hidden'); else { contDp.classList.add('hidden'); inDp.value=0; } calcPupuk(); }));

        function calcPupuk() {
            let rows = document.querySelectorAll('.cart-row');
            let grandSub = 0;
            let txtItem = [];
            let totalQty = 0;

            rows.forEach(row => {
                let sel = row.querySelector('.produk-select');
                let iQty = row.querySelector('.qty-input');
                let qty = parseInt(iQty.value) || 1;
                
                if(sel.selectedIndex > 0) {
                    let opt = sel.options[sel.selectedIndex];
                    let harga = parseFloat(opt.getAttribute('data-harga')) || 0;
                    grandSub += (harga * qty);
                    totalQty += qty;
                    
                    let namaOri = opt.text.replace('[PAKET] ', '');
                    let namaClean = namaOri.split(' (Stok:')[0];
                    txtItem.push(namaClean + " (" + qty + ")");
                }
            });

            document.getElementById('r_nama_barang').innerText = txtItem.length > 0 ? txtItem.join(', ') : '-';
            document.getElementById('r_qty').innerText = rows.length + ' Macam (' + totalQty + ' Pcs)';
            document.getElementById('r_sub').innerText = fmtRp(grandSub);

            let dMan = 0; if(togDis.checked) dMan = (document.querySelector('input[name="tipe_diskon"]:checked').value==='persen') ? grandSub * ((parseFloat(inDisP.value)||0)/100) : unfRp(inDisN.value);
            let dKup = 0; let valK = parseFloat(hNilaiKup.value)||0; if(valK>0) dKup = (hNilaiKup.getAttribute('data-tipe')==='Persentase') ? grandSub * (valK/100) : valK;
            let tDis = dMan + dKup;
            if(tDis>0){ document.getElementById('r_row_dis').classList.remove('hidden'); document.getElementById('r_dis').innerText = '- ' + fmtRp(tDis); } else document.getElementById('r_row_dis').classList.add('hidden');

            let o = unfRp(inOng.value);
            if(o>0){ document.getElementById('r_row_ong').classList.remove('hidden'); document.getElementById('r_ong').innerText = '+ ' + fmtRp(o); } else document.getElementById('r_row_ong').classList.add('hidden');

            let fnl = grandSub - tDis + o; if(fnl<0) fnl = 0; document.getElementById('r_final').innerText = fmtRp(fnl);
            
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
        // Query Menggabungkan Multi-Item jadi 1 Baris menggunakan GROUP BY no_order
        $q_data = mysqli_query($conn, "
            SELECT 
                no_order, tgl_order, nama_customer, no_hp, status, 
                SUM(total_harga) as grand_total, 
                SUM(dp_dibayar) as grand_dp, 
                SUM(nominal_pelunasan) as grand_pelunasan,
                SUM(ongkir) as total_ongkir,
                GROUP_CONCAT(CONCAT(nama_barang, ' (', qty, ' Pcs)') SEPARATOR '<br>') as detail_barang
            FROM order_pupuk 
            GROUP BY no_order 
            ORDER BY MAX(id) DESC
        ");
    ?>
    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-5 rounded-xl shadow-sm overflow-hidden">
        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-5">Data Riwayat Transaksi (Multi-Item)</h2>
        
        <div class="flex flex-col md:flex-row gap-4 mb-6">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchOrderPupuk" placeholder="Cari nama, nomor HP, atau nomor nota..." class="w-full pl-9 pr-4 py-2 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#58a6ff] text-[13px] shadow-sm">
            </div>
            <div class="w-full md:w-64 relative">
                <select id="filterStatusPupuk" class="w-full appearance-none px-4 py-2 bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md focus:outline-none focus:border-[#58a6ff] cursor-pointer text-[13px] shadow-sm font-medium">
                    <option value="all">Semua Status</option>
                    <option value="dp">DP (Uang Muka)</option>
                    <option value="lunas">Lunas</option>
                    <option value="selesai">Selesai</option>
                    <option value="batal">Batal</option>
                </select>
                <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
            </div>
        </div>

        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="bg-gray-50 dark:bg-[#0d1117] border-b border-gray-200 dark:border-[#30363d]">
                    <tr class="text-[10px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">
                        <th class="py-3.5 px-3">NOTA / CUSTOMER</th>
                        <th class="py-3.5 px-3">TGL ORDER</th>
                        <th class="py-3.5 px-3">DETAIL BARANG BELANJA</th>
                        <th class="py-3.5 px-3 text-right">TOTAL</th>
                        <th class="py-3.5 px-3 text-center">DP</th>
                        <th class="py-3.5 px-3 text-center">SISA</th>
                        <th class="py-3.5 px-3 text-center">STATUS</th>
                        <th class="py-3.5 px-3 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-[12px] bg-white dark:bg-[#161b22]">
                    <?php if(mysqli_num_rows($q_data) > 0): ?>
                        <?php while($o = mysqli_fetch_assoc($q_data)): 
                            $is_highlight = (isset($_GET['highlight']) && $_GET['highlight'] == $o['no_order']);
                            $kelas_blink = $is_highlight ? 'border-l-4 border-[#58a6ff] efek-kedip-biru' : '';
                            
                            $dp = $o['grand_dp'];
                            $sisa = $o['grand_total'] - $dp;

                            if (in_array(strtolower($o['status']), ['lunas', 'selesai'])) {
                                $sisa = 0;
                            }

                            $txt_dp = $dp > 0 ? 'Rp '.number_format($dp,0,',','.') : '-';
                            $txt_sisa = $sisa <= 0 ? '<span class="text-gray-400">Lunas</span>' : 'Rp '.number_format($sisa,0,',','.');

                            $st = strtolower($o['status']);
                            $st_color = 'text-gray-500 border-gray-500'; 
                            if($st=='dp') $st_color = 'text-[#d29922] border-[#d29922]/30 bg-[#d29922]/10';
                            if($st=='lunas'||$st=='selesai') $st_color = 'text-[#3fb950] border-[#3fb950]/30 bg-[#3fb950]/10';
                            if($st=='batal') $st_color = 'text-[#f85149] border-[#f85149]/30 bg-[#f85149]/10';
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors baris-order-pupuk <?= $kelas_blink ?>" data-name="<?= strtolower(htmlspecialchars($o['nama_customer'])) ?>" data-phone="<?= htmlspecialchars($o['no_hp']) ?>" data-nota="<?= strtolower($o['no_order']) ?>" data-status="<?= $st ?>">
                            <td class="py-3.5 px-3">
                                <p class="text-[12px] font-bold text-[#58a6ff] mb-0.5 cursor-help" title="<?= $o['no_order'] ?>"><?= substr($o['no_order'], 0, 18) ?>..</p>
                                <p class="font-bold text-gray-900 dark:text-white mb-0.5 whitespace-nowrap"><i class="fa-regular fa-user text-gray-400 mr-1"></i> <?= htmlspecialchars($o['nama_customer']) ?></p>
                                <p class="text-[10px] text-gray-500 dark:text-[#8b949e]"><?= htmlspecialchars($o['no_hp']) ?: '-' ?></p>
                            </td>
                            <td class="py-3.5 px-3">
                                <p class="font-medium text-gray-700 dark:text-[#c9d1d9] text-[11px]"><?= date('d M Y', strtotime($o['tgl_order'])) ?></p>
                            </td>
                            <td class="py-3.5 px-3">
                                <p class="font-bold text-gray-800 dark:text-gray-300 text-[11px] leading-relaxed"><?= $o['detail_barang'] ?></p>
                            </td>
                            <td class="py-3.5 px-3 text-right font-bold text-[#3fb950]">
                                Rp <?= number_format($o['grand_total'], 0, ',', '.') ?>
                            </td>
                            <td class="py-3.5 px-3 text-center text-gray-700 dark:text-[#c9d1d9] whitespace-nowrap"><?= $txt_dp ?></td>
                            <td class="py-3.5 px-3 text-center font-bold text-[#f85149] whitespace-nowrap"><?= $txt_sisa ?></td>
                            <td class="py-3.5 px-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border capitalize whitespace-nowrap <?= $st_color ?>"><?= $o['status'] ?></span>
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="relative group">
                                        <?php if($o['status'] != 'Batal'): ?>
                                        <select onchange="konfirmasiStatusPupuk(this, '<?= $o['no_order'] ?>', '<?= $o['status'] ?>')" class="appearance-none bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] <?= strpos($st_color, 'text-') !== false ? explode(' ', $st_color)[0] : 'text-gray-700' ?> font-bold text-[11px] rounded px-2 py-1 pr-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors">
                                            <option value="<?= $o['status'] ?>" class="text-gray-900 dark:text-white"><?= $o['status'] ?></option>
                                            <?php if($o['status'] == 'DP') echo '<option value="Lunas" class="text-[#3fb950]">Lunas</option>'; ?>
                                            <option value="Batal" class="text-[#f85149]">Batal</option>
                                        </select>
                                        <i class="fa-solid fa-chevron-down absolute right-1.5 top-1/2 transform -translate-y-1/2 text-[9px] text-gray-500 pointer-events-none"></i>
                                        <?php else: ?>
                                            <span class="text-[10px] text-gray-500 italic font-bold text-[#f85149]">Dibatalkan</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($o['grand_pelunasan'] > 0): ?>
                                        <a href="cetak/invoice-pupuk.php?id=<?= urlencode($o['no_order']) ?>&jenis=dp" target="_blank" class="w-6 h-6 rounded flex items-center justify-center text-[#d29922] hover:bg-[#d29922]/10 transition-colors" title="Cetak Struk DP"><i class="fa-solid fa-print"></i></a>
                                        <a href="cetak/invoice-pupuk.php?id=<?= urlencode($o['no_order']) ?>&jenis=lunas" target="_blank" class="w-6 h-6 rounded flex items-center justify-center text-[#3fb950] hover:bg-[#3fb950]/10 transition-colors" title="Cetak Struk Pelunasan"><i class="fa-solid fa-print"></i></a>
                                    <?php else: ?>
                                        <a href="cetak/invoice-pupuk.php?id=<?= urlencode($o['no_order']) ?>" target="_blank" class="w-6 h-6 rounded flex items-center justify-center text-[#58a6ff] hover:bg-[#58a6ff]/10 transition-colors" title="Cetak Invoice"><i class="fa-solid fa-print"></i></a>
                                    <?php endif; ?>
                                    <a href="?page=order-pupuk&tab=data&action=hapus_permanen&no_order=<?= urlencode($o['no_order']) ?>" onclick="return confirm('Hapus PERMANEN nota ini? Semua stok dari nota ini akan kembali ke gudang.')" class="w-6 h-6 rounded flex items-center justify-center text-[#f85149] hover:bg-[#f85149]/10 transition-colors" title="Hapus Order"><i class="fa-regular fa-trash-can"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-10 text-gray-500 italic">Belum ada data penjualan pupuk/obat.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        const searchInputPupuk = document.getElementById('searchOrderPupuk'); 
        const statusFilterPupuk = document.getElementById('filterStatusPupuk'); 
        const rowsPupuk = document.querySelectorAll('.baris-order-pupuk');
        
        function filterTablePupuk() { 
            let searchVal = searchInputPupuk.value.toLowerCase(); 
            let statusVal = statusFilterPupuk.value; 
            
            rowsPupuk.forEach(row => { 
                let name = row.getAttribute('data-name'); 
                let phone = row.getAttribute('data-phone'); 
                let nota = row.getAttribute('data-nota'); 
                let status = row.getAttribute('data-status'); 
                
                let matchSearch = name.includes(searchVal) || phone.includes(searchVal) || nota.includes(searchVal); 
                let matchStatus = (statusVal === 'all') || (status === statusVal); 
                
                if(matchSearch && matchStatus) { 
                    row.style.display = ''; 
                } else { 
                    row.style.display = 'none'; 
                } 
            }); 
        }
        
        if(searchInputPupuk) searchInputPupuk.addEventListener('input', filterTablePupuk); 
        if(statusFilterPupuk) statusFilterPupuk.addEventListener('change', filterTablePupuk);

        // Logic Highlighter efek kedip
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('highlight')) {
                window.history.replaceState({}, document.title, "?page=order-pupuk&tab=data");
            }
        });

        function konfirmasiStatusPupuk(selectElement, noOrder, statusLama) {
            let statusBaru = selectElement.value;
            if (statusBaru === statusLama) return;

            let pesanDialog = "Ubah status nota " + noOrder + " menjadi " + statusBaru + "?";
            
            if (statusBaru === 'Batal') {
                pesanDialog = "⚠️ PERHATIAN!\n\nMembatalkan nota multi-item ini akan mengembalikan SEMUA stok barang di dalamnya ke gudang.\nYakin ingin membatalkan?";
            } else if (statusBaru === 'Lunas') {
                pesanDialog = "Tandai nota ini sebagai Lunas?\nSisa hutang pembayaran akan otomatis dianggap lunas.";
            }

            if (confirm(pesanDialog)) {
                window.location.href = '?page=order-pupuk&action=update_status&no_order=' + noOrder + '&status_baru=' + statusBaru;
            } else {
                selectElement.value = statusLama;
            }
        }
    </script>

    <style>
        @keyframes kedipBiru { 0%, 100% { background-color: transparent; } 50% { background-color: rgba(88, 166, 255, 0.3); } }
        .efek-kedip-biru { animation: kedipBiru 0.6s ease-in-out 3; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
    </style>

    <?php endif; ?>

</div>