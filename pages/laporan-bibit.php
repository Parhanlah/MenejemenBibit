<?php
include __DIR__ . '/../components/koneksi.php';

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}

// 1. DETEKSI FILTER PERIODE (Default: harian)
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';

// 2. KONDISI SQL OTOMATIS BERDASARKAN PARAMETER
if ($periode == 'harian') {
    $sub_title = "Data Transaksi Hari Ini (" . date('d M Y') . ")";
    $where_clause = "WHERE DATE(tgl_booking) = CURDATE()";
} elseif ($periode == 'mingguan') {
    $sub_title = "Data Transaksi Minggu Ini";
    $where_clause = "WHERE YEARWEEK(tgl_booking, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($periode == 'bulanan') {
    $sub_title = "Data Transaksi Bulan Ini (" . date('F Y') . ")";
    $where_clause = "WHERE MONTH(tgl_booking) = MONTH(CURDATE()) AND YEAR(tgl_booking) = YEAR(CURDATE())";
}

// 3. QUERY DATA ASLI DARI DATABASE
$q_ringkasan = mysqli_query($conn, "SELECT SUM(total_harga) as pendapatan, COUNT(id_order) as total_trx FROM order_bibit $where_clause AND status='Lunas'");
$data_ringkasan = mysqli_fetch_assoc($q_ringkasan);

$total_pendapatan = $data_ringkasan['pendapatan'] ?? 0;
$total_transaksi = $data_ringkasan['total_trx'] ?? 0;

$q_tabel = mysqli_query($conn, "SELECT * FROM order_bibit $where_clause ORDER BY id_order DESC");
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-[#0d1117] p-6 rounded-xl shadow border border-gray-200 dark:border-[#30363d] flex flex-col md:flex-row justify-between items-md-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Laporan Keuangan - Order Bibit</h2>
            <p class="text-sm text-gray-500 dark:text-[#8b949e]"><?= $sub_title ?></p>
        </div>
        
        <div class="flex bg-gray-100 dark:bg-[#161b22] p-1 rounded-lg border dark:border-[#30363d]">
            <a href="?page=laporan-bibit&periode=harian" class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?= $periode == 'harian' ? 'bg-[#1f6feb] text-white shadow' : 'text-gray-500 dark:text-[#8b949e] hover:text-white' ?>">Harian</a>
            <a href="?page=laporan-bibit&periode=mingguan" class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?= $periode == 'mingguan' ? 'bg-[#1f6feb] text-white shadow' : 'text-gray-500 dark:text-[#8b949e] hover:text-white' ?>">Mingguan</a>
            <a href="?page=laporan-bibit&periode=bulanan" class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?= $periode == 'bulanan' ? 'bg-[#1f6feb] text-white shadow' : 'text-gray-500 dark:text-[#8b949e] hover:text-white' ?>">Bulanan</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-[#0d1117] p-6 rounded-xl shadow border border-gray-200 dark:border-[#30363d] flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500 text-xl"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase">Omset Pendapatan</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_pendapatan) ?></h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#0d1117] p-6 rounded-xl shadow border border-gray-200 dark:border-[#30363d] flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-sky-500/10 flex items-center justify-center text-sky-500 text-xl"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase">Volume Orderan</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><?= $total_transaksi ?> <span class="text-sm font-normal text-gray-400">Nota Selesai</span></h3>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#0d1117] rounded-xl shadow border border-gray-200 dark:border-[#30363d] overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 dark:bg-[#161b22] border-b border-gray-200 dark:border-[#30363d] text-[11px] font-bold text-gray-500 uppercase">
                <tr>
                    <th class="py-3.5 px-5">Nota</th><th class="py-3.5 px-5">Pelangan</th><th class="py-3.5 px-5">Total Belanja</th><th class="py-3.5 px-5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-sm text-gray-700 dark:text-gray-300">
                <?php if(mysqli_num_rows($q_tabel) > 0): ?>
                    <?php while($t = mysqli_fetch_assoc($q_tabel)): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]/50 transition-colors">
                        <td class="py-3 px-5 font-mono font-bold text-white"><?= $t['no_order'] ?? 'INV-'.$t['id_order'] ?></td>
                        <td class="py-3 px-5"><?= $t['nama_customer'] ?></td>
                        <td class="py-3 px-5 font-bold text-emerald-400"><?= formatRp($t['total_harga']) ?></td>
                        <td class="py-3 px-5"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><?= $t['status'] ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="py-8 text-center text-gray-500 italic bg-[#161b22]/20">Tidak ada transaksi pada periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>