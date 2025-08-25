<?php
session_start();
require_once '../leblebi.php';
require_once '../mail_functions.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erişim reddedildi!']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$reply = isset($_POST['reply']) ? trim($_POST['reply']) : '';

if (empty($reply)) {
    echo json_encode(['success' => false, 'message' => 'Yanıt boş olamaz']);
    exit;
}

try {
    // Mesaj detayını al
    $stmt = $conn->prepare("SELECT email, ad, soyad FROM iletisim_mesajlari WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message = $result->fetch_assoc();

    if (!$message) {
        throw new Exception('Mesaj bulunamadı');
    }

    $email_template = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Mesajınıza Yanıt</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { 
                background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
                padding: 20px; 
                text-align: center;
                border-radius: 10px 10px 0 0;
            }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; }
            .quote { 
                margin: 20px 0; 
                padding: 15px; 
                border-left: 4px solid #dd3675; 
                background-color: #f8f9fa; 
            }
            .logo { max-width: 200px; height: auto; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://fizyocela.site/assets/images/yenilogo.png' alt='FizyoCela Logo' class='logo'>
            </div>
            <div class='content'>
                <h2>Merhaba " . htmlspecialchars($message['ad'] . ' ' . $message['soyad']) . ",</h2>
                <p>Mesajınıza yanıtımız aşağıdadır:</p>
                <div class='quote'>
                    " . nl2br(htmlspecialchars($reply)) . "
                </div>
                <p>Başka sorularınız olursa bize yazmaktan çekinmeyin.</p>
            </div>
            <div class='footer'>
                <p>Saygılarımızla,<br>FizyoCela Ekibi</p>
                <p>
                    <a href='https://fizyocela.site'>www.fizyocela.site</a><br>
                    <small>Bu email otomatik olarak gönderilmiştir, lütfen yanıtlamayınız.</small>
                </p>
            </div>
        </div>
    </body>
    </html>";

    // Email gönderme işlemi
    $result = sendEmail($message['email'], 'FizyoCela - Mesajınıza Yanıt', $email_template);
    
    if ($result['success']) {
        // Veritabanında yanıt durumunu güncelle
        $stmt = $conn->prepare("UPDATE iletisim_mesajlari SET durum = 'yanitlandi', yanit = ?, yanit_tarihi = NOW() WHERE id = ?");
        $stmt->bind_param("si", $reply, $message_id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Yanıt başarıyla gönderildi ve kaydedildi.']);
        } else {
            throw new Exception('Veritabanı güncellenirken bir hata oluştu.');
        }
    } else {
        throw new Exception('Email gönderilemedi: ' . $result['message']);
    }

} catch (Exception $e) {
    error_log("Yanıt gönderme hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
} 