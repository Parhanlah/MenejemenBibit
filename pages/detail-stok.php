<?php
include __DIR__ . '/../components/koneksi.php';
$slug = isset($_GET['slug']) ? mysqli_real_escape_string($conn, $_GET['slug']) : '';

// =========================================================================
// 1. AJAX: SIMPAN SUPPLIER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_add_supplier'])) {
    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }
    $nama = mysqli_real_escape_string($conn, $_POST['nama_supplier']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon_supplier']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat_supplier']);
    
    $query = "INSERT INTO supplier (nama, telepon, alamat) VALUES ('$nama', '$telepon', '$alamat')";
    if (mysqli_query($conn, $query)) { echo json_encode(['status' => 'success', 'nama' => $nama]); } 
    else { echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]); }
    exit;
}

// =========================================================================
// 2. PROSES TAMBAH & EDIT VARIETAS BENIH
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_varietas'])) {
    $id_varietas = isset($_POST['id_varietas']) ? $_POST['id_varietas'] : '';
    $id_kat = $_POST['id_kategori'];
    $nama_bibit = mysqli_real_escape_string($conn, $_POST['nama_bibit']);
    $stok = (int)$_POST['stok'];
    $harga_beli = (int)$_POST['harga_beli'];
    $harga_jual = (int)$_POST['harga_jual'];
    $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
    $varietas = mysqli_real_escape_string($conn, $_POST['varietas']);
    
    $nama_file_foto = "";
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nama_file_foto = time() . "_" . uniqid() . "." . $ext; 
        move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $nama_file_foto);
    }

    if (empty($id_varietas)) {
        $query_save = "INSERT INTO varietas_bibit (id_kategori, nama_varietas, kode_varietas, stok_kg, harga_beli, harga_jual, supplier, foto) 
                       VALUES ('$id_kat', '$nama_bibit', '$varietas', '$stok', '$harga_beli', '$harga_jual', '$supplier', '$nama_file_foto')";
    } else {
        if (!empty($nama_file_foto)) {
            $query_save = "UPDATE varietas_bibit SET nama_varietas='$nama_bibit', kode_varietas='$varietas', stok_kg='$stok', harga_beli='$harga_beli', harga_jual='$harga_jual', supplier='$supplier', foto='$nama_file_foto' WHERE id='$id_varietas'";
        } else {
            $query_save = "UPDATE varietas_bibit SET nama_varietas='$nama_bibit', kode_varietas='$varietas', stok_kg='$stok', harga_beli='$harga_beli', harga_jual='$harga_jual', supplier='$supplier' WHERE id='$id_varietas'";
        }
    }
    mysqli_query($conn, $query_save);
    echo "<script>window.location.href='?page=detail-stok&slug=$slug';</script>";
}

// =========================================================================
// 3. PROSES RETUR BENIH
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_retur'])) {
    $id_retur = (int)$_POST['id_varietas_retur'];
    $jumlah_retur = (int)$_POST['jumlah_retur'];
    mysqli_query($conn, "UPDATE varietas_bibit SET stok_kg = stok_kg - $jumlah_retur WHERE id='$id_retur'");
    echo "<script>window.location.href='?page=detail-stok&slug=$slug';</script>";
}

// =========================================================================
// 4. PROSES HAPUS VARIETAS BENIH
// =========================================================================
if (isset($_GET['hapus_varietas'])) {
    $id_hapus = (int)$_GET['hapus_varietas'];
    $q_foto = mysqli_query($conn, "SELECT foto FROM varietas_bibit WHERE id='$id_hapus'");
    if($r_foto = mysqli_fetch_assoc($q_foto)) {
        if(!empty($r_foto['foto']) && file_exists('uploads/'.$r_foto['foto'])) unlink('uploads/'.$r_foto['foto']);
    }
    mysqli_query($conn, "DELETE FROM varietas_bibit WHERE id='$id_hapus'");
    echo "<script>window.location.href='?page=detail-stok&slug=$slug';</script>";
}

