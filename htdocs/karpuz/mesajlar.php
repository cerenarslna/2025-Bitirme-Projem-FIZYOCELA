<?php
// Güvenli erişim tanımla
define('SECURE_ACCESS', true);

session_start();
require '../leblebi.php';
include '../view/header.php';

/* Kullanıcı admin değil ise reddedilir */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    header('Location: ../limon.php');
    exit('Erişim reddedildi!');
}

// Sayfalama ayarları
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Temel sorgu
$base_query = "SELECT * FROM iletisim_mesajlari";
$count_query = "SELECT COUNT(*) as count FROM iletisim_mesajlari";

// Durum filtresi varsa
$where_clause = "";
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if ($status_filter && in_array($status_filter, ['beklemede', 'okundu', 'yanitlandi'])) {
    $where_clause = " WHERE durum = ?";
    $base_query .= $where_clause;
    $count_query .= $where_clause;
}

// Sıralama ekle
$base_query .= " ORDER BY tarih DESC LIMIT ? OFFSET ?";

// Mesajları al
$stmt = $conn->prepare($base_query);
if ($status_filter) {
    $stmt->bind_param("sii", $status_filter, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$mesajlar = $stmt->get_result();

// Toplam sayıyı al
$count_stmt = $conn->prepare($count_query);
if ($status_filter) {
    $count_stmt->bind_param("s", $status_filter);
}
$count_stmt->execute();
$total_count = $count_stmt->get_result()->fetch_assoc()['count'];
$total_pages = ceil($total_count / $limit);

// Her durum için sayıları al
$status_counts = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM iletisim_mesajlari")->fetch_assoc()['count'],
    'beklemede' => $conn->query("SELECT COUNT(*) as count FROM iletisim_mesajlari WHERE durum = 'beklemede'")->fetch_assoc()['count'],
    'okundu' => $conn->query("SELECT COUNT(*) as count FROM iletisim_mesajlari WHERE durum = 'okundu'")->fetch_assoc()['count'],
    'yanitlandi' => $conn->query("SELECT COUNT(*) as count FROM iletisim_mesajlari WHERE durum = 'yanitlandi'")->fetch_assoc()['count']
];

include 'includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <!-- Başlık Bölümü -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold mb-0">
                <i class="fas fa-cog me-2 gradient-text"></i>
                <span class="gradient-text">İletişim Mesajları</span>
            </h1>
            <p class="text-muted">Kullanıcılardan gelen mesajları yönetin ve yanıtlayın.</p>
        </div>
    </div>


    <!-- Durum Kartları -->
    <div class="row g-4 mb-4">
        <!-- Tüm Mesajlar -->
        <div class="col-md-3">
            <a href="?page=1" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= empty($status_filter) ? 'border-start border-5 border-primary' : '' ?>">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-kutusu bg-primary-subtle rounded-3 p-3 me-3">
                                <i class="fas fa-inbox fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0">Tüm Mesajlar</h5>
                        </div>
                        <h2 class="display-6 fw-bold mb-0"><?= $status_counts['total'] ?></h2>
                    </div>
                </div>
            </a>
        </div>

        <!-- Bekleyen Mesajlar -->
        <div class="col-md-3">
            <a href="?status=beklemede&page=1" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $status_filter === 'beklemede' ? 'border-start border-5 border-warning' : '' ?>">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-kutusu bg-warning-subtle rounded-3 p-3 me-3">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0">Bekleyen</h5>
                        </div>
                        <h2 class="display-6 fw-bold mb-0"><?= $status_counts['beklemede'] ?></h2>
                    </div>
                </div>
            </a>
        </div>

        <!-- Okunmuş Mesajlar -->
        <div class="col-md-3">
            <a href="?status=okundu&page=1" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $status_filter === 'okundu' ? 'border-start border-5 border-info' : '' ?>">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-kutusu bg-info-subtle rounded-3 p-3 me-3">
                                <i class="fas fa-envelope-open fa-2x text-info"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0">Okunmuş</h5>
                        </div>
                        <h2 class="display-6 fw-bold mb-0"><?= $status_counts['okundu'] ?></h2>
                    </div>
                </div>
            </a>
        </div>

        <!-- Yanıtlanmış Mesajlar -->
        <div class="col-md-3">
            <a href="?status=yanitlandi&page=1" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $status_filter === 'yanitlandi' ? 'border-start border-5 border-success' : '' ?>">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="icon-kutusu bg-success-subtle rounded-3 p-3 me-3">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0">Yanıtlanmış</h5>
                        </div>
                        <h2 class="display-6 fw-bold mb-0"><?= $status_counts['yanitlandi'] ?></h2>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Mesajlar Tablosu Kartı -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-list me-2"></i>Mesaj Listesi
                    </h5>
                </div>
                <div class="col-md-6">
                    <div class="search-container">
                        <i class="fas fa-search position-absolute ps-3" style="top: 50%; transform: translateY(-50%);"></i>
                        <input type="text" class="form-control ps-5" id="messageSearch" placeholder="Mesajlarda ara...">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3">Tarih</th>
                            <th class="px-4 py-3">Ad Soyad</th>
                            <th class="px-4 py-3">E-posta</th>
                            <th class="px-4 py-3">Konu</th>
                            <th class="px-4 py-3">Durum</th>
                            <th class="px-4 py-3 text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($mesaj = $mesajlar->fetch_assoc()): ?>
                            <tr class="message-row">
                                <td class="px-4"><?= date('d.m.Y H:i', strtotime($mesaj['tarih'])) ?></td>
                                <td class="px-4"><?= htmlspecialchars($mesaj['ad'] . ' ' . $mesaj['soyad']) ?></td>
                                <td class="px-4">
                                    <a href="mailto:<?= htmlspecialchars($mesaj['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($mesaj['email']) ?>
                                    </a>
                                </td>
                                <td class="px-4"><?= htmlspecialchars($mesaj['konu']) ?></td>
                                <td class="px-4">
                                    <span class="badge rounded-pill bg-<?= 
                                        $mesaj['durum'] === 'beklemede' ? 'warning' : 
                                        ($mesaj['durum'] === 'okundu' ? 'info' : 'success') 
                                    ?>">
                                        <?= ucfirst($mesaj['durum']) ?>
                                    </span>
                                </td>
                                <td class="px-4 text-end">
                                    <button class="btn btn-sm btn-primary view-message" 
                                            data-id="<?= $mesaj['id'] ?>" 
                                            title="Görüntüle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($mesaj['durum'] !== 'yanitlandi'): ?>
                                        <button class="btn btn-sm btn-success reply-message" 
                                                data-id="<?= $mesaj['id'] ?>" 
                                                title="Yanıtla">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger delete-message" 
                                            data-id="<?= $mesaj['id'] ?>" 
                                            title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($mesajlar->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p class="mb-0">Henüz mesaj bulunmuyor.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= $status_filter ? '&status=' . $status_filter : '' ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= $status_filter ? '&status=' . $status_filter : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= $status_filter ? '&status=' . $status_filter : '' ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mesaj Görüntüleme Modali -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-envelope me-2"></i>Mesaj Detayı
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- İçerik dinamik olarak yüklenecek -->
            </div>
        </div>
    </div>
</div>

<!-- Yanıtla Modali -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-reply me-2"></i>Mesajı Yanıtla
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="replyForm">
                    <input type="hidden" name="message_id" id="replyMessageId">
                    <div class="mb-3">
                        <label for="replyContent" class="form-label">Yanıtınız</label>
                        <textarea class="form-control" id="replyContent" name="reply" rows="5" required></textarea>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Yanıtla ve E-posta Gönder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.icon-kutusu {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    transition: transform 0.2s;
}

a:hover .card {
    transform: translateY(-5px);
}

.search-container {
    position: relative;
}

.message-row {
    transition: background-color 0.2s;
}

.message-row:hover {
    background-color: rgba(0,0,0,0.02);
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    margin: 0 2px;
}

@media (max-width: 768px) {
    .search-container {
        margin-top: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gerçek zamanlı arama
    document.getElementById('messageSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.message-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Mesajı görüntüle
    document.querySelectorAll('.view-message').forEach(button => {
        button.addEventListener('click', function() {
            const messageId = this.dataset.id;
            fetch('mesaj_detay.php?id=' + messageId)
                .then(response => response.text())
                .then(html => {
                    document.querySelector('#viewMessageModal .modal-body').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('viewMessageModal')).show();
                })
                .catch(error => {
                    alert('Mesaj detayı yüklenirken bir hata oluştu: ' + error.message);
                });
        });
    });

    // Mesajı yanıtla
    document.querySelectorAll('.reply-message').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('replyMessageId').value = this.dataset.id;
            new bootstrap.Modal(document.getElementById('replyModal')).show();
        });
    });

    // Yanıt formu gönderimi
    document.getElementById('replyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const modal = document.getElementById('replyModal');
        
        // Yükleme işlemi göstermek için butonu devre dışı bırak
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gönderiliyor...';
        
        fetch('mesaj_yanit.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())  // İlk olarak ham yanıt al
        .then(text => {
            try {
                return JSON.parse(text);  // JSON olarak çözümle
            } catch (e) {
                console.error('Server response:', text);  // Gerçek yanıtı logla
                throw new Error('Sunucu yanıtı geçersiz: ' + e.message);
            }
        })
        .then(data => {
            if (data.success) {
                // Başarı mesajı göster
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Bir hata oluştu');
            }
        })
        .catch(error => {
            // Hata mesajı göster
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: error.message
            });
        })
        .finally(() => {
            // Butonu tekrar etkinleştir ve orjinal metni geri yükle
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Yanıtla ve E-posta Gönder';
        });
    });

    // Mesajı sil
    document.querySelectorAll('.delete-message').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Bu mesajı silmek istediğinize emin misiniz?')) {
                const messageId = this.dataset.id;
                fetch('mesaj_sil.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + messageId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Mesaj silinirken bir hata oluştu: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error.message);
                });
            }
        });
    });
});
</script>

<?php include 'includes/sidebarend.php'; ?> 
</body>
</html> 