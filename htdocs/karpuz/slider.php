<?php
// Güvenli erişim tanımlanıyor
define('SECURE_ACCESS', true);

// Çıktı tamponlamasını en başta başlat
ob_start();

session_start();
require '../leblebi.php';
include '../view/header.php';

// AJAX isteği kontrol et
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit;
    }
    header('Location: ../limon.php');
    exit('Erişim reddedildi!');
}

// Slider işlemleri sınıfı
class SliderYonetim {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Slider öğelerini getir
    public function getSliderItems() {
        $sql = "SELECT s.*, g.baslik as gonderi_baslik 
                FROM slider_items s 
                LEFT JOIN gonderiler g ON s.gonderi_id = g.id 
                ORDER BY s.sira ASC";
        return $this->conn->query($sql);
    }
    
    // Slider ayarlarını getir
    public function getSliderSettings() {
        $sql = "SELECT * FROM slider_ayarlari LIMIT 1";
        $result = $this->conn->query($sql);
        if ($result->num_rows === 0) {
            // Eğer ayar yoksa, varsayılanı oluştur
            $this->conn->query("INSERT INTO slider_ayarlari (gecis_suresi) VALUES (5000)");
            return ['gecis_suresi' => 5000];
        }
        return $result->fetch_assoc();
    }
    
    // Slider ayarlarını güncelle
    public function updateSliderSettings($interval) {
        // Önce herhangi bir ayar var mı kontrol et
        $result = $this->conn->query("SELECT id FROM slider_ayarlari LIMIT 1");
        if ($result->num_rows === 0) {
            // Eğer ayar yoksa, yeni bir kayıt ekleyin
            $sql = "INSERT INTO slider_ayarlari (gecis_suresi) VALUES (?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $interval);
        } else {
            // Eğer ayar varsa, ilk satırı güncelleyin
            $sql = "UPDATE slider_ayarlari SET gecis_suresi = ? WHERE id = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $interval);
        }
        return $stmt->execute();
    }
    
