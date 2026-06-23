<?php
include 'components/koneksi.php';

$tgl_hari_ini = date('Y-m-d');

// 1. AMBIL DATA ORDER AKTIF
$q_active_orders = mysqli_query($conn, "SELECT * FROM order_bibit WHERE status NOT IN ('diambil', 'Selesai', 'Batal') ORDER BY id ASC");
$active_orders_map = [];
while($ao = mysqli_fetch_assoc($q_active_orders)) {
    $active_orders_map[$ao['id_baris']][] = $ao;
}

// 2. AMBIL DATA BARIS
$query_baris = mysqli_query($conn, "SELECT b.*, v.nama_varietas, v.kode_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id ORDER BY b.id_baris ASC");

$data_baris = [];
while($r = mysqli_fetch_assoc($query_baris)) {
    $status_db = $r['status'];
    if ($status_db == 'persiapan' && !empty($r['tgl_sebar']) && $r['tgl_sebar'] <= $tgl_hari_ini) {
        $status_db = 'tumbuh'; 
        mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh' WHERE id_baris=".$r['id_baris']);
    }

    $umur_hari = 0;
    if ($status_db != 'kosong' && $status_db != 'persiapan') {
        $diff = strtotime($tgl_hari_ini) - strtotime($r['tgl_sebar']);
        $umur_hari = floor($diff / 86400); 
        if ($umur_hari < 0) $umur_hari = 0;
    }

    $agri_status = ''; $m_free = (float)$r['tersedia_m'];
    
    if ($status_db == 'kosong' || $status_db == 'persiapan') {
        $agri_status = 'Kosong'; 
        $m_free = 0; 
    } else {
        if ($umur_hari <= 3) {
            $agri_status = 'Semai';
        } elseif ($umur_hari <= 11) {
            $agri_status = 'Muda';
        } elseif ($umur_hari <= 20) {
            $agri_status = 'Matang';
        } else {
            $agri_status = 'Tua';
        }
    }

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

    $total_tersedia = $m_kosong_sales + $m_free;

    $data_baris[$r['id_baris']] = [
        'varietas' => $r['nama_varietas'] ? $r['nama_varietas'] : '-',
        'agri_status' => $agri_status,
        'total_tersedia' => $total_tersedia,
        'kosong' => ($status_db == 'kosong' || $status_db == 'persiapan'),
        'umur_hari' => $umur_hari
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Ketersediaan Lahan Bibit - Poncotani</title>
    <!-- Auto Refresh setiap 60 detik -->
    <meta http-equiv="refresh" content="60">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
        // Check theme before body loads
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; }
    </style>
</head>
<body class="min-h-screen flex flex-col p-4 md:p-8 bg-[#f6f8fa] text-gray-900 dark:bg-[#0d1117] dark:text-[#c9d1d9] transition-colors duration-300">

    <div class="flex justify-between items-center mb-6 border-b border-gray-300 dark:border-gray-800 pb-4">
        <div>
            <h1 class="text-2xl md:text-4xl lg:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center">
                <i onclick="toggleTheme()" class="fa-solid fa-display text-[#3fb950] mr-4 cursor-pointer hover:scale-110 hover:text-green-500 transition-all duration-200" title="Toggle Terang/Gelap"></i>
                MONITORING LAHAN BIBIT
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2 text-sm md:text-lg tracking-wide">Peta ketersediaan baris bibit padi</p>
        </div>
        <div class="text-right flex flex-col items-end">
            <p class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white tracking-widest drop-shadow-md mt-2" id="clock">00:00:00</p>
            <p class="text-gray-600 dark:text-gray-400 text-sm md:text-base font-bold mt-1 tracking-wider"><?= date('d M Y') ?></p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 italic hidden md:block">Tekan 'F' Fullscreen | 'Spasi' Play/Pause | 'Kiri/Kanan' Pindah Slide</p>
        </div>
    </div>



    <!-- Page Indicator -->
    <div class="flex justify-between items-end mb-2 hidden" id="pagination-info">
        <span class="text-sm font-bold text-gray-600 dark:text-gray-400">Menampilkan Halaman <span id="current-page-text">1</span> dari <span id="total-page-text">1</span></span>
        <span class="text-xs text-gray-500 italic font-bold" id="auto-slide-status"><i class="fa-solid fa-play mr-1 text-green-500"></i> Auto-slide aktif</span>
    </div>

    <div class="w-full overflow-x-auto pb-4">
        <div id="grid-baris" class="grid grid-cols-10 gap-2 sm:gap-3 content-start pb-10 min-w-[900px] lg:min-w-full transition-opacity duration-500">
            <?php for($i=1; $i<=85; $i++): ?>
            <?php 
                $bg_color = "bg-gray-800/40 border-gray-700/50 text-gray-500 opacity-60"; // Default: Belum Aktif
                $text_tersedia = "";
                $status_text = "N/A";
                $glow = "";
                $badge_bg = "bg-black/40 text-white"; // default badge
                
                if(isset($data_baris[$i])) {
                    $b = $data_baris[$i];
                    $t = $b['total_tersedia'];
                    $agri_status = $b['kosong'] ? 'Kosong' : $b['agri_status'];
                    
                    // 1. Set Warna Kotak (Berdasarkan Fase Tanam seperti desain asli)
                    if ($agri_status == 'Kosong') {
                        $bg_color = "bg-gray-200 dark:bg-gray-500/20 border-gray-400 dark:border-gray-500 text-gray-600 dark:text-gray-400";
                    } elseif ($agri_status == 'Semai') {
                        $bg_color = "bg-[#58a6ff]/20 dark:bg-[#58a6ff]/30 border-[#0366d6] dark:border-[#58a6ff] text-[#0366d6] dark:text-[#58a6ff]";
                        $glow = "hover:shadow-[0_0_20px_rgba(88,166,255,0.5)]";
                    } elseif ($agri_status == 'Muda') {
                        $bg_color = "bg-[#3fb950]/20 dark:bg-[#3fb950]/30 border-[#238636] dark:border-[#3fb950] text-[#238636] dark:text-[#3fb950]";
                        $glow = "hover:shadow-[0_0_20px_rgba(63,185,80,0.5)]";
                    } elseif ($agri_status == 'Matang') {
                        $bg_color = "bg-[#d29922]/20 dark:bg-[#d29922]/30 border-[#9e6a03] dark:border-[#d29922] text-[#9e6a03] dark:text-[#d29922]";
                        $glow = "hover:shadow-[0_0_20px_rgba(210,153,34,0.5)]";
                    } elseif ($agri_status == 'Tua') {
                        $bg_color = "bg-[#f85149]/20 dark:bg-[#f85149]/30 border-[#da3633] dark:border-[#f85149] text-[#da3633] dark:text-[#f85149]";
                        $glow = "hover:shadow-[0_0_20px_rgba(248,81,73,0.5)]";
                    }

                    // 2. Set Warna Badge Tersedia (Ketersediaan Penjualan)
                    if ($agri_status == 'Kosong') {
                        $badge_bg = "bg-gray-200 text-gray-500 dark:bg-gray-800/80 dark:text-gray-500 shadow-none border border-gray-300 dark:border-gray-700";
                        $text_tersedia = "-";
                        $badge_text = "Kosong";
                    } else {
                        if($t == 12) {
                            $badge_bg = "bg-[#2ea043] text-white shadow-[0_0_8px_rgba(46,160,67,0.6)]"; // Full Green
                        } elseif ($t > 0) {
                            $badge_bg = "bg-[#d29922] text-white shadow-[0_0_8px_rgba(210,153,34,0.6)]"; // Partial Yellow
                        } else {
                            $badge_bg = "bg-[#f85149] text-white shadow-[0_0_8px_rgba(248,81,73,0.6)]"; // Empty Red
                        }
                        $text_tersedia = $t . "m";
                        $badge_text = "Sisa: " . $text_tersedia;
                    }
                    $status_text = $agri_status;
                }
            ?>
            <div class="grid-item-baris border-[3px] rounded-xl flex flex-col p-1.5 md:p-2 h-20 sm:h-24 md:h-28 lg:h-32 <?= $bg_color ?> <?= $glow ?> transition-all duration-300 relative group cursor-default">
                <span class="absolute top-1 left-1.5 sm:top-1.5 sm:left-2 text-[8px] sm:text-[10px] md:text-[11px] font-extrabold text-black dark:text-white drop-shadow-md">#<?= $i ?></span>
                <?php if(isset($data_baris[$i])): ?>
                    <div class="flex-1 flex flex-col items-center justify-center mt-2 sm:mt-3">
                        <?php if(!$b['kosong']): ?>
                            <span class="text-[12px] sm:text-[16px] md:text-[20px] lg:text-[24px] font-black text-gray-800 dark:text-gray-200 leading-none"><?= $b['umur_hari'] ?> <span class="text-[8px] sm:text-[10px] md:text-[12px] font-bold text-gray-500 dark:text-gray-400">HSS</span></span>
                        <?php else: ?>
                            <span class="text-[12px] sm:text-[16px] md:text-[20px] lg:text-[24px] font-black text-gray-400 dark:text-gray-500 leading-none">-</span>
                        <?php endif; ?>
                        <span class="text-[7px] sm:text-[8px] md:text-[9px] mt-0.5 sm:mt-1 uppercase tracking-widest text-center w-full truncate font-bold"><?= $status_text ?></span>
                    </div>

                    <span class="text-[8px] sm:text-[10px] md:text-[12px] font-extrabold <?= $badge_bg ?> px-1 py-0.5 rounded w-full mx-auto text-center truncate tracking-wide mt-auto"><?= $badge_text ?></span>
                    
                    <!-- Tooltip untuk info ekstra -->
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-max px-3 py-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 shadow-2xl">
                        <p class="font-bold text-blue-600 dark:text-[#58a6ff] mb-1">Baris #<?= $i ?></p>
                        <p>Fase: <span class="font-bold"><?= $status_text ?></span></p>
                        <?php if(!$b['kosong']): ?><p>Umur: <span class="font-bold"><?= $b['umur_hari'] ?> HSS</span></p><?php endif; ?>
                        <p>Tersedia: <span class="font-bold"><?= $text_tersedia ?></span></p>
                    </div>
                <?php else: ?>
                    <div class="flex-1 flex items-center justify-center">
                        <span class="text-[8px] sm:text-[10px] text-gray-600 mt-1">-</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
        </div>
    </div>


    <script>
        // Update jam secara realtime
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString('id-ID', { hour12: false });
        }, 1000);
        // Auto Slide Pagination Logic
        document.addEventListener('DOMContentLoaded', function() {
            const items = document.querySelectorAll('.grid-item-baris');
            const itemsPerPage = 50; // Tampilkan 50 kotak per halaman
            
            if (items.length > itemsPerPage) {
                const totalPages = Math.ceil(items.length / itemsPerPage);
                let currentPage = 0;
                let isPlaying = true;
                let slideInterval;
                const gridContainer = document.getElementById('grid-baris');
                const paginationInfo = document.getElementById('pagination-info');
                const slideStatus = document.getElementById('auto-slide-status');
                
                if (paginationInfo) {
                    paginationInfo.classList.remove('hidden');
                    document.getElementById('total-page-text').innerText = totalPages;
                }

                function showPage(page) {
                    items.forEach((item, index) => {
                        if (index >= page * itemsPerPage && index < (page + 1) * itemsPerPage) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    if (paginationInfo) {
                        document.getElementById('current-page-text').innerText = page + 1;
                    }
                }

                function transitionToPage(newPage) {
                    gridContainer.style.opacity = '0';
                    setTimeout(() => {
                        currentPage = newPage;
                        if (currentPage >= totalPages) currentPage = 0;
                        if (currentPage < 0) currentPage = totalPages - 1;
                        showPage(currentPage);
                        gridContainer.style.opacity = '1';
                    }, 500); 
                }

                function startSlide() {
                    isPlaying = true;
                    if(slideStatus) slideStatus.innerHTML = '<i class="fa-solid fa-play mr-1 text-green-500"></i> Auto-slide aktif';
                    slideInterval = setInterval(() => {
                        transitionToPage(currentPage + 1);
                    }, 15000); // Ganti slide setiap 15 detik
                }

                function stopSlide() {
                    isPlaying = false;
                    if(slideStatus) slideStatus.innerHTML = '<i class="fa-solid fa-pause mr-1 text-yellow-500"></i> Auto-slide dijeda';
                    clearInterval(slideInterval);
                }

                showPage(currentPage);
                startSlide();

                // Keyboard controls
                document.addEventListener('keydown', function(e) {
                    if (e.code === 'ArrowRight') {
                        stopSlide();
                        transitionToPage(currentPage + 1);
                    } else if (e.code === 'ArrowLeft') {
                        stopSlide();
                        transitionToPage(currentPage - 1);
                    } else if (e.code === 'Space') {
                        e.preventDefault(); // Mencegah scroll saat menekan spasi
                        if (isPlaying) stopSlide();
                        else startSlide();
                    }
                });
            }
        });
        // Fullscreen Shortcut
        document.addEventListener('keydown', function(e) {
            if (e.key === 'f' || e.key === 'F') {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(err => {
                        console.log(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                    });
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                }
            }
        });
    </script>
</body>
</html>
