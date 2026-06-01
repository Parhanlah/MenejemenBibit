<?php
// Menerima parameter halaman dari URL (misal: index.php?page=order-bibit)
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php include 'components/head.php'; ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 transition-colors duration-200">

    <div class="flex h-screen overflow-hidden">
        
        <!-- Panggil Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <main class="flex-1 flex flex-col min-w-0">
            
            <!-- Panggil Navbar -->
            <?php include 'components/navbar.php'; ?>

            <!-- Area Konten Dinamis -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 overflow-x-hidden">
                <?php 
                    // Logika untuk memuat halaman sesuai menu yang diklik
                    $file_path = "pages/" . $page . ".php";
                    
                    if (file_exists($file_path)) {
                        include $file_path;
                    } else {
                        echo "<div class='text-center text-red-500 mt-10'>Halaman <b>$page</b> tidak ditemukan (404).</div>";
                    }
                ?>
            </div>

        </main>
    </div>

    <?php include 'components/footer.php'; ?>
</body>
</html>