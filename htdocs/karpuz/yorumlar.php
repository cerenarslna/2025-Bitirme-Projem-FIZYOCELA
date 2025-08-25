<?php
// Güvenli erişim tanımlanıyor
define('SECURE_ACCESS', true);

session_start();
require '../leblebi.php';

// Toplu analiz isteğini işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_analysis') {
    header('Content-Type: application/json');
    
    // Admin erişimini kontrol et
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Erişim reddedildi!'
        ]);
        exit;
    }
    
    // Duygu analizi işlevlerini içe aktar
    require_once 'sentiment-araci/oto_analiz.php';
    
    // Toplu analiz yap
    $result = toplu_analiz_yap($conn);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => sprintf(
                '%d yorum başarıyla analiz edildi. %d yorum analiz edilemedi.',
                $result['analyzed'],
                $result['failed']
            )
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    exit;
}

include '../view/header.php'; 
/* Kullanıcı admin değil ise reddedilir */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    die('Erişim reddedildi!');
}

include 'includes/sidebar.php';

// Başarılı analiz mesajı kontrolü
$analiz_mesaji = '';
if (isset($_GET['analiz']) && $_GET['analiz'] == 'basarili') {
    $analiz_mesaji = '<div class="alert alert-success">Yorum başarıyla analiz edildi!</div>';
}

