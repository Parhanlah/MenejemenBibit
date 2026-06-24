<?php
include __DIR__ . '/../components/koneksi.php';

// =========================================================================
// 1. PROSES AKSI DARI MODAL (EXECUTE, BATALKAN, EDIT)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $baris_list = mysqli_real_escape_string($conn, $_POST['baris_list']);
    
    // A. EKSEKUSI SEBAR (Ubah status dari persiapan menjadi tumbuh)
    if ($_POST['action'] == 'execute') {
        mysqli_query($conn, "UPDATE bibit_baris SET status='tumbuh' WHERE id_baris IN ($baris_list)");
        echo "<script>alert('Sukses! Benih telah dieksekusi dan mulai masuk fase pertumbuhan.'); window.location.href='?page=calendar-persiapan';</script>";
        exit;
    } 
    // B. BATALKAN JADWAL (Kembalikan stok ke gudang, kosongkan lahan)
    elseif ($_POST['action'] == 'batalkan') {
        $id_var = (int)$_POST['id_varietas'];
        $jml_baris = count(explode(',', $baris_list));
        $stok_kembali = $jml_baris * 10; // 1 baris = 10 kg
        
        mysqli_query($conn, "UPDATE varietas_bibit SET stok_kg = stok_kg + $stok_kembali WHERE id='$id_var'");
        mysqli_query($conn, "UPDATE bibit_baris SET status='kosong', id_varietas=NULL, tgl_persiapan=NULL, tgl_sebar=NULL, tersedia_m=12.0 WHERE id_baris IN ($baris_list)");
        
        echo "<script>alert('Jadwal dibatalkan! Lahan dikosongkan dan $stok_kembali kg benih dikembalikan ke gudang.'); window.location.href='?page=calendar-persiapan';</script>";
        exit;
    }
    // C. EDIT TANGGAL
    elseif ($_POST['action'] == 'edit') {
        $tgl_p = mysqli_real_escape_string($conn, $_POST['tgl_persiapan']);
        $tgl_s = mysqli_real_escape_string($conn, $_POST['tgl_sebar']);
        mysqli_query($conn, "UPDATE bibit_baris SET tgl_persiapan='$tgl_p', tgl_sebar='$tgl_s' WHERE id_baris IN ($baris_list)");
        echo "<script>alert('Tanggal jadwal berhasil diperbarui!'); window.location.href='?page=calendar-persiapan';</script>";
        exit;
    }
}

// =========================================================================
// 2. LOGIKA PENANGGALAN KALENDER
// =========================================================================
$month = isset($_GET['m']) ? $_GET['m'] : date('m');
$year = isset($_GET['y']) ? $_GET['y'] : date('Y');

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$monthName = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
$namaBulan = $monthName[(int)$month - 1];
$dayOfWeek = $dateComponents['wday'];

$prevMonth = $month - 1; $prevYear = $year;
if($prevMonth == 0) { $prevMonth = 12; $prevYear = $year - 1; }
$nextMonth = $month + 1; $nextYear = $year;
if($nextMonth == 13) { $nextMonth = 1; $nextYear = $year + 1; }

