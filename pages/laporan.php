<?php
include 'components/koneksi.php';

// Auto-migrate kolom yang dibutuhkan jika diakses sebelum membuka order-bibit/order-pupuk
if(mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM order_bibit LIKE 'nominal_pelunasan'")) == 0) {
    mysqli_query($conn, "ALTER TABLE order_bibit ADD COLUMN nominal_pelunasan INT DEFAULT 0 AFTER dp_dibayar");
}
if(mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM order_pupuk LIKE 'nominal_pelunasan'")) == 0) {
    mysqli_query($conn, "ALTER TABLE order_pupuk ADD COLUMN nominal_pelunasan INT DEFAULT 0 AFTER dp_dibayar");
}
if(mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM order_pupuk LIKE 'tgl_lunas'")) == 0) {
    mysqli_query($conn, "ALTER TABLE order_pupuk ADD COLUMN tgl_lunas DATE NULL AFTER tgl_order");
}

// Setup array bahasa indonesia untuk hari dan bulan
$hari_indo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

function formatTgl($date_string) {
    global $hari_indo;
    $time = strtotime($date_string);
    $hari = $hari_indo[date('l', $time)];
    $tgl = str_pad(date('d', $time), 2, '0', STR_PAD_LEFT);
    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];
    $bln = $bulan[date('m', $time)];
    $thn = date('Y', $time);
    return "$hari, $tgl $bln $thn";
}

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}

$start_date = "2026-03-23"; // Dimulai dari Senin, 23 Maret 2026
$current_time = strtotime($start_date);