// İstatistikleri al
$stats_query = $conn->query("SELECT 
    COUNT(*) as toplam_yorum,
    COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as ana_yorum,
    COUNT(CASE WHEN parent_id IS NOT NULL THEN 1 END) as yanit,
    COUNT(CASE WHEN sentiment = 'Pozitif' THEN 1 END) as pozitif,
    COUNT(CASE WHEN sentiment = 'Nötr' THEN 1 END) as notr,
    COUNT(CASE WHEN sentiment = 'Negatif' THEN 1 END) as negatif,
    COUNT(CASE WHEN sentiment IS NULL THEN 1 END) as analiz_edilmemis,
    COUNT(CASE WHEN flag = 1 THEN 1 END) as flag_sayisi
FROM yorumlar");

$stats = $stats_query->fetch_assoc();

// Yorumları ve ilgili gönderi bilgilerini al
$query = $conn->query("SELECT y.id AS yorum_id, y.icerik, y.olusturulma_tarihi, y.gonderi_id, y.parent_id,
                              k.kullanici_adi, k.id as kullanici_id, k.profil_resmi, g.baslik, y.sentiment,
                              y.flag, y.flag_nedeni,
                              (SELECT COUNT(*) FROM yorum_begeniler WHERE yorum_id = y.id) as begeni_sayisi
                       FROM yorumlar y
                       JOIN kullanicilar k ON y.kullanici_id = k.id
                       JOIN gonderiler g ON y.gonderi_id = g.id
                       ORDER BY y.olusturulma_tarihi DESC");
?>

<div class="container-fluid py-4 px-4">
    <!-- Başlık Bölümü -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold mb-0">
                <i class="fas fa-comments me-2 gradient-text"></i>
                <span class="gradient-text">Yorum Yönetimi</span>
            </h1>
            <p class="text-muted">Tüm yorumları yönetin ve duygu analizi yapın</p>
        </div>
    </div>

    
    <?php if ($analiz_mesaji): ?>
        <div class="alert-container mb-4">
            <?php echo $analiz_mesaji; ?>
        </div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Toplam Yorum</h6>
                    <h3 class="mb-0"><?= number_format($stats['toplam_yorum']) ?></h3>
                    <small class="text-muted">
                        <?= number_format($stats['ana_yorum']) ?> ana yorum, 
                        <?= number_format($stats['yanit']) ?> yanıt
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Duygu Dağılımı</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success"><?= number_format($stats['pozitif']) ?> pozitif</span>
                        <span class="badge bg-secondary"><?= number_format($stats['notr']) ?> nötr</span>
                        <span class="badge bg-danger"><?= number_format($stats['negatif']) ?> negatif</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">İşaretli Yorumlar</h6>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-flag me-1"></i><?= number_format($stats['flag_sayisi']) ?> işaretli yorum
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Toplu İşlemler</h6>
                    <button class="btn btn-sm btn-primary" onclick="topluAnalizYap()">
                        <i class="fas fa-robot me-1"></i>Tümünü Analiz Et
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0 fw-bold">Yorum Filtreleme</h5>
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="yorumArama" class="form-control" placeholder="Yorumlarda ara...">
                        </div>
                        <select id="sentimentFiltre" class="form-select">
                            <option value="">Tüm duygular</option>
                            <option value="Pozitif">Pozitif</option>
                            <option value="Nötr">Nötr</option>
                            <option value="Negatif">Negatif</option>
                            <option value="null">Analiz edilmemiş</option>
                        </select>
                        <select id="yorumTipi" class="form-select">
                            <option value="">Tüm yorumlar</option>
                            <option value="ana">Ana yorumlar</option>
                            <option value="yanit">Yanıtlar</option>
                            <option value="flag">İşaretli yorumlar</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="yorumlar-listesi">
        <?php if ($query->num_rows > 0): ?>
            <?php while ($yorum = $query->fetch_assoc()): ?>
                <div class="card border-0 shadow-sm mb-3 yorum-kart" 
                     data-sentiment="<?= $yorum['sentiment'] ?? 'null' ?>"
                     data-tip="<?= $yorum['parent_id'] ? 'yanit' : 'ana' ?>"
                     data-flag="<?= $yorum['flag'] ? '1' : '0' ?>">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <a href="../profil.php?id=<?= $yorum['kullanici_id'] ?>" class="text-decoration-none d-flex align-items-center">
                                    <?php if ($yorum['profil_resmi']): ?>
                                        <div class="avatar avatar-sm rounded-circle bg-light me-3">
                                            <img src="../assets/images/profil/<?= htmlspecialchars($yorum['profil_resmi']) ?>" 
                                                 alt="<?= htmlspecialchars($yorum['kullanici_adi']) ?>"
                                                 class="rounded-circle"
                                                 width="36" height="36"
                                                 style="object-fit: cover;">
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar avatar-sm rounded-circle bg-primary me-3 d-flex align-items-center justify-content-center">
                                            <span class="text-white"><?= strtoupper(substr($yorum['kullanici_adi'], 0, 1)) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($yorum['kullanici_adi']) ?></h6>
                                        <small class="text-muted">
                                            <i class="far fa-clock me-1"></i><?= date('d.m.Y H:i', strtotime($yorum['olusturulma_tarihi'])) ?>
                                        </small>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-light text-dark">
                                    <i class="fas fa-heart me-1"></i><?= $yorum['begeni_sayisi'] ?> beğeni
                                </span>
                                <?php if ($yorum['sentiment']): ?>
                                    <span class="badge rounded-pill <?= $yorum['sentiment'] == 'Pozitif' ? 'bg-success' : ($yorum['sentiment'] == 'Negatif' ? 'bg-danger' : 'bg-secondary') ?>">
                                        <i class="fas <?= $yorum['sentiment'] == 'Pozitif' ? 'fa-smile' : ($yorum['sentiment'] == 'Negatif' ? 'fa-frown' : 'fa-meh') ?> me-1"></i>
                                        <?= htmlspecialchars($yorum['sentiment']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill bg-warning text-dark">
                                        <i class="fas fa-question-circle me-1"></i>Analiz edilmemiş
                                    </span>
                                <?php endif; ?>
                                <?php if ($yorum['flag']): ?>
                                    <span class="badge rounded-pill bg-danger">
                                        <i class="fas fa-flag me-1"></i>İşaretli
                                        <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="<?= htmlspecialchars($yorum['flag_nedeni']) ?>"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0 pb-3">
                        <div class="p-3 bg-light rounded mb-3">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($yorum['icerik'])) ?></p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-file-alt me-1"></i>Gönderi: 
                                    <a href="../gonderi.php?id=<?= $yorum['gonderi_id'] ?>" class="text-decoration-none" target="_blank">
                                        <?= htmlspecialchars($yorum['baslik']) ?>
                                    </a>
                                </span>
                                <?php if ($yorum['parent_id']): ?>
                                    <span class="badge bg-light text-dark ms-2">
                                        <i class="fas fa-reply me-1"></i>Yanıt
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="sentiment-araci/yorum_islemcisi.php">
                                    <input type="hidden" name="yorum_id" value="<?= $yorum['yorum_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $yorum['sentiment'] ? 'btn-outline-primary' : 'btn-primary' ?>">
                                        <i class="fas <?= $yorum['sentiment'] ? 'fa-sync-alt' : 'fa-robot' ?> me-1"></i>
                                        <?= $yorum['sentiment'] ? 'Yeniden Analiz Et' : 'Analiz Et' ?>
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="yorumSil(<?= $yorum['yorum_id'] ?>, <?= $yorum['gonderi_id'] ?>)">
                                    <i class="fas fa-trash-alt me-1"></i>Sil
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="far fa-comment-dots fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Henüz yorum bulunmuyor</h5>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Yorumlarda arama yapma ve filtreleme
function filterComments() {
    const searchText = document.getElementById('yorumArama').value.toLowerCase();
    const selectedSentiment = document.getElementById('sentimentFiltre').value;
    const selectedType = document.getElementById('yorumTipi').value;
    const yorumlar = document.querySelectorAll('.yorum-kart');
    
    yorumlar.forEach(yorum => {
        const yorumIcerik = yorum.querySelector('.card-body p').textContent.toLowerCase();
        const yorumYazar = yorum.querySelector('.card-header h6').textContent.toLowerCase();
        const yorumSentiment = yorum.dataset.sentiment;
        const yorumTip = yorum.dataset.tip;
        const yorumFlag = yorum.dataset.flag;
        
        const matchesSearch = yorumIcerik.includes(searchText) || yorumYazar.includes(searchText);
        const matchesSentiment = selectedSentiment === '' || yorumSentiment === selectedSentiment;
        const matchesType = selectedType === '' || 
                           (selectedType === 'flag' && yorumFlag === '1') || 
                           (selectedType !== 'flag' && yorumTip === selectedType);
        
        yorum.style.display = (matchesSearch && matchesSentiment && matchesType) ? 'block' : 'none';
    });
}

document.getElementById('yorumArama').addEventListener('input', filterComments);
document.getElementById('sentimentFiltre').addEventListener('change', filterComments);
document.getElementById('yorumTipi').addEventListener('change', filterComments);

// Bootstrap tooltips başlatma
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Yorum silme fonksiyonu
async function yorumSil(yorumId, gonderiId) {
    try {
        // Silme onayı
        const onay = await Swal.fire({
            title: 'Emin misiniz?',
            text: 'Bu yorumu silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, sil',
            cancelButtonText: 'İptal'
        });

        if (!onay.isConfirmed) {
            return;
        }

        // Yükleniyor göstergesi
        const loadingToast = Swal.fire({
            title: 'Yorum Siliniyor',
            html: 'Lütfen bekleyin...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        console.log('Silme isteği gönderiliyor:', { yorumId, gonderiId });

        // AJAX isteği
        const response = await fetch('../yorum_sil.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `yorum_id=${yorumId}&gonderi_id=${gonderiId}`
        });

        console.log('Ham yanıt:', response);
        const result = await response.json();
        console.log('İşlenmiş yanıt:', result);
        
        // Yükleniyor göstergesini kapat
        loadingToast.close();

        if (result.success) {
            // Başarılı silme
            await Swal.fire({
                icon: 'success',
                title: 'Başarılı',
                text: result.message || 'Yorum başarıyla silindi',
                confirmButtonText: 'Tamam'
            });
            
            // Sayfayı yenile
            window.location.reload();
        } else {
            // Hata durumu
            await Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: result.message || 'Yorum silinirken bir hata oluştu',
                confirmButtonText: 'Tamam'
            });
        }
    } catch (error) {
        console.error('Yorum silme hatası:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Hata',
            text: 'Yorum silinirken bir hata oluştu. Lütfen tekrar deneyin.',
            confirmButtonText: 'Tamam'
        });
    }
}

