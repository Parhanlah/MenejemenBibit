<?php
include __DIR__ . '/../components/koneksi.php';

// 1. AUTO-CREATE TABEL PENGATURAN SISTEM
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pengaturan_sistem` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `kunci` varchar(50) NOT NULL UNIQUE,
    `nilai` varchar(100) NOT NULL,
    PRIMARY KEY (`id`)
)");

// Insert default value jika masih kosong
$cek_global_bibit = mysqli_query($conn, "SELECT * FROM pengaturan_sistem WHERE kunci='harga_bibit_global'");
if(mysqli_num_rows($cek_global_bibit) == 0) {
    mysqli_query($conn, "INSERT INTO pengaturan_sistem (kunci, nilai) VALUES ('harga_bibit_global', '800000'), ('harga_jasa_tanam_global', '1200000')");
}

// 2. PROSES UPDATE HARGA GLOBAL
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_global'])) {
    $harga_b = str_replace('.', '', $_POST['harga_bibit_global']);
    $harga_j = str_replace('.', '', $_POST['harga_jasa_tanam_global']);
    
    mysqli_query($conn, "UPDATE pengaturan_sistem SET nilai='$harga_b' WHERE kunci='harga_bibit_global'");
    mysqli_query($conn, "UPDATE pengaturan_sistem SET nilai='$harga_j' WHERE kunci='harga_jasa_tanam_global'");
    echo "<script>alert('Harga global berhasil diperbarui!'); window.location.href='?page=super-admin';</script>"; exit;
}

// 3. PROSES UPDATE HARGA SPESIFIK VARIETAS
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_varietas'])) {
    $id_var = (int)$_POST['id_varietas'];
    $harga_spec = $_POST['harga_jual'] != '' ? str_replace('.', '', $_POST['harga_jual']) : "NULL";
    
    if($harga_spec === "NULL") {
        mysqli_query($conn, "UPDATE varietas_bibit SET harga_jual=NULL WHERE id='$id_var'");
    } else {
        mysqli_query($conn, "UPDATE varietas_bibit SET harga_jual='$harga_spec' WHERE id='$id_var'");
    }
    echo "<script>alert('Harga varietas berhasil diperbarui!'); window.location.href='?page=super-admin';</script>"; exit;
}

// 4. PROSES TAMBAH BARIS LAHAN BARU (AUTOMATIC SEGMENT 12M)
if(isset($_POST['tambah_baris'])) {
    $q_max = mysqli_query($conn, "SELECT MAX(id_baris) as max_baris FROM bibit_baris");
    $next_baris = (int)mysqli_fetch_assoc($q_max)['max_baris'] + 1;
    
    // Insert baris baru sebagai lahan kosong berukuran 12 meter
    mysqli_query($conn, "INSERT INTO bibit_baris (id_baris, status, tersedia_m) VALUES ('$next_baris', 'kosong', 12.0)");
    echo "<script>alert('Berhasil menambah Baris Lahan #$next_baris (12 Meter)!'); window.location.href='?page=super-admin';</script>"; exit;
}

// 4.1. PROSES HAPUS BARIS LAHAN TERAKHIR
if(isset($_POST['hapus_baris'])) {
    $q_max = mysqli_query($conn, "SELECT MAX(id_baris) as max_baris FROM bibit_baris");
    $last_baris = (int)mysqli_fetch_assoc($q_max)['max_baris'];
    
    if($last_baris > 0) {
        $q_cek = mysqli_query($conn, "SELECT status FROM bibit_baris WHERE id_baris='$last_baris'");
        $r_cek = mysqli_fetch_assoc($q_cek);
        if($r_cek && $r_cek['status'] == 'kosong') {
            mysqli_query($conn, "DELETE FROM bibit_baris WHERE id_baris='$last_baris'");
            echo "<script>alert('Berhasil menghapus Baris Lahan #$last_baris!'); window.location.href='?page=super-admin';</script>"; exit;
        } else {
            echo "<script>alert('Gagal! Baris Lahan #$last_baris tidak bisa dihapus karena statusnya tidak kosong (sedang digunakan).'); window.location.href='?page=super-admin';</script>"; exit;
        }
    } else {
        echo "<script>alert('Tidak ada baris lahan yang bisa dihapus!'); window.location.href='?page=super-admin';</script>"; exit;
    }
}

// AMBIL DATA NILAI TERKINI
$cfg_bibit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM pengaturan_sistem WHERE kunci='harga_bibit_global'"))['nilai'] ?? 800000;
$cfg_jasa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nilai FROM pengaturan_sistem WHERE kunci='harga_jasa_tanam_global'"))['nilai'] ?? 1200000;

$q_total_baris = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id_baris) as total FROM bibit_baris"))['total'];
$q_var = mysqli_query($conn, "SELECT * FROM varietas_bibit ORDER BY nama_varietas ASC");
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl border border-gray-200 dark:border-[#30363d] shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-2"><i class="fa-solid fa-map-location-dot text-blue-500 mr-2"></i> Kapasitas Lahan Baris</h3>
                <p class="text-[12px] text-gray-500 mb-4">Saat ini terdaftar total <strong class="text-gray-800 dark:text-white"><?= $q_total_baris ?> Baris</strong>. Setiap penambahan baris baru otomatis diset sebagai lahan kosong sepanjang 12 meter (8 segmen).</p>
            </div>
            <form method="POST" action="" class="flex gap-2">
                <button type="submit" name="tambah_baris" onclick="return confirm('Apakah Anda ingin mengekspansi area dengan menambah 1 baris baru?')" class="flex-1 bg-[#1f6feb] hover:bg-[#388bfd] text-white py-2.5 rounded-md text-[13px] font-bold transition-colors shadow-sm">+ Tambah Baris Lahan Baru</button>
                <button type="submit" name="hapus_baris" onclick="return confirm('PENTING!\n\nTindakan ini akan menghapus 1 baris lahan yang paling terakhir (Baris tertinggi).\nBaris hanya bisa dihapus jika statusnya KOSONG.\n\nLanjutkan penghapusan?')" class="bg-white dark:bg-[#0d1117] text-[#f85149] border border-[#f85149] hover:bg-[#f85149] hover:text-white px-4 py-2.5 rounded-md text-[13px] font-bold transition-colors shadow-sm"><i class="fa-solid fa-trash-can"></i></button>
            </form>
        </div>

        <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl border border-gray-200 dark:border-[#30363d] shadow-sm col-span-2">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4"><i class="fa-solid fa-money-bill-wave text-green-500 mr-2"></i> Konfigurasi Tarif Dasar Global</h3>
            <form method="POST" action="" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1.5">Harga Dasar Bibit per Baris (Global)</label>
                        <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[12px] font-bold">Rp</span><input type="text" name="harga_bibit_global" value="<?= number_format($cfg_bibit, 0, ',', '.') ?>" class="format-rupiah w-full pl-8 bg-gray-50 dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1.5">Harga Jasa Tanam per Baris (Global)</label>
                        <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[12px] font-bold">Rp</span><input type="text" name="harga_jasa_tanam_global" value="<?= number_format($cfg_jasa, 0, ',', '.') ?>" class="format-rupiah w-full pl-8 bg-gray-50 dark:bg-[#161b22] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-[#58a6ff]"></div>
                    </div>
                </div>
                <div class="flex justify-end"><button type="submit" name="update_global" class="bg-[#238636] hover:bg-[#2ea043] text-white px-5 py-2 rounded-md text-[13px] font-bold transition-colors shadow">Simpan Tarif Global</button></div>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl border border-gray-200 dark:border-[#30363d] shadow-sm">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-2"><i class="fa-solid fa-tags text-yellow-500 mr-2"></i> Kontrol Harga Spesifik Varietas (Pengecualian)</h3>
        <p class="text-[12px] text-gray-500 mb-4">Kosongkan harga jual varietas jika ingin otomatis mengikuti nominal **Tarif Global**.</p>
        
        <div class="overflow-x-auto border border-gray-200 dark:border-[#30363d] rounded-lg">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 dark:bg-[#161b22] border-b border-gray-200 dark:border-[#30363d] text-[11px] font-bold text-gray-500 uppercase">
                    <tr><th class="py-3 px-4">Nama Varietas</th><th class="py-3 px-4">Kode</th><th class="py-3 px-4">Status Pengaturan Tarif</th><th class="py-3 px-4 w-72 text-right">Aksi Penyesuaian</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-[13px]">
                    <?php while($v = mysqli_fetch_assoc($q_var)): ?>
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-[#161b22]/50">
                        <td class="py-3 px-4 font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($v['nama_varietas']) ?></td>
                        <td class="py-3 px-4 text-gray-500"><?= $v['kode_varietas'] ?></td>
                        <td class="py-3 px-4">
                            <?php if($v['harga_jual'] == NULL || $v['harga_jual'] == 0): ?>
                                <span class="text-gray-400 italic">Mengikuti Harga Global (Rp <?= number_format($cfg_bibit, 0, ',', '.') ?>)</span>
                            <?php else: ?>
                                <span class="text-[#3fb950] font-bold">Harga Khusus: Rp <?= number_format($v['harga_jual'], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-4">
                            <form method="POST" action="" class="flex gap-2 justify-end items-center">
                                <input type="hidden" name="id_varietas" value="<?= $v['id'] ?>">
                                <div class="relative w-40">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[11px]">Rp</span>
                                    <input type="text" name="harga_jual" placeholder="Ikut Global" value="<?= $v['harga_jual'] ? number_format($v['harga_jual'], 0, ',', '.') : '' ?>" class="format-rupiah w-full pl-7 text-right bg-white dark:bg-[#0d1117] border border-gray-300 dark:border-[#30363d] text-gray-900 dark:text-white rounded px-2 py-1 text-[12px] focus:outline-none">
                                </div>
                                <button type="submit" name="update_varietas" class="bg-gray-100 hover:bg-gray-200 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-700 dark:text-white border dark:border-transparent px-3 py-1 rounded text-[11px] font-bold transition-colors">Set</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Format input rupiah *real-time*
    document.querySelectorAll('.format-rupiah').forEach(inp => {
        inp.addEventListener('input', function() {
            let val = this.value.replace(/[^0-9]/g, '');
            this.value = val !== '' ? new Intl.NumberFormat('id-ID').format(parseInt(val)) : '';
        });
    });
</script>