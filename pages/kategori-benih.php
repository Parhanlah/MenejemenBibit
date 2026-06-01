<?php
include 'components/koneksi.php'; // Panggil koneksi database

// ==========================================
// LOGIKA CRUD (CREATE, UPDATE, DELETE)
// ==========================================

// 1. PROSES SIMPAN / EDIT DATA (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $id = $_POST['id_kategori'];
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $slug = mysqli_real_escape_string($conn, $_POST['slug']);
    $rgb = mysqli_real_escape_string($conn, $_POST['rgb']);
    $icon = 'text-green-500'; // Default icon sementara

    if (empty($id)) {
        // Jika ID kosong, berarti ini data BARU (INSERT)
        $query = "INSERT INTO kategori_bibit (nama, deskripsi, slug, rgb, icon) VALUES ('$nama', '$deskripsi', '$slug', '$rgb', '$icon')";
    } else {
        // Jika ID ada, berarti EDIT data (UPDATE)
        $query = "UPDATE kategori_bibit SET nama='$nama', deskripsi='$deskripsi', slug='$slug', rgb='$rgb' WHERE id='$id'";
    }
    
    mysqli_query($conn, $query);
    echo "<script>window.location.href='?page=kategori-benih';</script>"; // Refresh halaman
}

// 2. PROSES HAPUS DATA (GET)
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM kategori_bibit WHERE id='$id_hapus'");
    echo "<script>window.location.href='?page=kategori-benih';</script>";
}

// 3. AMBIL DATA DARI DATABASE (READ)
$result = mysqli_query($conn, "SELECT * FROM kategori_bibit ORDER BY id DESC");
?>

