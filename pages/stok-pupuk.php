<?php
include 'components/koneksi.php';

if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'satuan';

// =========================================================================
// AUTO-CREATE TABEL PAKET BUNDLING
// =========================================================================
if(mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'paket_pupuk'")) == 0) {
    mysqli_query($conn, "CREATE TABLE `paket_pupuk` (
        `id` int(11) NOT NULL AUTO_INCREMENT, `kode` varchar(50) NOT NULL, `nama` varchar(100) NOT NULL, 
        `deskripsi` text DEFAULT NULL, `harga` int(11) NOT NULL, PRIMARY KEY (`id`)
    )");
}
if(mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'paket_pupuk_item'")) == 0) {
    mysqli_query($conn, "CREATE TABLE `paket_pupuk_item` (
        `id` int(11) NOT NULL AUTO_INCREMENT, `id_paket` int(11) NOT NULL, `id_barang` int(11) NOT NULL, 
        `qty` int(11) NOT NULL, PRIMARY KEY (`id`)
    )");
}

// =========================================================================
// ENGINE MUTASI DATA PUPUK & OBAT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_pupuk'])) {
    $id = (int)$_POST['id_barang'];
    $kode = isset($_POST['kode_barang']) ? strtoupper(mysqli_real_escape_string($conn, $_POST['kode_barang'])) : '-';
    $nama = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $kategori = isset($_POST['kategori']) ? mysqli_real_escape_string($conn, $_POST['kategori']) : '-';
    $stok = (int)$_POST['stok'];
    $satuan = isset($_POST['satuan']) ? mysqli_real_escape_string($conn, $_POST['satuan']) : '-';
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
    echo "<script>window.location.href='?page=stok-pupuk&tab=satuan';</script>"; exit;
}

// =========================================================================
// ENGINE PAKET BUNDLING
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_paket'])) {
    $id = (int)$_POST['id_paket'];
    $kode = isset($_POST['kode_paket']) ? strtoupper(mysqli_real_escape_string($conn, $_POST['kode_paket'])) : '-';
    $nama = mysqli_real_escape_string($conn, $_POST['nama_paket']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi_paket']);
    $harga = (int)$_POST['harga_paket'];
    
    $id_barangs = $_POST['item_id_barang'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];

    if($id > 0) {
        mysqli_query($conn, "UPDATE paket_pupuk SET kode='$kode', nama='$nama', deskripsi='$deskripsi', harga='$harga' WHERE id='$id'");
        mysqli_query($conn, "DELETE FROM paket_pupuk_item WHERE id_paket='$id'");
        $id_paket = $id;
    } else {
        mysqli_query($conn, "INSERT INTO paket_pupuk (kode, nama, deskripsi, harga) VALUES ('$kode', '$nama', '$deskripsi', '$harga')");
        $id_paket = mysqli_insert_id($conn);
    }
    
    // Insert Items
    for($i = 0; $i < count($id_barangs); $i++) {
        $id_b = (int)$id_barangs[$i];
        $qty_b = (int)$qtys[$i];
        if($id_b > 0 && $qty_b > 0) {
            mysqli_query($conn, "INSERT INTO paket_pupuk_item (id_paket, id_barang, qty) VALUES ('$id_paket', '$id_b', '$qty_b')");
        }
    }
    echo "<script>window.location.href='?page=stok-pupuk&tab=paket';</script>"; exit;
}

if (isset($_GET['hapus_paket'])) {
    $id = (int)$_GET['hapus_paket'];
    mysqli_query($conn, "DELETE FROM paket_pupuk WHERE id='$id'");
    mysqli_query($conn, "DELETE FROM paket_pupuk_item WHERE id_paket='$id'");
    echo "<script>window.location.href='?page=stok-pupuk&tab=paket';</script>"; exit;
}

$query = mysqli_query($conn, "SELECT * FROM stok_pupuk ORDER BY id DESC");
$data_pupuk = [];
if($query) {
    while($r = mysqli_fetch_assoc($query)) {
        $data_pupuk[] = $r;
    }
}

