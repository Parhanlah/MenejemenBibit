<?php
include 'components/koneksi.php';

// =========================================================================
// 1. AUTO-SETUP DATABASE & DATA SIMULASI (Agar terlihat langsung hasilnya)
// =========================================================================
$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'bibit_baris'");
if(mysqli_num_rows($cek_tabel) == 0) {
    mysqli_query($conn, "CREATE TABLE `bibit_baris` (`id_baris` int(11) NOT NULL, `status` varchar(50) DEFAULT 'kosong', `id_varietas` int(11) DEFAULT NULL, `tgl_persiapan` date DEFAULT NULL, `tgl_sebar` date DEFAULT NULL, `tersedia_m` decimal(4,1) NOT NULL DEFAULT 12.0, PRIMARY KEY (`id_baris`))");
    for($i = 1; $i <= 85; $i++) { mysqli_query($conn, "INSERT INTO bibit_baris (id_baris, status, tersedia_m) VALUES ('$i', 'kosong', 12.0)"); }
    
    // Inject Data Simulasi agar langsung terlihat semua warna fase umur
    $d1 = date('Y-m-d', strtotime('-2 days'));  // 2 hari (Semai - Biru)
    $d2 = date('Y-m-d', strtotime('-6 days'));  // 6 hari (Muda - Hijau)
    $d3 = date('Y-m-d', strtotime('-15 days')); // 15 hari (Siap Tanam - Kuning)
    $d4 = date('Y-m-d', strtotime('-25 days')); // 25 hari (Tua - Merah)
    mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh', tgl_sebar='$d1' WHERE id_baris IN (1,2)");
    mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh', tgl_sebar='$d2' WHERE id_baris IN (4,5)");
    mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh', tgl_sebar='$d3' WHERE id_baris IN (7,8)");
    mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh', tgl_sebar='$d4' WHERE id_baris IN (10,11)");
}

// =========================================================================
// 2. PROSES JADWALKAN SEBAR
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_sebar'])) {
    $id_varietas = (int)$_POST['varietas'];
    $tgl_persiapan = mysqli_real_escape_string($conn, $_POST['tgl_persiapan']);
    $tgl_sebar = mysqli_real_escape_string($conn, $_POST['tgl_sebar']);
    
    $baris_dipilih = explode(',', $_POST['baris_dipilih']);
    $jumlah_baris = count($baris_dipilih);
    $total_kebutuhan_stok = $jumlah_baris * 10; 
    
    $q_cek = mysqli_query($conn, "SELECT stok_kg FROM varietas_bibit WHERE id='$id_varietas'");
    if (($data_stok = mysqli_fetch_assoc($q_cek)) && $data_stok['stok_kg'] >= $total_kebutuhan_stok) {
        mysqli_query($conn, "UPDATE varietas_bibit SET stok_kg = stok_kg - $total_kebutuhan_stok WHERE id='$id_varietas'");
        
        foreach ($baris_dipilih as $id_baris) {
            $id_b = (int)$id_baris;
            mysqli_query($conn, "UPDATE bibit_baris SET status='persiapan', id_varietas='$id_varietas', tgl_persiapan='$tgl_persiapan', tgl_sebar='$tgl_sebar', tersedia_m=12.0 WHERE id_baris='$id_b'");
        }
        // REDIRECT KE HALAMAN BIBIT-BARIS dan MENGIRIM PERINTAH BLINK (BERKEDIP)
        echo "<script>window.location.href='?page=bibit-baris&action=sebar_sukses&blink=calendar';</script>";
        exit;
    } else {
        echo "<script>alert('GAGAL! Stok tidak mencukupi.');</script>";
    }
}

// =========================================================================
// 3. LOGIKA HITUNG UMUR & FASE PERTUMBUHAN
// =========================================================================
$query_varietas = mysqli_query($conn, "SELECT id, nama_varietas, kode_varietas FROM varietas_bibit WHERE stok_kg > 0 ORDER BY nama_varietas ASC");
$list_varietas = []; while($row = mysqli_fetch_assoc($query_varietas)) { $list_varietas[] = $row; }

