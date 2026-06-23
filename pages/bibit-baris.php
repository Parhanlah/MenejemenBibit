<?php
include 'components/koneksi.php';

$tgl_hari_ini = date('Y-m-d');

// PROSES SAPU LAHAN
if (isset($_GET['sapu_lahan'])) {
    $id_b = (int)$_GET['sapu_lahan'];
    mysqli_query($conn, "UPDATE bibit_baris SET status='kosong', id_varietas=NULL, tgl_persiapan=NULL, tgl_sebar=NULL, tersedia_m=12.0 WHERE id_baris='$id_b'");
    mysqli_query($conn, "UPDATE order_bibit SET status='diambil', tgl_ambil='$tgl_hari_ini' WHERE id_baris='$id_b' AND status IN ('booking','lunas')");
    echo "<script>alert('Berhasil! Lahan baris #$id_b sudah disapu bersih dan kembali menjadi 12m Kosong.'); window.location.href='?page=bibit-baris';</script>"; exit;
}

// 1. AMBIL DATA ORDER AKTIF (UNTUK SEGMEN PENJUALAN & DAFTAR BOOKING)
    $q_active_orders = mysqli_query($conn, "SELECT * FROM order_bibit WHERE status NOT IN ('diambil', 'Selesai', 'Batal') ORDER BY id ASC");
    
    $active_orders_map = [];
    while($ao = mysqli_fetch_assoc($q_active_orders)) {
        $active_orders_map[$ao['id_baris']][] = $ao;
    }

// 2. AMBIL DATA BARIS UTAMA
$query_baris = mysqli_query($conn, "SELECT b.*, v.nama_varietas, v.kode_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id ORDER BY b.id_baris ASC");

$tot_semai = 0; $tot_muda = 0; $tot_matang = 0; $tot_tua = 0; $tot_kosong = 0;
$data_baris = [];