<!-- Wrapper Responsif Halaman -->
<div class="bg-white dark:bg-[#0f172a] min-h-full rounded-xl p-6 shadow border border-gray-100 dark:border-gray-800 transition-colors duration-200 relative">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div class="flex items-center gap-4">
            <a href="?page=stock-bibit" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors">
                <i class="fa-solid fa-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-xl md:text-2xl font-bold flex items-center text-gray-800 dark:text-white">
                    <i class="fa-solid fa-cube text-yellow-600 mr-3"></i> Manajemen Kategori Bibit Padi
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola kategori bibit padi untuk stock gudang</p>
            </div>
        </div>
        
        <button onclick="tambahData()" class="bg-[#00c853] hover:bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors flex items-center shadow">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Kategori
        </button>
    </div>

    <!-- Tabel Data Kategori -->
    <div class="bg-white dark:bg-[#1e293b] rounded-lg border border-gray-200 dark:border-gray-700 overflow-x-auto shadow-sm">
        <table class="w-full text-left border-collapse min-w-max">
            <thead class="border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-[#1e293b]">
                <tr>
                    <th class="px-6 py-4">Nama Kategori</th>
                    <th class="px-6 py-4">Deskripsi</th>
                    <th class="px-6 py-4">Page Slug</th>
                    <th class="px-6 py-4">Warna</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                
                <?php if(mysqli_num_rows($result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#2a374a] transition-colors">
                        <td class="px-6 py-4 font-medium flex items-center gap-3">
                            <i class="fa-solid fa-cube <?= $row['icon'] ?>"></i>
                            <span class="text-gray-800 dark:text-white"><?= $row['nama'] ?></span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400"><?= $row['deskripsi'] ?></td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400"><?= $row['slug'] ?></td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-sm border border-gray-300 dark:border-gray-600" style="background-color: rgb(<?= $row['rgb'] ?>);"></div>
                                <span class="text-xs"><?= $row['rgb'] ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <!-- Tombol Edit melempar data ke Javascript -->
                            <button onclick="editData('<?= $row['id'] ?>', '<?= $row['nama'] ?>', '<?= $row['deskripsi'] ?>', '<?= $row['slug'] ?>', '<?= $row['rgb'] ?>')" class="text-blue-500 hover:text-blue-600 dark:hover:text-blue-400 mx-2 transition-colors"><i class="fa-regular fa-pen-to-square"></i></button>
                            <!-- Tombol Hapus -->
                            <a href="?page=kategori-benih&hapus=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus kategori ini?')" class="text-red-500 hover:text-red-600 dark:hover:text-red-400 mx-2 transition-colors"><i class="fa-regular fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-6 text-gray-500 dark:text-gray-400">Belum ada data kategori. Silakan tambah baru.</td>
                    </tr>
                <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL FORM (Dipakai bersama untuk Tambah & Edit) -->
<!-- ========================================================================= -->
<div id="modal-form" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 hidden backdrop-blur-sm transition-opacity">
    <div class="bg-white dark:bg-[#1e293b] rounded-lg shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-gray-200 dark:border-gray-700 flex flex-col max-h-[90vh]">
        
        <!-- Formulir mengarah ke halaman ini sendiri -->
        <form method="POST" action="?page=kategori-benih">
            <!-- Input tersembunyi untuk menyimpan ID saat proses EDIT -->
            <input type="hidden" name="id_kategori" id="input-id">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <h3 id="modal-title" class="text-lg font-bold text-gray-900 dark:text-white">Tambah Kategori Bibit</h3>
            </div>

            <div class="p-6 space-y-5 overflow-y-auto">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" id="input-nama" required placeholder="Contoh: Bibit Padi" class="w-full bg-transparent border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Deskripsi</label>
                    <textarea name="deskripsi" id="input-desc" rows="3" placeholder="Deskripsi kategori (opsional)" class="w-full bg-transparent border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Page Slug <span class="text-red-500">*</span></label>
                    <input type="text" name="slug" id="input-slug" required placeholder="bibit-padi" class="w-full bg-transparent border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Warna (RGB) <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-3">
                        <input type="text" name="rgb" id="input-rgb" required placeholder="34,197,94" class="flex-1 bg-transparent border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-green-500">
                        <div class="w-10 h-10 rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden relative shrink-0 cursor-pointer">
                            <input type="color" id="input-color" class="absolute -top-2 -left-2 w-16 h-16 cursor-pointer">
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-[#0f172a] border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="tutupModal()" class="text-gray-700 dark:text-gray-300 bg-transparent border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 px-4 py-2 rounded-md text-sm font-medium">Batal</button>
                <button type="submit" name="simpan" class="bg-[#00c853] hover:bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPT PENGENDALI MODAL -->
<script>
    const modal = document.getElementById('modal-form');
    
    // Fungsi Buka Modal untuk TAMBAH Data Baru
    function tambahData() {
        document.getElementById('modal-title').innerText = "Tambah Kategori Bibit";
        document.getElementById('input-id').value = ""; // Kosongkan ID
        document.getElementById('input-nama').value = "";
        document.getElementById('input-desc').value = "";
        document.getElementById('input-slug').value = "";
        document.getElementById('input-rgb').value = "34,197,94"; // Default hijau
        document.getElementById('input-color').value = "#22c55e"; 
        modal.classList.remove('hidden');
    }

    // Fungsi Buka Modal untuk EDIT Data yang dipilih
    function editData(id, nama, desc, slug, rgb) {
        document.getElementById('modal-title').innerText = "Edit Kategori Bibit";
        document.getElementById('input-id').value = id; // Isi ID
        document.getElementById('input-nama').value = nama;
        document.getElementById('input-desc').value = desc;
        document.getElementById('input-slug').value = slug;
        document.getElementById('input-rgb').value = rgb;
        
        // Coba konversi RGB ke HEX untuk color picker saat edit
        let hexStr = rgbToHex(rgb);
        if(hexStr) document.getElementById('input-color').value = hexStr;

        modal.classList.remove('hidden');
    }

    function tutupModal() {
        modal.classList.add('hidden');
    }

    // Script auto-slug dan konversi warna (Sama seperti sebelumnya)
    document.getElementById('input-nama').addEventListener('input', function(e) {
        let slugText = e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
        document.getElementById('input-slug').value = slugText;
    });

    function hexToRgb(hex) {
        let r = 0, g = 0, b = 0;
        if (hex.length == 7) {
            r = parseInt(hex.substring(1,3), 16); g = parseInt(hex.substring(3,5), 16); b = parseInt(hex.substring(5,7), 16);
        }
        return `${r},${g},${b}`;
    }

    function rgbToHex(rgbStr) {
        let rgb = rgbStr.split(',').map(item => item.trim());
        if(rgb.length === 3) {
            let r = parseInt(rgb[0]).toString(16).padStart(2, '0');
            let g = parseInt(rgb[1]).toString(16).padStart(2, '0');
            let b = parseInt(rgb[2]).toString(16).padStart(2, '0');
            if (r !== 'NaN' && g !== 'NaN' && b !== 'NaN') return `#${r}${g}${b}`;
        }
        return null;
    }

    document.getElementById('input-color').addEventListener('input', function(e) {
        document.getElementById('input-rgb').value = hexToRgb(e.target.value);
    });
    document.getElementById('input-rgb').addEventListener('input', function(e) {
        let hexStr = rgbToHex(e.target.value);
        if (hexStr) document.getElementById('input-color').value = hexStr;
    });
</script>