$query_baris = mysqli_query($conn, "SELECT b.*, v.kode_varietas FROM bibit_baris b LEFT JOIN varietas_bibit v ON b.id_varietas = v.id ORDER BY b.id_baris ASC");

$counts = ['semua' => 0, 'semai' => 0, 'muda' => 0, 'siap' => 0, 'tua' => 0, 'kosong' => 0, 'persiapan' => 0];
$data_baris = [];
$tgl_hari_ini = date('Y-m-d');

while ($row = mysqli_fetch_assoc($query_baris)) {
    $counts['semua']++;
    $status_db = $row['status'];
    $teks_varietas = $row['kode_varietas'] ? htmlspecialchars($row['kode_varietas']) : '-';
    $corner_mark = false; // Tanda orange di pojok
    
    // Auto-update jika tanggal sebar sudah terlewati
    if ($status_db == 'persiapan' && !empty($row['tgl_sebar']) && $row['tgl_sebar'] <= $tgl_hari_ini) {
        $status_db = 'tumbuh';
        mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh' WHERE id_baris=".$row['id_baris']);
    }

    if ($status_db == 'kosong') {
        $kat = 'kosong'; $warna_dot = 'bg-white dark:bg-[#c9d1d9] border border-gray-400'; 
        $counts['kosong']++; $teks_waktu = 'Kosong'; $fase = 'Lahan Kosong';
    } elseif ($status_db == 'persiapan') {
        $kat = 'persiapan'; $warna_dot = 'bg-white dark:bg-[#c9d1d9] border border-gray-400'; 
        $corner_mark = true; // Tandai Orange
        $counts['persiapan']++; $teks_waktu = 'Persiapan'; $fase = 'Persiapan Sebar';
    } else {
        // Logika Umur Pertumbuhan
        $diff = strtotime($tgl_hari_ini) - strtotime($row['tgl_sebar']);
        $umur = floor($diff / 86400); if ($umur < 0) $umur = 0;
        $teks_waktu = $umur . ' Hari';

        if ($umur >= 0 && $umur <= 3) {
            $kat = 'semai'; $warna_dot = 'bg-[#1f6feb]'; $counts['semai']++; $fase = 'Bibit Semai'; // Biru
        } elseif ($umur >= 4 && $umur <= 11) {
            $kat = 'muda'; $warna_dot = 'bg-[#3fb950]'; $counts['muda']++; $fase = 'Bibit Muda'; // Hijau
        } elseif ($umur >= 12 && $umur <= 20) {
            $kat = 'siap'; $warna_dot = 'bg-[#d29922]'; $counts['siap']++; $fase = 'Siap Tanam'; // Kuning
        } else {
            $kat = 'tua'; $warna_dot = 'bg-[#f85149]'; $counts['tua']++; $fase = 'Bibit Tua'; // Merah
        }
    }

    $data_baris[] = ['no' => $row['id_baris'], 'kat' => $kat, 'dot' => $warna_dot, 'var' => $teks_varietas, 'waktu' => $teks_waktu, 'fase' => $fase, 'corner' => $corner_mark];
}