// Ambil Data Paket
$q_paket = mysqli_query($conn, "SELECT * FROM paket_pupuk ORDER BY id DESC");
$data_paket = [];
if($q_paket) {
    while($rp = mysqli_fetch_assoc($q_paket)) {
        $rp['items'] = [];
        $q_items = mysqli_query($conn, "SELECT ppi.*, sp.nama, sp.satuan FROM paket_pupuk_item ppi JOIN stok_pupuk sp ON ppi.id_barang = sp.id WHERE ppi.id_paket='{$rp['id']}'");
        while($ri = mysqli_fetch_assoc($q_items)) {
            $rp['items'][] = $ri;
        }
        $data_paket[] = $rp;
    }
}

// DUMMY DATA UNTUK PREVIEW UI

?>

<div class="space-y-6 animate-in fade-in zoom-in duration-300">
    <div class="bg-white dark:bg-[#0d1117] p-5 rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-flask text-[#d29922]"></i> Manajemen Stok & Paket Bundling
            </h2>
            <p class="text-sm text-gray-500 dark:text-[#8b949e] mt-1">Kelola ketersediaan barang non-benih di gudang beserta racikan paketnya</p>
        </div>
        <div class="flex gap-2">
            <a href="?page=stok-pupuk&tab=satuan" class="<?= $tab == 'satuan' ? 'bg-[#1f6feb] text-white shadow-sm' : 'bg-gray-100 dark:bg-[#161b22] text-gray-700 dark:text-[#8b949e] border border-gray-200 dark:border-[#30363d] hover:bg-gray-200 dark:hover:bg-[#21262d]' ?> px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center">
                <i class="fa-solid fa-boxes-stacked mr-2"></i> Stok Satuan
            </a>
            <a href="?page=stok-pupuk&tab=paket" class="<?= $tab == 'paket' ? 'bg-[#1f6feb] text-white shadow-sm' : 'bg-gray-100 dark:bg-[#161b22] text-gray-700 dark:text-[#8b949e] border border-gray-200 dark:border-[#30363d] hover:bg-gray-200 dark:hover:bg-[#21262d]' ?> px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center">
                <i class="fa-solid fa-layer-group mr-2"></i> Paket Bundling
            </a>
        </div>
    </div>

    <?php if($tab == 'satuan'): ?>
    <div class="flex justify-end">
        <button onclick="bukaModalPupuk(0)" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center shadow-sm transition-colors">
            <i class="fa-solid fa-plus mr-2"></i> Tambah Barang Satuan
        </button>
    </div>

    <div class="bg-white dark:bg-[#0d1117] rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-max">
                <thead class="text-left text-xs uppercase text-gray-500 dark:text-[#8b949e] border-b border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#161b22]">
                    <tr>
                        <th class="py-3 px-5">Nama Barang</th>
                        <th class="py-3 px-5 text-center">Sisa Stok</th>
                        <th class="py-3 px-5 text-right">Harga Jual</th>
                        <th class="py-3 px-5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-[#21262d] text-sm text-gray-700 dark:text-gray-300">
                    <?php foreach($data_pupuk as $row): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#161b22]/50 transition-colors">
                        <td class="py-3 px-5 font-bold text-gray-900 dark:text-white"><?= $row['nama'] ?></td>
                        <td class="py-3 px-5 text-center">
                            <?php $color = $row['stok'] < 10 ? 'text-red-500 bg-red-100 dark:bg-red-500/10 border-red-500/20' : 'text-emerald-600 bg-emerald-100 dark:bg-[#238636]/10 border-emerald-500/20 dark:text-[#3fb950]'; ?>
                            <span class="inline-block px-2 py-1 rounded border text-xs font-bold <?= $color ?>">
                                <?= $row['stok'] ?>
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
    <?php endif; ?>

    <?php if($tab == 'paket'): ?>
    <div class="flex justify-end">
        <button onclick="bukaModalPaket(0)" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center shadow-sm transition-colors">
            <i class="fa-solid fa-plus mr-2"></i> Buat Paket Baru
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($data_paket as $pkt): ?>
        <div class="bg-white dark:bg-[#0d1117] rounded-xl shadow-sm border border-gray-200 dark:border-[#30363d] overflow-hidden flex flex-col hover:border-[#58a6ff] transition-colors group">
            <div class="p-5 border-b border-gray-100 dark:border-[#21262d] flex justify-between items-start">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white leading-tight"><?= htmlspecialchars($pkt['nama']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($pkt['deskripsi']) ?></p>
                </div>
            </div>
            
            <div class="p-5 bg-gray-50 dark:bg-[#161b22] flex-1">
                <h4 class="text-xs font-bold text-gray-400 uppercase mb-3">Isi Racikan Paket:</h4>
                <ul class="space-y-2">
                    <?php foreach($pkt['items'] as $itm): ?>
                    <li class="flex justify-between items-center text-sm border-b border-gray-200 dark:border-[#30363d] pb-2 last:border-0 last:pb-0">
                        <span class="text-gray-700 dark:text-[#c9d1d9] flex items-center gap-2"><i class="fa-solid fa-circle-dot text-[8px] text-[#58a6ff]"></i> <?= htmlspecialchars($itm['nama']) ?></span>
                        <span class="font-bold text-gray-900 dark:text-white bg-gray-200 dark:bg-[#30363d] px-2 py-0.5 rounded text-xs"><?= $itm['qty'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="p-5 border-t border-gray-100 dark:border-[#21262d] flex justify-between items-center bg-white dark:bg-[#0d1117]">
                <div>
                    <span class="block text-[10px] text-gray-500 uppercase font-bold">Harga Paket</span>
                    <span class="text-lg font-bold text-[#3fb950]"><?= formatRp($pkt['harga']) ?></span>
                </div>
                <div class="flex gap-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                    <?php $json_paket = htmlspecialchars(json_encode($pkt), ENT_QUOTES, 'UTF-8'); ?>
                    <button onclick='bukaModalPaket(<?= $json_paket ?>)' class="w-8 h-8 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center hover:bg-blue-500 hover:text-white transition-colors"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                    <a href="?page=stok-pupuk&tab=paket&hapus_paket=<?= $pkt['id'] ?>" onclick="return confirm('Yakin menghapus paket ini?')" class="w-8 h-8 rounded bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors"><i class="fa-solid fa-trash text-xs"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(count($data_paket) == 0): ?>
        <div class="col-span-full py-10 text-center border border-dashed border-gray-300 dark:border-[#30363d] rounded-xl text-gray-500">
            <i class="fa-solid fa-box-open text-4xl mb-3 text-gray-300 dark:text-gray-600"></i>
            <p>Belum ada paket bundling yang dibuat.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Nama Barang Lengkap *</label>
                    <input type="text" name="nama_barang" id="nama" placeholder="Contoh: Pupuk Urea Nitrea 50kg" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Jml Stok</label>
                        <input type="number" name="stok" id="stok" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                    </div>
                    <div>
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
        document.getElementById('nama').value = '';
        document.getElementById('stok').value = '0';
        document.getElementById('harga').value = '0';
    } else {
        document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-[#d29922]"></i> Edit Data Barang';
        document.getElementById('id_barang').value = data.id;
        document.getElementById('nama').value = data.nama;
        document.getElementById('stok').value = data.stok;
        document.getElementById('harga').value = data.harga;
    }
}