while($r = mysqli_fetch_assoc($query_baris)) {
    $status_db = $r['status'];
    
    // Auto-update tumbuh jika tanggal sebar sudah lewat
    if ($status_db == 'persiapan' && !empty($r['tgl_sebar']) && $r['tgl_sebar'] <= $tgl_hari_ini) {
        $status_db = 'tumbuh'; mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh' WHERE id_baris=".$r['id_baris']);
    }

    $umur_hari = 0; $umur_txt = '-';
    if ($status_db != 'kosong' && $status_db != 'persiapan') {
        $diff = strtotime($tgl_hari_ini) - strtotime($r['tgl_sebar']);
        $umur_hari = floor($diff / 86400); if ($umur_hari < 0) $umur_hari = 0;
        $umur_txt = $umur_hari . 'h';
    }

    // LOGIKA PERTANIAN (UMUR) - FILOSOFI PADI
    $agri_status = ''; $agri_color = ''; $agri_filter = ''; $ui_dot = ''; $m_free = (float)$r['tersedia_m'];
    
    if ($status_db == 'kosong' || $status_db == 'persiapan') {
        $agri_status = 'Kosong'; $agri_color = 'text-gray-500 border-gray-500 bg-gray-500/10'; $agri_filter = 'kosong'; $ui_dot = 'bg-gray-500';
        $tot_kosong++;
        $m_free = 0; 
    } else {
        if ($umur_hari <= 3) {
            $agri_status = 'Semai'; $agri_color = 'text-[#58a6ff] border-[#58a6ff] bg-[#58a6ff]/10'; $agri_filter = 'semai'; $ui_dot = 'bg-[#1f6feb]';
            $tot_semai++;
        } elseif ($umur_hari <= 11) {
            $agri_status = 'Muda'; $agri_color = 'text-[#3fb950] border-[#3fb950] bg-[#3fb950]/10'; $agri_filter = 'muda'; $ui_dot = 'bg-[#3fb950]';
            $tot_muda++;
        } elseif ($umur_hari <= 20) {
            $agri_status = 'Matang'; $agri_color = 'text-[#d29922] border-[#d29922] bg-[#d29922]/10'; $agri_filter = 'matang'; $ui_dot = 'bg-[#d29922]';
            $tot_matang++;
        } else {
            // Teks disingkat jadi 'Tua', tapi meteran sisa ($m_free) tetap dibiarkan agar warna segmen tetap hijau!
            $agri_status = 'Tua'; $agri_color = 'text-[#f85149] border-[#f85149] bg-[#f85149]/10'; $agri_filter = 'tua'; $ui_dot = 'bg-[#f85149]';
            $tot_tua++;
        }
    }

    // LOGIKA PENJUALAN (SEGMEN KOTAK)
    $m_booking = 0; $m_lunas = 0;
    if(isset($active_orders_map[$r['id_baris']])) {
        foreach($active_orders_map[$r['id_baris']] as $ao) {
            $st = strtolower($ao['status']);
            if($st == 'booking' || $st == 'dp') { $m_booking += (float)$ao['panjang_m']; }
            if($st == 'lunas' || $st == 'persiapan' || $st == 'tanam') { $m_lunas += (float)$ao['panjang_m']; }
        }
    }
    
    $m_kosong_sales = 12.0 - ($m_lunas + $m_booking + $m_free);
    if($m_kosong_sales < 0) $m_kosong_sales = 0;

    $data_baris[] = [
        'no' => $r['id_baris'], 'varietas' => $r['nama_varietas'] ? $r['nama_varietas'] : '-', 'kode_var' => $r['kode_varietas'],
        'tgl_sebar' => $r['tgl_sebar'], 'umur_txt' => $umur_txt, 'umur_hari' => $umur_hari, 
        'agri_status' => $agri_status, 'agri_color' => $agri_color, 'agri_filter' => $agri_filter, 'ui_dot' => $ui_dot,
        'm_free' => $m_free, 'm_booking' => $m_booking, 'm_lunas' => $m_lunas, 'm_kosong_sales' => $m_kosong_sales
    ];
}
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-6 gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-bold flex items-center text-gray-900 dark:text-[#c9d1d9]"><i class="fa-solid fa-leaf text-[#3fb950] mr-3"></i> Manajemen Baris Bibit Padi</h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-1 ml-8">Pusat kontrol 85 Baris @ 12 meter | Pemantauan Fase Umur & Status Penjualan</p>
        </div>
        <div class="flex gap-2 w-full xl:w-auto">
            <a href="?page=calendar-persiapan" id="btn-calendar-persiapan" class="flex-1 xl:flex-none bg-gray-100 hover:bg-gray-200 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-transparent px-4 py-2 rounded-md text-[13px] font-bold transition-all duration-300 flex items-center justify-center">
                <i class="fa-regular fa-calendar mr-2"></i> Calendar
            </a>
            <a href="?page=sebar-benih" class="flex-1 xl:flex-none bg-[#1f6feb] hover:bg-[#388bfd] text-white px-4 py-2 rounded-md text-[13px] font-bold transition-colors text-center flex items-center justify-center shadow">
                + Sebar Benih Baru
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-md bg-[#1f6feb]/20 flex items-center justify-center text-[#58a6ff] shrink-0"><i class="fa-solid fa-droplet text-sm"></i></div>
            <div><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mb-0.5">Semai (0-3h)</p><h3 class="text-lg font-bold text-[#58a6ff] leading-none"><?= $tot_semai ?></h3></div>
        </div>
        <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-md bg-[#3fb950]/20 flex items-center justify-center text-[#3fb950] shrink-0"><i class="fa-solid fa-seedling text-sm"></i></div>
            <div><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mb-0.5">Muda (4-11h)</p><h3 class="text-lg font-bold text-[#3fb950] leading-none"><?= $tot_muda ?></h3></div>
        </div>
        <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-md bg-[#d29922]/20 flex items-center justify-center text-[#d29922] shrink-0"><i class="fa-solid fa-wheat-awn text-sm"></i></div>
            <div><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mb-0.5">Matang (12-20h)</p><h3 class="text-lg font-bold text-[#d29922] leading-none"><?= $tot_matang ?></h3></div>
        </div>
        <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-md bg-[#f85149]/20 flex items-center justify-center text-[#f85149] shrink-0"><i class="fa-solid fa-triangle-exclamation text-sm"></i></div>
            <div><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mb-0.5">Tua (>20h)</p><h3 class="text-lg font-bold text-[#f85149] leading-none"><?= $tot_tua ?></h3></div>
        </div>
        <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-md bg-gray-300 dark:bg-[#30363d] flex items-center justify-center text-gray-600 dark:text-gray-400 shrink-0"><i class="fa-solid fa-bed text-sm"></i></div>
            <div><p class="text-[10px] text-gray-500 dark:text-[#8b949e] mb-0.5">Lahan Kosong</p><h3 class="text-lg font-bold text-gray-700 dark:text-gray-300 leading-none"><?= $tot_kosong ?></h3></div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4" id="filter-container">
        <button onclick="filterTabel('semua', this)" class="filter-btn active-filter bg-[#1f6feb] text-white border border-[#1f6feb] px-4 py-1.5 rounded text-[12px] font-bold transition-colors">Semua Baris</button>
        <button onclick="filterTabel('semai', this)" class="filter-btn bg-gray-50 dark:bg-[#161b22] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-[#30363d] hover:bg-gray-100 dark:hover:bg-[#21262d] px-3 py-1.5 rounded text-[12px] font-bold flex items-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-[#1f6feb] mr-2"></div> Semai</button>
        <button onclick="filterTabel('muda', this)" class="filter-btn bg-gray-50 dark:bg-[#161b22] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-[#30363d] hover:bg-gray-100 dark:hover:bg-[#21262d] px-3 py-1.5 rounded text-[12px] font-bold flex items-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-[#3fb950] mr-2"></div> Muda</button>
        <button onclick="filterTabel('matang', this)" class="filter-btn bg-gray-50 dark:bg-[#161b22] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-[#30363d] hover:bg-gray-100 dark:hover:bg-[#21262d] px-3 py-1.5 rounded text-[12px] font-bold flex items-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-[#d29922] mr-2"></div> Matang</button>
        <button onclick="filterTabel('tua', this)" class="filter-btn bg-gray-50 dark:bg-[#161b22] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-[#30363d] hover:bg-gray-100 dark:hover:bg-[#21262d] px-3 py-1.5 rounded text-[12px] font-bold flex items-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-[#f85149] mr-2"></div> Tua</button>
        <button onclick="filterTabel('kosong', this)" class="filter-btn bg-gray-50 dark:bg-[#161b22] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-[#30363d] hover:bg-gray-100 dark:hover:bg-[#21262d] px-3 py-1.5 rounded text-[12px] font-bold flex items-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-gray-500 mr-2"></div> Kosong</button>
    </div>

    <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg mb-10">
        <table class="w-full text-left border-collapse min-w-max">
            <thead class="border-b border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#161b22]">
                <tr class="text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase">
                    <th class="py-3 px-4 w-16 text-center">NO</th>
                    <th class="py-3 px-4 w-48">VARIETAS</th>
                    <th class="py-3 px-4 w-24">UMUR</th>
                    <th class="py-3 px-4 w-32 text-center">FASE PERTUMBUHAN</th>
                    <th class="py-3 px-4 min-w-[400px]">SEGMEN PENJUALAN (12m)</th>
                    <th class="py-3 px-4 text-center w-16">AKSI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-[#21262d]" id="body-baris">
                <?php foreach($data_baris as $b): 
                    // LOGIKA RENDER GRADIENT SEGMEN
                    $czones = []; $c_pos = 0;
                    if($b['m_kosong_sales'] > 0) { $czones[] = ['c'=>'#e5e7eb', 'd'=>'#30363d', 's'=>$c_pos, 'e'=>$c_pos+$b['m_kosong_sales'], 'n'=>'Kosong']; $c_pos+=$b['m_kosong_sales']; }
                    if($b['m_lunas'] > 0)  { $czones[] = ['c'=>'#f85149', 'd'=>'#f85149', 's'=>$c_pos, 'e'=>$c_pos+$b['m_lunas'], 'n'=>'Lunas']; $c_pos+=$b['m_lunas']; }
                    if($b['m_booking'] > 0){ $czones[] = ['c'=>'#d29922', 'd'=>'#d29922', 's'=>$c_pos, 'e'=>$c_pos+$b['m_booking'], 'n'=>'Booking']; $c_pos+=$b['m_booking']; }
                    if($b['m_free'] > 0)   { $czones[] = ['c'=>'#2ea043', 'd'=>'#2ea043', 's'=>$c_pos, 'e'=>$c_pos+$b['m_free'], 'n'=>'Free']; $c_pos+=$b['m_free']; }
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22] cursor-pointer baris-row transition-colors" data-filter="<?= $b['agri_filter'] ?>" onclick="toggleRow(<?= $b['no'] ?>)">
                    <td class="py-3 px-4 text-center"><div class="w-7 h-7 mx-auto rounded-full flex items-center justify-center text-[12px] font-bold text-white shadow-sm <?= $b['ui_dot'] ?>"><?= $b['no'] ?></div></td>
                    <td class="py-3 px-4">
                        <p class="text-[13px] font-bold text-gray-900 dark:text-[#c9d1d9] mb-0.5"><?= htmlspecialchars($b['varietas']) ?></p>
                        <?php if($b['kode_var']): ?><p class="text-[10px] text-gray-500"><?= htmlspecialchars($b['kode_var']) ?></p><?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-[13px] font-bold text-gray-700 dark:text-[#8b949e]"><?= $b['umur_txt'] ?></td>
                    <td class="py-3 px-4 text-center"><span class="border px-2.5 py-0.5 rounded text-[10px] font-bold <?= $b['agri_color'] ?>"><?= $b['agri_status'] ?></span></td>
                    <td class="py-3 px-4">
                        <div class="grid grid-cols-8 gap-1.5 w-full">
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
                                    echo '<div class="bg-transparent border border-gray-300 dark:border-[#525964] border-dashed h-6 rounded w-full"></div>';
                                } else if(count($intersect) == 1) {
                                    $iz = $intersect[0];
                                    if($iz['n'] == 'Kosong') {
                                        echo '<div class="bg-transparent border border-gray-300 dark:border-[#525964] border-dashed h-6 rounded w-full"></div>';
                                    } else {
                                        echo '<div style="background-color:'.$iz['d'].';" class="h-6 rounded shadow-sm w-full"></div>';
                                    }
                                } else {
                                    $stops = []; $cum = 0;
                                    $total_iz = count($intersect);
                                    foreach($intersect as $idx => $iz) {
                                        $st_p = round($cum, 2); $cum += $iz['p']; $en_p = ($idx === $total_iz - 1) ? 100 : round($cum, 2);
                                        $stops[] = "{$iz['d']} {$st_p}%"; $stops[] = "{$iz['d']} {$en_p}%";
                                    }
                                    $grad = "linear-gradient(to right, ".implode(', ', $stops).")";
                                    echo '<div style="background: '.$grad.' no-repeat padding-box; border: none; outline: none;" class="h-6 rounded shadow-sm w-full"></div>';
                                }
                            }
                            ?>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-center text-gray-400"><i id="icon-<?= $b['no'] ?>" class="fa-solid fa-chevron-down text-sm transition-transform duration-300"></i></td>
                </tr>

                <tr id="detail-<?= $b['no'] ?>" class="hidden bg-gray-50 dark:bg-[#10141a]">
                    <td colspan="6" class="p-6 border-b border-gray-200 dark:border-[#30363d]">
                        <div class="flex flex-col md:flex-row gap-8">
                            <div class="flex-1">
                                <h4 class="text-[14px] font-bold text-gray-900 dark:text-white mb-4 flex items-center"><i class="fa-solid fa-circle-info text-[#58a6ff] mr-2"></i> Detail Baris #<?= $b['no'] ?></h4>
                                <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 mb-4">
                                    <div class="grid grid-cols-2 gap-y-3 text-[12px] text-gray-600 dark:text-[#8b949e]">
                                        <p>Varietas: <strong class="text-gray-900 dark:text-[#c9d1d9] block mt-0.5"><?= htmlspecialchars($b['varietas']) ?></strong></p>
                                        <p>Tanggal Sebar: <strong class="text-gray-900 dark:text-[#c9d1d9] block mt-0.5"><?= $b['tgl_sebar'] ? date('d/m/Y', strtotime($b['tgl_sebar'])) : '-' ?></strong></p>
                                        <p>Umur Benih: <strong class="text-gray-900 dark:text-[#c9d1d9] block mt-0.5"><?= $b['umur_hari'] ?> hari</strong></p>
                                        <p>Sisa Meter Jual: <strong class="<?= $b['m_free'] > 0 ? 'text-[#3fb950]' : 'text-gray-500 dark:text-gray-400' ?> block mt-0.5"><?= $b['m_free'] ?>m</strong></p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <?php if($b['agri_filter'] != 'kosong'): ?>
                                    <a href="?page=sebar-benih" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-4 py-2 rounded-md text-[12px] font-bold transition-colors text-center shadow-sm">Sebar Ulang Lahan</a>
                                    <a href="?page=bibit-baris&sapu_lahan=<?= $b['no'] ?>" onclick="return confirm('PENTING!\nSapu Lahan akan mereset baris ini menjadi 12m Kosong.\nSemua order di baris ini otomatis ditandai \'Diambil\'.\n\nYakin bersihkan baris #<?= $b['no'] ?>?')" class="bg-white dark:bg-[#21262d] text-[#f85149] border border-[#f85149]/30 hover:bg-[#f85149] hover:text-white px-4 py-2 rounded-md text-[12px] font-bold transition-colors text-center shadow-sm"><i class="fa-solid fa-broom mr-1"></i> Sapu Lahan</a>
                                    <?php else: ?>
                                    <a href="?page=sebar-benih" class="bg-[#238636] hover:bg-[#2ea043] text-white px-4 py-2 rounded-md text-[12px] font-bold transition-colors text-center shadow-sm"><i class="fa-solid fa-seedling mr-1"></i> Mulai Tanam</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex-1">
                                <h4 class="text-[13px] font-bold text-gray-900 dark:text-white mb-3 flex items-center"><i class="fa-solid fa-users mr-2 text-gray-400"></i> Daftar Pemesan Terpadu</h4>
                                    <div class="space-y-2">
                                        <?php 
                                        // Cek apakah ada order di baris ini dari Peta Radar Gabungan
                                        $orders_di_baris_ini = isset($active_orders_map[$b['no']]) ? $active_orders_map[$b['no']] : [];
                                        
                                        if(count($orders_di_baris_ini) > 0): 
                                            foreach($orders_di_baris_ini as $ao): 
                                                $st = strtolower($ao['status']);
                                                $badgeStatus = ($st == 'lunas' || $st == 'persiapan' || $st == 'tanam')
                                                    ? '<span class="bg-[#f85149]/10 text-[#f85149] border border-[#f85149]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">'.$ao['status'].'</span>' 
                                                    : '<span class="bg-[#d29922]/10 text-[#d29922] border border-[#d29922]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">'.$ao['status'].'</span>';
                                                
                                                $badgeTipe = (isset($ao['tipe_order']) && $ao['tipe_order'] == 'Jasa Tanam')
                                                    ? '<span class="bg-[#1f6feb]/10 text-[#58a6ff] border border-[#1f6feb]/30 px-2 py-0.5 rounded text-[10px] font-bold"><i class="fa-solid fa-person-digging"></i> Jasa Tanam</span>' 
                                                    : '<span class="bg-gray-500/10 text-gray-400 border border-gray-500/30 px-2 py-0.5 rounded text-[10px] font-bold"><i class="fa-solid fa-box"></i> Reguler</span>';
                                        ?>
                                        <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] p-3 rounded-lg flex justify-between items-center shadow-sm">
                                            <div>
                                                <div class="flex items-center gap-2 mb-1">
                                                    <p class="text-[13px] font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($ao['nama_customer']) ?></p>
                                                    <?= $badgeTipe ?>
                                                </div>
                                                <p class="text-[11px] text-gray-600 dark:text-[#8b949e] flex items-center gap-2"><span><i class="fa-solid fa-ruler-horizontal mr-1"></i> <?= (float)$ao['panjang_m'] ?>m</span> <?= $badgeStatus ?></p>
                                            </div>
                                            <?php 
                                        // Tentukan arah link detail berdasarkan tipe ordernya
                                        $link_detail_cerdas = (isset($ao['tipe_order']) && $ao['tipe_order'] == 'Jasa Tanam')
                                            ? '?page=jasa-tanam&tab=data&highlight=' . $ao['no_order']
                                            : '?page=order-bibit&tab=data&highlight=' . $ao['id'];
                                    ?>
                                    <a href="<?= $link_detail_cerdas ?>" class="text-[#58a6ff] hover:text-blue-500 text-[11px] font-bold transition-colors"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Detail</a>
                                        </div>
                                        <?php 
                                            endforeach; 
                                        else: 
                                        ?>
                                        <div class="text-[12px] text-gray-500 dark:text-[#8b949e] italic p-4 bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg border-dashed text-center">Lahan ini belum ada yang memesan.</div>
                                        <?php endif; ?>
                                    </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>.custom-scrollbar::-webkit-scrollbar { width: 5px; } .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }</style>

<script>
    // FUNGSI AKORDEON
    function toggleRow(id) {
        const detailRow = document.getElementById('detail-' + id);
        const icon = document.getElementById('icon-' + id);
        
        if(detailRow.classList.contains('hidden')) {
            detailRow.classList.remove('hidden');
            icon.classList.add('rotate-180'); 
        } else {
            detailRow.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }

    // FUNGSI FILTER SMART UX
    function filterTabel(kategori, btnElement) {
        // Reset warna semua tombol ke mode non-aktif
        const btns = document.querySelectorAll('.filter-btn');
        btns.forEach(btn => {
            btn.classList.remove('bg-[#1f6feb]', 'text-white', 'border-[#1f6feb]');
            btn.classList.add('bg-gray-50', 'dark:bg-[#161b22]', 'text-gray-700', 'dark:text-[#c9d1d9]', 'border-gray-300', 'dark:border-[#30363d]');
        });
        
        // Warnai tombol yang aktif
        btnElement.classList.remove('bg-gray-50', 'dark:bg-[#161b22]', 'text-gray-700', 'dark:text-[#c9d1d9]', 'border-gray-300', 'dark:border-[#30363d]');
        btnElement.classList.add('bg-[#1f6feb]', 'text-white', 'border-[#1f6feb]');

        // Saring Baris
        const rows = document.querySelectorAll('.baris-row');
        rows.forEach(row => {
            const detailRow = row.nextElementSibling; // Baris accordionnya
            if (kategori === 'semua' || row.getAttribute('data-filter') === kategori) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                detailRow.classList.add('hidden'); // Tutup accordion jika disembunyikan
                row.querySelector('i').classList.remove('rotate-180');
            }
        });
    }
</script>

<?php if(isset($_GET['blink']) && $_GET['blink'] == 'calendar'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnCal = document.getElementById('btn-calendar-persiapan');
        if(btnCal) {
            let count = 0;
            let interval = setInterval(() => {
                btnCal.classList.toggle('bg-gray-100'); btnCal.classList.toggle('bg-[#ea580c]'); btnCal.classList.toggle('text-white'); btnCal.classList.toggle('scale-105');
                count++;
                if(count >= 6) { clearInterval(interval); btnCal.className = "flex-1 xl:flex-none bg-gray-100 hover:bg-gray-200 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] border border-gray-300 dark:border-transparent px-4 py-2 rounded-md text-[13px] font-bold transition-all duration-300 flex items-center justify-center"; }
            }, 400); 
        }
    });
</script>
<?php endif; ?>