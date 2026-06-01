<?php
// Keluar dari folder cetak/ lalu masuk ke components/
include '../components/koneksi.php';

// Ambil ID dari URL
$id_order = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
if(!$id_order) die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h1>404 - Nota Tidak Ditemukan</h1><p>Nomor invoice tidak valid.</p></div>");

// =========================================================================================
// FUNGSI FORMAT RUPIAH & TERBILANG
// =========================================================================================
function formatRp($angka){ return number_format($angka, 0, ',', '.'); }

function penyebut($nilai) {
    $nilai = abs((int)$nilai);
    $huruf = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $temp = "";
    if ($nilai < 12) {
        $temp = " ". $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = penyebut($nilai - 10). " Belas";
    } else if ($nilai < 100) {
        $temp = penyebut($nilai / 10)." Puluh". penyebut($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " Seratus" . penyebut($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = penyebut($nilai / 100) . " Ratus" . penyebut($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " Seribu" . penyebut($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = penyebut($nilai / 1000) . " Ribu" . penyebut($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = penyebut($nilai / 1000000) . " Juta" . penyebut($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = penyebut($nilai / 1000000000) . " Milyar" . penyebut(fmod($nilai, 1000000000));
    }
    return $temp;
}

function terbilang($nilai) {
    if($nilai == 0) return "Nol";
    $hasil = trim(penyebut($nilai));
    return $hasil;
}

// =========================================================================================
// LOGIKA CERDAS PENCARIAN DATA MULTI-ITEM PUPUK
// =========================================================================================
$data = null; $items = []; $tgl_indo = ''; 
$grand_harga_dasar = 0; $grand_diskon = 0; $grand_ongkir = 0; 
$total_akhir = 0; $grand_dp = 0; $grand_sisa = 0;

$q = mysqli_query($conn, "SELECT * FROM order_pupuk WHERE no_order='$id_order'");
while($r = mysqli_fetch_assoc($q)) {
    if(!$data) {
        $data = $r; // Ambil identitas dari baris pertama saja
        $tgl_indo = date('d F Y', strtotime($r['tgl_order']));
    }
    
    $hd_item = $r['harga_satuan'] * $r['qty'];
    $diskon_item = ($hd_item * ($r['diskon_persen']/100)) + $r['diskon_nominal'];
    $ongkir_item = $r['ongkir'];
    
    // Akumulasi Keseluruhan Nota
    $grand_harga_dasar += $hd_item;
    $grand_diskon += $diskon_item;
    $grand_ongkir += $ongkir_item;
    $total_akhir += $r['total_harga'];
    $grand_dp += $r['dp_dibayar'];
    
    // Subtotal baris = murni Harga Dasar - Diskon (Ongkir tidak dimasukkan ke baris tabel)
    $subtotal_baris = $hd_item - $diskon_item;

    $items[] = [
        'nama' => htmlspecialchars($r['nama_barang']),
        'qty' => $r['qty'],
        'harga_satuan' => $r['harga_satuan'],
        'diskon' => $diskon_item,
        'jumlah_murni' => $subtotal_baris
    ];
}
$grand_sisa = $total_akhir - $grand_dp;

// --- SUNTIKAN ANTI-BUG PEMBULATAN (ROUNDING) ---
// Membulatkan puluhan terdekat untuk menghilangkan sisa Rp 1 - Rp 9 akibat pecahan proporsional
$grand_harga_dasar = round($grand_harga_dasar, -1);
$grand_diskon = round($grand_diskon, -1);
$grand_ongkir = round($grand_ongkir, -1);
$total_akhir = round($total_akhir, -1);
$grand_dp = round($grand_dp, -1);
$grand_sisa = round($grand_sisa, -1);

if(!$data || count($items) == 0) die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h1>Data Tidak Ditemukan</h1><p>Pastikan nomor invoice benar dan keranjang tidak kosong.</p></div>");

// LOGIKA PEMBUATAN NAMA FILE DOWNLOAD (PDF)
$safe_id_order = str_replace('/', '-', $id_order);
$nama_file = ucwords(trim($data['nama_customer'] ?? ''));
$alamat_file = ucwords(trim($data['alamat'] ?? ''));

$judul_dokumen = "Invoice " . $safe_id_order . " - " . $nama_file;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($judul_dokumen) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;0,900;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Roboto', sans-serif; margin: 0; padding: 0; background: #525659; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color: #222; }
        
        .no-print { text-align:center; padding:15px; background: #222; position: sticky; top:0; z-index: 999; border-bottom: 4px solid #35B04A; }
        .btn-print { padding:10px 25px; background:#35B04A; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold; font-size:14px; margin-right:10px; font-family: inherit; }
        .btn-close { padding:10px 25px; background:#fff; color:#333; border:none; border-radius:4px; cursor:pointer; font-weight:bold; font-size:14px; font-family: inherit; }

        /* KANVAS A4 */
        .a4-container {
            width: 210mm; min-height: 297mm; margin: 20px auto; background-color: white;
            position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 15mm 15mm; box-sizing: border-box; 
        }

        /* WATERMARK (Path ../assets/) */
        .a4-container::before {
            content: ""; position: absolute; top: 0; left: 0; bottom: 0; width: 180px; 
            background-image: url('../assets/watermark-daun.png'); background-position: left top; background-repeat: repeat-y; background-size: 110px auto; 
            opacity: 0.4; z-index: 0; pointer-events: none;
        }

        .header-wrap, .info-box, table, .calc-container, .footer-wrap { position: relative; z-index: 1; }

        @media print {
            body { background: white; }
            .a4-container { margin: 0; box-shadow: none; padding: 10mm 10mm; }
            .no-print { display: none !important; }
        }
        
        .color-primary { color: #0F314F; }
        .color-accent { color: #35B04A; }
        
        .header-wrap { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-top: 10px;}
        .brand-logo { width: 260px; margin-bottom: 8px; } 
        .brand-sub { font-size: 13px; color: #555; line-height: 1.5; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { font-size: 36px; font-weight: 900; margin: 0 0 5px 0; letter-spacing: 1px; }
        .invoice-title p { font-size: 14px; margin: 0; font-weight: 500; }
        
        .info-box { display: flex; justify-content: space-between; background: transparent; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .info-group { width: 48%; }
        .info-label { font-size: 11px; color: #666; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px;}
        .info-value { font-size: 14px; font-weight: 700; color: #111; margin-bottom: 12px; }
        .info-value:last-child { margin-bottom: 0; }
        
        /* TABEL BARANG */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: transparent; }
        th { background-color: #0F314F; color: white; padding: 10px 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #0F314F; text-align: center;}
        td { padding: 10px 8px; font-size: 12px; border: 1px solid #ddd; border-left: 1px solid #0F314F; border-right: 1px solid #0F314F; background-color: transparent; color: #222;}
        tr:last-child td { border-bottom: 1px solid #0F314F; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        
        /* KALKULASI TOTAL */
        .calc-container { display: flex; flex-direction: column; align-items: flex-end; margin-bottom: 30px; }
        .calc-table { width: 400px; border-collapse: collapse; }
        .calc-table td { padding: 8px 12px; border: none; font-size: 14px; color: #222;}
        .calc-table tr.border-top td { border-top: 1px solid #eee; }
        .calc-table tr.grand-total td { background-color: #f0fdf4; font-size: 18px; font-weight: 900; border-top: 2px solid #35B04A; border-bottom: 2px solid #35B04A; color: #111;}
        
        /* TERBILANG */
        .terbilang-right { text-align: right; font-style: italic; font-weight: 700; font-size: 13px; color: #222; margin-top: 8px; max-width: 450px; }
        
        /* TANDA TANGAN & STEMPEL (Disempurnakan) */
        .footer-wrap { display: flex; justify-content: flex-end; padding-top: 10px;}
        .signature { width: 220px; text-align: center; }
        .sig-title { font-size: 13px; font-weight: 600; margin-bottom: 0px; color: #222; position: relative; z-index: 10;}
        .sig-name { font-size: 15px; font-weight: 800; position: relative; z-index: 10; margin-top: 0px; color: #222;}

        img { max-width: 100%; height: auto; }
        
        /* Container diturunkan tingginya agar gambar melebar dan menyentuh teks */
        .ttd-img-container { position: relative; height: 75px; display: flex; align-items: center; justify-content: center; left: -10px; }
        
        /* Lebar cap & ttd diperbesar */
        .img-cap { position: absolute; width: 140px; z-index: 1; opacity: 0.85; margin-right: 15px; mix-blend-mode: multiply; }
        .img-ttd { position: absolute; width: 145px; z-index: 2; margin-left: 20px; margin-top: -5px; }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print"><i class="fa-solid fa-print"></i> CETAK INVOICE</button>
        <button onclick="window.close()" class="btn-close">Tutup Halaman</button>
    </div>

    <div class="a4-container">
        
        <!-- HEADER -->
        <div class="header-wrap">
            <div>
                <!-- LOGO DENGAN ../assets/ -->
                <img src="../assets/Logo-Std.png" class="brand-logo" alt="Logo Ponco Tani">
                <div class="brand-sub">
                    Desa Bodeh, Kec. Bodeh, Kab. Pemalang<br>
                    Telp/WA: 0851-2259-0637
                </div>
            </div>
            <div class="invoice-title">
                <h2 class="color-accent">INVOICE</h2>
                <p class="color-primary"><?= isset($data['no_order']) ? $data['no_order'] : $id_order ?></p>
            </div>
        </div>

        <!-- INFO PELANGGAN -->
        <div class="info-box">
            <div class="info-group">
                <div class="info-label">DITAGIHKAN KEPADA:</div>
                <div class="info-value" style="font-size: 16px; color: #0F314F;"><?= ucwords(htmlspecialchars($data['nama_customer'])) ?></div>
                <div class="info-label">NO. HANDPHONE:</div>
                <div class="info-value"><?= htmlspecialchars($data['no_hp'] ?: '-') ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">TANGGAL INVOICE:</div>
                <div class="info-value"><?= $tgl_indo ?></div>
                <div class="info-label">ALAMAT PENGIRIMAN:</div>
                <div class="info-value"><?= htmlspecialchars($data['alamat']) ?></div>
            </div>
        </div>

        <!-- TABEL BARANG (TANPA ONGKIR) -->
        <table>
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="35%" class="text-left">DESKRIPSI PRODUK</th>
                    <th width="10%" class="text-center">QTY</th>
                    <th width="18%" class="text-right">HARGA SATUAN</th>
                    <th width="14%" class="text-right">DISKON</th>
                    <th width="18%" class="text-right">JUMLAH (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach($items as $item): ?>
                <tr>
                    <td class="text-center" style="vertical-align: top; color: #222;"><?= $no++ ?></td>
                    <td class="text-left" style="vertical-align: top; color: #222;">
                        <b><?= $item['nama'] ?></b>
                    </td>
                    <td class="text-center font-bold" style="vertical-align: top; color: #222;"><?= $item['qty'] ?></td>
                    <td class="text-right" style="vertical-align: top; color: #222;"><?= formatRp($item['harga_satuan']) ?></td>
                    <td class="text-right" style="vertical-align: top; color: #222;"><?= $item['diskon'] > 0 ? formatRp($item['diskon']) : '-' ?></td>
                    <td class="text-right font-bold" style="vertical-align: top; color: #222;"><?= formatRp($item['jumlah_murni']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- KALKULASI KEUANGAN AKHIR (SEMUA WARNA HITAM & PEMBULATAN ANTI-BUG) -->
        <div class="calc-container">
            <table class="calc-table">
                <tr>
                    <td class="text-right">Total Harga Dasar</td>
                    <td class="text-right">Rp <?= formatRp($grand_harga_dasar) ?></td>
                </tr>
                
                <?php if($grand_diskon > 0): ?>
                <tr>
                    <td class="text-right">Total Diskon</td>
                    <td class="text-right font-bold">- Rp <?= formatRp($grand_diskon) ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if($grand_ongkir > 0): ?>
                <tr>
                    <td class="text-right">Total Biaya Kirim</td>
                    <td class="text-right font-bold">+ Rp <?= formatRp($grand_ongkir) ?></td>
                </tr>
                <?php endif; ?>

                <!-- BLOK LOGIKA: JIKA MASIH NGUTANG -->
                <?php if($grand_sisa > 0): ?>
                    <tr class="border-top">
                        <td class="text-right font-bold" style="padding-top: 12px;">Total Akumulasi Tagihan</td>
                        <td class="text-right font-bold" style="padding-top: 12px;">Rp <?= formatRp($total_akhir) ?></td>
                    </tr>
                    <tr>
                        <td class="text-right">Telah Dibayar (DP)</td>
                        <td class="text-right font-bold">- Rp <?= formatRp($grand_dp) ?></td>
                    </tr>
                    <tr class="grand-total">
                        <td class="text-right uppercase">TOTAL SISA BAYAR</td>
                        <td class="text-right font-bold">Rp <?= formatRp($grand_sisa) ?></td>
                    </tr>
            </table>
            <div class="terbilang-right">
                Terbilang (Sisa Bayar): <?= ucwords(terbilang($grand_sisa)) ?> Rupiah
            </div>

                <!-- BLOK LOGIKA: JIKA SUDAH LUNAS 100% -->
                <?php else: ?>
                    <tr class="border-top">
                        <td class="text-right font-bold" style="padding-top: 12px;">Total Akumulasi Tagihan</td>
                        <td class="text-right font-bold" style="padding-top: 12px;">Rp <?= formatRp($total_akhir) ?></td>
                    </tr>
                    <tr class="grand-total">
                        <td class="text-right uppercase">TOTAL TELAH DIBAYAR (LUNAS)</td>
                        <td class="text-right font-bold">Rp <?= formatRp($grand_dp) ?></td>
                    </tr>
            </table>
            <div class="terbilang-right">
                Terbilang: <?= ucwords(terbilang($total_akhir)) ?> Rupiah
            </div>
                <?php endif; ?>
        </div>

        <!-- FOOTER & TANDA TANGAN -->
        <div class="footer-wrap">
            <div class="signature">
                <div class="sig-title">Hormat Kami,</div>
                <div class="ttd-img-container">
                    <!-- STEMPEL DAN TTD DENGAN ../assets/ & UKURAN LEBIH BESAR -->
                    <img src="../assets/stempel.png" alt="" class="img-cap" onerror="this.style.display='none'">
                    <img src="../assets/ttd.png" alt="" class="img-ttd" onerror="this.style.display='none'">
                </div>
                <div class="sig-name">Ponco Widodo</div>
            </div>
        </div>

    </div>
</body>
</html>