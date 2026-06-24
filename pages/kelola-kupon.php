<?php
include __DIR__ . '/../components/koneksi.php';

// Helper Format Rupiah
if (!function_exists('formatRp')) {
    function formatRp($angka){ return "Rp " . number_format($angka, 0, ',', '.'); }
}

// =========================================================================
// 1. ENGINE PROSES (SIMPAN / EDIT / HAPUS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_kupon'])) {
    $id = (int)$_POST['id_kupon'];
    $kode = strtoupper(mysqli_real_escape_string($conn, $_POST['kode_kupon']));
    $nama = mysqli_real_escape_string($conn, $_POST['nama_kupon']);
    $tipe = mysqli_real_escape_string($conn, $_POST['tipe_diskon']);
    $nilai = (float)$_POST['nilai_diskon'];
    
    // Keamanan ganda backend untuk persentase
    if ($tipe === 'Persentase' && $nilai > 99) $nilai = 99;

    $min_order = (float)$_POST['min_order'];
    $max_kuota = (int)$_POST['kuota_max'];
    $berlaku = mysqli_real_escape_string($conn, $_POST['berlaku']);
    $tgl_m = $_POST['tgl_mulai'];
    $tgl_a = $_POST['tgl_akhir'];
    $ket = mysqli_real_escape_string($conn, $_POST['keterangan']);

    $kuota_str = ($max_kuota > 0) ? "0/$max_kuota" : "Unlimited";
    $periode = ($tgl_m && $tgl_a) ? date('d/m/Y', strtotime($tgl_m)) . ' - ' . date('d/m/Y', strtotime($tgl_a)) : 'Tidak terbatas';

    if($id > 0) {
        $q_old = mysqli_query($conn, "SELECT kuota FROM kupon_diskon WHERE id='$id'");
        $old_data = mysqli_fetch_assoc($q_old);
        if($max_kuota > 0) {
            $terpakai = (strpos($old_data['kuota'], '/') !== false) ? explode('/', $old_data['kuota'])[0] : 0;
            $kuota_str = "$terpakai/$max_kuota";
        }
        $sql = "UPDATE kupon_diskon SET kode='$kode', nama='$nama', tipe='$tipe', nilai='$nilai', berlaku='$berlaku', periode='$periode', kuota='$kuota_str', keterangan='$ket' WHERE id='$id'";
    } else {
        $sql = "INSERT INTO kupon_diskon (kode, nama, tipe, nilai, berlaku, periode, kuota, status, keterangan) 
                VALUES ('$kode', '$nama', '$tipe', '$nilai', '$berlaku', '$periode', '$kuota_str', 'Aktif', '$ket')";
    }

    if(mysqli_query($conn, $sql)) {
        echo "<script>window.location.href='?page=kelola-kupon';</script>";
    }
    exit;
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM kupon_diskon WHERE id='$id'");
    echo "<script>window.location.href='?page=kelola-kupon';</script>";
    exit;
}

$query = mysqli_query($conn, "SELECT * FROM kupon_diskon ORDER BY id DESC");
?>