    // Gönderileri getir (dropdown için)
    public function getGonderiler() {
        $sql = "SELECT id, baslik FROM gonderiler ORDER BY olusturulma_tarihi DESC";
        return $this->conn->query($sql);
    }

    
    // Resim yükleme
    public function uploadImage($file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = uniqid('slider_') . '.' . $ext;
            $targetPath = '../assets/images/slider/' . $newName;
            
            // Add error logging
            error_log("Attempting to upload file: " . $file['name']);
            error_log("Target path: " . $targetPath);
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                error_log("File upload successful: " . $newName);
                return $newName;
            } else {
                error_log("File upload failed. Error: " . error_get_last()['message']);
            }
        } else {
            error_log("File upload error code: " . $file['error']);
        }
        return null;
    }

    public function updateOrder($items) {
        $success = true;
        foreach ($items as $order => $id) {
            $stmt = $this->conn->prepare("UPDATE slider_items SET sira = ? WHERE id = ?");
            $stmt->bind_param("ii", $order, $id);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        return $success;
    }

    public function getSliderItem($id) {
        $stmt = $this->conn->prepare("SELECT * FROM slider_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateItemOrder($id, $newOrder) {
        // İşlem başlat
        $this->conn->begin_transaction();
        
        try {
            // Şu anki öğenin sırasını al
            $stmt = $this->conn->prepare("SELECT sira FROM slider_items WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentItem = $result->fetch_assoc();
            
            if ($currentItem) {
                $currentOrder = $currentItem['sira'];
                
                if ($newOrder > $currentOrder) {
                    // Aşağı kaydırma - öğeleri yukarı kaydır
                    $stmt = $this->conn->prepare("
                        UPDATE slider_items 
                        SET sira = sira - 1 
                        WHERE sira <= ? AND sira > ? AND id != ?
                    ");
                    $stmt->bind_param("iii", $newOrder, $currentOrder, $id);
                    $stmt->execute();
                } else {
                    // Yukarı kaydırma - öğeleri aşağı kaydır
                    $stmt = $this->conn->prepare("
                        UPDATE slider_items 
                        SET sira = sira + 1 
                        WHERE sira >= ? AND sira < ? AND id != ?
                    ");
                    $stmt->bind_param("iii", $newOrder, $currentOrder, $id);
                    $stmt->execute();
                }
                
                // Sıralamayı güncelle
                $stmt = $this->conn->prepare("UPDATE slider_items SET sira = ? WHERE id = ?");
                $stmt->bind_param("ii", $newOrder, $id);
                $stmt->execute();
                
                $this->conn->commit();
                return true;
            }
            
            $this->conn->rollback();
            return false;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
}

$sliderYonetim = new SliderYonetim($conn);

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $interval = intval($_POST['gecis_suresi']);
                if ($interval >= 1000 && $interval <= 10000) {
                    $sliderYonetim->updateSliderSettings($interval);
                }
                header('Location: slider.php');
                exit;
                break;
                
            case 'add':
                $type = $_POST['type'];
                $baslik = $_POST['baslik'];
                $aciklama = $_POST['aciklama'];
                $button_yazi = $_POST['button_yazi'];
                $button_link = $_POST['button_link'];
                $gonderiId = ($type === 'gonderi') ? $_POST['gonderi_id'] : null;
                $sira = $_POST['sira'];
                
                $image = null;
                if ($type === 'custom' && isset($_FILES['image'])) {
                    $image = $sliderYonetim->uploadImage($_FILES['image']);
                }
                
                $stmt = $conn->prepare("INSERT INTO slider_items (type, baslik, aciklama, image, button_yazi, button_link, gonderi_id, sira) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssii", $type, $baslik, $aciklama, $image, $button_yazi, $button_link, $gonderiId, $sira);
                $stmt->execute();
                break;
                
            case 'update':
                $id = $_POST['id'];
                $aktif = isset($_POST['aktif']) ? 1 : 0;
                $sira = $_POST['sira'];
                
                $stmt = $conn->prepare("UPDATE slider_items SET aktif = ?, sira = ? WHERE id = ?");
                $stmt->bind_param("iii", $aktif, $sira, $id);
                $stmt->execute();
                break;
                
            case 'delete':
                $id = $_POST['id'];
                // Önce resmi sil
                $stmt = $conn->prepare("SELECT image FROM slider_items WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    if ($row['image']) {
                        @unlink('../assets/images/slider/' . $row['image']);
                    }
                }
                
                // Sonra kaydı sil
                $stmt = $conn->prepare("DELETE FROM slider_items WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                break;

            case 'update_order':
                if (isset($_POST['id']) && isset($_POST['sira'])) {
                    $id = intval($_POST['id']);
                    $newOrder = intval($_POST['sira']);
                    
                    try {
                        $success = $sliderYonetim->updateItemOrder($id, $newOrder);
                        header('Content-Type: application/json');
                        echo json_encode(['success' => $success]);
                    } catch (Exception $e) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                    exit;
                }
                break;

            case 'edit':
                $id = $_POST['id'];
                $type = $_POST['type'];
                $baslik = $_POST['baslik'];
                $aciklama = $_POST['aciklama'];
                $button_yazi = $_POST['button_yazi'];
                $button_link = $_POST['button_link'];
                $gonderiId = ($type === 'gonderi') ? $_POST['gonderi_id'] : null;
                
                $image = null;
                if ($type === 'custom' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // Eğer eski resim varsa sil
                    $old_item = $sliderYonetim->getSliderItem($id);
                    if ($old_item && $old_item['image']) {
                        @unlink('../assets/images/slider/' . $old_item['image']);
                    }
                    $image = $sliderYonetim->uploadImage($_FILES['image']);
                }
                
                $sql = "UPDATE slider_items SET type = ?, baslik = ?, aciklama = ?, button_yazi = ?, button_link = ?, gonderi_id = ?";
                $params = [$type, $baslik, $aciklama, $button_yazi, $button_link, $gonderiId];
                $types = "sssssi";
                
                if ($image !== null) {
                    $sql .= ", image = ?";
                    $params[] = $image;
                    $types .= "s";
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                $types .= "i";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                
                header('Location: slider.php');
                exit;
                break;
        }
        
        header('Location: slider.php');
        exit;
    }
}

include 'includes/sidebar.php';

// Slider ayarlarını al
$sliderSettings = $sliderYonetim->getSliderSettings();
?>
<script>

document.addEventListener('DOMContentLoaded', function() {
    // Slayt stilini değiştir
    document.getElementById('slideType').addEventListener('change', function() {
        const customFields = document.getElementById('customFields');
        const gonderiFields = document.getElementById('gonderiFields');
        const buttonFields = document.getElementById('buttonFields');
        const showButtonCheck = document.getElementById('showButton');
        
        if (this.value === 'custom') {
            customFields.style.display = 'block';
            gonderiFields.style.display = 'none';
        } else {
            customFields.style.display = 'none';
            gonderiFields.style.display = 'block';
            showButtonCheck.checked = false;
            buttonFields.style.display = 'none';
        }
    });

    // Buton alanını göster/gizle
    window.toggleButtonFields = function() {
        const buttonFields = document.getElementById('buttonFields');
        const showButton = document.getElementById('showButton');
        buttonFields.style.display = showButton.checked ? 'block' : 'none';
        
        if (!showButton.checked) {
            document.getElementById('button_yazi').value = '';
            document.getElementById('button_link').value = '';
        }
    }
});

</script>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Başlık Bölümü -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="fw-bold mb-0">
                    <i class="fas fa-cog me-2 gradient-text"></i>
                    <span class="gradient-text">Slider Yönetimi</span>
                </h1>
                <p class="text-muted">Ana sayfa slider içeriklerini yönetin</p>
            </div>
        </div>
        <button type="button" class="btn btn-primary gradient-custom" data-bs-toggle="modal" data-bs-target="#addSliderModal">
            <i class="fas fa-plus-circle me-2"></i>Yeni Slide Ekle
        </button>
    </div>

    <!-- Slider Ayarları -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light py-3">
            <h5 class="mb-0">Slider Ayarları</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row align-items-end">
                <input type="hidden" name="action" value="update_settings">
                <div class="col-md-6">
                    <label class="form-label">Geçiş Süresi (ms)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="gecis_suresi" 
                               value="<?= htmlspecialchars($sliderSettings['gecis_suresi']) ?>" 
                               min="1000" max="10000" step="500" required>
                        <span class="input-group-text">ms</span>
                    </div>
                    <div class="form-text">Her slaytın görüntülenme süresi (1000ms = 1 saniye)</div>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Slider Listesi -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3 px-4">Görsel</th>
                            <th class="py-3">Başlık</th>
                            <th class="py-3">Tür</th>
                            <th class="py-3" style="width: 100px">Sıra</th>
                            <th class="py-3">Durum</th>
                            <th class="py-3 text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sliderItems = $sliderYonetim->getSliderItems();
                        while ($item = $sliderItems->fetch_assoc()):
                            $imageUrl = '';
                            if ($item['type'] === 'custom' && $item['image']) {
                                $imageUrl = '../assets/images/slider/' . $item['image'];
                            } elseif ($item['type'] === 'gonderi') {
                                $stmt = $conn->prepare("SELECT resim FROM gonderiler WHERE id = ?");
                                $stmt->bind_param("i", $item['gonderi_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($gonderi = $result->fetch_assoc()) {
                                    $imageUrl = '../assets/images/uploads/' . $gonderi['resim'];
                                }
                            }
                        ?>
                        <tr>
                            <td class="py-3 px-4">
                                <?php if ($imageUrl): ?>
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Slider Görsel" 
                                         style="width: 100px; height: 60px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div class="bg-light rounded" style="width: 100px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?= htmlspecialchars($item['type'] === 'gonderi' ? $item['gonderi_baslik'] : $item['baslik']) ?>
                            </td>
                            <td class="py-3">
                                <span class="badge <?= $item['type'] === 'custom' ? 'bg-primary' : 'bg-info' ?>">
                                    <?= $item['type'] === 'custom' ? 'Özel' : 'Gönderi' ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <input type="number" 
                                       class="form-control form-control-sm order-input" 
                                       value="<?= $item['sira'] ?>" 
                                       min="1"
                                       data-id="<?= $item['id'] ?>"
                                       style="width: 80px">
                            </td>
                            <td class="py-3">
                                <form method="post" class="d-inline-flex align-items-center">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="sira" value="<?= $item['sira'] ?>">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="aktif" 
                                               <?= $item['aktif'] ? 'checked' : '' ?> 
                                               onchange="this.form.submit()">
                                    </div>
                                </form>
                            </td>
                            <td class="py-3 text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                        onclick="editSlide(<?= htmlspecialchars(json_encode($item)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Bu slider öğesini silmek istediğinize emin misiniz?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash-alt"></i>
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

<!-- Yeni Slide Ekleme Modal -->
<div class="modal fade" id="addSliderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Slide Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data" id="sliderForm" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Slide Türü</label>
                        <select class="form-select" name="type" id="slideType" required>
                            <option value="custom">Özel Slide</option>
                            <option value="gonderi">Gönderi Slide</option>
                        </select>
                    </div>
                    
                    <div id="customFields">
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="baslik">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Görsel</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="showButton" onchange="toggleButtonFields()">
                                <label class="form-check-label" for="showButton">
                                    Buton Ekle
                                </label>
                            </div>
                        </div>
                        
                        <div id="buttonFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Buton Yazısı</label>
                                <input type="text" class="form-control" name="button_yazi">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Buton Link</label>
                                <input type="text" class="form-control" name="button_link">
                            </div>
                        </div>
                    </div>
                    
                    <div id="gonderiFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Gönderi Seç</label>
                            <select class="form-select" name="gonderi_id">
                                <?php
                                $gonderiler = $sliderYonetim->getGonderiler();
                                while ($gonderi = $gonderiler->fetch_assoc()):
                                ?>
                                <option value="<?= $gonderi['id'] ?>"><?= htmlspecialchars($gonderi['baslik']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sıra</label>
                        <input type="number" class="form-control" name="sira" value="0" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Slider Düzenleme Modal -->
<div class="modal fade" id="editSliderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Slider Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data" id="editSliderForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Slide Türü</label>
                        <select class="form-select" name="type" id="edit_slideType" required>
                            <option value="custom">Özel Slide</option>
                            <option value="gonderi">Gönderi Slide</option>
                        </select>
                    </div>
                    
                    <div id="edit_customFields">
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="baslik" id="edit_baslik">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" id="edit_aciklama" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Görsel</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <small class="form-text text-muted">Yeni görsel yüklemezseniz mevcut görsel korunacaktır.</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_showButton" onchange="toggleEditButtonFields()">
                                <label class="form-check-label" for="edit_showButton">
                                    Buton Ekle
                                </label>
                            </div>
                        </div>
                        
                        <div id="edit_buttonFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Buton Yazısı</label>
                                <input type="text" class="form-control" name="button_yazi" id="edit_button_yazi">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Buton Link</label>
                                <input type="text" class="form-control" name="button_link" id="edit_button_link">
                            </div>
                        </div>
                    </div>
                    
                    <div id="edit_gonderiFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Gönderi Seç</label>
                            <select class="form-select" name="gonderi_id" id="edit_gonderi_id">
                                <?php
                                $gonderiler = $sliderYonetim->getGonderiler();
                                while ($gonderi = $gonderiler->fetch_assoc()):
                                ?>
                                <option value="<?= $gonderi['id'] ?>"><?= htmlspecialchars($gonderi['baslik']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Sıralama işlemleri
document.querySelectorAll('.order-input').forEach(input => {
    let originalValue = input.value;
    
    input.addEventListener('change', function() {
        const id = this.dataset.id;
        let newOrder = parseInt(this.value);
        
        // Girişi doğrula
        if (isNaN(newOrder) || newOrder < 1) {
            this.value = originalValue;
            alert('Lütfen geçerli bir sayı giriniz (minimum 1)');
            return;
        }
        
        // Yükleme işleminin gösterilmesi
        this.disabled = true;
        const loadingSpinner = document.createElement('span');
        loadingSpinner.className = 'spinner-border spinner-border-sm ms-2';
        loadingSpinner.setAttribute('role', 'status');
        this.parentNode.appendChild(loadingSpinner);
        
        // İstek gönder
        fetch('ajax/update_slider_order.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `id=${id}&sira=${newOrder}`
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (!response.ok || !contentType || !contentType.includes('application/json')) {
                throw new Error('Server error occurred');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                throw new Error(data.error || 'Sıralama güncellenirken bir hata oluştu.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Sıralama güncellenirken bir hata oluştu: ' + error.message);
            this.value = originalValue;
            this.disabled = false;
            if (loadingSpinner) {
                loadingSpinner.remove();
            }
        });
    });
    
    // Giriş doğrulaması ekle
    input.addEventListener('input', function() {
        if (this.value === '') return;
        
        let value = parseInt(this.value);
        if (isNaN(value) || value < 1) {
            this.value = 1;
        }
    });
});

// Düzenleme işlevi
function editSlide(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_slideType').value = item.type;
    document.getElementById('edit_baslik').value = item.baslik || '';
    document.getElementById('edit_aciklama').value = item.aciklama || '';
    
    if (item.type === 'custom') {
        document.getElementById('edit_customFields').style.display = 'block';
        document.getElementById('edit_gonderiFields').style.display = 'none';
        
        // Buton alanlarını işle
        const hasButton = item.button_yazi && item.button_link;
        document.getElementById('edit_showButton').checked = hasButton;
        document.getElementById('edit_buttonFields').style.display = hasButton ? 'block' : 'none';
        document.getElementById('edit_button_yazi').value = item.button_yazi || '';
        document.getElementById('edit_button_link').value = item.button_link || '';
    } else {
        document.getElementById('edit_customFields').style.display = 'none';
        document.getElementById('edit_gonderiFields').style.display = 'block';
        document.getElementById('edit_gonderi_id').value = item.gonderi_id;
    }
    
    new bootstrap.Modal(document.getElementById('editSliderModal')).show();
}

document.getElementById('edit_slideType').addEventListener('change', function() {
    const customFields = document.getElementById('edit_customFields');
    const gonderiFields = document.getElementById('edit_gonderiFields');
    const buttonFields = document.getElementById('edit_buttonFields');
    const showButtonCheck = document.getElementById('edit_showButton');
    
    if (this.value === 'custom') {
        customFields.style.display = 'block';
        gonderiFields.style.display = 'none';
    } else {
        customFields.style.display = 'none';
        gonderiFields.style.display = 'block';
        showButtonCheck.checked = false;
        buttonFields.style.display = 'none';
    }
});

function toggleEditButtonFields() {
    const buttonFields = document.getElementById('edit_buttonFields');
    const showButton = document.getElementById('edit_showButton');
    buttonFields.style.display = showButton.checked ? 'block' : 'none';
    
    if (!showButton.checked) {
        document.getElementById('edit_button_yazi').value = '';
        document.getElementById('edit_button_link').value = '';
    }
}
</script>

<style>
.order-input::-webkit-inner-spin-button,
.order-input::-webkit-outer-spin-button {
    opacity: 1;
}

.order-input {
    text-align: center;
}
</style>

<?php include 'includes/sidebarend.php'; ?> 