function tutupModalPupuk() { 
    document.getElementById('modal-pupuk').classList.add('hidden'); 
}
</script>

<div id="modal-paket" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
    <div class="bg-[#0d1117] border border-[#30363d] rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-[#30363d] flex justify-between items-center bg-[#161b22]">
            <h3 class="text-white font-bold flex items-center gap-2" id="modal-paket-title">
                <i class="fa-solid fa-layer-group text-[#d29922]"></i> Buat Paket Baru
            </h3>
            <button onclick="tutupModalPaket()" class="text-gray-400 hover:text-white transition-colors"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <form method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="id_paket" id="id_paket">
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Nama Paket *</label>
                        <input type="text" name="nama_paket" id="nama_paket" placeholder="Contoh: Paket Anti Wereng" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Harga Paket *</label>
                        <input type="number" name="harga_paket" id="harga_paket" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none" required>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-400 uppercase mb-1.5">Deskripsi Singkat</label>
                    <textarea name="deskripsi_paket" id="deskripsi_paket" rows="2" class="w-full bg-[#010409] border border-[#30363d] rounded px-3 py-2 text-sm text-white focus:border-[#58a6ff] outline-none"></textarea>
                </div>
                
                <hr class="border-[#30363d] my-4">
                
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-[11px] font-bold text-gray-400 uppercase">Isi Racikan Paket</label>
                        <button type="button" onclick="tambahItemPaket()" class="text-xs bg-[#21262d] hover:bg-[#30363d] border border-[#30363d] text-white px-2 py-1 rounded transition-colors"><i class="fa-solid fa-plus mr-1"></i> Tambah Item</button>
                    </div>
                    <div id="container-item-paket" class="space-y-2">
                        <!-- Items appended here by JS -->
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-[#161b22] border-t border-[#30363d] flex gap-3">
                <button type="submit" name="simpan_paket" class="flex-1 bg-[#238636] hover:bg-[#2ea043] text-white py-2.5 rounded font-bold text-sm transition-all shadow">Simpan Paket</button>
                <button type="button" onclick="tutupModalPaket()" class="flex-1 bg-[#21262d] hover:bg-[#30363d] text-white py-2.5 rounded font-bold text-sm transition-all border border-[#30363d]">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
// Data Barang for Dropdown
const dataBarang = <?= json_encode($data_pupuk) ?>;

function getBarangOptions(selectedId = 0) {
    let options = '<option value="">-- Pilih Barang --</option>';
    dataBarang.forEach(b => {
        let sel = b.id == selectedId ? 'selected' : '';
        options += `<option value="${b.id}" ${sel}>${b.nama}</option>`;
    });
    return options;
}

function tambahItemPaket(idBarang = 0, qty = 1) {
    const container = document.getElementById('container-item-paket');
    const row = document.createElement('div');
    row.className = 'flex gap-2 items-center';
    row.innerHTML = `
        <select name="item_id_barang[]" class="flex-1 bg-[#010409] border border-[#30363d] rounded px-2 py-1.5 text-sm text-white focus:border-[#58a6ff] outline-none" required>
            ${getBarangOptions(idBarang)}
        </select>
        <input type="number" name="item_qty[]" value="${qty}" min="1" class="w-20 bg-[#010409] border border-[#30363d] rounded px-2 py-1.5 text-sm text-white text-center focus:border-[#58a6ff] outline-none" required>
        <button type="button" onclick="this.parentElement.remove()" class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-500/10 rounded transition-colors"><i class="fa-solid fa-trash"></i></button>
    `;
    container.appendChild(row);
}

function bukaModalPaket(data) {
    document.getElementById('modal-paket').classList.remove('hidden');
    document.getElementById('container-item-paket').innerHTML = ''; // reset
    
    if(data === 0) {
        document.getElementById('modal-paket-title').innerHTML = '<i class="fa-solid fa-layer-group text-[#d29922]"></i> Buat Paket Baru';
        document.getElementById('id_paket').value = 0;
        document.getElementById('nama_paket').value = '';
        document.getElementById('deskripsi_paket').value = '';
        document.getElementById('harga_paket').value = '0';
        tambahItemPaket(); // Add 1 empty row
    } else {
        document.getElementById('modal-paket-title').innerHTML = '<i class="fa-solid fa-pen-to-square text-[#d29922]"></i> Edit Paket';
        document.getElementById('id_paket').value = data.id;
        document.getElementById('nama_paket').value = data.nama;
        document.getElementById('deskripsi_paket').value = data.deskripsi;
        document.getElementById('harga_paket').value = data.harga;
        
        if(data.items && data.items.length > 0) {
            data.items.forEach(itm => tambahItemPaket(itm.id_barang, itm.qty));
        } else {
            tambahItemPaket();
        }
    }
}

function tutupModalPaket() { 
    document.getElementById('modal-paket').classList.add('hidden'); 
}
</script>