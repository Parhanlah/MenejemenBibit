<?php
include __DIR__ . '/../components/koneksi.php';

// =========================================================================
// 1. LOGIKA PENANGGALAN KALENDER
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
// 2. TARIK DATA ORDER (JASA TANAM & PENGAMBILAN BIBIT)
// =========================================================================
// Tarik order yang aktif (bukan batal) dan memiliki tanggal tanam atau ambil
$query_order = mysqli_query($conn, "
    SELECT id, no_order, nama_customer, no_hp, lokasi_sawah, varietas, panjang_m, tgl_tanam, tgl_ambil, tipe_order, status 
    FROM order_bibit 
    WHERE status != 'Batal' AND (tgl_tanam IS NOT NULL OR tgl_ambil IS NOT NULL)
");

$semua_order = [];
$event_tanam = [];
$event_ambil = [];

while($row = mysqli_fetch_assoc($query_order)) {
    $semua_order[] = $row;
    
    // Kelompokkan event JASA TANAM berdasarkan tgl_tanam
    if ($row['tipe_order'] == 'Jasa Tanam' && !empty($row['tgl_tanam'])) {
        $t = $row['tgl_tanam'];
        if(!isset($event_tanam[$t])) $event_tanam[$t] = ['jml_order' => 0, 'total_meter' => 0];
        $event_tanam[$t]['jml_order'] += 1;
        $event_tanam[$t]['total_meter'] += (float)$row['panjang_m'];
    }
    
    // Kelompokkan event PENGAMBILAN berdasarkan tgl_ambil (Reguler / Ambil Mandiri)
    if ((empty($row['tipe_order']) || $row['tipe_order'] == 'Reguler') && !empty($row['tgl_ambil'])) {
        $a = $row['tgl_ambil'];
        if(!isset($event_ambil[$a])) $event_ambil[$a] = ['jml_order' => 0, 'total_meter' => 0];
        $event_ambil[$a]['jml_order'] += 1;
        $event_ambil[$a]['total_meter'] += (float)$row['panjang_m'];
    }
}
?>

<div class="bg-white dark:bg-[#0d1117] min-h-full rounded-xl p-4 md:p-6 shadow border border-gray-100 dark:border-[#30363d] transition-colors duration-200">
    
    <div class="flex items-center gap-3 mb-6">
        <div>
            <h1 class="text-lg md:text-xl font-bold flex items-center text-gray-800 dark:text-[#c9d1d9]">
                <i class="fa-solid fa-calendar-check text-[#3fb950] mr-3"></i> Kalender Logistik & Operasional
            </h1>
            <p class="text-[13px] text-gray-500 dark:text-[#8b949e] mt-0.5 ml-8">Pusat pemantauan jadwal Jasa Tanam ke sawah & Pengambilan order bibit reguler.</p>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex gap-4">
            <label class="flex items-center text-[13px] dark:text-[#c9d1d9] font-medium cursor-pointer">
                <input type="checkbox" id="filter-tanam" checked class="mr-2 rounded text-[#3fb950] focus:ring-[#3fb950] w-4 h-4 cursor-pointer" onchange="filterKalender()"> 
                <div class="w-3 h-3 rounded bg-[#3fb950] mx-2"></div> Jadwal Jasa Tanam
            </label>
            <label class="flex items-center text-[13px] dark:text-[#c9d1d9] font-medium cursor-pointer">
                <input type="checkbox" id="filter-ambil" checked class="mr-2 rounded text-[#1f6feb] focus:ring-[#1f6feb] w-4 h-4 cursor-pointer" onchange="filterKalender()"> 
                <div class="w-3 h-3 rounded bg-[#1f6feb] mx-2"></div> Jadwal Pengambilan/Kirim
            </label>
        </div>
        <div class="text-[12px] text-gray-500 dark:text-[#8b949e] flex items-center">
            <i class="fa-solid fa-circle-info mr-2"></i> Klik kotak tanggal untuk rincian tugas hari tersebut.
        </div>
    </div>

    <div class="bg-white dark:bg-[#161b22] border border-gray-200 dark:border-[#30363d] rounded-xl overflow-hidden shadow-sm">
        
        <div class="flex justify-between items-center p-4 border-b dark:border-[#30363d] bg-gray-50 dark:bg-[#0d1117]/30">
            <a href="?page=calendar-operasional&m=<?= str_pad($prevMonth, 2, '0', STR_PAD_LEFT) ?>&y=<?= $prevYear ?>" class="text-gray-400 hover:text-gray-900 dark:hover:text-white p-2 transition-colors"><i class="fa-solid fa-chevron-left"></i></a>
            <h2 class="text-lg font-bold text-gray-800 dark:text-[#c9d1d9] uppercase tracking-wider"><?= $namaBulan . " " . $year ?></h2>
            <a href="?page=calendar-operasional&m=<?= str_pad($nextMonth, 2, '0', STR_PAD_LEFT) ?>&y=<?= $nextYear ?>" class="text-gray-400 hover:text-gray-900 dark:hover:text-white p-2 transition-colors"><i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="grid grid-cols-7 border-b dark:border-[#30363d] text-center bg-gray-50 dark:bg-[#0d1117]/30">
            <?php foreach(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $day): ?>
                <div class="py-3 text-[12px] font-bold text-gray-500 dark:text-[#8b949e] uppercase"><?= $day ?></div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-7 border-l border-t-0 dark:border-[#30363d]">
            <?php 
                // Render blank boxes for days before 1st of month
                for($i = 0; $i < $dayOfWeek; $i++) { 
                    echo "<div class='min-h-[130px] border-r border-b border-gray-100 dark:border-[#30363d] p-2 bg-gray-50 dark:bg-[#0d1117]/50'></div>"; 
                }

                // Render actual days
                for($day = 1; $day <= $numberDays; $day++) {
                    $currentDate = $year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $isToday = ($currentDate == date('Y-m-d')) ? 'bg-blue-50 dark:bg-[#58a6ff]/10 border-blue-200 dark:border-[#58a6ff]/30 shadow-inner' : 'bg-white dark:bg-[#161b22] border-gray-100 dark:border-[#30363d]';
                    $textToday = ($currentDate == date('Y-m-d')) ? 'text-[#1f6feb] dark:text-[#58a6ff] font-extrabold text-[16px]' : 'text-gray-700 dark:text-[#c9d1d9] font-bold text-[14px]';

                    echo "<div onclick=\"bukaDetailHarian('$currentDate')\" class='min-h-[130px] border-r border-b p-2 hover:bg-gray-100 dark:hover:bg-[#21262d] transition-colors cursor-pointer relative group $isToday'>";
                    echo "<div class='$textToday mb-2'>$day</div>";

                    // BADGE PENGAMBILAN (BIRU)
                    if(isset($event_ambil[$currentDate])) {
                        echo "<div class='event-badge badge-ambil bg-[#1f6feb] text-white text-[10px] font-bold px-2 py-1.5 rounded mb-1.5 shadow-sm truncate border border-[#1f6feb] hover:bg-[#388bfd]'>";
                        echo "<i class='fa-solid fa-box-open mr-1 opacity-80'></i> " . $event_ambil[$currentDate]['jml_order'] . " Ambil (" . $event_ambil[$currentDate]['total_meter'] . "m)";
                        echo "</div>";
                    }

                    // BADGE JASA TANAM (HIJAU / MERAH JIKA OVERLOAD)
                    if(isset($event_tanam[$currentDate])) {
                        $is_overload = $event_tanam[$currentDate]['total_meter'] >= 50; // CONTOH: Jika lebih dari 50 meter per hari, warna merah peringatan
                        $bgTanam = $is_overload ? 'bg-[#f85149] border-[#f85149]' : 'bg-[#238636] border-[#2ea043] hover:bg-[#2ea043]';
                        $iconTanam = $is_overload ? 'fa-triangle-exclamation' : 'fa-person-digging';
                        
                        echo "<div class='event-badge badge-tanam $bgTanam text-white text-[10px] font-bold px-2 py-1.5 rounded mb-1.5 shadow-sm truncate border'>";
                        echo "<i class='fa-solid $iconTanam mr-1 opacity-80'></i> " . $event_tanam[$currentDate]['jml_order'] . " Tanam (" . $event_tanam[$currentDate]['total_meter'] . "m)";
                        echo "</div>";
                    }

                    echo "</div>";
                }

                // Render blank boxes for remaining days
                $remainingDays = (7 - (($dayOfWeek + $numberDays) % 7)) % 7;
                for($i = 0; $i < $remainingDays; $i++) { 
                    echo "<div class='min-h-[130px] border-r border-b border-gray-100 dark:border-[#30363d] p-2 bg-gray-50 dark:bg-[#0d1117]/50'></div>"; 
                }
            ?>
        </div>
    </div>
</div>

<div id="modal-harian" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 hidden backdrop-blur-sm transition-opacity duration-300">
    <div class="bg-white dark:bg-[#161b22] rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden border border-gray-200 dark:border-[#30363d] flex flex-col max-h-[90vh]">
        
        <div class="px-6 py-4 border-b border-gray-200 dark:border-[#30363d] flex justify-between items-center bg-gray-50 dark:bg-[#0d1117] shrink-0">
            <h3 class="text-[16px] font-bold text-gray-900 dark:text-white flex items-center">
                <i class="fa-regular fa-calendar-check mr-2 text-[#58a6ff]"></i> 
                Tugas Harian: <span id="modal-title-date" class="ml-1 text-[#58a6ff]"></span>
            </h3>
            <button type="button" onclick="tutupDetailHarian()" class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <div class="p-6 overflow-y-auto custom-scrollbar space-y-6 flex-1">
            
            <div>
                <h4 class="text-[13px] font-bold text-[#1f6feb] dark:text-[#58a6ff] mb-3 flex items-center border-b border-gray-200 dark:border-[#30363d] pb-2 uppercase tracking-wide">
                    <i class="fa-solid fa-box-open mr-2"></i> Jadwal Pengambilan / Kirim Bibit Reguler
                </h4>
                <div id="list-ambil" class="space-y-3">
                    </div>
            </div>

            <div class="mt-6">
                <h4 class="text-[13px] font-bold text-[#2ea043] dark:text-[#3fb950] mb-3 flex items-center border-b border-gray-200 dark:border-[#30363d] pb-2 uppercase tracking-wide">
                    <i class="fa-solid fa-person-digging mr-2"></i> Jadwal Turun Sawah (Jasa Tanam)
                </h4>
                <div id="list-tanam" class="space-y-3">
                    </div>
            </div>

        </div>

        <div class="p-4 bg-gray-50 dark:bg-[#0d1117] border-t border-gray-200 dark:border-[#30363d] shrink-0 flex justify-end">
            <button onclick="tutupDetailHarian()" class="bg-gray-200 hover:bg-gray-300 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-800 dark:text-[#c9d1d9] px-6 py-2 rounded-md text-[13px] font-bold transition-colors">Tutup Jendela</button>
        </div>
    </div>
</div>

<script>
    // Ambil Data Order dari PHP ke JavaScript
    const semuaOrder = <?= json_encode($semua_order) ?>;
    
    const namaBulanIndo = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    const namaHariIndo = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];

    function formatTanggalIndo(tglString) {
        let d = new Date(tglString);
        return namaHariIndo[d.getDay()] + ", " + d.getDate() + " " + namaBulanIndo[d.getMonth()] + " " + d.getFullYear();
    }

    function filterKalender() {
        let showTanam = document.getElementById('filter-tanam').checked;
        let showAmbil = document.getElementById('filter-ambil').checked;

        document.querySelectorAll('.badge-tanam').forEach(el => el.style.display = showTanam ? 'block' : 'none');
        document.querySelectorAll('.badge-ambil').forEach(el => el.style.display = showAmbil ? 'block' : 'none');
    }

    function bukaDetailHarian(tgl_klik) {
        document.getElementById('modal-title-date').innerText = formatTanggalIndo(tgl_klik);
        
        let containerAmbil = document.getElementById('list-ambil');
        let containerTanam = document.getElementById('list-tanam');
        
        let htmlAmbil = '';
        let htmlTanam = '';
        let countAmbil = 0;
        let countTanam = 0;

        semuaOrder.forEach(o => {
            // Render Badge Status Dinamis
            let badgeStatus = '';
            let st = o.status.toLowerCase();
            if(st === 'lunas') badgeStatus = '<span class="bg-[#f85149]/10 text-[#f85149] border border-[#f85149]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">Lunas</span>';
            else if(st === 'selesai' || st === 'diambil') badgeStatus = '<span class="bg-[#3fb950]/10 text-[#3fb950] border border-[#3fb950]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">' + o.status + '</span>';
            else badgeStatus = '<span class="bg-[#d29922]/10 text-[#d29922] border border-[#d29922]/30 px-2 py-0.5 rounded text-[10px] font-bold capitalize">' + o.status + '</span>';

            // Filter Jasa Tanam
            if (o.tipe_order === 'Jasa Tanam' && o.tgl_tanam === tgl_klik) {
                countTanam++;
                htmlTanam += `
                    <div class="bg-white dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex justify-between items-center shadow-sm">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5">
                                <p class="text-[14px] font-bold text-gray-900 dark:text-white">${o.nama_customer}</p>
                                ${badgeStatus}
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-[#8b949e] mb-1"><i class="fa-solid fa-map-location-dot mr-1"></i> ${o.lokasi_sawah}</p>
                            <p class="text-[11px] font-bold text-[#3fb950]"><i class="fa-solid fa-seedling mr-1"></i> ${parseFloat(o.panjang_m)} Meter Lahan</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[10px] text-gray-400 mb-2">Nota: ${o.no_order}</p>
                            <a href="?page=jasa-tanam&tab=data&highlight=${o.no_order}" class="bg-gray-100 hover:bg-gray-200 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] px-3 py-1.5 rounded text-[11px] font-bold transition-colors border border-gray-200 dark:border-[#30363d]">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Cek Order
                            </a>
                        </div>
                    </div>`;
            }

            // Filter Pengambilan (Reguler)
            if ((!o.tipe_order || o.tipe_order === 'Reguler') && o.tgl_ambil === tgl_klik) {
                countAmbil++;
                let strId = o.no_order ? o.no_order : o.id; // Fallback untuk reguler lama
                htmlAmbil += `
                    <div class="bg-white dark:bg-[#0d1117] border border-gray-200 dark:border-[#30363d] rounded-lg p-4 flex justify-between items-center shadow-sm">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1.5">
                                <p class="text-[14px] font-bold text-gray-900 dark:text-white">${o.nama_customer}</p>
                                ${badgeStatus}
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-[#8b949e] mb-1"><i class="fa-solid fa-leaf mr-1"></i> Varietas: <strong>${o.varietas || '-'}</strong></p>
                            <p class="text-[11px] font-bold text-[#1f6feb]"><i class="fa-solid fa-ruler-horizontal mr-1"></i> ${parseFloat(o.panjang_m)} Meter Bibit</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-[10px] text-gray-400 mb-2">Ref ID: #${strId}</p>
                            <a href="?page=order-bibit&tab=data&highlight=${o.id}" class="bg-gray-100 hover:bg-gray-200 dark:bg-[#21262d] dark:hover:bg-[#30363d] text-gray-700 dark:text-[#c9d1d9] px-3 py-1.5 rounded text-[11px] font-bold transition-colors border border-gray-200 dark:border-[#30363d]">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Cek Order
                            </a>
                        </div>
                    </div>`;
            }
        });

        if(countAmbil === 0) {
            htmlAmbil = '<div class="text-[12px] text-gray-500 dark:text-[#8b949e] italic p-4 border border-dashed border-gray-200 dark:border-[#30363d] rounded-lg text-center bg-gray-50 dark:bg-[#0d1117]/50">Tidak ada jadwal pengambilan bibit hari ini.</div>';
        }
        if(countTanam === 0) {
            htmlTanam = '<div class="text-[12px] text-gray-500 dark:text-[#8b949e] italic p-4 border border-dashed border-gray-200 dark:border-[#30363d] rounded-lg text-center bg-gray-50 dark:bg-[#0d1117]/50">Tidak ada jadwal pekerja turun ke sawah hari ini.</div>';
        }

        containerAmbil.innerHTML = htmlAmbil;
        containerTanam.innerHTML = htmlTanam;

        // Hanya tampilkan jika setidaknya ada 1 event (mencegah klik tanggal kosong)
        if(countAmbil > 0 || countTanam > 0) {
            document.getElementById('modal-harian').classList.remove('hidden');
        } else {
            // Bisa tambahkan toast notification "Tidak ada jadwal di tanggal ini"
        }
    }

    function tutupDetailHarian() { 
        document.getElementById('modal-harian').classList.add('hidden'); 
    }
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
</style>