// =========================================================================
// AMBIL DATA UTAMA (KATEGORI & VARIETAS)
// =========================================================================
$query_kategori = mysqli_query($conn, "SELECT * FROM kategori_bibit WHERE slug='$slug'");
if(mysqli_num_rows($query_kategori) == 0) { echo "<div class='p-6 text-center text-red-500 font-bold'>Kategori tidak ditemukan!</div>"; exit; }
$kategori = mysqli_fetch_assoc($query_kategori);
$id_kategori = $kategori['id'];
$nama_kategori = $kategori['nama'];

$query_varietas = mysqli_query($conn, "SELECT * FROM varietas_bibit WHERE id_kategori='$id_kategori' ORDER BY id DESC");
$total_varietas = mysqli_num_rows($query_varietas);
$total_stok = 0; $nilai_persediaan = 0; $perlu_restock = 0; $data_varietas = [];

while($row = mysqli_fetch_assoc($query_varietas)) {
    $total_stok += $row['stok_kg'];
    $nilai_persediaan += ($row['stok_kg'] * $row['harga_beli']);
    if($row['stok_kg'] <= 50) { $perlu_restock += 1; }
    
    if($row['stok_kg'] == 0) { $badge = '<span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Habis</span>'; } 
    elseif($row['stok_kg'] <= 50) { $badge = '<span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Menipis</span>'; } 
    elseif($row['stok_kg'] > 300) { $badge = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-[#d4edda] text-[#155724]">Banyak</span>'; } 
    else { $badge = '<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-[#e2e8f0] text-[#1e293b] dark:bg-[#cce5ff] dark:text-[#004085]">Normal</span>'; }
    $row['badge'] = $badge; $data_varietas[] = $row;
}

$query_supplier = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nama ASC");
$data_supplier = [];
while($s = mysqli_fetch_assoc($query_supplier)) { $data_supplier[] = $s; }

function formatRupiah($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div class="flex items-center gap-3">
            <a href="?page=stock-benih" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors"><i class="fa-solid fa-arrow-left text-sm md:text-base"></i></a>
            <div>
                <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]"><i class="fa-solid fa-cube text-[#d2a878] mr-3"></i> Stok Gudang - <?= ucwords(str_ireplace('bibit', 'benih padi', htmlspecialchars($nama_kategori))) ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-2 w-full md:w-auto">
            <button onclick="bukaModalStock()" class="flex-1 md:flex-none bg-[#238636] hover:bg-[#2ea043] text-white px-4 py-2 rounded-md text-[13px] font-medium transition-colors flex items-center justify-center shadow"><i class="fa-solid fa-plus mr-2"></i> Tambah Stok Benih</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-50 dark:bg-[#161b22] p-4 rounded-lg border border-gray-200 dark:border-[#30363d] flex justify-between items-center"><div><p class="text-[12px] text-gray-500 dark:text-[#8b949e] mb-1">Total Varietas</p><h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= $total_varietas ?></h3></div><i class="fa-solid fa-cube text-2xl text-blue-500"></i></div>
        <div class="bg-gray-50 dark:bg-[#161b22] p-4 rounded-lg border border-gray-200 dark:border-[#30363d] flex justify-between items-center"><div><p class="text-[12px] text-gray-500 dark:text-[#8b949e] mb-1">Total Stok (kg)</p><h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= number_format($total_stok, 0, ',', '.') ?></h3></div><i class="fa-solid fa-leaf text-2xl text-green-500"></i></div>
        <div class="bg-gray-50 dark:bg-[#161b22] p-4 rounded-lg border border-gray-200 dark:border-[#30363d] flex justify-between items-center"><div><p class="text-[12px] text-gray-500 dark:text-[#8b949e] mb-1">Nilai Persediaan</p><h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= formatRupiah($nilai_persediaan) ?></h3></div><i class="fa-solid fa-box-open text-2xl text-purple-500"></i></div>
        <div class="bg-gray-50 dark:bg-[#161b22] p-4 rounded-lg border border-gray-200 dark:border-[#30363d] flex justify-between items-center"><div><p class="text-[12px] text-gray-500 dark:text-[#8b949e] mb-1">Perlu Re-stok</p><h3 class="text-xl font-bold text-gray-900 dark:text-white"><?= $perlu_restock ?></h3></div><i class="fa-solid fa-boxes-stacked text-2xl text-orange-500"></i></div>
    </div>

    <div class="bg-white dark:bg-[#0d1117] rounded-lg border border-gray-200 dark:border-[#30363d] overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-max">
            <thead class="border-b border-gray-200 dark:border-[#30363d] text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider bg-gray-50 dark:bg-[#161b22]">
                <tr>
                    <th class="px-5 py-3">Nama Varietas Benih</th>
                    <th class="px-5 py-3 text-center">Stok (kg)</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3">Harga Beli</th>
                    <th class="px-5 py-3">Harga Jual</th>
                    <th class="px-5 py-3">Supplier</th>
                    <th class="px-5 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-[#30363d] text-[13px]">
                <?php if(count($data_varietas) > 0): ?>
                    <?php foreach($data_varietas as $v): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22] transition-colors">
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-gray-900 dark:text-[#c9d1d9]"><?= htmlspecialchars($v['nama_varietas']) ?></p>
                            <p class="text-[12px] text-gray-500 dark:text-[#8b949e] mt-0.5"><?= htmlspecialchars($v['kode_varietas']) ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-center font-bold text-gray-900 dark:text-white"><?= number_format($v['stok_kg'], 0, ',', '.') ?></td>
                        <td class="px-5 py-3.5 text-center"><?= $v['badge'] ?></td>
                        <td class="px-5 py-3.5 text-gray-600 dark:text-[#c9d1d9]"><?= formatRupiah($v['harga_beli']) ?></td>
                        <td class="px-5 py-3.5 text-gray-600 dark:text-[#c9d1d9]"><?= formatRupiah($v['harga_jual']) ?></td>
                        <td class="px-5 py-3.5 text-gray-600 dark:text-[#8b949e]"><?= htmlspecialchars($v['supplier']) ?></td>
                        <td class="px-5 py-3.5 text-center font-semibold">
                            <button onclick="editVarietas(<?= $v['id'] ?>, '<?= htmlspecialchars($v['nama_varietas']) ?>', <?= $v['stok_kg'] ?>, '<?= htmlspecialchars($v['kode_varietas']) ?>', <?= $v['harga_beli'] ?>, <?= $v['harga_jual'] ?>, '<?= htmlspecialchars($v['supplier']) ?>')" class="text-green-600 dark:text-[#3fb950] hover:underline mx-1">Edit</button>
                            <button onclick="bukaModalRetur(<?= $v['id'] ?>, '<?= htmlspecialchars($v['nama_varietas']) ?>', <?= $v['stok_kg'] ?>)" class="text-orange-600 dark:text-[#d29922] hover:underline mx-1">Retur</button>
                            <button onclick="hapusVarietas(<?= $v['id'] ?>, '<?= htmlspecialchars($v['nama_varietas']) ?>')" class="text-red-600 dark:text-[#f85149] hover:underline mx-1">Hapus</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center py-8 text-gray-500 dark:text-[#8b949e]">Belum ada data varietas. Silakan tambah stok benih.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modal-stock" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
    <div class="bg-[#1a202c] rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden border border-[#2d3748] flex flex-col max-h-[95vh] text-gray-300">
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="id_kategori" value="<?= $id_kategori ?>">
            <input type="hidden" name="id_varietas" id="input-id-varietas">
            
            <div class="px-6 py-4 border-b border-[#2d3748] flex justify-between items-center shrink-0">
                <h3 id="modal-stock-title" class="text-sm font-bold text-white tracking-wide">Tambah Benih Padi</h3>
                <button type="button" onclick="tutupModalStock()" class="text-gray-500 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar">
                <div>
                    <label class="block text-[13px] font-medium text-gray-400 mb-1.5">Nama Benih</label>
                    <input type="text" name="nama_bibit" id="input-nama-bibit" required class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 transition-colors">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-gray-400 mb-1.5">Stok (Kg)</label>
                        <input type="number" name="stok" id="input-stok" value="0" required class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-400 mb-1.5">Varietas (Kode)</label>
                        <input type="text" name="varietas" id="input-varietas" placeholder="Contoh: IR64" class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-gray-400 mb-1.5">Harga Beli</label>
                        <input type="number" name="harga_beli" id="input-beli" value="0" class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-gray-400 mb-1.5">Harga Jual</label>
                        <input type="number" name="harga_jual" id="input-jual" value="0" class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-white mb-1.5">Supplier <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select name="supplier" id="select-supplier" required class="flex-1 bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-green-500 transition-colors appearance-none">
                            <option value="">Pilih Supplier</option>
                            <?php foreach($data_supplier as $sup): ?>
                                <option id="opt-sup-<?= $sup['id'] ?>" value="<?= htmlspecialchars($sup['nama']) ?>"><?= htmlspecialchars($sup['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="bukaFormSupplier()" class="w-10 h-10 bg-[#00c853] hover:bg-green-600 text-white rounded-md flex items-center justify-center shrink-0 transition-colors">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    
                    <div id="form-tambah-supplier" class="hidden mt-3 bg-[#1e2532] border border-[#2d3748] rounded-md p-4 shadow-inner">
                        <h4 id="title-form-sup" class="text-[13px] font-bold text-white mb-3">Tambah Supplier Baru</h4>
                        <input type="hidden" id="sup_id" value="">
                        <div class="space-y-3">
                            <input type="text" id="sup_nama" placeholder="Nama Supplier *" class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-green-500">
                            <input type="text" id="sup_telp" placeholder="Telepon (opsional)" class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-green-500">
                            <input type="text" id="sup_alamat" placeholder="Alamat (opsional)" class="w-full bg-[#151a26] border border-[#2d3748] text-white rounded-md px-3 py-2 text-sm focus:outline-none focus:border-green-500">
                            <div class="flex gap-2 mt-4">
                                <button type="button" id="btn-simpan-sup" onclick="simpanSupplierAjax()" class="flex-1 bg-[#00c853] hover:bg-green-600 text-white py-2 rounded-md text-sm font-medium transition-colors">Simpan</button>
                                <button type="button" onclick="tutupFormSupplier()" class="px-5 bg-transparent border border-[#2d3748] text-gray-400 hover:text-white rounded-md text-sm font-medium transition-colors">Batal</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 bg-[#1e2532] border border-[#2d3748] rounded-md p-3">
                        <p class="text-[12px] text-gray-400 mb-2">Daftar Supplier:</p>
                        <ul id="list-supplier" class="space-y-2 text-[13px] text-gray-300 max-h-32 overflow-y-auto custom-scrollbar pr-2">
                            <?php foreach($data_supplier as $sup): ?>
                            <li id="li-sup-<?= $sup['id'] ?>" class="flex justify-between items-center border-b border-[#2d3748] pb-2 last:border-0 last:pb-0 pt-1">
                                <span id="text-sup-<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nama']) ?></span>
                                <div>
                                    <i onclick="editSupplier(<?= $sup['id'] ?>, '<?= htmlspecialchars($sup['nama']) ?>', '<?= htmlspecialchars($sup['telepon']) ?>', '<?= htmlspecialchars($sup['alamat']) ?>')" class="fa-solid fa-pen-to-square text-blue-500 mx-1.5 cursor-pointer hover:text-blue-400"></i>
                                    <i onclick="hapusSupplier(<?= $sup['id'] ?>, '<?= htmlspecialchars($sup['nama']) ?>')" class="fa-solid fa-trash-can text-red-500 mx-1.5 cursor-pointer hover:text-red-400"></i>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-gray-400 mb-1.5">Foto (Kosongkan jika tidak diganti)</label>
                    <input type="file" name="foto" accept="image/*" class="w-full bg-[#151a26] border border-[#2d3748] text-gray-400 rounded-md px-3 py-1.5 text-sm file:bg-[#2d3748] file:text-white hover:file:bg-[#3a475e] transition-colors cursor-pointer file:border-0 file:py-1 file:px-3 file:rounded">
                </div>
            </div>

            <div class="px-6 py-4 border-t border-[#2d3748] flex justify-end gap-3 shrink-0">
                <button type="button" onclick="tutupModalStock()" class="text-gray-400 hover:text-white bg-transparent border border-[#2d3748] px-5 py-2 rounded-md text-sm font-medium">Batal</button>
                <button type="submit" name="simpan_varietas" id="btn-submit-stock" class="bg-[#5b52f6] hover:bg-indigo-500 text-white px-5 py-2 rounded-md text-sm font-medium">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-retur" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
    <div class="bg-[#1a1d24] rounded-lg shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-[#2d333b] flex flex-col text-gray-300">
        <form method="POST" action="">
            <input type="hidden" name="id_varietas_retur" id="retur_id">
            <div class="px-5 py-4 border-b border-[#2d333b] flex justify-between items-center">
                <h3 class="text-base font-bold text-white">Retur Benih</h3>
                <button type="button" onclick="tutupModalRetur()" class="text-gray-500 hover:text-gray-300 transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            <div class="p-5 space-y-5">
                <div class="bg-[#22272e] rounded-md p-4 border border-[#2d333b]">
                    <p class="text-[12px] text-gray-500 mb-1">Item</p>
                    <h4 id="retur_nama" class="text-base font-bold text-white mb-1">Nama Item</h4>
                    <p id="retur_stok_text" class="text-[13px] text-gray-400">Stok tersedia: 0 kg</p>
                </div>
                <div>
                    <label class="block text-[13px] font-bold text-white mb-2">Jumlah Retur (kg) <span class="text-red-500">*</span></label>
                    <input type="number" name="jumlah_retur" id="retur_jumlah" min="1" required value="0" class="w-full bg-[#161b22] border border-[#30363d] text-white rounded-md px-3 py-2.5 text-sm focus:outline-none focus:border-[#ea580c] transition-colors">
                </div>
                <div>
                    <label class="block text-[13px] font-bold text-white mb-2">Alasan Retur <span class="text-red-500">*</span></label>
                    <textarea name="alasan_retur" required rows="3" placeholder="Jelaskan alasan retur..." class="w-full bg-[#161b22] border border-[#30363d] text-white rounded-md px-3 py-2.5 text-sm focus:outline-none focus:border-[#ea580c] transition-colors resize-none"></textarea>
                </div>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <button type="button" onclick="tutupModalRetur()" class="flex-1 text-gray-400 hover:text-white bg-transparent border border-[#30363d] hover:bg-[#22272e] py-2.5 rounded-md text-sm font-medium transition-colors">Batal</button>
                <button type="submit" name="proses_retur" class="flex-1 bg-[#ea580c] hover:bg-[#f97316] text-white py-2.5 rounded-md text-sm font-medium transition-colors">Proses Retur</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modalStock = document.getElementById('modal-stock');
    
    function bukaModalStock() {
        document.getElementById('modal-stock-title').innerText = "Tambah Benih Padi";
        document.getElementById('btn-submit-stock').innerText = "Simpan Data";
        document.getElementById('input-id-varietas').value = "";
        document.getElementById('input-nama-bibit').value = "";
        document.getElementById('input-stok').value = "0";
        document.getElementById('input-varietas').value = "";
        document.getElementById('input-beli').value = "0";
        document.getElementById('input-jual').value = "0";
        document.getElementById('select-supplier').value = ""; // Dikosongkan
        modalStock.classList.remove('hidden');
    }

    function editVarietas(id, nama, stok, varietas, beli, jual, supplier) {
        document.getElementById('modal-stock-title').innerText = "Edit Benih: " + nama;
        document.getElementById('btn-submit-stock').innerText = "Update Data";
        document.getElementById('input-id-varietas').value = id;
        document.getElementById('input-nama-bibit').value = nama;
        document.getElementById('input-stok').value = stok;
        document.getElementById('input-varietas').value = varietas;
        document.getElementById('input-beli').value = beli;
        document.getElementById('input-jual').value = jual;
        
        // Pilih supplier yang sesuai di dropdown
        document.getElementById('select-supplier').value = supplier;
        
        modalStock.classList.remove('hidden');
    }

    function tutupModalStock() { modalStock.classList.add('hidden'); }

    function hapusVarietas(id, nama) {
        if(confirm("Apakah Anda yakin ingin menghapus benih '" + nama + "' secara permanen?")) {
            window.location.href = "?page=detail-stok&slug=<?= $slug ?>&hapus_varietas=" + id;
        }
    }

    // --- SCRIPT SUPPLIER (AJAX) ---
    const formSup = document.getElementById('form-tambah-supplier');
    function bukaFormSupplier() {
        document.getElementById('sup_id').value = '';
        document.getElementById('sup_nama').value = '';
        document.getElementById('title-form-sup').innerText = 'Tambah Supplier Baru';
        document.getElementById('btn-simpan-sup').innerText = 'Simpan Baru';
        formSup.classList.remove('hidden');
    }
    function editSupplier(id, nama, telp, alamat) {
        document.getElementById('sup_id').value = id;
        document.getElementById('sup_nama').value = nama;
        document.getElementById('sup_telp').value = telp;
        document.getElementById('sup_alamat').value = alamat;
        document.getElementById('title-form-sup').innerText = 'Edit Supplier: ' + nama;
        document.getElementById('btn-simpan-sup').innerText = 'Update Data';
        formSup.classList.remove('hidden');
    }
    function tutupFormSupplier() { formSup.classList.add('hidden'); }

    function simpanSupplierAjax() {
        const id = document.getElementById('sup_id').value;
        const nama = document.getElementById('sup_nama').value;
        const telp = document.getElementById('sup_telp').value;
        const alamat = document.getElementById('sup_alamat').value;
        if(!nama) { alert("Nama Supplier wajib diisi!"); return; }

        let formData = new FormData();
        formData.append('nama_supplier', nama); formData.append('telepon_supplier', telp); formData.append('alamat_supplier', alamat);
        if (id === '') { formData.append('ajax_add_supplier', '1'); } 
        else { formData.append('ajax_edit_supplier', '1'); formData.append('id_supplier', id); }

        fetch('proses_supplier.php', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
            if(data.status === 'success') {
                if (id === '') {
                    let select = document.getElementById('select-supplier');
                    let option = document.createElement("option");
                    option.id = "opt-sup-" + data.id; option.value = data.nama; option.text = data.nama;
                    select.add(option); select.value = data.nama;
                    let list = document.getElementById('list-supplier');
                    let li = document.createElement('li'); li.id = "li-sup-" + data.id;
                    li.className = "flex justify-between items-center border-b border-[#2d3748] pb-2 last:border-0 last:pb-0 pt-1 text-[13px] text-gray-300";
                    li.innerHTML = `<span id="text-sup-${data.id}">${data.nama}</span> <div><i onclick="editSupplier(${data.id}, '${data.nama}', '${data.telepon}', '${data.alamat}')" class="fa-solid fa-pen-to-square text-blue-500 mx-1.5 cursor-pointer hover:text-blue-400"></i><i onclick="hapusSupplier(${data.id}, '${data.nama}')" class="fa-solid fa-trash-can text-red-500 mx-1.5 cursor-pointer hover:text-red-400"></i></div>`;
                    list.insertBefore(li, list.firstChild);
                } else {
                    document.getElementById('text-sup-' + id).innerText = data.nama;
                    let opt = document.getElementById('opt-sup-' + id);
                    if(opt) { opt.value = data.nama; opt.text = data.nama; }
                }
                tutupFormSupplier();
            } else { alert("Gagal: " + data.message); }
        });
    }

    function hapusSupplier(id, nama) {
        if(confirm("Yakin ingin menghapus supplier: " + nama + "?")) {
            let formData = new FormData();
            formData.append('ajax_delete_supplier', '1'); formData.append('id_supplier', id); formData.append('nama_supplier', nama);
            fetch('proses_supplier.php', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
                if(data.status === 'success') {
                    const li = document.getElementById('li-sup-' + id); if(li) li.remove();
                    const opt = document.getElementById('opt-sup-' + id); if(opt) opt.remove();
                } else { alert("Gagal menghapus: " + data.message); }
            });
        }
    }

    // --- SCRIPT RETUR ---
    const modalRetur = document.getElementById('modal-retur');
    function bukaModalRetur(id, nama, stok_max) {
        document.getElementById('retur_id').value = id;
        document.getElementById('retur_nama').innerText = nama;
        document.getElementById('retur_stok_text').innerText = "Stok tersedia: " + stok_max + " kg";
        const inputJumlah = document.getElementById('retur_jumlah');
        inputJumlah.max = stok_max;
        inputJumlah.value = 0;
        modalRetur.classList.remove('hidden');
    }
    function tutupModalRetur() { modalRetur.classList.add('hidden'); }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
</style>