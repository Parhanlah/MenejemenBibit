<?php 
    // Ambil parameter '?page=' dari URL saat ini
    $halaman_aktif = isset($_GET['page']) ? $_GET['page'] : ''; 
    
    // Fungsi untuk menentukan warna menu aktif
    function cekAktif($target_halaman, $halaman_sekarang) {
        $daftar_target = explode(',', $target_halaman);
        if (in_array($halaman_sekarang, $daftar_target)) {
            return 'bg-blue-50 dark:bg-[#1f6feb]/20 text-blue-600 dark:text-[#58a6ff] font-bold shadow-sm';
        } else {
            return 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-[#21262d] hover:text-gray-900 dark:hover:text-white font-medium';
        }
    }
?>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden backdrop-blur-sm transition-opacity duration-300 opacity-0 md:hidden"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 transform -translate-x-full md:translate-x-0 md:relative transition-all duration-300 ease-in-out bg-white dark:bg-[#0b1120] border-r border-gray-200 dark:border-gray-800 flex flex-col justify-between shrink-0 h-full">
    
    <div class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200 dark:border-gray-800 shrink-0">
            <div class="flex items-center">
                <img src="https://poncotani.com/wp-content/uploads/2025/07/Graphic-ID-50x50.png" alt="Logo" class="w-8 h-8 mr-3">
                <span class="font-bold text-xl tracking-tight text-gray-900 dark:text-white">Ponco<span class="text-gray-500 font-normal">Tani</span></span>
            </div>
            <button id="close-sidebar-btn" class="md:hidden text-gray-400 hover:text-gray-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <nav class="p-4 space-y-6">
            <div>
                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 mb-3 ml-2 uppercase tracking-wider">Operasional & Bisnis</p>
                <ul class="space-y-1">
                    <li><a href="?page=stock-benih" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('stock-benih,detail-stok', $halaman_aktif) ?>"><i class="fa-solid fa-cube w-5 text-center mr-3"></i> Stok Benih Padi</a></li>
                    <li><a href="?page=stok-pupuk" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('stok-pupuk', $halaman_aktif) ?>"><i class="fa-solid fa-flask w-5 text-center mr-3"></i> Stok Pupuk & Obat</a></li>
                    <li><a href="?page=order-pupuk" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('order-pupuk', $halaman_aktif) ?>"><i class="fa-solid fa-cart-arrow-down w-5 text-center mr-3"></i> Order Pupuk & Obat</a></li>
                    <li><a href="?page=order-bibit" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('order-bibit', $halaman_aktif) ?>"><i class="fa-solid fa-cart-shopping w-5 text-center mr-3"></i> Order Bibit Padi</a></li>
                    <li><a href="?page=jasa-tanam" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('jasa-tanam', $halaman_aktif) ?>"><i class="fa-solid fa-person-digging w-5 text-center mr-3"></i> Jasa Tanam Padi</a></li>
                    <li><a href="?page=kelola-kupon" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('kelola-kupon', $halaman_aktif) ?>"><i class="fa-solid fa-ticket w-5 text-center mr-3"></i> Kelola Kupon</a></li>
                </ul>
            </div>

            <div>
                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 mb-3 ml-2 uppercase tracking-wider">Manajemen Lahan</p>
                <ul class="space-y-1">
                    <li><a href="?page=bibit-baris" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('bibit-baris', $halaman_aktif) ?>"><i class="fa-solid fa-chart-line w-5 text-center mr-3"></i> Bibit Baris</a></li>
                    <li><a href="?page=sebar-benih" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('sebar-benih', $halaman_aktif) ?>"><i class="fa-solid fa-seedling w-5 text-center mr-3"></i> Sebar Benih</a></li>
                </ul>
            </div>

            <div>
                <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 mb-3 ml-2 uppercase tracking-wider">Analisis & Pelaporan</p>
                <ul class="space-y-1">
                    <li><a href="?page=laporan" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('laporan', $halaman_aktif) ?>"><i class="fa-solid fa-file-invoice-dollar w-5 text-center mr-3"></i> Laporan Utama</a></li>
                </ul>
            </div>
        </nav>
    </div>

    <div class="shrink-0">
        <div class="px-4 pb-2 pt-4 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b1120]">
            <a href="?page=super-admin" class="flex items-center px-2 py-2 text-sm rounded-lg transition-colors <?= cekAktif('super-admin', $halaman_aktif) ?>">
                <i class="fa-solid fa-gear w-5 text-center mr-3"></i> Pengaturan
            </a>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-[#161b22]">
            <p class="text-xs text-gray-500 dark:text-gray-400">Logged in as:</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Bagus - Bibit Manager</p>
        </div>
    </div>
</aside>

<style>.custom-scrollbar::-webkit-scrollbar { width: 4px; } .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }</style>