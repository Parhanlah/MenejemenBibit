<?php
include 'components/koneksi.php';

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}

// 1. ENGINE FILTER PERIODE WAKTU
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';

if ($periode == 'harian') {
    $judul_periode = "Hari Ini (" . date('d M Y') . ")";
    $sql_filter = "DATE(tgl_order) = CURDATE()";
} elseif ($periode == 'mingguan') {
    $judul_periode = "Minggu Ini";
    $sql_filter = "YEARWEEK(tgl_order, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($periode == 'bulanan') {
    $judul_periode = "Bulan Ini (" . date('F Y') . ")";
    $sql_filter = "MONTH(tgl_order) = MONTH(CURDATE()) AND YEAR(tgl_order) = YEAR(CURDATE())";
} else {
    $judul_periode = "Semua Waktu";
    $sql_filter = "1=1"; // Tampilkan semua
}

// 2. QUERY DATABASE (Contoh penggabungan data bibit & jasa tanam yang lunas)
// Catatan: Sesuaikan nama tabel dan kolom dengan struktur database asli Anda
/*
$q_laporan = mysqli_query($conn, "SELECT * FROM transaksi_utama WHERE status='Lunas' AND $sql_filter ORDER BY id DESC");
$total_omset = 0; $total_bibit = 0; $total_jasa = 0;
while($row = mysqli_fetch_assoc($q_laporan)) {
    $total_omset += $row['total_bayar'];
    $total_bibit += $row['subtotal_bibit'];
    $total_jasa += $row['subtotal_jasa'];
}
*/

// DUMMY DATA UNTUK PREVIEW UI
$total_omset = 15500000;
$total_bibit = 12000000;
$total_jasa  = 3500000;
?>

<div class="space-y-6 animate-in fade-in zoom-in duration-300">
    
    <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar text-[#58a6ff]"></i> Laporan Transaksi Terpadu
            </h2>
            <p class="text-sm text-gray-500 dark:text-[#8b949e] mt-1">Data Penjualan: <span class="font-bold text-gray-700 dark:text-gray-300"><?= $judul_periode ?></span></p>
        </div>
        
        <div class="flex bg-gray-100 dark:bg-[#161b22] p-1 rounded-lg border border-gray-200 dark:border-[#30363d]">
            <a href="?page=laporan&periode=harian" class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?= $periode == 'harian' ? 'bg-white dark:bg-[#1f6feb] text-blue-600 dark:text-white shadow-sm' : 'text-gray-500 dark:text-[#8b949e] hover:text-gray-900 dark:hover:text-white' ?>">Harian</a>
            <a href="?page=laporan&periode=mingguan" class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?= $periode == 'mingguan' ? 'bg-white dark:bg-[#1f6feb] text-blue-600 dark:text-white shadow-sm' : 'text-gray-500 dark:text-[#8b949e] hover:text-gray-900 dark:hover:text-white' ?>">Mingguan</a>
            <a href="?page=laporan&periode=bulanan" class="px-4 py-1.5 text-xs font-bold rounded-md transition-all <?= $periode == 'bulanan' ? 'bg-white dark:bg-[#1f6feb] text-blue-600 dark:text-white shadow-sm' : 'text-gray-500 dark:text-[#8b949e] hover:text-gray-900 dark:hover:text-white' ?>">Bulanan</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-[#1f6feb]/10 flex items-center justify-center text-blue-600 dark:text-[#58a6ff] text-xl"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Omset Kas</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_omset) ?></h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-[#238636]/10 flex items-center justify-center text-emerald-600 dark:text-[#3fb950] text-xl"><i class="fa-solid fa-seedling"></i></div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Pendapatan Bibit</p>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_bibit) ?></h3>
            </div>
        </div>
        <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-purple-50 dark:bg-[#8957e5]/10 flex items-center justify-center text-purple-600 dark:text-[#bc8cff] text-xl"><i class="fa-solid fa-person-digging"></i></div>
            <div>
                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Pendapatan Jasa Tanam</p>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRp($total_jasa) ?></h3>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-[#0d1117] rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#161b22] flex justify-between items-center">
            <h3 class="font-bold text-sm text-gray-900 dark:text-white">Rincian Transaksi Selesai</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 dark:bg-[#161b22] border-b border-gray-200 dark:border-[#30363d] text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-5">No. Invoice</th>
                        <th class="py-3 px-5">Tanggal</th>
                        <th class="py-3 px-5">Pelanggan</th>
                        <th class="py-3 px-5">Rincian Order</th>
                        <th class="py-3 px-5 text-right">Total Dibayar</th>
                        <th class="py-3 px-5 text-center">Cetak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-sm text-gray-700 dark:text-gray-300">
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]/50 transition-colors">
                        <td class="py-3 px-5 font-mono font-bold text-gray-900 dark:text-[#58a6ff]">INV-PCT/01/I/2026</td>
                        <td class="py-3 px-5">05 Jan 2026</td>
                        <td class="py-3 px-5 font-medium">Dr. Joko Pitoyo</td>
                        <td class="py-3 px-5">
                            <span class="inline-block px-2 py-1 bg-emerald-100 dark:bg-[#238636]/20 text-emerald-700 dark:text-[#3fb950] text-[10px] rounded mr-1">Bibit</span>
                            <span class="inline-block px-2 py-1 bg-purple-100 dark:bg-[#8957e5]/20 text-purple-700 dark:text-[#bc8cff] text-[10px] rounded">Jasa Tanam</span>
                        </td>
                        <td class="py-3 px-5 text-right font-bold text-gray-900 dark:text-white">Rp 3.200.000</td>
                        <td class="py-3 px-5 text-center">
                            <a href="cetak-invoice.php?id=INV-PCT/01/I/2026" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded bg-gray-100 dark:bg-[#21262d] text-gray-600 dark:text-gray-400 hover:bg-blue-600 hover:text-white dark:hover:bg-[#1f6feb] transition-colors" title="Cetak A4">
                                <i class="fa-solid fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]/50 transition-colors">
                        <td class="py-3 px-5 font-mono font-bold text-gray-900 dark:text-[#58a6ff]">INV-PCT/02/I/2026</td>
                        <td class="py-3 px-5">05 Jan 2026</td>
                        <td class="py-3 px-5 font-medium">Bpk. Safuwan</td>
                        <td class="py-3 px-5">
                            <span class="inline-block px-2 py-1 bg-emerald-100 dark:bg-[#238636]/20 text-emerald-700 dark:text-[#3fb950] text-[10px] rounded mr-1">Bibit</span>
                        </td>
                        <td class="py-3 px-5 text-right font-bold text-gray-900 dark:text-white">Rp 850.000</td>
                        <td class="py-3 px-5 text-center">
                            <a href="cetak-invoice.php?id=INV-PCT/02/I/2026" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded bg-gray-100 dark:bg-[#21262d] text-gray-600 dark:text-gray-400 hover:bg-blue-600 hover:text-white dark:hover:bg-[#1f6feb] transition-colors" title="Cetak A4">
                                <i class="fa-solid fa-print"></i>
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>