<?php
include __DIR__ . '/../components/koneksi.php'; // Panggil koneksi database

// Ambil semua data kategori dari database
$query = "SELECT * FROM kategori_bibit ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Fungsi menentukan status
function getStatus($stok) {
    if ($stok == 0) return ['label' => '<i class="fa-solid fa-xmark"></i> HABIS', 'class' => 'text-red-500'];
    if ($stok <= 300) return ['label' => '<i class="fa-solid fa-triangle-exclamation"></i> MENIPIS', 'class' => 'text-yellow-500'];
    return ['label' => '<i class="fa-solid fa-check"></i> AMAN', 'class' => 'text-green-500']; 
}
?>

<div class="bg-white dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] min-h-full rounded-xl p-6 text-gray-800 dark:text-[#c9d1d9] shadow transition-colors duration-200">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold flex items-center text-gray-900 dark:text-white">
                <i class="fa-solid fa-cube text-[#d2a878] mr-3"></i> Stok Gudang Benih Padi
            </h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-1 ml-8">Kelola persediaan bahan benih untuk penyebaran ke baris. Klik kategori untuk melihat detail stok per varietas.</p>
        </div>
        
        <a href="?page=kategori-benih" class="bg-[#238636] hover:bg-[#2ea043] text-white px-4 py-2 rounded-md text-[13px] font-medium transition-colors flex items-center shadow-lg inline-flex">
            <i class="fa-solid fa-gear mr-2"></i> Kelola Kategori
        </a>
    </div>

    <div class="bg-blue-50 dark:bg-[#161b22] border border-blue-100 dark:border-[#30363d] rounded-lg p-4 mb-8 flex gap-4 items-start transition-colors duration-200">
        <i class="fa-solid fa-lightbulb text-blue-500 dark:text-blue-400 mt-1"></i>
        <div>
            <h3 class="font-semibold text-[13px] text-gray-800 dark:text-white mb-1">Fitur Stok Gudang</h3>
            <ul class="text-[12px] text-gray-600 dark:text-[#8b949e] space-y-1 list-disc list-inside">
                <li>Stok otomatis berkurang saat sebar benih (10 kg per baris)</li>
                <li>Peringatan real-time jika stok menipis atau habis</li>
                <li>Terintegrasi dengan manajemen baris bibit padi</li>
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        
        <?php if(mysqli_num_rows($result) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
                <?php 
                    $id_kategori = $row['id'];
                    $rgb = $row['rgb']; 
                    
                    // ==============================================================
                    // LOGIKA BARU: Hitung total stok dari tabel varietas_bibit
                    // ==============================================================
                    $q_stok = mysqli_query($conn, "SELECT SUM(stok_kg) as total_stok FROM varietas_bibit WHERE id_kategori='$id_kategori'");
                    $d_stok = mysqli_fetch_assoc($q_stok);
                    
                    // Jika total_stok kosong (null), jadikan 0. Jika ada, pakai angkanya.
                    $stok = $d_stok['total_stok'] ? $d_stok['total_stok'] : 0; 
                    $status = getStatus($stok); 
                ?>
                
                <div class="bg-white dark:bg-[#161b22] border rounded-lg p-5 flex flex-col justify-between hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors group shadow-sm" style="border-color: rgb(<?= $rgb ?>);">
                    
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 rounded-md flex items-center justify-center" style="background-color: rgba(<?= $rgb ?>, 0.1);">
                            <i class="fa-solid fa-cube text-lg" style="color: rgb(<?= $rgb ?>);"></i>
                        </div>
                        <span class="text-[11px] font-bold <?= $status['class'] ?>">
                            <?= $status['label'] ?>
                        </span>
                    </div>

                    <div class="mt-4 mb-2">
                        <h3 class="text-[14px] font-bold text-gray-700 dark:text-[#c9d1d9] group-hover:text-gray-900 dark:group-hover:text-white transition-colors"><?= ucwords(str_ireplace('bibit', 'benih padi', htmlspecialchars($row['nama']))) ?></h3>
                        <div class="mt-1 flex items-baseline">
                            <span class="text-3xl font-bold text-gray-900 dark:text-white"><?= number_format($stok, 0, ',', '.') ?></span>
                            <span class="text-sm text-gray-500 dark:text-[#8b949e] ml-1">kg</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-[#30363d] transition-colors">
                        <a href="?page=detail-stok&slug=<?= $row['slug'] ?>" class="text-indigo-600 dark:text-[#58a6ff] hover:text-indigo-800 dark:hover:text-[#79c0ff] text-[12px] font-medium inline-flex items-center transition-colors">
                            Lihat Detail <i class="fa-solid fa-arrow-right-long ml-2"></i>
                        </a>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 text-yellow-600 dark:text-yellow-400 p-6 rounded-lg text-center">
                <i class="fa-solid fa-box-open text-3xl mb-3"></i>
                <p class="text-[13px]">Belum ada data kategori benih. Silakan tambahkan kategori terlebih dahulu.</p>
                <a href="?page=kategori-benih" class="mt-3 inline-block bg-yellow-100 dark:bg-yellow-800 text-yellow-700 dark:text-yellow-200 px-4 py-2 rounded-md text-sm font-medium hover:bg-yellow-200 dark:hover:bg-yellow-700 transition-colors">Kelola Kategori</a>
            </div>
        <?php endif; ?>

    </div>
</div>