// =========================================================================
// 3. TARIK DATA BATCH (TERMASUK RIWAYAT YANG SUDAH SELESAI)
// =========================================================================
// Query ini mengambil semua data yang punya jadwal. Jika statusnya bukan 'persiapan',
// berarti ia sudah "Selesai" dieksekusi (is_executed = 1).
$query_batch = mysqli_query($conn, "
    SELECT 
        GROUP_CONCAT(id_baris ORDER BY id_baris ASC SEPARATOR ', ') as baris_list, 
        COUNT(id_baris) as jml, 
        v.id as id_var, 
        v.nama_varietas, 
        b.tgl_persiapan, 
        b.tgl_sebar,
        IF(b.status = 'persiapan', 0, 1) as is_executed
    FROM bibit_baris b 
    LEFT JOIN varietas_bibit v ON b.id_varietas = v.id 
    WHERE b.tgl_persiapan IS NOT NULL AND b.tgl_sebar IS NOT NULL
    GROUP BY b.tgl_persiapan, b.tgl_sebar, v.id, v.nama_varietas, is_executed
");

$batches = [];
while($row = mysqli_fetch_assoc($query_batch)) { $batches[] = $row; }

// Rekap event untuk pill kalender
$event_persiapan = []; $event_sebar = [];
foreach($batches as $b) {
    // Hitung total baris persiapan per tanggal
    if(date('m', strtotime($b['tgl_persiapan'])) == $month) {
        if(!isset($event_persiapan[$b['tgl_persiapan']])) $event_persiapan[$b['tgl_persiapan']] = 0;
        $event_persiapan[$b['tgl_persiapan']] += $b['jml'];
    }
    // Hitung total baris sebar per tanggal
    if(date('m', strtotime($b['tgl_sebar'])) == $month) {
        if(!isset($event_sebar[$b['tgl_sebar']])) $event_sebar[$b['tgl_sebar']] = 0;
        $event_sebar[$b['tgl_sebar']] += $b['jml'];
    }
}
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    
    <div class="flex items-center gap-3 mb-6">
        <a href="?page=bibit-baris" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors"><i class="fa-solid fa-arrow-left text-sm md:text-base"></i></a>
        <div>
            <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]"><i class="fa-solid fa-calendar-days text-[#58a6ff] mr-3"></i> Calendar Persiapan Sebar Benih</h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-0.5 ml-8">Lihat jadwal persiapan dan sebar benih (Riwayat & Mendatang)</p>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 mb-6 flex items-center gap-6">
        <div class="flex items-center text-[13px] dark:text-[#c9d1d9] font-medium"><div class="w-3 h-3 rounded bg-[#1f6feb] mr-2"></div> Tanggal Persiapan</div>
        <div class="flex items-center text-[13px] dark:text-[#c9d1d9] font-medium"><div class="w-3 h-3 rounded bg-[#3fb950] mr-2"></div> Tanggal Sebar</div>
        <div class="flex items-center text-[12px] dark:text-[#8b949e] ml-auto"><i class="fa-regular fa-hand-pointer mr-2"></i> Klik kotak tanggal untuk lihat detail</div>
    </div>

    <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl overflow-hidden">
        
        <div class="flex justify-between items-center p-4 border-b dark:border-[#30363d]">
            <a href="?page=calendar-persiapan&m=<?= str_pad($prevMonth, 2, '0', STR_PAD_LEFT) ?>&y=<?= $prevYear ?>" class="text-gray-400 hover:text-white p-2"><i class="fa-solid fa-chevron-left"></i></a>
            <h2 class="text-lg font-bold dark:text-[#c9d1d9]"><?= $namaBulan . " " . $year ?></h2>
            <a href="?page=calendar-persiapan&m=<?= str_pad($nextMonth, 2, '0', STR_PAD_LEFT) ?>&y=<?= $nextYear ?>" class="text-gray-400 hover:text-white p-2"><i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="grid grid-cols-7 border-b dark:border-[#30363d] text-center bg-[#0d1117]/30">
            <?php foreach(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $day): ?>
                <div class="py-3 text-[12px] font-bold text-gray-500 dark:text-[#8b949e]"><?= $day ?></div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-7 border-l border-t-0 dark:border-[#30363d]">
            <?php 
                for($i = 0; $i < $dayOfWeek; $i++) { echo "<div class='min-h-[120px] border-r border-b dark:border-[#30363d] p-2 bg-gray-100 dark:bg-[#0d1117]/50'></div>"; }

                for($day = 1; $day <= $numberDays; $day++) {
                    $currentDate = $year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $isToday = ($currentDate == date('Y-m-d')) ? 'bg-[#58a6ff]/10 border-[#58a6ff]/30 shadow-inner' : '';
                    $textToday = ($currentDate == date('Y-m-d')) ? 'text-[#58a6ff] font-bold text-[16px]' : 'text-gray-700 dark:text-[#c9d1d9] font-medium text-[14px]';

                    // Seluruh kotak div menjadi bisa diklik
                    echo "<div onclick=\"bukaDetail('$currentDate')\" class='min-h-[120px] border-r border-b dark:border-[#30363d] p-2 hover:bg-gray-200 dark:hover:bg-[#21262d] transition-colors cursor-pointer group $isToday'>";
                    echo "<div class='$textToday mb-2'>$day</div>";

                    if(isset($event_persiapan[$currentDate])) {
                        echo "<div class='bg-[#1f6feb] text-white text-[11px] font-bold px-2 py-1 rounded mb-1.5 shadow-sm truncate'><div class='w-1.5 h-1.5 rounded-full bg-white/60 inline-block mr-1'></div> " . $event_persiapan[$currentDate] . " baris</div>";
                    }
                    if(isset($event_sebar[$currentDate])) {
                        echo "<div class='bg-[#3fb950] text-white text-[11px] font-bold px-2 py-1 rounded mb-1.5 shadow-sm truncate'><div class='w-1.5 h-1.5 rounded-full bg-white/60 inline-block mr-1'></div> " . $event_sebar[$currentDate] . " baris</div>";
                    }

                    echo "</div>";
                }

                $remainingDays = (7 - (($dayOfWeek + $numberDays) % 7)) % 7;
                for($i = 0; $i < $remainingDays; $i++) { echo "<div class='min-h-[120px] border-r border-b dark:border-[#30363d] p-2 bg-gray-100 dark:bg-[#0d1117]/50'></div>"; }
            ?>
        </div>
    </div>
</div>

<div id="modal-detail" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
    <div class="bg-[#161b22] rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-[#30363d] flex flex-col max-h-[90vh]">
        
        <div class="px-5 py-4 border-b border-[#30363d] flex justify-between items-center bg-[#0d1117] shrink-0">
            <h3 class="text-[15px] font-bold text-white flex items-center"><i class="fa-regular fa-calendar-days mr-2 text-[#58a6ff]"></i> <span id="modal-title-date">Tanggal</span></h3>
            <button type="button" onclick="tutupDetail()" class="text-gray-500 hover:text-gray-300"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <div id="det_cards_container" class="p-5 overflow-y-auto custom-scrollbar">
            </div>

        <div class="p-3 bg-[#0d1117] border-t border-[#30363d] shrink-0">
            <button onclick="tutupDetail()" class="w-full bg-[#21262d] hover:bg-[#30363d] text-[#c9d1d9] py-2 rounded-md text-[13px] font-bold transition-colors border border-[#30363d]">Tutup</button>
        </div>
    </div>
</div>

<div id="modal-edit" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity">
    <div class="bg-[#161b22] rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-[#30363d] flex flex-col text-gray-300">
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="baris_list" id="edit_baris_list">

            <div class="px-5 py-4 border-b border-[#30363d] flex justify-between items-center bg-[#0d1117]">
                <h3 class="text-[15px] font-bold text-white flex items-center"><i class="fa-solid fa-pen text-[#d2a878] mr-2"></i> Edit Persiapan Sebar</h3>
            </div>

            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-[12px] font-bold text-white mb-1.5">Varietas <span class="text-red-500">*</span></label>
                    <input type="text" id="edit_var" readonly class="w-full bg-[#0d1117] border border-[#30363d] text-gray-500 rounded-md px-3 py-2 text-sm focus:outline-none cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-white mb-1.5">Tanggal Persiapan <span class="text-red-500">*</span></label>
                    <input type="date" name="tgl_persiapan" id="edit_tgl_p" required class="w-full bg-[#0d1117] border border-[#30363d] text-white rounded-md px-3 py-2 text-sm focus:border-[#58a6ff] focus:outline-none [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-white mb-1.5">Tanggal Sebar <span class="text-red-500">*</span></label>
                    <input type="date" name="tgl_sebar" id="edit_tgl_s" required class="w-full bg-[#0d1117] border border-[#30363d] text-white rounded-md px-3 py-2 text-sm focus:border-[#58a6ff] focus:outline-none [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-[12px] font-bold text-white mb-1.5">Petugas</label>
                    <input type="text" value="Admin" readonly class="w-full bg-[#0d1117] border border-[#30363d] text-white rounded-md px-3 py-2 text-sm focus:outline-none cursor-not-allowed">
                </div>
                <div class="bg-[#d29922]/10 border border-[#d29922]/30 rounded-md p-3">
                    <p class="text-[12px] text-[#d29922]"><i class="fa-regular fa-lightbulb mr-1"></i> <strong>Info:</strong> <span id="edit_info"></span></p>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-[#30363d] flex justify-between gap-3 bg-[#0d1117]">
                <button type="submit" class="flex-1 bg-[#1f6feb] hover:bg-[#388bfd] text-white py-2 rounded-md text-[13px] font-bold flex items-center justify-center shadow"><i class="fa-solid fa-floppy-disk mr-2"></i> Simpan</button>
                <button type="button" onclick="tutupEdit()" class="flex-1 bg-[#21262d] hover:bg-[#30363d] text-[#c9d1d9] border border-[#30363d] py-2 rounded-md text-[13px] font-bold transition-colors">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Ambil Data PHP ke JavaScript dengan aman
    const allBatches = <?= json_encode($batches) ?>;
    
    const namaBulanIndo = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    const namaHariIndo = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];

    function formatTanggalIndo(tglString) {
        let d = new Date(tglString);
        return namaHariIndo[d.getDay()] + ", " + d.getDate() + " " + namaBulanIndo[d.getMonth()] + " " + d.getFullYear();
    }

    function bukaDetail(tgl_klik) {
        let container = document.getElementById('det_cards_container');
        container.innerHTML = '';
        let found = false;
        
        document.getElementById('modal-title-date').innerText = formatTanggalIndo(tgl_klik);

        allBatches.forEach(b => {
            let isPersiapan = (b.tgl_persiapan === tgl_klik);
            let isSebar = (b.tgl_sebar === tgl_klik);
            
            if(isPersiapan || isSebar) {
                found = true;
                let isExecuted = (b.is_executed == 1); // 1 = Selesai/Riwayat, 0 = Persiapan
                
                // --- 1. Tipe Event (Biru / Hijau) ---
                let typeBadge = '';
                if(isPersiapan && !isSebar) {
                    typeBadge = `<span class="bg-[#1f6feb] text-white px-2 py-0.5 rounded text-[11px] font-bold flex items-center"><div class="w-1.5 h-1.5 rounded-full bg-white/60 mr-1.5"></div> Persiapan</span>`;
                } else if(isSebar && !isPersiapan) {
                    typeBadge = `<span class="bg-[#238636] text-white px-2 py-0.5 rounded text-[11px] font-bold flex items-center"><div class="w-1.5 h-1.5 rounded-full bg-white/60 mr-1.5"></div> Sebar</span>`;
                } else {
                    typeBadge = `<span class="bg-[#238636] text-white px-2 py-0.5 rounded text-[11px] font-bold flex items-center"><div class="w-1.5 h-1.5 rounded-full bg-white/60 mr-1.5"></div> Sebar & Persiapan</span>`;
                }

                // --- 2. Status & Tombol Aksi ---
                let statusBadge = '';
                let actionHtml = '';
                
                if(isExecuted) {
                    // Jika riwayat, tampilkan badge abu-abu "Selesai", jangan beri tombol
                    statusBadge = `<span class="bg-gray-600 border border-gray-500 text-white px-2 py-0.5 rounded text-[11px] font-bold shadow-sm">Selesai</span>`;
                } else {
                    // Cek kelayakan eksekusi (Tgl Hari ini vs Tgl Sebar)
                    let today = new Date(); today.setHours(0,0,0,0);
                    let tglS = new Date(b.tgl_sebar); tglS.setHours(0,0,0,0);
                    
                    if(today >= tglS) {
                        statusBadge = `<span class="bg-[#3fb950] text-white px-2 py-0.5 rounded text-[11px] font-bold">Siap</span>`;
                        actionHtml = `
                            <div class="flex gap-3 mb-3 mt-5">
                                <button type="button" onclick='bukaEdit(${JSON.stringify(b)})' class="flex-1 bg-[#1f6feb] hover:bg-[#388bfd] text-white py-2 rounded-md text-[12px] font-bold shadow"><i class="fa-solid fa-pen mr-2"></i> Edit</button>
                                <form method="POST" class="flex-1" onsubmit="return confirm('Yakin membatalkan jadwal ini? Lahan akan dikosongkan dan benih dikembalikan ke gudang.')">
                                    <input type="hidden" name="action" value="batalkan">
                                    <input type="hidden" name="baris_list" value="${b.baris_list}">
                                    <input type="hidden" name="id_varietas" value="${b.id_var}">
                                    <button type="submit" class="w-full bg-[#da3633] hover:bg-[#f85149] text-white py-2 rounded-md text-[12px] font-bold shadow"><i class="fa-solid fa-trash-can mr-2"></i> Batalkan</button>
                                </form>
                            </div>
                            <form method="POST" onsubmit="return confirm('Mulai proses tumbuh? Baris ini akan ditandai sukses disebar.')">
                                <input type="hidden" name="action" value="execute">
                                <input type="hidden" name="baris_list" value="${b.baris_list}">
                                <button type="submit" class="w-full bg-[#238636] hover:bg-[#2ea043] text-white py-2.5 rounded-md text-[13px] font-bold shadow-lg"><i class="fa-solid fa-seedling mr-2"></i> Execute Sebar Benih</button>
                            </form>
                        `;
                    } else {
                        statusBadge = `<span class="bg-[#30363d] text-gray-300 px-2 py-0.5 rounded text-[11px] font-bold">Belum Siap</span>`;
                        actionHtml = `
                            <div class="flex gap-3 mt-5">
                                <button type="button" onclick='bukaEdit(${JSON.stringify(b)})' class="flex-1 bg-[#1f6feb] hover:bg-[#388bfd] text-white py-2 rounded-md text-[12px] font-bold shadow"><i class="fa-solid fa-pen mr-2"></i> Edit</button>
                                <form method="POST" class="flex-1" onsubmit="return confirm('Yakin membatalkan jadwal ini?')">
                                    <input type="hidden" name="action" value="batalkan">
                                    <input type="hidden" name="baris_list" value="${b.baris_list}">
                                    <input type="hidden" name="id_varietas" value="${b.id_var}">
                                    <button type="submit" class="w-full bg-[#da3633] hover:bg-[#f85149] text-white py-2 rounded-md text-[12px] font-bold shadow"><i class="fa-solid fa-trash-can mr-2"></i> Batalkan</button>
                                </form>
                            </div>
                        `;
                    }
                }
                
                // --- 3. Format Render Teks ---
                let pArr = b.tgl_persiapan.split('-'); let tglPFormat = `${pArr[2]}/${pArr[1]}/${pArr[0]}`;
                let sArr = b.tgl_sebar.split('-'); let tglSFormat = `${sArr[2]}/${sArr[1]}/${sArr[0]}`;
                
                let barisHtml = '';
                b.baris_list.split(',').forEach(bx => {
                    barisHtml += `<span class="bg-[#30363d] text-[#8b949e] px-1.5 py-0.5 rounded text-[11px] font-bold mr-1 mb-1 inline-block border border-[#484f58]">#${bx.trim()}</span>`;
                });
                
                // Bangun Kartu HTML
                let card = `
                <div class="bg-[#0d1117] border border-[#30363d] rounded-lg p-5 mb-5 shadow-sm">
                    <div class="flex gap-2 mb-3">${typeBadge} ${statusBadge}</div>
                    <h2 class="text-[17px] font-bold text-white mb-4">${b.nama_varietas || '-'}</h2>
                    
                    <div class="grid grid-cols-2 gap-y-4 gap-x-6 mb-5">
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Jumlah Baris:</p><p class="text-[13px] font-bold text-white">${b.jml} baris</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Estimasi Benih:</p><p class="text-[13px] font-bold text-white">${b.jml * 10} kg</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Tanggal Persiapan:</p><p class="text-[13px] font-bold text-white">${tglPFormat}</p></div>
                        <div><p class="text-[12px] text-gray-500 mb-0.5">Tanggal Sebar:</p><p class="text-[13px] font-bold text-white">${tglSFormat}</p></div>
                    </div>
                    
                    <div>
                        <p class="text-[12px] text-gray-500 mb-2">Baris yang akan disebar:</p>
                        <div class="flex flex-wrap">${barisHtml}</div>
                    </div>
                    ${actionHtml}
                </div>`;
                
                container.innerHTML += card;
            }
        });
        
        if(found) document.getElementById('modal-detail').classList.remove('hidden');
    }

    function tutupDetail() { document.getElementById('modal-detail').classList.add('hidden'); }

    // Logika Edit Modal
    function bukaEdit(b) {
        document.getElementById('modal-detail').classList.add('hidden');
        document.getElementById('edit_baris_list').value = b.baris_list;
        document.getElementById('edit_var').value = b.nama_varietas || '-';
        document.getElementById('edit_tgl_p').value = b.tgl_persiapan;
        document.getElementById('edit_tgl_s').value = b.tgl_sebar;
        document.getElementById('edit_info').innerHTML = `Baris yang dipilih: <strong>${b.jml} baris (${b.jml * 10} kg)</strong>`;
        document.getElementById('modal-edit').classList.remove('hidden');
    }

    function tutupEdit() { document.getElementById('modal-edit').classList.add('hidden'); }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
</style>