<div class="bg-white dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] min-h-full rounded-xl p-4 md:p-6 text-gray-800 dark:text-gray-300 shadow transition-colors duration-200">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="fa-solid fa-tags text-[#58a6ff]"></i> Manajemen Kupon Diskon</h1>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Kelola kode kupon diskon untuk order bibit padi dan jasa tanam.</p>
            </div>
            <button onclick="bukaModalKupon(0)" class="bg-[#1f6feb] hover:bg-[#388bfd] text-white px-5 py-2.5 rounded-lg text-xs font-bold transition-all flex items-center shadow-lg"><i class="fa-solid fa-plus mr-2"></i> Tambah Kupon</button>
        </div>

        <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl overflow-hidden shadow-sm dark:shadow-2xl transition-colors duration-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-[#0d1117] border-b border-gray-200 dark:border-[#30363d] text-[10px] uppercase font-bold text-gray-500 tracking-wider transition-colors duration-200">
                            <th class="py-4 px-5">Kode</th><th class="py-4 px-5">Nama</th><th class="py-4 px-5 text-center">Tipe</th><th class="py-4 px-5">Nilai</th><th class="py-4 px-5">Berlaku Untuk</th><th class="py-4 px-5">Periode</th><th class="py-4 px-5">Kuota</th><th class="py-4 px-5 text-center">Status</th><th class="py-4 px-5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-[#30363d] transition-colors duration-200">
                        <?php while($row = mysqli_fetch_assoc($query)): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-[#21262d] transition-colors group">
                            <td class="py-4 px-5 font-mono font-bold text-[#58a6ff] text-sm"><?= $row['kode'] ?></td>
                            <td class="py-4 px-5 text-gray-900 dark:text-white text-xs font-medium"><?= $row['nama'] ?></td>
                            <td class="py-4 px-5 text-center"><span class="px-2 py-0.5 rounded text-[9px] font-bold <?= $row['tipe']=='Nominal' ? 'bg-green-100 dark:bg-green-500/10 text-green-600 dark:text-green-500 border border-green-200 dark:border-green-500/20' : 'bg-blue-100 dark:bg-blue-500/10 text-blue-600 dark:text-blue-500 border border-blue-200 dark:border-blue-500/20' ?>"><?= strtoupper($row['tipe']) ?></span></td>
                            <td class="py-4 px-5 text-gray-900 dark:text-white text-xs font-bold"><?= $row['tipe']=='Nominal' ? formatRp($row['nilai']) : $row['nilai'].'%' ?></td>
                            <td class="py-4 px-5 text-gray-600 dark:text-gray-400 text-xs"><?= $row['berlaku'] ?></td><td class="py-4 px-5 text-gray-500 dark:text-gray-500 text-[11px]"><?= $row['periode'] ?></td><td class="py-4 px-5 text-gray-500 dark:text-gray-400 text-xs"><?= $row['kuota'] ?></td>
                            <td class="py-4 px-5 text-center"><span class="px-2 py-0.5 rounded-full text-[9px] font-bold border <?= $row['status']=='Aktif' ? 'text-[#3fb950] border-[#3fb950]/30 bg-[#3fb950]/10' : 'text-[#f85149] border-[#f85149]/30 bg-[#f85149]/10' ?>"><?= $row['status'] ?></span></td>
                            <td class="py-4 px-5 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick='bukaModalKupon(<?= json_encode($row) ?>)' class="w-7 h-7 flex items-center justify-center rounded bg-gray-100 dark:bg-[#21262d] text-gray-500 dark:text-gray-400 hover:text-gray-900 hover:bg-gray-200 dark:hover:text-white dark:hover:bg-[#30363d] transition-all"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                                    <a href="?page=kelola-kupon&hapus=<?= $row['id'] ?>" onclick="return confirm('Hapus kupon ini?')" class="w-7 h-7 flex items-center justify-center rounded bg-gray-100 dark:bg-[#21262d] text-gray-500 dark:text-gray-400 hover:text-[#f85149] hover:bg-red-50 dark:hover:bg-[#f85149]/10 transition-all"><i class="fa-solid fa-trash-can text-xs"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modal-kupon" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-sm hidden animate-in fade-in duration-300">
    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-2xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden flex flex-col text-gray-800 dark:text-gray-300 transition-colors duration-200">
        
        <div class="px-6 py-5 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117] transition-colors duration-200">
            <h3 class="text-gray-900 dark:text-white font-bold flex items-center gap-2"><i class="fa-solid fa-tag text-[#58a6ff]"></i> <span id="modal-title">Tambah Kupon Baru</span></h3>
            <button onclick="tutupModalKupon()" class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <form method="POST" class="flex flex-col">
            <input type="hidden" name="id_kupon" id="id_kupon" value="0">
            
            <div class="p-6 space-y-5 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Kode Kupon <span class="text-red-500">*</span></label>
                        <input type="text" name="kode_kupon" id="kode" required placeholder="CONTOH: BIBIT50K" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none transition-all placeholder:text-gray-400 dark:placeholder:text-gray-700 font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Nama Kupon <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_kupon" id="nama" required placeholder="Contoh: Potongan 50rb" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none transition-all placeholder:text-gray-400 dark:placeholder:text-gray-700">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Tipe Diskon <span class="text-red-500">*</span></label>
                        <select name="tipe_diskon" id="tipe" onchange="sinkronTipeDiskon()" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none cursor-pointer">
                            <option value="Nominal">Nominal (Rp)</option>
                            <option value="Persentase">Persentase (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Nilai Diskon <span class="text-red-500">*</span></label>
                        <div class="relative flex items-center">
                            <span id="label-rp" class="absolute left-3 text-gray-500 font-bold text-sm pointer-events-none">Rp</span>
                            <input type="number" name="nilai_diskon" id="nilai" required placeholder="0" min="0" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg pl-9 pr-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none transition-all font-bold">
                            <span id="label-persen" class="absolute right-3 text-gray-500 font-bold text-sm pointer-events-none hidden">%</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Min. Order (Rp)</label>
                        <input type="number" name="min_order" id="min_order" value="0" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Kuota Penggunaan</label>
                        <input type="number" name="kuota_max" id="kuota" value="0" placeholder="0 = Unlimited" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Berlaku Untuk</label>
                    <select name="berlaku" id="berlaku" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none cursor-pointer">
                        <option value="Semua Order">Semua Order</option>
                        <option value="Order Bibit">Khusus Order Bibit</option>
                        <option value="Jasa Tanam">Khusus Jasa Tanam</option>
                        <option value="Pupuk & Obat">Khusus Pupuk & Obat</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Tanggal Mulai</label>
                        <input type="date" name="tgl_mulai" id="tgl_m" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none [color-scheme:light] dark:[color-scheme:dark]">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" id="tgl_a" class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none [color-scheme:light] dark:[color-scheme:dark]">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-500 uppercase tracking-widest mb-2">Keterangan</label>
                    <textarea name="keterangan" id="ket" rows="3" placeholder="Deskripsi kupon..." class="w-full bg-white dark:bg-[#010409] border border-gray-300 dark:border-[#30363d] rounded-lg px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-[#58a6ff] outline-none resize-none placeholder:text-gray-400 dark:placeholder:text-gray-700"></textarea>
                </div>
            </div>

            <div class="px-6 py-5 border-t border-gray-200 dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117] flex flex-col md:flex-row gap-3 transition-colors duration-200">
                <button type="button" onclick="tutupModalKupon()" class="flex-1 bg-gray-200 dark:bg-[#21262d] hover:bg-gray-300 dark:hover:bg-[#30363d] text-gray-800 dark:text-gray-300 py-3 rounded-xl font-bold text-xs transition-all order-2 md:order-1">Batal</button>
                <button type="submit" name="simpan_kupon" id="btn-submit" class="flex-1 bg-[#3446eb] hover:bg-[#4659f7] text-white py-3 rounded-xl font-bold text-xs transition-all shadow-lg order-1 md:order-2">Buat Kupon</button>
            </div>
        </form>
    </div>
</div>

<script>
    // FUNGSI SINKRONISASI TIPE DISKON (Rp / %)
    function sinkronTipeDiskon() {
        let tipe = document.getElementById('tipe').value;
        let inputNilai = document.getElementById('nilai');
        let labelRp = document.getElementById('label-rp');
        let labelPersen = document.getElementById('label-persen');

        if (tipe === 'Persentase') {
            labelRp.classList.add('hidden');
            labelPersen.classList.remove('hidden');
            inputNilai.classList.remove('pl-9');
            inputNilai.classList.add('pl-4', 'pr-9'); // Geser text input ke kiri, % di kanan
            
            // Batasi angka maksimal 99 saat tipe diganti
            if (parseInt(inputNilai.value) > 99) inputNilai.value = 99;
        } else {
            labelRp.classList.remove('hidden');
            labelPersen.classList.add('hidden');
            inputNilai.classList.remove('pl-4', 'pr-9');
            inputNilai.classList.add('pl-9', 'pr-4'); // Geser text input ke kanan, Rp di kiri
        }
    }

    // LISTENER CEGAH KETIKAN LEBIH DARI 99
    document.getElementById('nilai').addEventListener('input', function() {
        let tipe = document.getElementById('tipe').value;
        if (tipe === 'Persentase' && parseInt(this.value) > 99) {
            this.value = 99; 
        }
    });

    function bukaModalKupon(data) {
        document.getElementById('modal-kupon').classList.remove('hidden');
        if(data === 0) {
            document.getElementById('modal-title').innerText = "Tambah Kupon Baru";
            document.getElementById('btn-submit').innerText = "Buat Kupon";
            document.getElementById('id_kupon').value = 0;
            document.getElementById('kode').value = ''; 
            document.getElementById('nama').value = '';
            document.getElementById('tipe').value = 'Nominal'; // Default Nominal
            document.getElementById('nilai').value = '0';
            document.getElementById('min_order').value = '0';
            document.getElementById('kuota').value = '0';
            document.getElementById('ket').value = '';
        } else {
            document.getElementById('modal-title').innerText = "Edit Kupon: " + data.kode;
            document.getElementById('btn-submit').innerText = "Simpan Perubahan";
            document.getElementById('id_kupon').value = data.id;
            document.getElementById('kode').value = data.kode;
            document.getElementById('nama').value = data.nama;
            document.getElementById('tipe').value = data.tipe;
            document.getElementById('nilai').value = data.nilai;
            document.getElementById('berlaku').value = data.berlaku;
            document.getElementById('ket').value = data.keterangan;
            
            let maxK = 0; 
            if(data.kuota.includes('/')) maxK = data.kuota.split('/')[1]; 
            else if(data.kuota === 'Unlimited') maxK = 0;
            document.getElementById('kuota').value = maxK;
        }
        
        // Selalu sinkronkan tampilan kotak Rp/% saat modal pertama kali dibuka
        sinkronTipeDiskon();
    }

    function tutupModalKupon() { document.getElementById('modal-kupon').classList.add('hidden'); }
    window.onclick = function(event) { let modal = document.getElementById('modal-kupon'); if (event.target == modal) { tutupModalKupon(); } }
</script>