$tgl_target = date('Y-m-d', strtotime('+3 days'));
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d]">
    <div class="flex items-center gap-3 mb-6">
        <a href="?page=bibit-baris" class="text-gray-400 hover:text-white transition-colors"><i class="fa-solid fa-arrow-left"></i></a>
        <div><h1 class="text-xl font-bold dark:text-[#c9d1d9]"><i class="fa-solid fa-seedling text-[#3fb950] mr-3"></i> Persiapan Sebar Benih</h1></div>
    </div>

    <form id="form-sebar" method="POST" action="">
        <div class="bg-gray-50 dark:bg-[#161b22] rounded-xl border dark:border-[#30363d] p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Varietas <span class="text-red-500">*</span></label>
                    <select name="varietas" required class="w-full bg-white dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-white rounded-md px-3 py-2 text-[13px] focus:border-[#58a6ff] outline-none">
                        <option value="">Pilih varietas</option>
                        <?php foreach($list_varietas as $v): ?><option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nama_varietas']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Tgl Persiapan <span class="text-red-500">*</span></label>
                    <input type="date" id="tgl_persiapan" name="tgl_persiapan" value="<?= $tgl_hari_ini ?>" required class="w-full bg-white dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-white rounded-md px-3 py-2 text-[13px] [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-gray-700 dark:text-[#c9d1d9] mb-2">Tgl Sebar (Otomatis) <span class="text-red-500">*</span></label>
                    <input type="date" id="tgl_sebar" name="tgl_sebar" value="<?= $tgl_target ?>" required class="w-full bg-white dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-white rounded-md px-3 py-2 text-[13px] [color-scheme:dark]">
                </div>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-[#161b22] rounded-xl border dark:border-[#30363d] p-6 mb-20">
            <h2 class="text-[15px] font-bold dark:text-white mb-4">Filter & Pilih Baris (<span id="count-selected" class="text-[#58a6ff]">0</span>)</h2>
            
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <button type="button" data-filter="all" class="btn-filter dark:bg-[#30363d] border dark:border-[#58a6ff] dark:text-[#c9d1d9] px-3 py-1.5 rounded-md text-[12px] font-medium shadow-sm"><i class="fa-solid fa-bars mr-2"></i> Semua (<?= $counts['semua'] ?>)</button>
                <button type="button" data-filter="semai" class="btn-filter dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-[#8b949e] px-3 py-1.5 rounded-md text-[12px] font-medium flex items-center"><div class="w-2.5 h-2.5 rounded-full bg-[#1f6feb] mr-2"></div> 0-3h Semai (<?= $counts['semai'] ?>)</button>
                <button type="button" data-filter="muda" class="btn-filter dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-[#8b949e] px-3 py-1.5 rounded-md text-[12px] font-medium flex items-center"><div class="w-2.5 h-2.5 rounded-full bg-[#3fb950] mr-2"></div> 4-11h Muda (<?= $counts['muda'] ?>)</button>
                <button type="button" data-filter="siap" class="btn-filter dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-[#8b949e] px-3 py-1.5 rounded-md text-[12px] font-medium flex items-center"><div class="w-2.5 h-2.5 rounded-full bg-[#d29922] mr-2"></div> 12-20h Siap (<?= $counts['siap'] ?>)</button>
                <button type="button" data-filter="tua" class="btn-filter dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-[#8b949e] px-3 py-1.5 rounded-md text-[12px] font-medium flex items-center"><div class="w-2.5 h-2.5 rounded-full bg-[#f85149] mr-2"></div> >20h Tua (<?= $counts['tua'] ?>)</button>
                <button type="button" data-filter="kosong" class="btn-filter dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-[#8b949e] px-3 py-1.5 rounded-md text-[12px] font-medium flex items-center"><div class="w-2.5 h-2.5 rounded-full bg-white mr-2"></div> Kosong (<?= $counts['kosong'] ?>)</button>
                <button type="button" data-filter="persiapan" class="btn-filter dark:bg-[#0d1117] border dark:border-[#30363d] dark:text-[#8b949e] px-3 py-1.5 rounded-md text-[12px] font-medium flex items-center"><div class="w-2.5 h-2.5 rounded-full bg-[#ea580c] mr-2"></div> Persiapan (<?= $counts['persiapan'] ?>)</button>
            </div>

            <input type="hidden" name="baris_dipilih" id="input_baris_dipilih" value="">
            
            <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-10 xl:grid-cols-14 gap-2.5">
                <?php foreach($data_baris as $b): ?>
                <div class="baris-item relative dark:bg-[#21262d] border dark:border-[#30363d] rounded-lg p-2 flex flex-col items-center justify-center cursor-pointer hover:border-[#58a6ff] transition-all h-24 select-none" data-id="<?= $b['no'] ?>" data-kategori="<?= $b['kat'] ?>">
                    <div class="w-2.5 h-2.5 rounded-full <?= $b['dot'] ?> absolute top-2"></div>
                    
                    <?php if($b['corner']): ?>
                        <div class="absolute top-1 right-1 w-2.5 h-2.5 bg-[#ea580c] rounded-full shadow-md" title="Persiapan Sebar Belum Dieksekusi"></div>
                    <?php endif; ?>

                    <span class="text-[15px] font-bold dark:text-white mt-3"><?= $b['no'] ?></span>
                    <span class="text-[9px] dark:text-[#8b949e] text-center mt-1 truncate w-full"><?= $b['var'] ?></span>
                    <span class="text-[9px] dark:text-[#6e7681] mt-0.5" title="<?= $b['fase'] ?>"><?= $b['waktu'] ?></span>
                    
                    <div class="check-overlay absolute inset-0 bg-blue-500/10 border-2 border-[#58a6ff] rounded-lg items-center justify-center hidden">
                        <div class="bg-blue-500 text-white rounded-full w-5 h-5 flex items-center justify-center absolute -top-2 -right-2 shadow-md"><i class="fa-solid fa-check text-[10px]"></i></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fixed bottom-0 left-0 md:left-64 right-0 dark:bg-[#0d1117] border-t dark:border-[#30363d] p-4 flex justify-between z-40">
            <p class="text-[13px] dark:text-[#8b949e]">Baris dipilih: <strong id="footer-count" class="text-[#58a6ff]">0</strong></p>
            <button type="submit" name="proses_sebar" id="btn-submit" disabled class="bg-[#238636] hover:bg-[#2ea043] disabled:bg-[#21262d] disabled:text-[#6e7681] text-white px-6 py-2 rounded-md text-[13px] font-bold">Jadwalkan Sebar</button>
        </div>
    </form>
</div>

<script>
    document.getElementById('tgl_persiapan').addEventListener('change', function() {
        if(this.value) {
            let d = new Date(this.value); d.setDate(d.getDate() + 3);
            document.getElementById('tgl_sebar').value = d.toISOString().split('T')[0];
        }
    });

    const items = document.querySelectorAll('.baris-item'), input = document.getElementById('input_baris_dipilih'), btn = document.getElementById('btn-submit');
    let sel = new Set();

    items.forEach(i => i.addEventListener('click', function() {
        if (this.style.display === 'none') return;
        let id = this.getAttribute('data-id'), over = this.querySelector('.check-overlay');
        if(sel.has(id)){ sel.delete(id); over.classList.add('hidden'); this.classList.remove('border-[#58a6ff]'); this.classList.add('border-[#30363d]'); } 
        else { sel.add(id); over.classList.remove('hidden'); this.classList.remove('border-[#30363d]'); this.classList.add('border-[#58a6ff]'); }
        document.getElementById('count-selected').innerText = sel.size; document.getElementById('footer-count').innerText = sel.size;
        input.value = Array.from(sel).join(','); btn.disabled = sel.size === 0;
    }));

    const filters = document.querySelectorAll('.btn-filter');
    filters.forEach(f => f.addEventListener('click', function() {
        filters.forEach(b => { b.classList.remove('dark:bg-[#30363d]', 'dark:border-[#58a6ff]', 'dark:text-[#c9d1d9]', 'shadow-sm'); b.classList.add('dark:bg-[#0d1117]', 'dark:border-[#30363d]', 'dark:text-[#8b949e]'); });
        this.classList.add('dark:bg-[#30363d]', 'dark:border-[#58a6ff]', 'dark:text-[#c9d1d9]', 'shadow-sm'); this.classList.remove('dark:bg-[#0d1117]', 'dark:text-[#8b949e]');
        
        let val = this.getAttribute('data-filter');
        items.forEach(i => {
            if (val === 'all' || i.getAttribute('data-kategori') === val) { i.style.display = 'flex'; } 
            else { i.style.display = 'none'; if(sel.has(i.getAttribute('data-id'))) i.click(); } // auto uncheck
        });
    }));
</script>