// Hitung total akumulasi selamanya
$total_jasa_tanam_all = 0;
$total_bibit_all = 0;
$total_pupuk_all = 0;
$grand_total_all = 0;
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    <?php $tab = isset($_GET['tab']) ? $_GET['tab'] : 'harian'; ?>
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]"><i class="fa-solid fa-file-invoice-dollar text-[#58a6ff] mr-3"></i> Laporan Keuangan & Penjualan</h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-0.5 ml-8">Analisis omzet dan pantauan kinerja harian</p>
        </div>
        <div class="flex gap-2 w-full md:w-auto bg-gray-100 dark:bg-[#161b22] p-1 rounded-lg border border-gray-200 dark:border-[#30363d]">
            <a href="?page=laporan&tab=harian" class="<?= $tab == 'harian' ? 'bg-white dark:bg-[#21262d] text-[#1f6feb] shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-[#8b949e] dark:hover:text-[#c9d1d9]' ?> px-4 py-2 rounded-md text-[13px] font-bold transition-all text-center flex-1 md:flex-none">
                <i class="fa-solid fa-chart-line mr-1.5"></i> Detail Laporan
            </a>
            <a href="?page=laporan&tab=periodik" class="<?= $tab == 'periodik' ? 'bg-white dark:bg-[#21262d] text-[#1f6feb] shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-[#8b949e] dark:hover:text-[#c9d1d9]' ?> px-4 py-2 rounded-md text-[13px] font-bold transition-all text-center flex-1 md:flex-none">
                <i class="fa-solid fa-calendar-days mr-1.5"></i> Keuangan Periodik
            </a>
        </div>
    </div>

    <?php if($tab == 'periodik'): ?>
    <div class="overflow-x-auto border border-gray-300 dark:border-[#444c56] rounded-t-lg bg-white dark:bg-[#0d1117] shadow-sm animate-in fade-in duration-300">
        <table class="w-full text-left border-collapse min-w-max">
            <thead class="bg-gray-100 dark:bg-[#161b22]">
                <tr>
                    <th class="py-4 px-4 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[15px] w-20" rowspan="2">Quartal</th>
                    <th class="py-4 px-4 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[15px] w-20" rowspan="2">Periode</th>
                    <th class="py-4 px-4 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[15px]" colspan="4" rowspan="2">Minggu</th>
                    <th class="py-2 px-4 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[13px] bg-green-50 dark:bg-green-900/20" colspan="4">Omzet Keuangan Mingguan</th>
                </tr>
                <tr>
                    <th class="py-2 px-3 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[11px] bg-green-50 dark:bg-green-900/20 w-28">Jasa Tanam</th>
                    <th class="py-2 px-3 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[11px] bg-green-50 dark:bg-green-900/20 w-28">Jual Bibit</th>
                    <th class="py-2 px-3 border border-gray-300 dark:border-[#444c56] text-center font-bold text-gray-900 dark:text-white text-[11px] bg-green-50 dark:bg-green-900/20 w-28">Pupuk & Obat</th>
                    <th class="py-2 px-3 border border-gray-300 dark:border-[#444c56] text-center font-bold text-[#1f6feb] dark:text-[#58a6ff] text-[12px] bg-blue-50 dark:bg-blue-900/20 w-32">TOTAL OMZET</th>
                </tr>
            </thead>
            <tbody class="text-[13px] bg-white dark:bg-[#0d1117]">
                <?php
                for ($q = 1; $q <= 4; $q++) {
                    for ($p = 1; $p <= 3; $p++) {
                        for ($w = 1; $w <= 4; $w++) {
                            $end_time = $current_time + (6 * 86400);
                            
                            $tgl_start_db = date('Y-m-d', $current_time);
                            $tgl_end_db = date('Y-m-d', $end_time);
                            
                            $str_start = formatTgl($tgl_start_db);
                            $str_end = formatTgl($tgl_end_db);
                            
                            // Hitung Omzet Jasa Tanam (tgl_booking antara start dan end, dan pelunasan di tgl_lunas)
                            $q_jt = mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_bibit WHERE tipe_order='Jasa Tanam' AND status!='Batal' AND tgl_booking >= '$tgl_start_db' AND tgl_booking <= '$tgl_end_db'");
                            $r_jt = mysqli_fetch_assoc($q_jt);
                            $q_jt_l = mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_bibit WHERE tipe_order='Jasa Tanam' AND status!='Batal' AND tgl_lunas >= '$tgl_start_db' AND tgl_lunas <= '$tgl_end_db'");
                            $r_jt_l = mysqli_fetch_assoc($q_jt_l);
                            $omzet_jt = (int)$r_jt['total'] + (int)$r_jt_l['total'];
                            
                            // Hitung Omzet Bibit (tgl_booking antara start dan end, dan pelunasan di tgl_lunas)
                            $q_b = mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_bibit WHERE (tipe_order IS NULL OR tipe_order != 'Jasa Tanam') AND status!='Batal' AND tgl_booking >= '$tgl_start_db' AND tgl_booking <= '$tgl_end_db'");
                            $r_b = mysqli_fetch_assoc($q_b);
                            $q_b_l = mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_bibit WHERE (tipe_order IS NULL OR tipe_order != 'Jasa Tanam') AND status!='Batal' AND tgl_lunas >= '$tgl_start_db' AND tgl_lunas <= '$tgl_end_db'");
                            $r_b_l = mysqli_fetch_assoc($q_b_l);
                            $omzet_b = (int)$r_b['total'] + (int)$r_b_l['total'];
                            
                            // Hitung Omzet Pupuk & Obat (tgl_order antara start dan end, dan pelunasan di tgl_lunas)
                            $q_p = mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_pupuk WHERE status!='Batal' AND DATE(tgl_order) >= '$tgl_start_db' AND DATE(tgl_order) <= '$tgl_end_db'");
                            $r_p = mysqli_fetch_assoc($q_p);
                            $q_p_l = mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_pupuk WHERE status!='Batal' AND tgl_lunas >= '$tgl_start_db' AND tgl_lunas <= '$tgl_end_db'");
                            $r_p_l = mysqli_fetch_assoc($q_p_l);
                            $omzet_p = (int)$r_p['total'] + (int)$r_p_l['total'];
                            
                            $total_mingguan = $omzet_jt + $omzet_b + $omzet_p;
                            
                            $total_jasa_tanam_all += $omzet_jt;
                            $total_bibit_all += $omzet_b;
                            $total_pupuk_all += $omzet_p;
                            $grand_total_all += $total_mingguan;
                            
                            echo "<tr class='hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors'>";
                            
                            if ($p == 1 && $w == 1) {
                                echo "<td class='py-2 px-2 border border-gray-300 dark:border-[#444c56] text-center align-middle font-bold text-gray-900 dark:text-white bg-[#caddfc] dark:bg-[#0c2d6b] text-[16px]' rowspan='12'>Q{$q}</td>";
                            }
                            
                            if ($w == 1) {
                                echo "<td class='py-2 px-2 border border-gray-300 dark:border-[#444c56] text-center align-middle font-bold text-gray-900 dark:text-white bg-[#dce8fd] dark:bg-[#163a7a] text-[15px]' rowspan='4'>P{$p}</td>";
                            }
                            
                            echo "<td class='py-2 px-2 border border-gray-300 dark:border-[#444c56] text-center font-medium text-gray-800 dark:text-[#c9d1d9] w-10'>{$w}</td>";
                            echo "<td class='py-2 px-3 border border-gray-300 dark:border-[#444c56] text-right text-gray-800 dark:text-[#c9d1d9] w-48'>{$str_start}</td>";
                            echo "<td class='py-2 px-2 border border-gray-300 dark:border-[#444c56] text-center text-gray-500 dark:text-[#8b949e] w-10'>s.d</td>";
                            echo "<td class='py-2 px-3 border border-gray-300 dark:border-[#444c56] text-left text-gray-800 dark:text-[#c9d1d9] w-48'>{$str_end}</td>";
                            
                            echo "<td class='py-2 px-3 border border-gray-300 dark:border-[#444c56] text-right font-medium text-[#2ea043]'>".($omzet_jt > 0 ? formatRp($omzet_jt) : '-')."</td>";
                            echo "<td class='py-2 px-3 border border-gray-300 dark:border-[#444c56] text-right font-medium text-[#2ea043]'>".($omzet_b > 0 ? formatRp($omzet_b) : '-')."</td>";
                            echo "<td class='py-2 px-3 border border-gray-300 dark:border-[#444c56] text-right font-medium text-[#2ea043]'>".($omzet_p > 0 ? formatRp($omzet_p) : '-')."</td>";
                            echo "<td class='py-2 px-3 border border-gray-300 dark:border-[#444c56] text-right font-bold text-[#1f6feb] dark:text-[#58a6ff] bg-blue-50/50 dark:bg-blue-900/10'>".($total_mingguan > 0 ? formatRp($total_mingguan) : '-')."</td>";
                            
                            echo "</tr>";
                            
                            $current_time += (7 * 86400);
                        }
                    }
                }
                ?>
            </tbody>
            <tfoot class="bg-gray-100 dark:bg-[#161b22]">
                <tr>
                    <td colspan="6" class="py-4 px-4 border border-gray-300 dark:border-[#444c56] text-right font-bold text-gray-900 dark:text-white uppercase text-[14px]">TOTAL KESELURUHAN (Q1 - Q4)</td>
                    <td class="py-4 px-3 border border-gray-300 dark:border-[#444c56] text-right font-bold text-[#2ea043] text-[14px]"><?= formatRp($total_jasa_tanam_all) ?></td>
                    <td class="py-4 px-3 border border-gray-300 dark:border-[#444c56] text-right font-bold text-[#2ea043] text-[14px]"><?= formatRp($total_bibit_all) ?></td>
                    <td class="py-4 px-3 border border-gray-300 dark:border-[#444c56] text-right font-bold text-[#2ea043] text-[14px]"><?= formatRp($total_pupuk_all) ?></td>
                    <td class="py-4 px-3 border border-gray-300 dark:border-[#444c56] text-right font-bold text-[#1f6feb] dark:text-[#58a6ff] text-[16px] bg-blue-50/50 dark:bg-blue-900/10"><?= formatRp($grand_total_all) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <?php if($tab == 'harian'): ?>
    <?php
        $tgl_hari_ini = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');
        
        // HITUNG DATA HARI INI
        $q_jt_hari = mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_bibit WHERE tipe_order='Jasa Tanam' AND status!='Batal' AND tgl_booking = '$tgl_hari_ini'");
        $q_jt_hari_lunas = mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_bibit WHERE tipe_order='Jasa Tanam' AND status!='Batal' AND tgl_lunas = '$tgl_hari_ini'");
        $omzet_jt_hari = (int)mysqli_fetch_assoc($q_jt_hari)['total'] + (int)mysqli_fetch_assoc($q_jt_hari_lunas)['total'];
        
        $q_b_hari = mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_bibit WHERE (tipe_order IS NULL OR tipe_order != 'Jasa Tanam') AND status!='Batal' AND tgl_booking = '$tgl_hari_ini'");
        $q_b_hari_lunas = mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_bibit WHERE (tipe_order IS NULL OR tipe_order != 'Jasa Tanam') AND status!='Batal' AND tgl_lunas = '$tgl_hari_ini'");
        $omzet_b_hari = (int)mysqli_fetch_assoc($q_b_hari)['total'] + (int)mysqli_fetch_assoc($q_b_hari_lunas)['total'];
        
        $q_p_hari = mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_pupuk WHERE status!='Batal' AND DATE(tgl_order) = '$tgl_hari_ini'");
        $q_p_hari_lunas = mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_pupuk WHERE status!='Batal' AND tgl_lunas = '$tgl_hari_ini'");
        $omzet_p_hari = (int)mysqli_fetch_assoc($q_p_hari)['total'] + (int)mysqli_fetch_assoc($q_p_hari_lunas)['total'];
        
        $total_hari_ini = $omzet_jt_hari + $omzet_b_hari + $omzet_p_hari;
        
        // PREPARE DATA FOR CHART (1 Bulan Penuh)
        $bulan_ini = date('m', strtotime($tgl_hari_ini));
        $tahun_ini = date('Y', strtotime($tgl_hari_ini));
        $jumlah_hari = cal_days_in_month(CAL_GREGORIAN, $bulan_ini, $tahun_ini);
        
        $bulan_indo_list = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
        $nama_bulan_ini = $bulan_indo_list[$bulan_ini] . " " . $tahun_ini;
        
        $prev_month_date = date('Y-m-d', strtotime("$tahun_ini-$bulan_ini-01 -1 month"));
        $next_month_date = date('Y-m-d', strtotime("$tahun_ini-$bulan_ini-01 +1 month"));

        $labels = [];
        $data_jt = [];
        $data_b = [];
        $data_p = [];
        
        $total_bulan_jt = 0;
        $total_bulan_b = 0;
        $total_bulan_p = 0;
        
        for($i=1; $i<=$jumlah_hari; $i++) {
            // Tanggal untuk hari ke-$i di bulan ini
            $d_curr = sprintf("%04d-%02d-%02d", $tahun_ini, $bulan_ini, $i);
            $labels[] = date('d M', strtotime($d_curr));
            
            // Omzet Current
            $o_jt_dp = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_bibit WHERE tipe_order='Jasa Tanam' AND status!='Batal' AND tgl_booking = '$d_curr'"))['total'];
            $o_jt_l = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_bibit WHERE tipe_order='Jasa Tanam' AND status!='Batal' AND tgl_lunas = '$d_curr'"))['total'];
            $o_jt = $o_jt_dp + $o_jt_l;
            
            $o_b_dp = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_bibit WHERE (tipe_order IS NULL OR tipe_order != 'Jasa Tanam') AND status!='Batal' AND tgl_booking = '$d_curr'"))['total'];
            $o_b_l = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_bibit WHERE (tipe_order IS NULL OR tipe_order != 'Jasa Tanam') AND status!='Batal' AND tgl_lunas = '$d_curr'"))['total'];
            $o_b = $o_b_dp + $o_b_l;
            
            $o_p_dp = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(dp_dibayar) as total FROM order_pupuk WHERE status!='Batal' AND DATE(tgl_order) = '$d_curr'"))['total'];
            $o_p_l = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(nominal_pelunasan) as total FROM order_pupuk WHERE status!='Batal' AND tgl_lunas = '$d_curr'"))['total'];
            $o_p = $o_p_dp + $o_p_l;
            
            $data_jt[] = $o_jt;
            $data_b[] = $o_b;
            $data_p[] = $o_p;
            
            $total_bulan_jt += $o_jt;
            $total_bulan_b += $o_b;
            $total_bulan_p += $o_p;
        }
    ?>
    <div class="animate-in fade-in duration-300">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <h2 class="text-[15px] font-bold text-gray-900 dark:text-white flex items-center"><i class="fa-solid fa-bolt text-[#d29922] mr-2"></i> Kinerja Harian (<?= formatTgl($tgl_hari_ini) ?>)</h2>
            <form method="GET" action="" class="flex items-center gap-2">
                <input type="hidden" name="page" value="laporan">
                <input type="hidden" name="tab" value="harian">
                <input type="text" id="datePicker" name="tgl" value="<?= $tgl_hari_ini ?>" placeholder="Pilih Tanggal..." class="bg-gray-50 dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white text-[13px] rounded-md px-3 py-1.5 focus:outline-none focus:border-[#58a6ff] w-32 text-center">
                <button type="submit" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-3 py-1.5 rounded-md text-[13px] font-bold transition-colors shadow-sm">Tampilkan</button>
                <a href="?page=laporan&tab=harian&tgl=<?= date('Y-m-d') ?>" class="bg-[#3fb950] hover:bg-[#2ea043] text-white px-3 py-1.5 rounded-md text-[13px] font-bold transition-colors shadow-sm">Hari Ini</a>
            </form>
        </div>
        
        <!-- CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-gradient-to-br from-[#1f6feb] to-[#0c2d6b] rounded-xl p-5 shadow-sm text-white relative overflow-hidden">
                <i class="fa-solid fa-wallet absolute -right-4 -bottom-4 text-7xl opacity-20"></i>
                <p class="text-[12px] font-bold text-blue-100 uppercase tracking-wider mb-1">Total Omzet</p>
                <h3 class="text-2xl font-bold"><?= formatRp($total_hari_ini) ?></h3>
            </div>
            
            <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-[#1f6feb]/10 flex items-center justify-center text-[#58a6ff]"><i class="fa-solid fa-person-digging text-sm"></i></div>
                    <p class="text-[12px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">Jasa Tanam</p>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($omzet_jt_hari) ?></h3>
            </div>
            
            <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-[#3fb950]/10 flex items-center justify-center text-[#3fb950]"><i class="fa-solid fa-seedling text-sm"></i></div>
                    <p class="text-[12px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">Jual Bibit</p>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($omzet_b_hari) ?></h3>
            </div>
            
            <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-full bg-[#a371f7]/10 flex items-center justify-center text-[#a371f7]"><i class="fa-solid fa-flask text-sm"></i></div>
                    <p class="text-[12px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">Pupuk & Obat</p>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($omzet_p_hari) ?></h3>
            </div>
        </div>

        <!-- CHART -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[15px] font-bold text-gray-900 dark:text-white flex items-center"><i class="fa-solid fa-chart-area text-[#3fb950] mr-2"></i> Grafik Omzet Bulanan (<?= $nama_bulan_ini ?>)</h2>
            <div class="flex gap-2">
                <a href="?page=laporan&tab=harian&tgl=<?= $prev_month_date ?>" class="bg-white dark:bg-[#161b22] hover:bg-gray-50 dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] px-3 py-1.5 rounded-md text-[12px] font-bold transition-colors border border-gray-300 dark:border-[#30363d] shadow-sm flex items-center">
                    <i class="fa-solid fa-chevron-left mr-1.5"></i> Bulan Sebelumnya
                </a>
                <a href="?page=laporan&tab=harian&tgl=<?= date('Y-m-d') ?>" class="bg-[#3fb950] hover:bg-[#2ea043] text-white px-3 py-1.5 rounded-md text-[12px] font-bold transition-colors shadow-sm flex items-center">
                    Bulan Ini
                </a>
                <a href="?page=laporan&tab=harian&tgl=<?= $next_month_date ?>" class="bg-white dark:bg-[#161b22] hover:bg-gray-50 dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] px-3 py-1.5 rounded-md text-[12px] font-bold transition-colors border border-gray-300 dark:border-[#30363d] shadow-sm flex items-center">
                    Bulan Selanjutnya <i class="fa-solid fa-chevron-right ml-1.5"></i>
                </a>
            </div>
        </div>
        <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl p-5 shadow-sm">
            <canvas id="salesChart" height="280"></canvas>
        </div>
        
        <!-- MONTHLY TOTALS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="bg-[#1f6feb]/10 border border-[#1f6feb]/20 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-bold text-[#1f6feb] uppercase tracking-wider mb-1">Total Jasa Tanam (<?= $nama_bulan_ini ?>)</p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_bulan_jt) ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-[#1f6feb]/20 flex items-center justify-center text-[#1f6feb]"><i class="fa-solid fa-person-digging text-lg"></i></div>
            </div>
            
            <div class="bg-[#3fb950]/10 border border-[#3fb950]/20 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-bold text-[#3fb950] uppercase tracking-wider mb-1">Total Jual Bibit (<?= $nama_bulan_ini ?>)</p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_bulan_b) ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-[#3fb950]/20 flex items-center justify-center text-[#3fb950]"><i class="fa-solid fa-seedling text-lg"></i></div>
            </div>
            
            <div class="bg-[#a371f7]/10 border border-[#a371f7]/20 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-bold text-[#a371f7] uppercase tracking-wider mb-1">Total Pupuk & Obat (<?= $nama_bulan_ini ?>)</p>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_bulan_p) ?></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-[#a371f7]/20 flex items-center justify-center text-[#a371f7]"><i class="fa-solid fa-flask text-lg"></i></div>
            </div>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Inisialisasi Flatpickr
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d M Y", // Menampilkan format hari bulan tahun
            defaultDate: "<?= $tgl_hari_ini ?>"
        });

        const ctx = document.getElementById('salesChart').getContext('2d');
        
        let gradientJT = ctx.createLinearGradient(0, 0, 0, 400);
        gradientJT.addColorStop(0, 'rgba(31, 111, 235, 0.4)'); // #1f6feb (blue)
        gradientJT.addColorStop(1, 'rgba(31, 111, 235, 0)');
        
        let gradientB = ctx.createLinearGradient(0, 0, 0, 400);
        gradientB.addColorStop(0, 'rgba(63, 185, 80, 0.4)'); // #3fb950 (green)
        gradientB.addColorStop(1, 'rgba(63, 185, 80, 0)');
        
        let gradientP = ctx.createLinearGradient(0, 0, 0, 400);
        gradientP.addColorStop(0, 'rgba(163, 113, 247, 0.4)'); // #a371f7 (purple)
        gradientP.addColorStop(1, 'rgba(163, 113, 247, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [
                    {
                        label: 'Jasa Tanam',
                        data: <?= json_encode($data_jt) ?>,
                        borderColor: '#1f6feb',
                        backgroundColor: gradientJT,
                        borderWidth: 2,
                        pointBackgroundColor: '#1f6feb',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#1f6feb',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Jual Bibit',
                        data: <?= json_encode($data_b) ?>,
                        borderColor: '#3fb950',
                        backgroundColor: gradientB,
                        borderWidth: 2,
                        pointBackgroundColor: '#3fb950',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#3fb950',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Pupuk & Obat',
                        data: <?= json_encode($data_p) ?>,
                        borderColor: '#a371f7',
                        backgroundColor: gradientP,
                        borderWidth: 2,
                        pointBackgroundColor: '#a371f7',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#a371f7',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#8b949e',
                            font: { family: "'Inter', sans-serif", weight: 'bold' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(13, 17, 23, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#c9d1d9',
                        borderColor: '#30363d',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#30363d', drawBorder: false },
                        ticks: {
                            color: '#8b949e',
                            callback: function(value) {
                                if (value >= 1000000) { return 'Rp ' + (value / 1000000) + ' Jt'; }
                                if (value >= 1000) { return 'Rp ' + (value / 1000) + ' Rb'; }
                                return value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#8b949e', font: { weight: 'bold' } }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</div>
