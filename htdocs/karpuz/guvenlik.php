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

// Engelli IP'leri kaldırma işlemi
if (isset($_POST['engel_kaldir']) && isset($_POST['ip_adresi'])) {
    $ip = $_POST['ip_adresi'];
    $stmt = $conn->prepare("DELETE FROM ip_engel WHERE ip_adresi = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $_SESSION['success'] = 'IP engeli kaldırıldı.';
    header('Location: guvenlik.php');
    exit;
}

// İstatistikleri al
$son24saat = $conn->query("SELECT COUNT(*) as sayi FROM giris_takip 
                          WHERE durum = 'basarisiz' 
                          AND giris_tarihi > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc();

$engelli_ip_sayisi = $conn->query("SELECT COUNT(*) as sayi FROM ip_engel 
                                  WHERE engel_bitis_tarihi > NOW() OR engel_bitis_tarihi IS NULL")->fetch_assoc();

$basarili_girisler = $conn->query("SELECT COUNT(*) as sayi FROM giris_takip 
                                  WHERE durum = 'basarili' 
                                  AND giris_tarihi > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc();

include 'includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <!-- Başlık Bölümü -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold mb-0">
                <i class="fas fa-cog me-2 gradient-text"></i>
                <span class="gradient-text">Güvenlik İzleme Paneli</span>
            </h1>
            <p class="text-muted">Sistem güvenliği ve giriş denemelerini buradan takip edebilirsiniz.</p>
        </div>
    </div>    

    <div class="row g-4">
        <!-- Başarısız Giriş -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-kutusu bg-warning-subtle rounded-3 p-3 me-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0">Başarısız Giriş</h5>
                    </div>
                    <h2 class="display-4 fw-bold mb-0"><?php echo $son24saat['sayi']; ?></h2>
                    <p class="text-muted mt-2 mb-0">Son 24 saat içinde</p>
                    <div class="mt-3">
                        <a href="reset_login_attempts.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-redo me-1"></i>Giriş Denemelerini Sıfırla
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Engelli IP -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-kutusu bg-danger-subtle rounded-3 p-3 me-3">
                            <i class="fas fa-ban fa-2x text-danger"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0">Engelli IP</h5>
                    </div>
                    <h2 class="display-4 fw-bold mb-0"><?php echo $engelli_ip_sayisi['sayi']; ?></h2>
                    <p class="text-muted mt-2 mb-0">Aktif engelli IP sayısı</p>
                </div>
            </div>
        </div>

        <!-- Başarılı Giriş -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-kutusu bg-success-subtle rounded-3 p-3 me-3">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-0">Başarılı Giriş</h5>
                    </div>
                    <h2 class="display-4 fw-bold mb-0"><?php echo $basarili_girisler['sayi']; ?></h2>
                    <p class="text-muted mt-2 mb-0">Son 24 saat içinde</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detaylı Bilgiler -->
    <div class="row mt-5">
        <!-- Aktif IP Engelleri -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-ban me-2"></i>Aktif IP Engelleri</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>IP Adresi</th>
                                    <th>Engellenme Tarihi</th>
                                    <th>Bitiş Tarihi</th>
                                    <th>Engelleme Nedeni</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $engelli_ipler = $conn->query("SELECT * FROM ip_engel WHERE engel_bitis_tarihi > NOW() OR engel_bitis_tarihi IS NULL ORDER BY engelleme_tarihi DESC");
                                while ($ip = $engelli_ipler->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($ip['ip_adresi']) ?></td>
                                    <td><?= htmlspecialchars($ip['engelleme_tarihi']) ?></td>
                                    <td><?= $ip['engel_bitis_tarihi'] ? htmlspecialchars($ip['engel_bitis_tarihi']) : 'Süresiz' ?></td>
                                    <td><?= htmlspecialchars($ip['engelleme_nedeni']) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="ip_adresi" value="<?= htmlspecialchars($ip['ip_adresi']) ?>">
                                            <button type="submit" name="engel_kaldir" class="btn btn-warning btn-sm">
                                                <i class="fas fa-unlock me-1"></i>Engeli Kaldır
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son Başarısız Giriş Denemeleri -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Son Başarısız Giriş Denemeleri</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>IP Adresi</th>
                                    <th>Tarih</th>
                                    <th>Kullanıcı ID</th>
                                    <th>Durum</th>
                                    <th>Tarayıcı Bilgisi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $giris_denemeleri = $conn->query("SELECT * FROM giris_takip WHERE durum = 'basarisiz' ORDER BY giris_tarihi DESC LIMIT 20");
                                while ($deneme = $giris_denemeleri->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($deneme['ip_adresi']) ?></td>
                                    <td><?= htmlspecialchars($deneme['giris_tarihi']) ?></td>
                                    <td><?= $deneme['kullanici_id'] ? htmlspecialchars($deneme['kullanici_id']) : 'Bilinmiyor' ?></td>
                                    <td><span class="badge bg-danger">Başarısız</span></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($deneme['tarayici_bilgisi']) ?></small></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
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
</style> 