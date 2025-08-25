<?php
// Güvenli erişim tanımlanıyor
define('SECURE_ACCESS', true);

// Admin header'ı dahil ediyor, oturum, güvenlik ve ortak işlevleri işliyor
require_once 'admin_header.php';

// View header ve sidebar'ı dahil ediyor
include '../view/header.php';
include 'includes/sidebar.php';

// Şirket bilgilerini getir
$stmt = $conn->prepare("SELECT * FROM sirket_bilgileri LIMIT 1");
$stmt->execute();
$sirket = $stmt->get_result()->fetch_assoc();
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <!-- Başlık Bölümü -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="fw-bold mb-0">
                    <i class="fas fa-cog me-2 gradient-text"></i>
                    <span class="gradient-text">Şirket Bilgileri</span>
                </h1>
                <p class="text-muted">Şirket bilgilerini düzenleyin.</p>
            </div>
        </div>
    </div>
            
    <?php if (isset($_SESSION['mesaj'])): ?>
        <div class="alert alert-<?= $_SESSION['mesaj_tur'] ?> alert-dismissible fade show" role="alert">
            <?= $_SESSION['mesaj'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['mesaj'], $_SESSION['mesaj_tur']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="sirket_bilgileri_kaydet.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-4">
                    <label for="sirket_adi" class="form-label">Şirket Adı</label>
                    <input type="text" class="form-control form-control-lg" id="sirket_adi" name="sirket_adi" 
                           value="<?= htmlspecialchars($sirket['sirket_adi'] ?? '') ?>" required>
                    <div class="invalid-feedback">Lütfen şirket adını giriniz.</div>
                </div>
                
                <div class="mb-4">
                    <label for="adres" class="form-label">Adres</label>
                    <textarea class="form-control" id="adres" name="adres" rows="3" required><?= htmlspecialchars($sirket['adres'] ?? '') ?></textarea>
                    <div class="invalid-feedback">Lütfen adresi giriniz.</div>
                </div>
                
                <div class="mb-4">
                    <label for="telefon" class="form-label">Telefon</label>
                    <input type="tel" class="form-control" id="telefon" name="telefon" 
                           value="<?= htmlspecialchars($sirket['telefon'] ?? '') ?>" required>
                    <div class="invalid-feedback">Lütfen telefon numarasını giriniz.</div>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="form-label">E-posta</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($sirket['email'] ?? '') ?>" required>
                    <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi giriniz.</div>
                </div>

                
                <div class="mb-4">
                    <label for="harita" class="form-label">Google Harita Kodu</label>
                    <textarea class="form-control" id="harita" name="harita" rows="3" disabled><?= htmlspecialchars($sirket['harita'] ?? '') ?></textarea>
                    <div class="form-text text-muted">Google Maps embed kodunu buraya yapıştırın.(Hazır değil)</div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary gradient-custom btn-lg">
                        <i class="fas fa-save me-2"></i>Bilgileri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form doğrulama
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include 'includes/sidebarend.php'; ?>
