<?php
// Direkt erişim engellendi
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

$path = basename($_SERVER['PHP_SELF']);

// Admin ve Fizyoterapist kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin', 'fizyoterapist'])) {
  die('Erişim reddedildi!');
}

// Geçirilen bağlantıyı kullan, yoksa global bağlantıyı kullan
$db_conn = isset($sidebar_conn) ? $sidebar_conn : (isset($conn) ? $conn : null);

if (!$db_conn) {
    die('Veritabanı bağlantısı bulunamadı.');
}

// Kullanıcının rolünü ve bilgilerini al
$userRole = $_SESSION['rol'];
$stmt = $db_conn->prepare("SELECT profil_resmi FROM kullanicilar WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!-- Yan Bar --> 
<div class="container-fluid">
    <div class="row flex-nowrap">
        <div class="col-auto col-md-3 col-xl-2 px-sm-2 px-0 bg-dark position-fixed">
            <div class="d-flex flex-column align-items-center align-items-sm-start px-3 pt-4 min-vh-100">
                <a href="/" class="d-flex align-items-center pb-3 mb-md-1 text-decoration-none">
                    <span class="fs-5 d-none d-sm-inline text-white">FizyoCela</span>
                    <span class="fs-5 d-inline d-sm-none text-white">FC</span>
                </a>
                <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start w-100" id="menu">
                    <?php if ($userRole === 'admin' || $userRole === 'superuser'): ?>
                    <li class="nav-item w-100">
                        <a href="dashboard.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'dashboard.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-gauge pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="nav-item w-100">
                        <a href="slider.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'slider.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-images pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Slider Yönetimi</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item w-100">
                        <a href="gonderiler.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'gonderiler.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-pen-fancy pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Gönderiler</span>
                        </a>
                    </li>
                    
                    <?php if ($userRole === 'admin' || $userRole === 'superuser'): ?>
                    <li class="nav-item w-100">
                        <a href="kullanicilar.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'kullanicilar.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-users pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Kullanıcılar</span>
                        </a>
                    </li>
                    
                    <li class="nav-item w-100">
                        <a href="yorumlar.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'yorumlar.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-comments pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Yorumlar</span>
                        </a>
                    </li>
                    
                    <li class="nav-item w-100">
                        <a href="mesajlar.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'mesajlar.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-envelope pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">İletişim Mesajları</span>
                        </a>
                    </li>

                    <li class="nav-item w-100">
                        <a href="sirket_bilgiler.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'sirket_bilgiler.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-building pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Şirket Bilgileri</span>
                        </a>
                    </li>

                    <li class="nav-item w-100">
                        <a href="guvenlik.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'guvenlik.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-shield-halved pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Güvenlik</span>
                        </a>
                    </li>
                    
                    <li class="nav-item w-100">
                        <a href="ayarlar.php" class="nav-link px-0 align-middle text-white <?php echo $path == 'ayarlar.php' ? 'active gradient-custom bg-primary' : ''; ?>">
                            <i class="fa-solid fa-gear pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Ayarlar</span>
                        </a>
                    </li>


                    <?php endif; ?>
                    
                    <li class="nav-item w-100 mt-2">
                        <a href="../limon.php" class="nav-link px-0 align-middle text-white">
                            <i class="fa-solid fa-rotate-left pe-none"></i>
                            <span class="ms-2 d-none d-sm-inline">Siteye Dön</span>
                        </a>
                    </li>
                </ul>

                <hr class="text-white w-100">
                <div class="dropdown pb-4 w-100">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($user['profil_resmi']): ?>
                            <img src="../assets/images/profil/<?= htmlspecialchars($user['profil_resmi']) ?>" 
                                 alt="<?= htmlspecialchars($_SESSION['kullanici_adi']) ?>" 
                                 class="rounded-circle"
                                 width="32" height="32" 
                                 style="object-fit: cover;">
                        <?php else: ?>
                            <img src="../assets/images/profil/kullaniciph.png" 
                                 alt="<?= htmlspecialchars($_SESSION['kullanici_adi']) ?>" 
                                 class="rounded-circle"
                                 width="32" height="32" 
                                 style="object-fit: cover;">
                        <?php endif; ?>
                        <span class="d-none d-sm-inline ms-2"><?php echo $_SESSION['kullanici_adi']?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                        <li><a class="dropdown-item" href="#">Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php">Çıkış Yap</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-auto col-md-3 col-xl-2"></div>
        <div class="col py-3">
            <style>
                @media (max-width: 767.98px) {
                    .col-auto {
                        width: 4.5rem !important;
                    }
                    
                    .nav-link {
                        padding: 0.5rem 0.2rem !important;
                        text-align: center;
                    }
                    
                    .nav-link i {
                        font-size: 1.5rem !important;
                    }
                    
                    .dropdown-toggle::after {
                        display: none;
                    }
                    
                    .dropdown img {
                        margin: 0 !important;
                    }
                }

                .nav-link:hover {
                    background-color: rgba(255, 255, 255, 0.1);
                }

                .nav-link.active {
                    background-color: #0d6efd !important;
                }

                .dropdown-menu {
                    margin-left: -100px;
                }

                .nav-link i {
                    width: 1.5rem;
                    text-align: center;
                }

                /* Yan barı içeriğin üzerinde kalması için z-index ekleyin */
                .position-fixed {
                    z-index: 1030;
                }
            </style>