<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DISTRICARNES - Productos-Categorias </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="shortcut icon" href="./assets/icon/image-removebg-preview sin fondo (1).ico" type="image/x-icon">
    <link rel="stylesheet" href="./static/css/header_en_general.css" />
    <link rel="stylesheet" href="./static/css/productos.css" />
    <link rel="stylesheet" href="./static/css/chatbot.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="./static/css/responsive.css" />

</head>

<body class=" bg-black text-white ">
    <?php
    // Inicialización de conexión y parámetros para filtros/paginación
    require_once __DIR__ . '/backend/php/conexion.php';
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
    $subcategoria = isset($_GET['subcategoria']) ? trim($_GET['subcategoria']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 12;
    $offset = ($page - 1) * $perPage;
    $baseParams = [];
    if ($q !== '') $baseParams['q'] = $q;
    if ($categoria !== '') $baseParams['categoria'] = $categoria;
    if ($subcategoria !== '') $baseParams['subcategoria'] = $subcategoria;

    // Utilidades para derivar categorías y normalizar texto
    function norm($s)
    {
        return mb_strtolower(trim((string)$s), 'UTF-8');
    }
    function deriveCategoryFromName($name)
    {
        $n = norm($name);
        if (preg_match('/(res|vaca|ternera|carne\s*de\s*res)/i', $n)) return 'res';
        if (preg_match('/(cerdo|puerco|chancho)/i', $n)) return 'cerdo';
        if (preg_match('/(pollo|gallina|pechuga|muslo)/i', $n)) return 'pollo';
        if (preg_match('/(pescado|robalo|bagre|mojarra|tilapia)/i', $n)) return 'pescado';
        return 'otros';
    }
    function imageForCategory($cat)
    {
        // Fallback a una imagen por categoría
        switch ($cat) {
            case 'cerdo':
                return base_prefix() . '/static/images/lomo_de_cerdo.jpeg';
            case 'res':
                return base_prefix() . '/static/images/lomo fresco.jpg';
            case 'pollo':
                return base_prefix() . '/static/images/imagenhero1.jpeg';
            case 'pescado':
                return base_prefix() . '/static/images/filete_de_robalo.jpg';
            default:
                return base_prefix() . '/static/images/image.png';
        }
    }

    // Normalización de rutas a formato web
    function base_prefix()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $base = rtrim(dirname($script), '/');
        return ($base && $base !== '/') ? $base : '';
    }
    function normalize_web_path($fsPath, $rootDir)
    {
        $p = str_replace('\\', '/', (string)$fsPath);
        $root = str_replace('\\', '/', (string)$rootDir);
        if (strpos($p, $root) === 0) {
            $p = substr($p, strlen($root));
        }
        if ($p !== '' && $p[0] !== '/') {
            $p = '/' . $p;
        }
        $prefix = base_prefix();
        if ($prefix && strpos($p, $prefix . '/') !== 0) {
            $p = $prefix . $p;
        }
        return $p;
    }

    // Intentar encontrar imagen por coincidencia de nombre en static/images/products o static/images
    function find_fallback_image($name, $imagesDir, $imagesProductsDir, $rootDir)
    {
        $lower = trim(mb_strtolower((string)$name));
        if ($lower === '') return normalize_web_path($imagesDir . '/image.png', $rootDir);
        $strip = preg_replace('/[^a-z0-9]+/i', '', $lower);
        $exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $dirs = [$imagesProductsDir, $imagesDir];
        foreach ($dirs as $dir) {
            foreach ($exts as $ext) {
                foreach (glob($dir . DIRECTORY_SEPARATOR . '*.' . $ext) as $file) {
                    $base = mb_strtolower(pathinfo($file, PATHINFO_FILENAME));
                    $baseStripped = preg_replace('/[^a-z0-9]+/i', '', $base);
                    if ($baseStripped === $strip || strpos($baseStripped, $strip) !== false) {
                        return normalize_web_path($file, $rootDir);
                    }
                }
            }
        }
        $generic = $imagesDir . DIRECTORY_SEPARATOR . 'image.png';
        return file_exists($generic) ? normalize_web_path($generic, $rootDir) : imageForCategory('otros');
    }
    // Normaliza y obtiene la imagen desde la fila de BD, si existe
    function imageFromRow(array $row): ?string
    {
        $candidates = ['imagen', 'image', 'imagen_url', 'image_url', 'foto', 'imagen_producto', 'url_imagen'];
        $img = null;
        foreach ($candidates as $c) {
            if (isset($row[$c]) && trim((string)$row[$c]) !== '') {
                $img = (string)$row[$c];
                break;
            }
        }
        if ($img === null) return null;
        $img = str_replace('\\', '/', $img);
        // Si es URL absoluta, devolver tal cual
        if (preg_match('#^https?://#i', $img)) return $img;
        // Directorios base
        $rootDir = __DIR__;
        $imagesDir = $rootDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'images';
        $imagesProductsDir = $imagesDir . DIRECTORY_SEPARATOR . 'products';

        // Caso 1: contiene 'static/images'
        $pos = strpos($img, 'static/images');
        if ($pos !== false) {
            $webPath = base_prefix() . '/' . substr($img, $pos);
            $fsCandidate = $rootDir . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
            if (file_exists($fsCandidate)) {
                return $webPath;
            }
            // Intentar con basename en directorios conocidos
            $base = basename($img);
            $try1 = $imagesProductsDir . DIRECTORY_SEPARATOR . $base;
            $try2 = $imagesDir . DIRECTORY_SEPARATOR . $base;
            if (file_exists($try1)) return normalize_web_path($try1, $rootDir);
            if (file_exists($try2)) return normalize_web_path($try2, $rootDir);
            return null;
        }

        // Caso 2: relativo o solo nombre de archivo
        $base = basename($img);
        $try1 = $imagesProductsDir . DIRECTORY_SEPARATOR . $base;
        $try2 = $imagesDir . DIRECTORY_SEPARATOR . $base;
        $try3 = $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $img), DIRECTORY_SEPARATOR);
        if (file_exists($try1)) return normalize_web_path($try1, $rootDir);
        if (file_exists($try2)) return normalize_web_path($try2, $rootDir);
        if (file_exists($try3)) return normalize_web_path($try3, $rootDir);
        // No encontrado: devolver null para activar fallback
        return null;
    }

    // Cargar todos los productos (según esquema real de BD)
    $allProducts = [];
    if ($res = $conexion->query("SELECT * FROM producto ORDER BY id_producto DESC")) {
        while ($r = $res->fetch_assoc()) {
            $allProducts[] = $r;
        }
        $res->free();
    }

    // Derivar conteos de categorías y etiquetas desde nombres
    $categoryCounts = [];
    $tagCounts = [];
    foreach ($allProducts as $p) {
        $cat = deriveCategoryFromName($p['nombre'] ?? '');
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;

        $words = preg_split('/[\s\-_,.]+/', norm($p['nombre'] ?? ''));
        foreach ($words as $w) {
            if (strlen($w) >= 4 && !in_array($w, ['res', 'cerdo', 'pollo', 'pescado', 'otros'])) {
                $tagCounts[$w] = ($tagCounts[$w] ?? 0) + 1;
            }
        }
    }
    arsort($tagCounts);
    // Limitar a las 10 etiquetas más frecuentes
    $tagCounts = array_slice($tagCounts, 0, 10, true);

    // Filtrar en memoria según parámetros actuales
    $filtered = [];
    $qNorm = norm($q);
    $catNorm = norm($categoria);
    $subNorm = norm($subcategoria);
    foreach ($allProducts as $p) {
        $name = $p['nombre'] ?? '';
        $nameNorm = norm($name);
        if ($q !== '' && strpos($nameNorm, $qNorm) === false) continue;
        $cat = deriveCategoryFromName($name);
        if ($categoria !== '' && $cat !== $catNorm) continue;
        if ($subcategoria !== '' && strpos($nameNorm, $subNorm) === false) continue;
        $p['__cat'] = $cat;
        $filtered[] = $p;
    }

    $total = count($filtered);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $pageProducts = array_slice($filtered, $offset, $perPage);
    $startDisplay = ($total > 0) ? ($offset + 1) : 0;
    $endDisplay = min($total, $offset + count($pageProducts));
    ?>
    <!-- Header -->
    <header class="header ">
        <div class="header-content ">
            <div class="logo ">
                <img src="./assets/icon/LOGO-DISTRICARNES.png " alt="DISTRICARNES Logo ">
            </div>

            <!-- Buscador central estilo ML y pill promocional -->
            <div class="ml-search">
                <form action="productos.php" method="get">
                    <input type="search" name="q" id="site-search" placeholder="Buscar productos, marcas y más…" />
                    <button type="submit" aria-label="Buscar"><i class="fas fa-search"></i></button>
                </form>
            </div>


            <!-- Enlaces rápidos + botón de carrito (siempre visibles) -->
            <div id="quickLinks" class="ml-actions">
                <a id="cartButton" class="ml-icon-btn ml-icon-bounce" href="./carrito-de-compras/index.html" aria-label="Carrito">
                    <i class="bi bi-cart"></i>
                    <span class="ml-badge" id="cartCount">0</span>
                </a>
                <!-- Botones de acceso y registro -->
                <div id="authButtons" class="flex gap-3">
                    <a href="./login/login.html" class="block">
                        <button
                            style="background-color: rgb(255, 0, 0); border-radius: 50px; color: white; border: 2px solid red;"
                            onmouseover="this.style.borderColor='red'; this.style.backgroundColor='black'; this.style.color='white';"
                            onmouseout="this.style.borderColor='red'; this.style.backgroundColor='red'; this.style.color='white';"
                            class="bg-red-700 hover:bg-red-800 transition text-white text-sm font-semibold px-4 py-2 rounded">
                            <i class="bi bi-box-arrow-in-right" style="font-size: 1.5rem;"></i> INICAR SESIÓN 
                        </button>
                    </a>
                    <a href="./login/register.html" class="block">
                        <button
                            style="background-color: rgb(255, 0, 0); border-radius: 50px; color: white; border: 2px solid red;"
                            onmouseover="this.style.borderColor='red'; this.style.backgroundColor='black'; this.style.color='white';"
                            onmouseout="this.style.borderColor='red'; this.style.backgroundColor='red'; this.style.color='white';"
                            class="bg-red-700 hover:bg-red-800 transition text-white text-sm font-semibold px-4 py-2 rounded">
                            <i class="bi bi-person-plus-fill" style="font-size: 1.5rem;"></i>
                            REGISTRARSE
                        </button>
                    </a>
                </div>
            </div>


            <!-- Botones para usuario logueado (inicialmente ocultos) -->
            <div id="userLoggedButtons" style="display: none; box-shadow: 0 0 20px rgba(0,0,0,0.8);">
                <div class="user-profile-container">
                    <button class="menu-button" onclick="toggleUserDropdown()" aria-expanded="false"
                        aria-haspopup="true">
                        <span class="user-avatar" id="userAvatar"></span>
                        <span class="user-name" id="userName"></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>

                    <!-- User Dropdown Menu -->
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-info-dropdown">
                            <div class="user-avatar-large" id="userAvatarLarge"></div>
                            <div class="user-details">
                                <h4 id="userFullName"></h4>
                                <p id="userEmail"></p>
                                <span class="user-role" id="userRole"></span>
                            </div>
                        </div>


                        <div class="menu-divider"></div>

                        <div class="menu-items">
                            <a href="#" class="menu-item">
                                <i class="fas fa-user"></i>
                                <span>Mi Perfil</span>
                            </a>


                            <a href="./historial.html" class="menu-item">
                                <i class="fas fa-clock"></i> Historial de compra
                            </a>
                            <a href="./favoritos.html" class="menu-item">
                                <i class="fas fa-heart"></i> Mis favoritos
                            </a>
                            <a href="#" class="menu-item">
                                <i class="fas fa-edit"></i>
                                <span>Editar Perfil</span>
                            </a>
                            <a href="#" class="menu-item">
                                <i class="fas fa-key"></i>
                                <span>Cambiar Contraseña</span>
                            </a>

                            <a href="admin/admin_settings.html" class="menu-item">
                                <i class="fas fa-cog"></i>
                                <span>Configuración</span>
                            </a>


                            <div class="menu-divider"></div>

                            <a href="#" class="menu-item logout" onclick="logout()">
                                <i class="fas fa-sign-out-alt" style="color: red;"></i>
                                <span style="color: red;">Cerrar Sesión</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="nav-menu" id="navMenu">
                <style>
                    /* Contenedor centrado en la página */
                    .nav-menu {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                        justify-content: center;
                                                gap: 6rem; /* Increased spacing */
                        width: 100%;
                        max-width: 960px;
                        /* ancho máximo del nav centrado */
                        margin: 0 auto;
                        /* centra horizontalmente dentro del header */
                        box-sizing: border-box;
                        padding: 0.25rem 0.5rem;
                        text-align: center;
                    }

                    /* Asegura que los enlaces y botones queden centrados visualmente */
                    .nav-menu>a,
                    .nav-menu>div {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .nav-menu a {
                        text-decoration: none;
                        color: inherit;
                        padding: 0.35rem 0.75rem;
                        font-family: 'Montserrat', sans-serif;
                        /* Applied Montserrat font */
                    }

                    /* Mantener los botones de auth alineados y centrados */
                    #authButtons {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        justify-content: center;
                    }

                    /* En móvil, permitir que los elementos se apilen centrados */
                    @media (max-width: 768px) {
                        .nav-menu {
                            justify-content: center;
                            padding: 0.5rem;
                        }

                        /* Hacer los botones más compactos en móvil */
                        #authButtons {
                            width: 100%;
                            justify-content: center;
                            gap: 0.5rem;
                        }
                    }
                </style>

                <!-- Botones de navegación -->
                <a href="./index.html" >Inicio</a>
                <a href="./productos.php" class="active" >Productos</a>
                <a href="./promociones.html">Ofertas</a>
                <a href="./contacto.html">Contacto</a>
                <a href="./sobre_nosotros.html">Quienes Somos</a>



                <!-- Estilos y funcionalidad mejorados PARA EL MENU DEL USUARIO LOGUEADO -->
                <style>
                    /* Contenedor principal con fondo negro y separación de bordes */
                    #userLoggedButtons {
                        background-color: #000000;
                        padding: 1rem;
                        border-radius: 10px;
                        margin: 0.5rem;
                    }

                    .user-profile-container {
                        position: relative;
                        display: inline-block;
                    }

                    .menu-button {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        background: linear-gradient(135deg, #000000 0%, #000000 100%);
                        border: 2px solid #000000;
                        border-radius: 50px;
                        padding: 0.75rem 1.5rem;
                        color: #ffffff;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                    }

                    .menu-button:hover {
                        border-color: #ff0000;
                        background: linear-gradient(135deg, #000000 0%, #000000 100%);
                        box-shadow: 0 6px 25px rgba(255, 0, 0, 0.25);
                        transform: translateY(-2px);
                    }

                    .menu-button:active {
                        transform: translateY(0);
                    }

                    .user-avatar {
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 1.1rem;
                        color: white;
                        box-shadow: 0 2px 8px rgba(255, 0, 0, 0.3);
                    }

                    .user-name {
                        font-size: 0.95rem;
                        color: #f0f0f0;
                        max-width: 120px;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }

                    .dropdown-arrow {
                        margin-left: 0.5rem;
                        transition: transform 0.3s ease;
                        color: #ff0000;
                    }

                    .menu-button[aria-expanded="true"] .dropdown-arrow {
                        transform: rotate(180deg);
                    }

                    .user-dropdown {
                        position: absolute;
                        top: calc(100% + 12px);
                        right: 0;
                        background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
                        border: 2px solid #333;
                        border-radius: 16px;
                        min-width: 280px;
                        max-width: 320px;
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-10px);
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        z-index: 1000;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
                        backdrop-filter: blur(10px);
                    }

                    .user-dropdown.active {
                        opacity: 1;
                        visibility: visible;
                        transform: translateY(0);
                    }

                    .user-info-dropdown {
                        display: flex;
                        align-items: center;
                        gap: 1rem;
                        padding: 1.5rem;
                        border-bottom: 1px solid #333;
                    }

                    .user-avatar-large {
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 1.5rem;
                        color: white;
                        box-shadow: 0 4px 12px rgba(255, 0, 0, 0.3);
                    }

                    .user-details h4 {
                        color: #ffffff;
                        margin: 0 0 0.25rem 0;
                        font-size: 1.1rem;
                        font-weight: 600;
                    }

                    .user-details p {
                        color: #cccccc;
                        margin: 0 0 0.5rem 0;
                        font-size: 0.85rem;
                    }

                    .user-role {
                        background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
                        color: white;
                        padding: 0.25rem 0.75rem;
                        border-radius: 20px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        display: inline-block;
                    }

                    .menu-items {
                        padding: 0.5rem 0;
                    }

                    .menu-item {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        padding: 0.875rem 1.5rem;
                        color: #e0e0e0;
                        text-decoration: none;
                        transition: all 0.2s ease;
                        border-radius: 8px;
                        margin: 0 0.5rem;
                    }

                    .menu-item:hover {
                        background: rgba(255, 0, 0, 0.1);
                        color: #ffffff;
                        transform: translateX(4px);
                    }

                    .menu-item.logout {
                        color: #ff6b6b;
                    }

                    .menu-item.logout:hover {
                        background: rgba(255, 0, 0, 0.15);
                        color: #ff4444;
                    }

                    .menu-item i {
                        width: 20px;
                        text-align: center;
                        color: #ff0000;
                    }

                    .menu-divider {
                        height: 1px;
                        background: linear-gradient(90deg, transparent 0%, #333 50%, transparent 100%);
                        margin: 0.5rem 1.5rem;
                    }

                    @media (max-width: 768px) {
                        .user-dropdown {
                            right: -10px;
                            min-width: 260px;
                        }

                        .menu-button {
                            padding: 0.625rem 1.25rem;
                        }

                        .user-name {
                            max-width: 80px;
                        }
                    }
                </style>

                <script>
                    // Función mejorada para toggle del dropdown
                    function toggleUserDropdown() {
                        const dropdown = document.getElementById('userDropdown');
                        const button = document.querySelector('.menu-button');
                        const isOpen = dropdown.classList.contains('active');

                        // Cerrar todos los dropdowns primero
                        document.querySelectorAll('.user-dropdown').forEach(d => d.classList.remove('active'));
                        document.querySelectorAll('.menu-button').forEach(b => b.setAttribute('aria-expanded', 'false'));

                        if (!isOpen) {
                            dropdown.classList.add('active');
                            button.setAttribute('aria-expanded', 'true');
                        }
                    }

                    document.addEventListener('click', function (event) {
                        const container = document.querySelector('.user-profile-container');
                        if (!container.contains(event.target)) {
                            const dd = document.getElementById('userDropdown');
                            if (dd) dd.classList.remove('active');
                            const btn = document.querySelector('.menu-button');
                            if (btn) btn.setAttribute('aria-expanded', 'false');
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            const dd = document.getElementById('userDropdown');
                            if (dd) dd.classList.remove('active');
                            const btn = document.querySelector('.menu-button');
                            if (btn) btn.setAttribute('aria-expanded', 'false');
                        }
                    });

                    function updateUserProfile(userData) {
                        if (!userData) return;
                        const name = userData.name || userData.nombres_completos || 'Usuario';
                        const initials = (name.charAt(0) || 'U').toUpperCase();
                        const avatar = document.getElementById('userAvatar');
                        const userName = document.getElementById('userName');
                        const avatarLarge = document.getElementById('userAvatarLarge');
                        const fullName = document.getElementById('userFullName');
                        const email = document.getElementById('userEmail');
                        const role = document.getElementById('userRole');

                        if (avatar) avatar.textContent = initials;
                        if (userName) userName.textContent = name;
                        if (avatarLarge) avatarLarge.textContent = initials;
                        if (fullName) fullName.textContent = userData.fullName || name;
                        if (email) email.textContent = userData.email || userData.correo_electronico || '';
                        if (role) role.textContent = userData.role || userData.rol || 'Usuario';
                    }

                    document.addEventListener('DOMContentLoaded', function () {
                        // placeholder para posibles inicializaciones
                    });

                    // Modal y sesión
                    function closeModal() {
                        const modal = document.getElementById('welcomeModal');
                        if (modal) modal.style.display = 'none';
                    }

                    window.addEventListener('load', function () {
                        var modalEl = document.getElementById('welcomeModal');
                        if (modalEl) modalEl.style.display = 'flex';
                        if (window.AuthSystem && typeof AuthSystem.checkUserSession === 'function') {
                            AuthSystem.checkUserSession();
                        } else if (typeof checkUserSession === 'function') {
                            checkUserSession();
                        }
                    });

                    function checkUserSession() {
                        const userData = localStorage.getItem('userData');
                        const sessionData = sessionStorage.getItem('currentSession');

                        if (userData || sessionData) {
                            const raw = JSON.parse(userData || sessionData);
                            if (raw && raw.isLoggedIn) {
                                const currentUser = raw.user ? raw.user : raw;
                                const authButtons = document.getElementById('authButtons');
                                const userLoggedButtons = document.getElementById('userLoggedButtons');
                                if (authButtons) authButtons.style.display = 'none';
                                if (userLoggedButtons) userLoggedButtons.style.display = 'block';

                                const displayName = currentUser.nombres_completos || currentUser.nombre || currentUser.correo_electronico || currentUser.email || 'Usuario';
                                const displayEmail = currentUser.correo_electronico || currentUser.email || '';
                                const displayRole = currentUser.rol || '';
                                const initials = (displayName.charAt(0) || 'U').toUpperCase();

                                const userAvatar = document.getElementById('userAvatar');
                                const userName = document.getElementById('userName');
                                const userAvatarLarge = document.getElementById('userAvatarLarge');
                                const userFullName = document.getElementById('userFullName');
                                const userEmail = document.getElementById('userEmail');
                                const userRole = document.getElementById('userRole');

                                if (userAvatar) userAvatar.textContent = initials;
                                if (userName) userName.textContent = displayName;
                                if (userAvatarLarge) userAvatarLarge.textContent = initials;
                                if (userFullName) userFullName.textContent = displayName;
                                if (userEmail) userEmail.textContent = displayEmail;
                                if (userRole) userRole.textContent = displayRole ? displayRole.charAt(0).toUpperCase() + displayRole.slice(1) : '';
                            }
                        }
                    }


                </script>
            </nav>

            <button class="mobile-toggle " id="mobileToggle " aria-label="Toggle menu ">
                <i class="fas fa-bars "></i>
            </button>
        </div>
    </header>

    <!-- Sesion de productos  -->
    <!-- PAGE TITLE -->
    <section class="page-title">
        <h1 class="text-white font-extrabold text-xl md:text-2xl mb-12 text-center team-title ">
            TODOS LOS
            <span class="text-red-600 italic " style="color: red;">PRODUCTOS</span>
        </h1>
        <div class="breadcrumb">
            <a href="#" style="color: rgb(255, 0, 0);">Inicio</a> <span style="color: white;">›</span>
            <span style="color: white;">Todos los productos</span>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="container">
        <!-- SIDEBAR -->
        <aside class="sidebar" style="color: black;">
            <div class="filter-section">
                <h3>¿Qué buscas?</h3>

                <form class="search-box" action="productos.php" method="get" style="margin-bottom:10px ; color: black;">
                    <input style="color: #000000;" type="search" name="q" id="site-search-sidebar" placeholder="Buscar productos..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    <button type="submit">Buscar</button>
                </form>
            </div>

            <div class="filter-section" style="color: black;">
                <h3>Categorías</h3>
                <div class="filter-group" style="color: white;">
                    <?php
                    $selectedCat = isset($_GET['categoria']) ? strtolower(trim($_GET['categoria'])) : '';
                    foreach ($categoryCounts as $catName => $cnt) {
                        $label = strtoupper($catName);
                        $checked = ($selectedCat === $catName) ? 'checked' : '';
                        $paramsOn = $baseParams;
                        $paramsOn['categoria'] = $catName;
                        $paramsOn['page'] = 1;
                        $urlOn = 'productos.php?' . http_build_query($paramsOn);

                        $paramsOff = $baseParams;
                        unset($paramsOff['categoria']);
                        $paramsOff['page'] = 1;
                        $urlOff = 'productos.php?' . http_build_query($paramsOff);
                        echo '<label><input type="checkbox" data-category="' . htmlspecialchars($catName) . '" ' . $checked . ' onchange="window.location.href=this.checked?\'' . $urlOn . '\':\'' . $urlOff . '\'"> ' . htmlspecialchars($label) . ' <span class="count">(' . intval($cnt) . ')</span></label>';
                    }
                    ?>
                </div>
            </div>

            <div class="filter-section" style="color: white;">
                <h3>Etiquetas</h3>
                <div class="filter-group" style="color: white;">
                    <?php
                    $selectedSub = isset($_GET['subcategoria']) ? strtolower(trim($_GET['subcategoria'])) : '';
                    foreach ($tagCounts as $tag => $cnt) {
                        $label = strtoupper($tag);
                        $checked = ($selectedSub === $tag) ? 'checked' : '';
                        $paramsOn = $baseParams;
                        $paramsOn['subcategoria'] = $tag;
                        $paramsOn['page'] = 1;
                        $urlOn = 'productos.php?' . http_build_query($paramsOn);

                        $paramsOff = $baseParams;
                        unset($paramsOff['subcategoria']);
                        $paramsOff['page'] = 1;
                        $urlOff = 'productos.php?' . http_build_query($paramsOff);
                        echo '<label><input type="checkbox" data-tag="' . htmlspecialchars($tag) . '" ' . $checked . ' onchange="window.location.href=this.checked?\'' . $urlOn . '\':\'' . $urlOff . '\'"> ' . htmlspecialchars($label) . ' <span class="count">(' . intval($cnt) . ')</span></label>';
                    }
                    ?>
                </div>
            </div>
        </aside>

        <!-- PRODUCTS -->
        <main class="products-grid" style="color: white;">
            <div class="products-header">
                <div class="products-count" style="color: white;">Mostrando <?php echo $startDisplay . '-' . $endDisplay . ' de ' . $total . ' resultados'; ?></div>
            </div>

            <div class="products-grid-container" id="products-container" data-server-render="1">
                <style>
                    .product-card {
                        position: relative;
                    }

                    .product-image {
                        position: relative;
                    }

                    .favorite-btn {
                        position: absolute;
                        top: 8px;
                        right: 8px;
                        background: rgba(0, 0, 0, 0.6);
                        border: none;
                        border-radius: 50%;
                        width: 36px;
                        height: 36px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: #fff;
                        opacity: 0;
                        transition: opacity .2s ease, transform .1s ease, color .2s ease;
                        cursor: pointer;
                    }

                    .product-card:hover .favorite-btn {
                        opacity: 1;
                    }

                    .favorite-btn i {
                        font-size: 18px;
                    }

                    .favorite-btn.favorited {
                        color: #ff2f2f;
                    }

                    .favorite-btn:active {
                        transform: scale(0.97);
                    }
                </style>
                <?php
                // Renderizado basado en datos ya filtrados en memoria
                $shown = 0;
                // Preparar rutas para fallback por nombre
                $rootDir = __DIR__;
                $imagesDir = $rootDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'images';
                $imagesProductsDir = $imagesDir . DIRECTORY_SEPARATOR . 'products';

                foreach ($pageProducts as $row) {
                    $shown++;
                    $id = htmlspecialchars($row['id_producto'] ?? '');
                    $name = htmlspecialchars($row['nombre'] ?? 'Producto');
                    $price = floatval($row['precio_venta'] ?? 0);
                    $cat = $row['__cat'] ?? deriveCategoryFromName($row['nombre'] ?? '');
                    $image = imageFromRow($row);
                    if (!$image) {
                        // Intentar encontrar una imagen que coincida con el nombre
                        $image = find_fallback_image(($row['nombre'] ?? ''), $imagesDir, $imagesProductsDir, $rootDir);
                        if (!$image) {
                            $image = imageForCategory($cat);
                        }
                    }
                    echo '<div class="product-card">';
                    echo '  <div class="product-image">';
                    echo '    <img src="' . $image . '" alt="' . $name . '">';
                    echo '    <button class="favorite-btn" aria-label="Añadir a favoritos" data-id="' . $id . '" data-name="' . $name . '" data-price="' . number_format($price, 2, '.', '') . '" data-image="' . $image . '"><i class="far fa-heart"></i></button>';
                    echo '  </div>';
                    echo '  <div class="product-info">';
                    echo '    <h3 class="product-name">' . $name . '</h3>';
                    echo '    <div class="product-price">$' . number_format($price, 2, '.', '') . '</div>';
                    echo '    <div class="product-actions">';
                    echo '      <button class="btn btn-add" data-id="' . $id . '">Añadir al carrito</button>';
                    echo '      <button class="btn btn-info">+ Info</button>';
                    echo '    </div>';
                    echo '  </div>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- SECCIONES POR CATEGORÍAS -->
            <section id="category-sections" class="category-sections">
                <!-- Secciones de Res, Cerdo y Pollo se renderizan vía JS -->
            </section>

            <div class="pagination" style="color: white;">
                <?php
                // Construcción de paginación
                $baseParams = [];
                if ($q !== '') $baseParams['q'] = $q;
                if ($categoria !== '') $baseParams['categoria'] = $categoria;
                if ($subcategoria !== '') $baseParams['subcategoria'] = $subcategoria;

                function buildUrl($page, $params)
                {
                    $params['page'] = $page;
                    return 'productos.php?' . http_build_query($params) . '#products-container';
                }

                $prevDisabled = ($page <= 1);
                $nextDisabled = ($page >= $totalPages);

                // Primera página
                echo '<a class="' . ($page <= 1 ? 'disabled' : '') . '" href="' . ($page <= 1 ? '#' : buildUrl(1, $baseParams)) . '">Primera</a>';
                // Página anterior
                echo '<a class="' . ($prevDisabled ? 'disabled' : '') . '" href="' . ($prevDisabled ? '#' : buildUrl($page - 1, $baseParams)) . '">‹ Anterior</a>';

                // Mostrar hasta 5 páginas centradas
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++) {
                    $active = ($p === $page) ? 'active' : '';
                    echo '<a class="' . $active . '" href="' . buildUrl($p, $baseParams) . '">' . $p . '</a>';
                }

                // Página siguiente
                echo '<a class="' . ($nextDisabled ? 'disabled' : '') . '" href="' . ($nextDisabled ? '#' : buildUrl($page + 1, $baseParams)) . '">Siguiente ›</a>';
                // Última página
                echo '<a class="' . ($page >= $totalPages ? 'disabled' : '') . '" href="' . ($page >= $totalPages ? '#' : buildUrl($totalPages, $baseParams)) . '">Última</a>';
                ?>
            </div>
        </main>
    </div>
    <br>

    <!--PAGINADO-->



    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">

            <!-- Columna 1: Información de Contacto -->
            <div class="footer-column">
                <h4>INFORMACIÓN DE CONTACTO</h4>
                <p><i class="fas fa-map-marker-alt"></i> Dirección: OLAYA HERRERA</p>
                <p><i class="fas fa-phone"></i> Teléfono: 301 5210177</p>
                <p><i class="fas fa-envelope"></i> Email: districarnesnavarro@gmail.com</p>
                <div class="social-icons">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-linkedin-in"></i></a>
          <a href="#"><i class="fab fa-youtube"></i></a>
        </div>
            </div>

            <!-- Columna 2: Información -->
            <div class="footer-column">
                <h4>INFORMACIÓN</h4>
                <ul>
                    <li><i class="fas fa-info-circle"></i> Información Delivery</li>
                    <li><i class="fas fa-shield-alt"></i> Políticas de Privacidad</li>
                    <li><i class="fas fa-file-contract"></i> Términos y condiciones</li>
                    <li><i class="fas fa-headset"></i> Contáctanos</li>
                </ul>
            </div>

            <!-- Columna 3: Mi Cuenta -->
            <div class="footer-column">
                <h4>MI CUENTA</h4>
                <ul>
                    <li><i class="fas fa-user"></i> Mi cuenta</li>
                    <li><i class="fas fa-history"></i> Historial de ordenes</li>
                    <li><i class="fas fa-heart"></i> Lista de deseos</li>
                    <li><i class="fas fa-newspaper"></i> Boletín</li>
                    <li><i class="fas fa-undo"></i> Reembolsos</li>
                </ul>
            </div>

            <!-- Columna 4: Boletín Informativo -->
            <div class="footer-column">
                <h4>BOLETÍN INFORMATIVO</h4>
                <p>Suscríbete a nuestros boletines ahora y mantente al día con nuevas colecciones y ofertas exclusivas.</p>
                <form class="newsletter-form">
                    <input type="email" placeholder="Ingresa el correo aquí..." required />
                    <button type="submit">SUSCRÍBETE</button>
                </form>
            </div>

        </div>

        <!-- Pie inferior -->
        <div class="footer-bottom">
            <div class="footer-logo">
                <!-- ⚠️ REEMPLAZA ESTA IMAGEN CON TU LOGO REAL -->
                <img src="./assets/icon/LOGO-DISTRICARNES.png" alt="Logo Districarnes" />
                <span>DISTRICARNES HERMANOS NAVARROS</span>
            </div>
            <div class="payment-logos">
                <i class="fab fa-cc-visa" title="Visa" style="font-size:28px;color:#1A1F71;margin-right:8px;"></i>
                <i class="fab fa-cc-mastercard" title="Mastercard" style="font-size:28px;color:#EB001B;margin-right:8px;"></i>
                <i class="fab fa-cc-paypal" title="PayPal" style="font-size:28px;color:#003087;margin-right:8px;"></i>
                <i class="fab fa-cc-amex" title="American Express" style="font-size:28px;color:#2E77BC;margin-right:8px;"></i>
                <i class="fab fa-cc-discover" title="Discover" style="font-size:28px;color:#FF6000;margin-right:8px;"></i>
            </div>
        </div>

    </footer>

    <!-- CHAT BOT -->
    <div class="chatbot-toggle" onclick="toggleChatbot()" title="Abrir chat DISTRICARNES" aria-label="Abrir chat DISTRICARNES">
        <i class="fas fa-robot"></i>
    </div>
    <div class="chatbot-container">
        <div class="chatbot-header">
            <div class="header-info">
                <div class="bot-avatar"><i class="fas fa-robot"></i></div>
                <h3>DISTRICARNES HERMANOS NAVARRO</h3>
                <p>Asistente Virtual</p>
                <p>Tu especialista en carnes premium</p>
            </div>
            <button class="close-btn" onclick="toggleChatbot()" aria-label="Cerrar chat">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chatbot-messages">
            <div class="message bot-message">
                ¡Hola! 🥩 Soy tu asistente de DISTRICARNES. ¿En qué puedo ayudarte hoy?
                <div class="menu-options">
                    <div class="menu-option">
                        <i class="fas fa-drumstick-bite"></i> Ver productos cárnicos
                    </div>
                    <div class="menu-option">
                        <i class="fas fa-cut"></i> Tipos de cortes
                    </div>
                    <div class="menu-option">
                        <i class="fas fa-clock"></i> Horarios y ubicación
                    </div>
                    <div class="menu-option">
                        <i class="fas fa-tags"></i> Precios y ofertas
                    </div>
                    <div class="menu-option">
                        <i class="fas fa-info-circle"></i> Sobre nosotros
                    </div>
                    <div class="menu-option">
                        <i class="fas fa-phone"></i> Contactar
                    </div>
                </div>
                <div class="message-timestamp">10:01 AM</div>
            </div>
        </div>
        <div class="chatbot-input">
            <div class="input-container">
                <input type="text" class="chat-input" placeholder="¿Qué deseas saber sobre nuestras carnes?" onkeypress="handleKeyPress(event)" autocomplete="off" />
                <button class="voice-btn" title="Entrada de voz (No implementado)">
                    <i class="fas fa-microphone"></i>
                </button>
                <button class="send-btn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="quick-actions">
                <button class="quick-action" onclick="handleQuickAction('productos')">
                    <i class="fas fa-drumstick-bite"></i> Ver Productos
                </button>
                <button class="quick-action" onclick="handleQuickAction('horarios')">
                    <i class="fas fa-clock"></i> Horarios
                </button>
                <button class="quick-action" onclick="handleQuickAction('contacto')">
                    <i class="fas fa-phone"></i> Contacto
                </button>
            </div>
        </div>
    </div>

    <!-- Script de autenticación global -->
    <script src="./static/js/header_actions.js"></script>
    <script src="./js/auth.js"></script>
    <script src="./static/js/cart_badge.js"></script>
    <script src="./static/js/history_favorites.js"></script>
    <script>
        (function() {
            function hasUserSession() {
                try {
                    const rawStr = localStorage.getItem('userData') || sessionStorage.getItem('currentSession');
                    if (!rawStr) return false;
                    const raw = JSON.parse(rawStr);
                    const user = raw && raw.user ? raw.user : raw;
                    const email = (user && (user.correo_electronico || user.email));
                    const id = (user && (user.id_usuario || user.id));
                    return Boolean(email || id);
                } catch (e) {
                    return false;
                }
            }

            function setBtnState(btn, favorited) {
                const icon = btn.querySelector('i');
                if (favorited) {
                    btn.classList.add('favorited');
                    if (icon) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                    }
                } else {
                    btn.classList.remove('favorited');
                    if (icon) {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                }
            }

            function initFavoritesUI() {
                const buttons = document.querySelectorAll('.favorite-btn');
                const list = (window.FavoritesStore && FavoritesStore.all()) || [];
                buttons.forEach(btn => {
                    const id = btn.dataset.id;
                    const exists = list.some(i => i && String(i.id) === String(id));
                    setBtnState(btn, exists);
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (!hasUserSession()) {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Inicia sesión para usar favoritos',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                            return;
                        }
                        const id = this.dataset.id;
                        const name = this.dataset.name || (this.closest('.product-card')?.querySelector('.product-name')?.textContent?.trim() || 'Producto');
                        const price = parseFloat(this.dataset.price || (this.closest('.product-card')?.querySelector('.product-price')?.textContent?.replace(/[^0-9.,]/g, '').replace(',', '.') || '0'));
                        const image = this.dataset.image || (this.closest('.product-card')?.querySelector('.product-image img')?.src || '');

                        const currentlyFavorited = this.classList.contains('favorited');
                        if (currentlyFavorited) {
                            if (window.FavoritesStore) {
                                FavoritesStore.remove(id);
                            }
                            setBtnState(this, false);
                        } else {
                            if (window.FavoritesStore) {
                                FavoritesStore.add({
                                    id,
                                    name,
                                    price,
                                    image
                                });
                            }
                            setBtnState(this, true);
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Añadido a favoritos',
                                    toast: true,
                                    position: 'top-end',
                                    timer: 1200,
                                    showConfirmButton: false,
                                    background: '#28a745',
                                    color: '#fff'
                                });
                            }
                        }
                    });
                });

                // React a cambios desde otras páginas
                window.addEventListener('favorites:updated', function() {
                    const fresh = (window.FavoritesStore && FavoritesStore.all()) || [];
                    document.querySelectorAll('.favorite-btn').forEach(btn => {
                        const id = btn.dataset.id;
                        const exists = fresh.some(i => i && String(i.id) === String(id));
                        setBtnState(btn, exists);
                    });
                });

                // Al cerrar sesión, limpiar estados visuales de favoritos
                window.addEventListener('auth:loggedOut', function() {
                    document.querySelectorAll('.favorite-btn').forEach(btn => setBtnState(btn, false));
                });
            }

            document.addEventListener('DOMContentLoaded', initFavoritesUI);
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.AuthSystem && typeof AuthSystem.checkUserSession === 'function') {
                AuthSystem.checkUserSession();
            }
        });
    </script>
    <script src="./static/js/cart_utils.js"></script>
    <script src="./static/js/index.js"></script>
    <script src="./static/js/chatbot.js"></script>
    <script src="./static/js/loader.js" defer></script>
    <script src="./static/js/session_guard.js" defer></script>
    <script src="./static/js/network_guard.js" defer></script>
</body>
<script src="./static/js/productos.js"></script>

</html>