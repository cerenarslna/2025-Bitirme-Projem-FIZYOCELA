<?php
// Çıkış buffer'ı başlat
ob_start();

// Güvenli erişim tanımlanmamışsa tanımla
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Start session before any output
session_start();
require '../leblebi.php';

// Admin ve Fizyoterapist kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin', 'fizyoterapist'])) {
    header('Location: ../limon.php?error=yetki');
    ob_end_flush();
    exit('Erişim reddedildi!');
}

require_once 'admin_header.php';

// Güvenli yönlendirme fonksiyonu
function safeRedirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    }
    
    echo '<script>window.location.href="' . $url . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
    exit();
}

// Başlık ve yan çubuğu dahil et
include '../view/header.php';
include 'includes/sidebar.php';

// Yardımcı fonksiyonlar - Kodun temel işlevlerini sağlar
class GonderiYonetim {
    private $conn;
    
    // Veritabanı bağlantısını başlat
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Veritabanı bağlantısını al
    public function getConnection() {
        return $this->conn;
    }
    
    // Resim yükleme fonksiyonu
    public function gorselYukle($dosya) {
        try {
            if ($dosya['error'] === UPLOAD_ERR_OK) {
                $uzanti = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
                // İzin verilen dosya türlerini kontrol et
                $izinli_uzantilar = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($uzanti, $izinli_uzantilar)) {
                    throw new Exception("Geçersiz dosya türü. Sadece JPG, JPEG, PNG ve GIF dosyaları yüklenebilir.");
                }

                $yeni_ad = uniqid() . '.' . $uzanti;
                $hedef_klasor = '../assets/images/uploads/';
                
                // Klasör yoksa oluştur
                if (!file_exists($hedef_klasor)) {
                    if (!mkdir($hedef_klasor, 0755, true)) {
                        throw new Exception("Klasör oluşturulamadı");
                    }
                }

                // Dosya izinlerini kontrol et
                if (!is_writable($hedef_klasor)) {
                    throw new Exception("Klasöre yazma izni yok");
                }

                if (move_uploaded_file($dosya['tmp_name'], $hedef_klasor . $yeni_ad)) {
                    chmod($hedef_klasor . $yeni_ad, 0644);
                    return $yeni_ad;
                } else {
                    throw new Exception("Dosya yüklenirken bir hata oluştu");
                }
            }
            return null;
        } catch (Exception $e) {
            error_log("Görsel yükleme hatası: " . $e->getMessage());
            return null;
        }
    }
    
    // Egzersiz sayfalarını seçim kutusunda görüntüle
    public function getEgzersizSayfalari() {
        $stmt = $this->conn->prepare("SELECT * FROM egzersiz_sayfalari ORDER BY baslik");
        $stmt->execute();
        return $stmt->get_result();
    }
    
    // Gönderi ekleme işlemi
    public function gonderiEkle($baslik, $icerik, $yazar_id, $blog, $egzersiz_sayfa_id = null, $resim_adi = null) {
        $stmt = $this->conn->prepare("INSERT INTO gonderiler (baslik, icerik, yazar_id, blog, egzersiz_sayfa_id, resim) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiss", $baslik, $icerik, $yazar_id, $blog, $egzersiz_sayfa_id, $resim_adi);
        return $stmt->execute();
    }
    
    // Gönderi güncelleme işlemi
    public function gonderiGuncelle($id, $baslik, $icerik, $blog, $egzersiz_sayfa_id = null) {
        try {
            // Eğer egzersiz_sayfa_id boş string ise NULL yap
            if ($egzersiz_sayfa_id === '') {
                $egzersiz_sayfa_id = null;
            }

            if (in_array($_SESSION['rol'], ['superuser', 'admin'])) {
                $stmt = $this->conn->prepare("UPDATE gonderiler SET baslik = ?, icerik = ?, blog = ?, egzersiz_sayfa_id = ? WHERE id = ?");
                $stmt->bind_param("ssiii", $baslik, $icerik, $blog, $egzersiz_sayfa_id, $id);
            } else {
                $stmt = $this->conn->prepare("UPDATE gonderiler SET baslik = ?, icerik = ?, blog = ?, egzersiz_sayfa_id = ? WHERE id = ? AND yazar_id = ?");
                $stmt->bind_param("ssiiii", $baslik, $icerik, $blog, $egzersiz_sayfa_id, $id, $_SESSION['user_id']);
            }
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Gönderi güncelleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    // Gönderi resim güncelleme
    public function resimGuncelle($id, $yeni_resim = null, $resim_sil = false) {
        // Eğer resim silinecekse
        if ($resim_sil) {
            // Önce mevcut resim dosyasını bul
            $stmt = $this->conn->prepare("SELECT resim FROM gonderiler WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            // Eğer resim varsa, dosyayı sil
            if ($row && !empty($row['resim'])) {
                $oldPath = '../assets/images/uploads/' . $row['resim'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            // Resim alanını null yap
            $stmt = $this->conn->prepare("UPDATE gonderiler SET resim = NULL WHERE id = ?");
            $stmt->bind_param("i", $id);
            return $stmt->execute();
        }
        
        // Eğer yeni resim yüklenecekse
        if ($yeni_resim) {
            $stmt = $this->conn->prepare("UPDATE gonderiler SET resim = ? WHERE id = ?");
            $stmt->bind_param("si", $yeni_resim, $id);
            return $stmt->execute();
        }
        
        return true;
    }
    
    // Gönderi silme işlemi
    public function gonderiSil($id) {
        // Önce gönderiyi kontrol et
        if (!in_array($_SESSION['rol'], ['superuser', 'admin'])) {
            $check = $this->conn->prepare("SELECT yazar_id FROM gonderiler WHERE id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $result = $check->get_result();
            $gonderi = $result->fetch_assoc();
            
            if (!$gonderi || $gonderi['yazar_id'] !== $_SESSION['user_id']) {
                return false;
            }
        }
        
        // Önce gönderiyle ilgili resmi sil
        $this->resimGuncelle($id, null, true);
        
        // Sonra gönderiyi sil
        $stmt = $this->conn->prepare("DELETE FROM gonderiler WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    // Gönderi alma fonksiyonu
    public function gonderiGetir($id) {
        // Admin ve superuser tüm gönderileri düzenleyebilir, fizyoterapist sadece kendi gönderilerini
        if (in_array($_SESSION['rol'], ['superuser', 'admin'])) {
            $stmt = $this->conn->prepare("SELECT * FROM gonderiler WHERE id = ?");
            $stmt->bind_param("i", $id);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM gonderiler WHERE id = ? AND yazar_id = ?");
            $stmt->bind_param("ii", $id, $_SESSION['user_id']);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    // Tüm gönderileri alma fonksiyonu
    public function tumGonderileriGetir() {
        // Admin ve superuser tüm gönderileri görebilir, fizyoterapist sadece kendi gönderilerini
        if (in_array($_SESSION['rol'], ['superuser', 'admin'])) {
            $sql = "SELECT g.*, k.kullanici_adi FROM gonderiler g
                    JOIN kullanicilar k ON g.yazar_id = k.id
                    ORDER BY g.olusturulma_tarihi DESC";
            return $this->conn->query($sql);
        } else {
            $sql = "SELECT g.*, k.kullanici_adi FROM gonderiler g
                    JOIN kullanicilar k ON g.yazar_id = k.id
                    WHERE g.yazar_id = ?
                    ORDER BY g.olusturulma_tarihi DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            return $stmt->get_result();
        }
    }
    
    // Admin ve Fizyoterapist kontrolü
    public function adminKontrol() {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin', 'fizyoterapist'])) {
            header('Location: ../limon.php?error=yetki');
            exit('Erişim reddedildi!');
        }
        return true;
    }
}

$gonderiYonetim = new GonderiYonetim($conn);

// İşlem seçici - URL parametresine göre doğru işlevi çağırır
if (isset($_GET['islem'])) {
    $islem = $_GET['islem'];
    
    switch ($islem) {
        case 'liste':
            gonderileriListele($gonderiYonetim);
            break;
            
        case 'ekle_form':
            gonderiEkleFormu($gonderiYonetim);
            break;
            
        case 'ekle_islem':
            gonderiEkleIslemi($gonderiYonetim);
            break;
            
        case 'duzenle_form':
            gonderiDuzenleFormu($gonderiYonetim);
            break;
            
        case 'duzenle_islem':
            gonderiDuzenleIslemi($gonderiYonetim);
            break;
            
        case 'sil':
            gonderiSilIslemi($gonderiYonetim);
            break;
            
        default:
            gonderileriListele($gonderiYonetim);
            break;
    }
} else {
    // Varsayılan olarak gönderi listesini göster
    gonderileriListele($gonderiYonetim);
}

// Gönderi listesini gösterme fonksiyonu
function gonderileriListele($gonderiYonetim) {
    global $conn;
    $sonuc = $gonderiYonetim->tumGonderileriGetir();
    ?>
    <div class="container-fluid py-4 px-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <!-- Başlık Bölümü -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="fw-bold mb-0">
                    <i class="fas fa-cog me-2 gradient-text"></i>
                    <span class="gradient-text">Gönderiler</span>
                </h1>
                <p class="text-muted">Gönderilerinizi yönetin.</p>
            </div>
        </div>

            <div class="d-flex flex-column flex-md-row gap-2 align-items-stretch align-items-md-center">
                <div class="input-group">
                    <input type="text" id="gonderiArama" class="form-control" placeholder="Gönderi ara...">
                    <span class="input-group-text gradient-custom bg-primary text-white">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
                <select id="tipFilter" class="form-select">
                    <option value="all">Tüm Gönderiler</option>
                    <option value="blog">Blog Yazıları</option>
                    <option value="exercise">Egzersiz Gönderileri</option>
                </select>
                <select id="egzersizSayfaFilter" class="form-select">
                    <option value="all">Tüm Egzersiz Sayfaları</option>
                    <?php
                    $egzersiz_sayfalari = $gonderiYonetim->getEgzersizSayfalari();
                    while ($sayfa = $egzersiz_sayfalari->fetch_assoc()): ?>
                        <option value="<?= $sayfa['id'] ?>"><?= htmlspecialchars($sayfa['baslik']) ?></option>
                    <?php endwhile; ?>
                </select>
                <a href="?islem=ekle_form" class="btn btn-primary gradient-custom d-flex align-items-center">
                    <i class="fas fa-plus-circle me-2"></i>Yeni Gönderi
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="gonderiTablosu">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3 px-4">Yazar</th>
                            <th class="py-3">Başlık</th>
                            <th class="py-3">Görsel</th>
                            <th class="py-3 d-none d-md-table-cell">Tür</th>
                            <th class="py-3 d-none d-lg-table-cell">Tarih</th>
                            <th class="py-3 text-end pe-4">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($gonderi = $sonuc->fetch_assoc()): ?>
                            <tr data-type="<?= $gonderi['blog'] ? 'blog' : 'exercise' ?>" 
                               data-exercise-page-id="<?= $gonderi['egzersiz_sayfa_id'] ?>">
                                <td class="py-3 px-4">
                                    <div class="d-flex align-items-center">
                                        <?php
                                        // Get user profile image
                                        $stmt = $conn->prepare("SELECT profil_resmi FROM kullanicilar WHERE id = ?");
                                        $stmt->bind_param("i", $gonderi['yazar_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $user = $result->fetch_assoc();
                                        ?>
                                        <?php if ($user && $user['profil_resmi']): ?>
                                            <img src="../assets/images/profil/<?= htmlspecialchars($user['profil_resmi']) ?>" 
                                                 alt="<?= htmlspecialchars($gonderi['kullanici_adi']) ?>" 
                                                 class="rounded-circle me-3"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="../assets/images/profil/kullaniciph.png" 
                                                 alt="<?= htmlspecialchars($gonderi['kullanici_adi']) ?>" 
                                                 class="rounded-circle me-3"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php endif; ?>
                                        <div class="fw-medium text-dark"><?= htmlspecialchars($gonderi['kullanici_adi']) ?></div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column">
                                        <span class="fw-medium"><?= htmlspecialchars($gonderi['baslik']) ?></span>
                                        <?php if ($gonderi['egzersiz_sayfa_id']): ?>
                                            <?php
                                            $stmt = $conn->prepare("SELECT baslik FROM egzersiz_sayfalari WHERE id = ?");
                                            $stmt->bind_param("i", $gonderi['egzersiz_sayfa_id']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $sayfa = $result->fetch_assoc();
                                            ?>
                                            <small class="text-muted">
                                                <i class="fas fa-link me-1"></i>
                                                <?= $sayfa ? htmlspecialchars($sayfa['baslik']) : 'Silinmiş Sayfa' ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <?php if (!empty($gonderi['resim'])): ?>
                                        <div class="position-relative">
                                            <img src="../assets/images/uploads/<?= htmlspecialchars($gonderi['resim']) ?>" 
                                                 alt="Gönderi Görseli"
                                                 class="img-thumbnail"
                                                 style="width: 80px; height: 80px; object-fit: cover;">
                                            <a href="../assets/images/uploads/<?= htmlspecialchars($gonderi['resim']) ?>" 
                                               class="position-absolute top-0 end-0 bg-light rounded-circle p-1"
                                               target="_blank"
                                               title="Görseli Tam Boyutta Aç">
                                                <i class="fas fa-expand text-primary"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small">
                                            <i class="fas fa-image me-1"></i> Görsel Yok
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 d-none d-md-table-cell">
                                    <?php if ($gonderi['blog']): ?>
                                        <span class="badge bg-info">Blog</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Egzersiz</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 d-none d-lg-table-cell">
                                    <div class="d-flex align-items-center">
                                        <i class="far fa-calendar-alt text-muted me-2"></i>
                                        <span><?= date('d.m.Y', strtotime($gonderi['olusturulma_tarihi'])) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-end pe-4">
                                    <div class="btn-group">
                                        <a href="?islem=duzenle_form&id=<?= $gonderi['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i> Düzenle
                                        </a>
                                        <a href="?islem=sil&id=<?= $gonderi['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Bu gönderiyi silmek istediğinize emin misiniz?')">
                                            <i class="fas fa-trash-alt me-1"></i> Sil
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
    /* Genel stil iyileştirmeleri */
    .table th {
        font-weight: 600;
        white-space: nowrap;
    }

    .btn-group .btn {
        padding: 0.375rem 0.75rem;
    }

    .hover-shadow-md {
        transition: all 0.2s ease;
    }

    .hover-shadow-md:hover {
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.1)!important;
    }

    /* Görsel stil iyileştirmeleri */
    .img-thumbnail {
        transition: all 0.2s ease-in-out;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .img-thumbnail:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .position-relative .position-absolute {
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }
    
    .position-relative:hover .position-absolute {
        opacity: 1;
    }

    /* Mobil görünüm iyileştirmeleri */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        
        .table td {
            padding: 1rem 0.5rem;
        }
        
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .btn-group .btn {
            width: 100%;
            border-radius: 0.25rem !important;
        }

        .img-thumbnail {
            width: 60px !important;
            height: 60px !important;
        }
    }

    /* Tablo responsive iyileştirmeleri */
    .table-responsive {
        margin: 0;
        padding: 0;
        border-radius: 0.5rem;
    }

    @media (max-width: 992px) {
        .table-responsive {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
    }
    </style>

    <script>
    // Gönderi arama fonksiyonu
    document.getElementById('gonderiArama').addEventListener('input', filterPosts);
    document.getElementById('tipFilter').addEventListener('change', filterPosts);
    document.getElementById('egzersizSayfaFilter').addEventListener('change', filterPosts);

    function filterPosts() {
        const searchText = document.getElementById('gonderiArama').value.toLowerCase();
        const selectedType = document.getElementById('tipFilter').value;
        const selectedPage = document.getElementById('egzersizSayfaFilter').value;
        const gonderiler = document.querySelectorAll('#gonderiTablosu tbody tr');
        
        gonderiler.forEach(gonderi => {
            const baslik = gonderi.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const yazar = gonderi.querySelector('td:first-child').textContent.toLowerCase();
            const type = gonderi.dataset.type;
            
            // Get exercise page ID from the link text if it exists
            const egzersizLink = gonderi.querySelector('td:nth-child(2) small');
            const egzersizPageId = egzersizLink ? gonderi.dataset.exercisePageId : null;
            
            const matchesSearch = baslik.includes(searchText) || yazar.includes(searchText);
            const matchesType = selectedType === 'all' || type === selectedType;
            const matchesPage = selectedPage === 'all' || 
                              (selectedPage && egzersizPageId === selectedPage);
            
            gonderi.style.display = (matchesSearch && matchesType && 
                                   (selectedPage === 'all' || type === 'blog' || matchesPage)) 
                                   ? '' : 'none';
        });
    }
    </script>
    <?php
}

// Gönderi ekleme formu
function gonderiEkleFormu($gonderiYonetim) {
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $is_blog = strpos($referrer, 'page=blog') !== false;
    $egzersiz_sayfalari = $gonderiYonetim->getEgzersizSayfalari();
    $selected_sayfa = isset($_GET['egzersiz_sayfa']) ? $_GET['egzersiz_sayfa'] : null;
    ?>
    <div class="container-fluid py-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold text-primary mb-1">
                    <i class="fas fa-plus-circle me-2"></i>Yeni Gönderi Ekle
                </h1>
                <p class="text-muted mb-0">Yeni bir gönderi oluşturun</p>
            </div>
            <div>
                <a href="?islem=liste" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="?islem=ekle_islem" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="baslik" class="form-label">Başlık</label>
                        <input type="text" class="form-control form-control-lg" id="baslik" name="baslik" required minlength="3">
                        <div class="invalid-feedback">
                            Lütfen en az 3 karakterden oluşan bir başlık giriniz.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="icerik" class="form-label">İçerik</label>
                        <textarea class="form-control" id="icerik" name="icerik" rows="10" required></textarea>
                        <div class="invalid-feedback">
                            Lütfen içerik giriniz.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="resim" class="form-label">Görsel</label>
                        <input type="file" class="form-control" id="resim" name="resim" accept="image/*" onchange="previewImage(this)">
                        <div class="form-text">Gönderi için bir görsel ekleyebilirsiniz (isteğe bağlı)</div>
                        <div id="imagePreview" class="mt-2" style="display: none;">
                            <img src="#" alt="Görsel Önizleme" style="max-width: 200px; max-height: 200px;">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="blog" name="blog" value="1" <?= $is_blog ? 'checked' : '' ?>>
                            <label class="form-check-label" for="blog">
                                Blog gönderisi olarak yayınla
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="egzersiz_sayfa" class="form-label">Egzersiz Sayfası</label>
                        <select class="form-select" id="egzersiz_sayfa" name="egzersiz_sayfa">
                            <option value="">Seçiniz...</option>
                            <?php while ($sayfa = $egzersiz_sayfalari->fetch_assoc()): ?>
                                <option value="<?= $sayfa['id'] ?>" <?= $selected_sayfa == $sayfa['sayfa_kodu'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sayfa['baslik']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Eğer bu bir egzersiz gönderisi ise, hangi sayfada görüntüleneceğini seçin</div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Gönderiyi Yayınla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // TinyMCE başlatma
        tinymce.init({
            selector: '#icerik',
            height: 500,
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; }',
            language: 'tr',
            images_upload_url: 'upload.php',
            images_upload_handler: function (blobInfo, success, failure) {
                var xhr, formData;
                xhr = new XMLHttpRequest();
                xhr.withCredentials = false;
                xhr.open('POST', 'upload.php');
                xhr.onload = function() {
                    var json;
                    if (xhr.status != 200) {
                        failure('HTTP Error: ' + xhr.status);
                        return;
                    }
                    json = JSON.parse(xhr.responseText);
                    if (!json || typeof json.location != 'string') {
                        failure('Invalid JSON: ' + xhr.responseText);
                        return;
                    }
                    success(json.location);
                };
                formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                xhr.send(formData);
            }
        });

        // Görsel önizleme fonksiyonu
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = preview.querySelector('img');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

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
    <?php
}

// Gönderi ekle işlem fonksiyonu
function gonderiEkleIslemi($gonderiYonetim) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: ?islem=liste");
        exit;
    }
    
    // Gerekli alanları doğrula
    if (empty($_POST['baslik']) || empty($_POST['icerik'])) {
        header("Location: ?islem=ekle_form&error=required");
        exit;
    }
    
    try {
        $baslik = trim($_POST['baslik']);
        $icerik = trim($_POST['icerik']);
        $yazar_id = $_SESSION['user_id'];
        
        $resim_adi = isset($_FILES['resim']) && $_FILES['resim']['error'] != UPLOAD_ERR_NO_FILE 
                    ? $gonderiYonetim->gorselYukle($_FILES['resim']) 
                    : null;
        
        $blog = isset($_POST['blog']) ? 1 : 0;
        $egzersiz_sayfa_id = isset($_POST['egzersiz_sayfa']) ? $_POST['egzersiz_sayfa'] : null;
        
        if ($gonderiYonetim->gonderiEkle($baslik, $icerik, $yazar_id, $blog, $egzersiz_sayfa_id, $resim_adi)) {
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            if (strpos($referrer, 'page=blog') !== false) {
                safeRedirect('../limon.php?page=blog&success=1');
            } else {
                safeRedirect('?islem=liste&success=1');
            }
        } else {
            throw new Exception("Gönderi eklenirken bir hata oluştu.");
        }
    } catch (Exception $e) {
        error_log("Gönderi ekleme hatası: " . $e->getMessage());
        safeRedirect('?islem=ekle_form&error=' . urlencode($e->getMessage()));
    }
    exit;
}

// Gönderi düzenleme formu
function gonderiDuzenleFormu($gonderiYonetim) {
    try {
        if (!isset($_GET['id'])) {
            header("Location: ?islem=liste");
            exit;
        }
        
        $id = intval($_GET['id']);
        $post = $gonderiYonetim->gonderiGetir($id);
        
        if (!$post) {
            throw new Exception('Gönderi bulunamadı.');
        }
        
        // Hata mesajını görüntüle
        if (isset($_GET['error'])) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
        }
        $egzersiz_sayfalari = $gonderiYonetim->getEgzersizSayfalari();
        ?>
        <div class="container-fluid py-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fw-bold text-primary mb-1">
                        <i class="fas fa-edit me-2"></i>Gönderi Düzenle
                    </h1>
                    <p class="text-muted mb-0">Gönderi içeriğini düzenleyin</p>
                </div>
                <div>
                    <a href="?islem=liste" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form action="?islem=duzenle_islem" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                        
                        <div class="mb-4">
                            <label for="baslik" class="form-label">Başlık</label>
                            <input type="text" class="form-control form-control-lg" id="baslik" name="baslik" 
                                   value="<?= htmlspecialchars($post['baslik']) ?>" required minlength="3">
                            <div class="invalid-feedback">
                                Lütfen en az 3 karakterden oluşan bir başlık giriniz.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="icerik" class="form-label">İçerik</label>
                            <textarea class="form-control" id="icerik" name="icerik" rows="10" required><?= htmlspecialchars($post['icerik']) ?></textarea>
                            <div class="invalid-feedback">
                                Lütfen içerik giriniz.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Mevcut Görsel</label>
                            <div id="onizleme-alanı" class="mb-3" <?= empty($post['resim']) ? 'style="display: none;"' : '' ?>>
                                <?php if (!empty($post['resim'])): ?>
                                    <img src="../assets/images/uploads/<?= htmlspecialchars($post['resim']) ?>" 
                                         id="resim-onizleme"
                                         alt="Gönderi Görseli" 
                                         class="img-thumbnail"
                                         style="max-width: 200px; max-height: 200px;">
                                    <button type="button" class="btn btn-sm btn-danger mt-2" onclick="gorselSil()">
                                        <i class="fas fa-trash-alt me-2"></i>Görseli Kaldır
                                    </button>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="resim_sil" id="resim_sil" value="0">
                            
                            <label for="resim" class="form-label">Yeni Görsel Yükle</label>
                            <input type="file" class="form-control" id="resim" name="resim" accept="image/*" onchange="previewImage(this)">
                            <div class="form-text">Mevcut görseli değiştirmek için yeni bir görsel yükleyebilirsiniz</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="blog" name="blog" value="1" 
                                       <?= $post['blog'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="blog">
                                    Blog gönderisi olarak yayınla
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="egzersiz_sayfa" class="form-label">Egzersiz Sayfası</label>
                            <select class="form-select" id="egzersiz_sayfa" name="egzersiz_sayfa">
                                <option value="">Seçiniz...</option>
                                <?php while ($sayfa = $egzersiz_sayfalari->fetch_assoc()): ?>
                                    <option value="<?= $sayfa['id'] ?>" <?= $post['egzersiz_sayfa_id'] == $sayfa['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sayfa['baslik']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text">Eğer bu bir egzersiz gönderisi ise, hangi sayfada görüntüleneceğini seçin</div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // TinyMCE initialization
            tinymce.init({
                selector: '#icerik',
                height: 500,
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
                toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; }',
                language: 'tr',
                images_upload_url: 'upload.php',
                images_upload_handler: function (blobInfo, success, failure) {
                    var xhr, formData;
                    xhr = new XMLHttpRequest();
                    xhr.withCredentials = false;
                    xhr.open('POST', 'upload.php');
                    xhr.onload = function() {
                        var json;
                        if (xhr.status != 200) {
                            failure('HTTP Error: ' + xhr.status);
                            return;
                        }
                        json = JSON.parse(xhr.responseText);
                        if (!json || typeof json.location != 'string') {
                            failure('Invalid JSON: ' + xhr.responseText);
                            return;
                        }
                        success(json.location);
                    };
                    formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    xhr.send(formData);
                }
            });

            // Görsel önizleme fonksiyonu
            function previewImage(input) {
                const preview = document.getElementById('resim-onizleme');
                const container = document.getElementById('onizleme-alanı');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        container.style.display = 'block';
                    }
                    reader.readAsDataURL(input.files[0]);
                    document.getElementById('resim_sil').value = '0';
                }
            }

            // Görsel silme fonksiyonu
            function gorselSil() {
                const preview = document.getElementById('resim-onizleme');
                const container = document.getElementById('onizleme-alanı');
                const input = document.getElementById('resim');
                preview.src = '#';
                container.style.display = 'none';
                input.value = '';
                document.getElementById('resim_sil').value = '1';
            }

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
        <?php
    } catch (Exception $e) {
        error_log("Gönderi düzenleme formu hatası: " . $e->getMessage());
        die($e->getMessage());
    }
}

// Gönderi düzenleme işlemi
function gonderiDuzenleIslemi($gonderiYonetim) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
        header("Location: ?islem=liste");
        exit;
    }
    
    $id = intval($_POST['id']);
    $baslik = $_POST['baslik'];
    $icerik = $_POST['icerik'];
    $blog = isset($_POST['blog']) ? 1 : 0;
    $egzersiz_sayfa_id = isset($_POST['egzersiz_sayfa']) ? $_POST['egzersiz_sayfa'] : null;
    
    // Resim işlemleri
    $resim_sil = isset($_POST['resim_sil']) && $_POST['resim_sil'] == '1';
    $yeni_resim = null;
    
    if (!$resim_sil && isset($_FILES['resim']) && $_FILES['resim']['error'] != UPLOAD_ERR_NO_FILE) {
        $yeni_resim = $gonderiYonetim->gorselYukle($_FILES['resim']);
        if ($yeni_resim) {
            $gonderiYonetim->resimGuncelle($id, $yeni_resim);
        }
    } elseif ($resim_sil) {
        $gonderiYonetim->resimGuncelle($id, null, true);
    }
    
    // Gönderi bilgilerini güncelle
    if ($gonderiYonetim->gonderiGuncelle($id, $baslik, $icerik, $blog, $egzersiz_sayfa_id)) {
        safeRedirect('?islem=liste&success=1');
    } else {
        safeRedirect('?islem=duzenle_form&id=' . $id . '&error=1');
    }
    exit;
}

// Gönderi silme işlemi
function gonderiSilIslemi($gonderiYonetim) {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: ?islem=liste");
        exit;
    }
    
    $gonderi_id = intval($_GET['id']);
    
    if ($gonderiYonetim->gonderiSil($gonderi_id)) {
        safeRedirect('?islem=liste&deleted=1');
    } else {
        safeRedirect('?islem=liste&error=delete');
    }
    exit;
}

// Dosya sonunda
ob_end_flush();
?>