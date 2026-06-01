<script>
    // ==========================================
    // 1. LOGIKA DARK MODE TEMA
    // ==========================================
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const htmlElement = document.documentElement;

    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        htmlElement.classList.add('dark');
        if(themeIcon) themeIcon.classList.replace('fa-moon', 'fa-sun');
    }

    if(themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            htmlElement.classList.toggle('dark');
            if (htmlElement.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            } else {
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('fa-sun', 'fa-moon');
            }
        });
    }

    // ==========================================
    // 2. LOGIKA SIDEBAR SMART TOGGLE (MOBILE & DESKTOP)
    // ==========================================
    const btnMenu = document.getElementById('mobile-menu-btn');
    const btnClose = document.getElementById('close-sidebar-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    function toggleSidebar() {
        // Cek Lebar Layar (Layar >= 768px berarti Laptop/Tablet Besar)
        if (window.innerWidth >= 768) {
            // LOGIKA LAPTOP: Sembunyikan sidebar dengan menarik margin kirinya
            sidebar.classList.toggle('md:-ml-64');
        } else {
            // LOGIKA HP/MOBILE: Munculkan dengan efek geser (Slide) & Overlay Gelap
            sidebar.classList.toggle('-translate-x-full');
            
            if (overlay.classList.contains('hidden')) {
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10); // Animasi fade in
            } else {
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300); // Tunggu animasi selesai
            }
        }
    }

    // Event Listener
    if(btnMenu) btnMenu.addEventListener('click', toggleSidebar);
    if(btnClose) btnClose.addEventListener('click', toggleSidebar);
    if(overlay) overlay.addEventListener('click', toggleSidebar); // Tutup sidebar saat background gelap diklik

</script>