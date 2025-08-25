<?php
session_start();
require_once '../leblebi.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    exit('Erişim reddedildi!');
}

if (!isset($_GET['id'])) {
    exit('Mesaj ID bulunamadı.');
}

$id = (int)$_GET['id'];

// Mesaj detayını al
$stmt = $conn->prepare("SELECT * FROM iletisim_mesajlari WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$mesaj = $stmt->get_result()->fetch_assoc();

if (!$mesaj) {
    exit('Mesaj bulunamadı.');
}

// Durum 'beklemede' ise 'okundu' yap
if ($mesaj['durum'] === 'beklemede') {
    $update = $conn->prepare("UPDATE iletisim_mesajlari SET durum = 'okundu' WHERE id = ?");
    $update->bind_param("i", $id);
    $update->execute();
    $mesaj['durum'] = 'okundu';
}
?>

<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Gönderen</h6>
                <p class="mb-0"><?= htmlspecialchars($mesaj['ad'] . ' ' . $mesaj['soyad']) ?></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Tarih</h6>
                <p class="mb-0"><?= date('d.m.Y H:i', strtotime($mesaj['tarih'])) ?></p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">E-posta</h6>
                <p class="mb-0">
                    <a href="mailto:<?= htmlspecialchars($mesaj['email']) ?>">
                        <?= htmlspecialchars($mesaj['email']) ?>
                    </a>
                </p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Telefon</h6>
                <p class="mb-0">
                    <a href="tel:<?= htmlspecialchars($mesaj['telefon']) ?>">
                        <?= htmlspecialchars($mesaj['telefon']) ?>
                    </a>
                </p>
            </div>
        </div>

        <div class="mb-4">
            <h6 class="text-muted">Konu</h6>
            <p class="mb-0"><?= htmlspecialchars($mesaj['konu']) ?></p>
        </div>

        <div class="mb-4">
            <h6 class="text-muted">Mesaj</h6>
            <p class="mb-0"><?= nl2br(htmlspecialchars($mesaj['mesaj'])) ?></p>
        </div>

        <?php if ($mesaj['yanit']): ?>
        <div class="mt-4 pt-4 border-top">
            <h6 class="text-muted">Yanıt</h6>
            <p class="mb-2"><?= nl2br(htmlspecialchars($mesaj['yanit'])) ?></p>
            <small class="text-muted">
                Yanıtlanma Tarihi: <?= date('d.m.Y H:i', strtotime($mesaj['yanit_tarihi'])) ?>
            </small>
        </div>
        <?php endif; ?>
    </div>
</div> 