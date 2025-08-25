<?php
// Eğer direkt istek yapılıyorsa header'ı ayarla
if (!defined('SECURE_ACCESS')) {
    header('Content-Type: application/json');
}
require __DIR__ . '/../../leblebi.php';

function kontrolEt($icerik) {
    global $conn;
    
    // Yasaklı kelimeleri al
    $stmt = $conn->prepare("SELECT kelime FROM yasakli_kelimeler");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $yasakli_kelimeler = [];
    while ($row = $result->fetch_assoc()) {
        $yasakli_kelimeler[] = $row['kelime'];
    }
    
    // Yorumu küçük harfe çevir
    $icerik = mb_strtolower($icerik, 'UTF-8');
    
    // Bulunan yasaklı kelimeleri sakla
    $bulunan_kelimeler = [];
    
    foreach ($yasakli_kelimeler as $kelime) {
        // Kelimeyi küçük harfe çevir
        $kelime = mb_strtolower($kelime, 'UTF-8');
        
        // Regex pattern oluştur - kelime sınırları (\b) ile tam kelime eşleşmesi
        $pattern = '/\b' . preg_quote($kelime, '/') . '\b/u';
        
        // Eşleşme kontrolü
        if (preg_match($pattern, $icerik)) {
            $bulunan_kelimeler[] = $kelime;
        }
    }
    
    return [
        'flag' => !empty($bulunan_kelimeler),
        'bulunan_kelimeler' => $bulunan_kelimeler
    ];
}

//Eğer direkt istek yapılıyorsa POST isteği için işlem yap
if (!defined('SECURE_ACCESS') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug için gelen veriyi logla
    error_log("POST data received: " . print_r($_POST, true));
    error_log("Raw input: " . file_get_contents('php://input'));

    // Hem JSON hem de form verisi için kontrol
    $icerik = null;
    
    // JSON verisi kontrolü
    $json_data = json_decode(file_get_contents('php://input'), true);
    if ($json_data && isset($json_data['icerik'])) {
        $icerik = $json_data['icerik'];
    }
    
    // Form verisi kontrolü
    if (isset($_POST['icerik'])) {
        $icerik = $_POST['icerik'];
    }
    
    if ($icerik === null) {
        echo json_encode([
            'basarili' => false,
            'mesaj' => 'İçerik bulunamadı'
        ]);
        exit;
    }
    
    $sonuc = kontrolEt($icerik);
    
    echo json_encode([
        'basarili' => true,
        'flag' => $sonuc['flag'],
        'bulunan_kelimeler' => $sonuc['bulunan_kelimeler']
    ]);
} elseif (!defined('SECURE_ACCESS')) {
    echo json_encode([
        'basarili' => false,
        'mesaj' => 'Geçersiz istek metodu'
    ]);
} 