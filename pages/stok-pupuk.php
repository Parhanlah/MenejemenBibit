<?php
include 'components/koneksi.php';

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}

// =========================================================================
// ENGINE MUTASI DATA PUPUK & OBAT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pupuk'])) {
    $id = (int)$_POST['id_barang'];
    $kode = strtoupper(mysqli_real_escape_string($conn, $_POST['kode_barang']));
    $nama = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $stok = (int)$_POST['stok'];
    $satuan = mysqli_real_escape_string($conn, $_POST['satuan']);
    $harga = (float)$_POST['harga'];

    // Catatan: Pastikan Anda sudah membuat tabel 'stok_pupuk' di database PhpMyAdmin
    if($id > 0) {
        mysqli_query($conn, "UPDATE stok_pupuk SET kode='$kode', nama='$nama', kategori='$kategori', stok='$stok', satuan='$satuan', harga='$harga' WHERE id='$id'");
    } else {
        mysqli_query($conn, "INSERT INTO stok_pupuk (kode, nama, kategori, stok, satuan, harga) VALUES ('$kode', '$nama', '$kategori', '$stok', '$satuan', '$harga')");
    }
    echo "<script>window.location.href='?page=stok-pupuk';</script>"; exit;
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM stok_pupuk WHERE id='$id'");
    echo "<script>window.location.href='?page=stok-pupuk';</script>"; exit;
}

$query = mysqli_query($conn, "SELECT * FROM stok_pupuk ORDER BY id DESC");
$data_pupuk = [];
if($query) {
    while($r = mysqli_fetch_assoc($query)) {
        $data_pupuk[] = $r;
    }
}

// DUMMY DATA UNTUK PREVIEW UI

?>

<div class="space-y-6 animate-in fade-in zoom-in duration-300">
    <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] flex justify-between items-center">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-flask text-[#d29922]"></i> Manajemen Stok Pupuk & Obat
            </h2>
            <p class="text-sm text-gray-500 dark:text-[#8b949e] mt-1">Kelola ketersediaan barang non-benih di gudang</p>
        </div>
        <button onclick="bukaModalPupuk(0)" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center shadow-sm transition-colors">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Barang
        </button>
    </div>

    <div class="bg-white dark:bg-[#0d1117] rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="bg-gray-50 dark:bg-[#161b22] border-b border-gray-200 dark:border-[#30363d] text-[11px] font-bold text-gray-500 dark:text-[#8b949e] uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-5">Kode Barang</th>
                        <th class="py-3 px-5">Nama & Kategori</th>
                        <th class="py-3 px-5 text-center">Sisa Stok</th>
                        <th class="py-3 px-5 text-right">Harga Jual</th>
                        <th class="py-3 px-5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-sm text-gray-700 dark:text-gray-300">
                    <?php foreach($data_pupuk as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]/50 transition-colors">
                        <td class="py-3 px-5 font-mono font-bold text-gray-900 dark:text-[#58a6ff]"><?= $row['kode'] ?></td>
                        <td class="py-3 px-5">
                            <div class="font-bold text-gray-900 dark:text-white"><?= $row['nama'] ?></div>
                            <div class="text-[11px] text-gray-500"><?= $row['kategori'] ?></div>
                        </td>
                        <td class="py-3 px-5 text-center">
                            <?php $color = $row['stok'] < 10 ? 'text-red-500 bg-red-100 dark:bg-red-500/10 border-red-500/20' : 'text-emerald-600 bg-emerald-100 dark:bg-[#238636]/10 border-emerald-500/20 dark:text-[#3fb950]'; ?>
                            <span class="inline-block px-2 py-1 rounded border text-xs font-bold <?= $color ?>">
                                <?= $row['stok'] ?> <?= $row['satuan'] ?>
                            </span>
                        </td>
                        <td class="py-3 px-5 text-right font-bold text-gray-900 dark:text-white"><?= formatRp($row['harga']) ?></td>
                        <td class="py-3 px-5 text-center">
                            <?php $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                            <button onclick='bukaModalPupuk(<?= $json_data ?>)' class="text-blue-500 hover:text-blue-400 mx-2 transition-colors"><i class="fa-solid fa-pen-to-square"></i></button>
                            <a href="?page=stok-pupuk&hapus=<?= $row['id'] ?>" onclick="return confirm('Yakin menghapus barang ini?')" class="text-red-500 hover:text-red-400 mx-2 transition-colors"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-pupuk" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
    <div class="bg-[#0d1117] border border-[#30363d] rounded-xl shadow-2xl w-full max-w-xl mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-[#30363d] flex justify-between items-center bg-[#161b22]">
            <h3 class="text-white font-bold flex items-center gap-2" id="modal-title">
                <i class="fa-solid fa-box text-[#d29922]"></i> Tambah Data Barang
            </h3>
            <button onclick="tutupModalPupuk()" class="text-gray-400 hover:text-white transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="id_barang" id="id_barang">
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Kode SKU *</label>
                        <input type="text" name="kode_barang" id="kode" placeholder="Misal: PPK-01" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none font-mono" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Kategori *</label>
                        <select name="kategori" id="kategori" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white outline-none" required>
                            <option value="Pupuk Padat">Pupuk Padat</option>
                            <option value="Pupuk Cair">Pupuk Cair</option>
                            <option value="Pestisida/Obat">Pestisida / Obat</option>
                            <option value="Alat Pertanian">Alat Pertanian</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Nama Barang Lengkap *</label>
                    <input type="text" name="nama_barang" id="nama" placeholder="Contoh: Pupuk Urea Nitrea 50kg" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-1">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Jml Stok</label>
                        <input type="number" name="stok" id="stok" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Satuan</label>
                        <select name="satuan" id="satuan" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white outline-none" required>
                            <option value="Karung">Karung</option>
                            <option value="Kg">Kg</option>
                            <option value="Liter">Liter</option>
                            <option value="Botol">Botol</option>
                            <option value="Pack">Pack</option>
                        </select>
                    </div>
                    <div class="col-span-1">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Harga Jual</label>
                        <input type="number" name="harga" id="harga" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-[#161b22] border-t border-[#30363d] flex gap-3">
                <button type="submit" name="simpan_pupuk" class="flex-1 bg-[#238636] hover:bg-[#2ea043] text-white py-2.5 rounded font-bold text-sm transition-all shadow">Simpan Data</button>
                <button type="button" onclick="tutupModalPupuk()" class="flex-1 bg-[#21262d] hover:bg-[#30363d] text-white py-2.5 rounded font-bold text-sm transition-all border border-[#30363d]">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalPupuk(data) {
    document.getElementById('modal-pupuk').classList.remove('hidden');
    if(data === 0) {
        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-box text-[#d29922]"></i> Tambah Data Barang';
        document.getElementById('id_barang').value = 0;
        document.getElementById('kode').value = '';
        document.getElementById('nama').value = '';
        document.getElementById('stok').value = '0';
        document.getElementById('harga').value = '0';
    } else {
        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-[#d29922]"></i> Edit Data Barang';
        document.getElementById('id_barang').value = data.id;
        document.getElementById('kode').value = data.kode;
        document.getElementById('nama').value = data.nama;
        document.getElementById('kategori').value = data.kategori;
        document.getElementById('stok').value = data.stok;
        document.getElementById('satuan').value = data.satuan;
        document.getElementById('harga').value = data.harga;
    }
}

function tutupModalPupuk() { 
    document.getElementById('modal-pupuk').classList.add('hidden'); 
}
</script>