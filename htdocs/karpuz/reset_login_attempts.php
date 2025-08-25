<?php
// Güvenli erişim tanımlanıyor
define('SECURE_ACCESS', true);

session_start();
require '../leblebi.php';
require '../functions.php';

// Yönetici kontrolü
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    header('Location: ../limon.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_adresi = isset($_POST['ip_adresi']) ? trim($_POST['ip_adresi']) : null;
    girisDenemeleriniSifirla($conn, $ip_adresi);
    $_SESSION['success'] = 'Giriş denemeleri başarıyla sıfırlandı.';
    header('Location: guvenlik.php');
    exit;
}

include '../view/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Giriş Denemelerini Sıfırla</h1>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="post" class="mb-4">
                        <div class="mb-3">
                            <label for="ip_adresi" class="form-label">IP Adresi (Opsiyonel)</label>
                            <input type="text" class="form-control" id="ip_adresi" name="ip_adresi" placeholder="Tüm kayıtları sıfırlamak için boş bırakın">
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Giriş Denemelerini Sıfırla
                        </button>
                    </form>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bu işlem seçilen IP adresi için veya tüm IP'ler için:
                        <ul class="mb-0 mt-2">
                            <li>IP engelleme kayıtlarını temizler</li>
                            <li>Giriş deneme geçmişini siler</li>
                            <li>Oturum sayaçlarını sıfırlar</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/sidebarend.php'; ?> 