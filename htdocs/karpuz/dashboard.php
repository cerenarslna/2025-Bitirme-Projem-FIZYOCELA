<?php
// Güvenli erişim tanımlanmamışsa tanımla
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

session_start();
require '../leblebi.php';
include '../view/header.php'; 
/* Kullanıcı admin değil ise reddedilir */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    header('Location: gonderiler.php');
    exit('Erişim reddedildi!');
}
?>

<?php
// Kullanıcı sayısı
$sql_users = "SELECT COUNT(*) AS toplam_kullanici FROM kullanicilar";
$result_users = $conn->query($sql_users);
$toplam_kullanici = $result_users->fetch_assoc()['toplam_kullanici'];

// Gönderi sayısı
$sql_posts = "SELECT COUNT(*) AS toplam_gonderi FROM gonderiler";
$result_posts = $conn->query($sql_posts);
$toplam_gonderi = $result_posts->fetch_assoc()['toplam_gonderi'];

// Yorum sayısı
$sql_yorum = "SELECT COUNT(*) AS toplam_yorum FROM yorumlar";
$result_yorum = $conn->query($sql_yorum);
$toplam_yorum = $result_yorum->fetch_assoc()['toplam_yorum'];
?>

<?php include 'includes/sidebar.php'; ?>

<div class="container-fluid py-4">
        <!-- Başlık Bölümü -->
        <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold mb-0">
                <i class="fas fa-cog me-2 gradient-text"></i>
                <span class="gradient-text">Yönetim Paneli</span>
            </h1>
            <p class="text-muted">Site istatistiklerini ve özetleri buradan takip edebilirsiniz.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kullanıcı Sayısı -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-kutusu bg-primary-subtle rounded-3 p-3 me-3">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0">Kayıtlı Kullanıcı</h5>
                    </div>
                    <h2 class="display-4 fw-bold mb-0"><?php echo $toplam_kullanici; ?></h2>
                    <p class="text-muted mt-2 mb-0">
                        <a href="kullanicilar.php" class="text-decoration-none">
                            <i class="fas fa-arrow-right me-1"></i>Kullanıcıları Görüntüle
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Gönderi Sayısı -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-kutusu bg-success-subtle rounded-3 p-3 me-3">
                            <i class="fas fa-file-alt fa-2x text-success"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0">Gönderiler</h5>
                    </div>
                    <h2 class="display-4 fw-bold mb-0"><?php echo $toplam_gonderi; ?></h2>
                    <p class="text-muted mt-2 mb-0">
                        <a href="gonderiler.php" class="text-decoration-none">
                            <i class="fas fa-arrow-right me-1"></i>Gönderileri Görüntüle
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Yorum Sayısı -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-kutusu bg-info-subtle rounded-3 p-3 me-3">
                            <i class="fas fa-comments fa-2x text-info"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0">Toplam Yorum</h5>
                    </div>
                    <h2 class="display-4 fw-bold mb-0"><?php echo $toplam_yorum; ?></h2>
                    <p class="text-muted mt-2 mb-0">
                        <a href="yorumlar.php" class="text-decoration-none">
                            <i class="fas fa-arrow-right me-1"></i>Yorumları Görüntüle
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Hızlı erişim bölümü -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-chart-line me-2"></i>Hızlı Erişim</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <a href="gonderi_ekle.php" class="text-decoration-none">
                                <div class="hizli-baglanti p-4 rounded bg-light text-center">
                                    <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                    <h5 class="fw-bold">Yeni Gönderi Ekle</h5>
                                    <p class="text-muted mb-0">Site içeriğini zenginleştirin</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="yorumlar.php" class="text-decoration-none">
                                <div class="hizli-baglanti p-4 rounded bg-light text-center">
                                    <i class="fas fa-comments fa-3x text-info mb-3"></i>
                                    <h5 class="fw-bold">Yorumları Yönet</h5>
                                    <p class="text-muted mb-0">Yorumları inceleyin ve analiz edin</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="kullanicilar.php" class="text-decoration-none">
                                <div class="hizli-baglanti p-4 rounded bg-light text-center">
                                    <i class="fas fa-user-cog fa-3x text-success mb-3"></i>
                                    <h5 class="fw-bold">Kullanıcı Yönetimi</h5>
                                    <p class="text-muted mb-0">Kullanıcı hesaplarını düzenleyin</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/sidebarend.php'; ?>


<style>
    .icon-kutusu {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .hizli-baglanti {
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .hizli-baglanti:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
</style>