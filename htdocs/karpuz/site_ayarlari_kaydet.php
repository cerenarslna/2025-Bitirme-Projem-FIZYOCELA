<?php
session_start();
require '../leblebi.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Erişim reddedildi!']));
}

// Gerekli parametrelerin kontrolü
if (!isset($_POST['key']) || !isset($_POST['value'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Gerekli parametreler eksik!']));
}

$anahtar = $_POST['key'];
$deger = $_POST['value'];

// Anahtar doğrulama
if (!in_array($anahtar, ['site_about'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Geçersiz ayar anahtarı!']));
}

try {
    // Ayarın mevcut olup olmadığını kontrol et
    $kontrol_sorgu = $conn->prepare("SELECT id FROM site_ayarlari WHERE setting_key = ?");
    $kontrol_sorgu->bind_param("s", $anahtar);
    $kontrol_sorgu->execute();
    $sonuc = $kontrol_sorgu->get_result();

    if ($sonuc->num_rows > 0) {
        // Mevcut ayarı güncelle
        $sorgu = $conn->prepare("UPDATE site_ayarlari SET setting_value = ? WHERE setting_key = ?");
        $sorgu->bind_param("ss", $deger, $anahtar);
    } else {
        // Yeni ayar ekle
        $sorgu = $conn->prepare("INSERT INTO site_ayarlari (setting_key, setting_value) VALUES (?, ?)");
        $sorgu->bind_param("ss", $anahtar, $deger);
    }

    if ($sorgu->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Veritabanı hatası: " . $sorgu->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 