// Toplu analiz fonksiyonu
async function topluAnalizYap() {
    if (!confirm('Analiz edilmemiş tüm yorumları analiz etmek istediğinize emin misiniz?')) {
        return;
    }
    
    try {
        // Yükleniyor göstergesi
        const loadingToast = Swal.fire({
            title: 'Toplu Analiz Yapılıyor',
            html: 'Lütfen bekleyin...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // AJAX isteği
        const response = await fetch('yorumlar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=batch_analysis'
        });
        
        const result = await response.json();
        
        // Yükleniyor göstergesini kapat
        loadingToast.close();
        
        if (result.success) {
            // Başarılı sonuç
            await Swal.fire({
                icon: 'success',
                title: 'Toplu Analiz Tamamlandı',
                text: result.message,
                confirmButtonText: 'Tamam'
            });
            
            // Sayfayı yenile
            window.location.reload();
        } else {
            // Hata durumu
            await Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: result.message || 'Toplu analiz sırasında bir hata oluştu',
                confirmButtonText: 'Tamam'
            });
        }
    } catch (error) {
        console.error('Toplu analiz hatası:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Hata',
            text: 'Toplu analiz sırasında bir hata oluştu. Lütfen tekrar deneyin.',
            confirmButtonText: 'Tamam'
        });
    }
}
</script>

<style>
.avatar {
    width: 36px;
    height: 36px;
}
.badge {
    font-weight: 500;
    padding: 0.5em 0.7em;
}
.yorum-kart {
    transition: all 0.2s ease;
}
.yorum-kart:hover {
    transform: translateY(-2px);
}
.alert-container {
    position: relative;
}
.progress {
    border-radius: 1rem;
}
.progress-bar {
    transition: width 0.6s ease;
}
</style>
