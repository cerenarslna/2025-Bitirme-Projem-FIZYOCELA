<?php
header('Content-Type: application/json');
require '../leblebi.php';

session_start();

// Güvenlik kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    die(json_encode(['success' => false, 'message' => 'Erişim reddedildi!']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Geçersiz istek metodu!']));
}

if (!isset($_FILES['file'])) {
    die(json_encode(['success' => false, 'message' => 'Dosya yüklenmedi!']));
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['json', 'csv'])) {
    die(json_encode(['success' => false, 'message' => 'Sadece JSON ve CSV dosyaları desteklenmektedir!']));
}

$words = [];

// JSON dosyası işleme
if ($ext === 'json') {
    $content = file_get_contents($file['tmp_name']);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        die(json_encode(['success' => false, 'message' => 'Geçersiz JSON formatı!']));
    }
    
    // JSON formatı kontrolü
    if (isset($data['kelimeler']) && is_array($data['kelimeler'])) {
        $words = $data['kelimeler'];
    } elseif (is_array($data)) {
        $words = $data;
    } else {
        die(json_encode(['success' => false, 'message' => 'Geçersiz JSON yapısı!']));
    }
}

// CSV dosyası işleme
if ($ext === 'csv') {
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            foreach ($data as $word) {
                $word = trim($word);
                if (!empty($word)) {
                    $words[] = $word;
                }
            }
        }
        fclose($handle);
    }
}

// Kelimeleri veritabanına ekle
$added = 0;
$stmt = $conn->prepare("INSERT IGNORE INTO yasakli_kelimeler (kelime) VALUES (?)");

foreach ($words as $word) {
    $word = trim($word);
    if (!empty($word)) {
        $stmt->bind_param("s", $word);
        if ($stmt->execute()) {
            $added += $stmt->affected_rows;
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => $added . ' kelime başarıyla eklendi.',
    'added_count' => $added
]); 