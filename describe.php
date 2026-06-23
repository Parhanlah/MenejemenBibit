<?php
include 'c:/xampp/htdocs/Poncotaniweb/components/koneksi.php';

$tables = ['order_bibit', 'order_pupuk'];
foreach($tables as $t) {
    echo "TABLE: $t\n";
    $q = mysqli_query($conn, "DESCRIBE $t");
    while($r = mysqli_fetch_assoc($q)) {
        echo "  {$r['Field']} - {$r['Type']}\n";
    }
}
?>
