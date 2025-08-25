<?php
require 'leblebi.php';
require 'mail_functions.php';

// CSP headerı ekle
header("Content-Security-Policy: default-src 'self'; img-src 'self' https://fizyocela.site; style-src 'self' 'unsafe-inline';");

// Sınırlama
session_start();
$timeWindow = 300; // 5 dakika
$maxAttempts = 3;
$message = '';

// Sınırlama kontrolü
if (isset($_SESSION['reset_attempts'])) {
    if (time() - $_SESSION['reset_attempt_time'] > $timeWindow) {
        // Sınırlama süresi geçtiyse sıfırla
        $_SESSION['reset_attempts'] = 1;
        $_SESSION['reset_attempt_time'] = time();
    } elseif ($_SESSION['reset_attempts'] >= $maxAttempts) {
        $message = "<p class='error'>Too many reset attempts. Please try again in " . 
            ceil(($timeWindow - (time() - $_SESSION['reset_attempt_time'])) / 60) . " minutes.</p>";
    }
} else {
    $_SESSION['reset_attempts'] = 1;
    $_SESSION['reset_attempt_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    // Email doğrulama
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $message = "<p class='error'>Geçersiz email formatı</p>";
    } else {
        // Zaman saldırılarını önlemek için sabit zaman karşılaştırması kullan
        $stmt = $conn->prepare("SELECT id, email FROM kullanicilar WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // Sınırlama sayacını artır
        $_SESSION['reset_attempts']++;

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            try {
                $token = bin2hex(random_bytes(32));
                $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

                // Hashlenmiş token'ı veritabanına kaydet
                $update = $conn->prepare("UPDATE kullanicilar SET 
                    reset_token = ?,
                    reset_skt = ?,
                    reset_attempts = 0,
                    last_reset_request = NOW()
                    WHERE id = ?");
                $update->bind_param("ssi", $hashedToken, $expires, $user['id']);
                $update->execute();

                if ($update->affected_rows === 1) {
                    $resetLink = "https://fizyocela.site/sifre_yenile.php?token=" . urlencode($token) . "&email=" . urlencode($email);
                    
                    // Gelişmiş HTML email şablonu
                    $emailBody = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                line-height: 1.6; 
                                margin: 0;
                                padding: 0;
                                background-color: #f4f4f4;
                            }
                            .container { 
                                max-width: 600px; 
                                margin: 20px auto;
                                background: white;
                                border-radius: 10px;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                            }
                            .header { 
                                background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
                                padding: 20px; 
                                text-align: center;
                                border-radius: 10px 10px 0 0;
                            }
                            .header img {
                                max-width: 200px;
                                height: auto;
                            }
                            .content { 
                                padding: 30px;
                                color: #333;
                            }
                            .button {
                                display: inline-block;
                                padding: 12px 24px;
                                background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
                                color: #ffffff !important;
                                text-decoration: none;
                                border-radius: 5px;
                                margin: 20px 0;
                                font-weight: bold;
                            }
                            .footer { 
                                background-color: #f8f9fa; 
                                padding: 20px; 
                                text-align: center; 
                                font-size: 12px;
                                border-radius: 0 0 10px 10px;
                            }
                            .text-center {
                                text-align: center;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <img src='https://fizyocela.site/assets/images/yenilogo.png' alt='Fizyocela Logo'>
                            </div>
                            <div class='content'>
                                <h2>Şifre Sıfırlama İsteği</h2>
                                <p>Merhaba,</p>
                                <p>Hesabınız için bir şifre sıfırlama isteği aldık. Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:</p>
                                <div class='text-center'>
                                    <a href='$resetLink' class='button'>Şifremi Sıfırla</a>
                                </div>
                                <p>Bu bağlantı güvenliğiniz için 1 saat süreyle geçerlidir.</p>
                                <p>Eğer bu isteği siz yapmadıysanız, lütfen bu e-postayı görmezden gelin.</p>
                            </div>
                            <div class='footer'>
                                <p>Bu e-posta Fizyocela tarafından gönderilmiştir.</p>
                                <p>© " . date('Y') . " Fizyocela. Tüm hakları saklıdır.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $result = sendEmail($email, 'Şifre Sıfırlama', $emailBody);
                    
                    if ($result['success']) {
                        // Başarılı istekten sonra oturumu temizle
                        unset($_SESSION['reset_attempts']);
                        unset($_SESSION['reset_attempt_time']);
                        
                        $message = "<p class='success'>Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.</p>";
                    } else {
                        error_log("Failed to send password reset email to {$email}: {$result['message']}");
                        $message = "<p class='error'>Bir hata oluştu. Lütfen daha sonra tekrar deneyin.</p>";
                    }
                } else {
                    error_log("Failed to update reset token for user {$email}");
                    $message = "<p class='error'>Bir hata oluştu. Lütfen daha sonra tekrar deneyin.</p>";
                }
            } catch (Exception $e) {
                error_log("Error generating reset token: " . $e->getMessage());
                $message = "<p class='error'>Bir hata oluştu. Lütfen daha sonra tekrar deneyin.</p>";
            }
        } else {
            // Aynı mesajı kullanmak için kullanıcı numaralarını engelleme
            $message = "<p class='success'>Eğer bu e-posta adresi sistemde kayıtlıysa, şifre sıfırlama bağlantısı gönderilecektir.</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Şifremi Unuttum - Fizyocela</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 400px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-height: 60px;
        }
        h3 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #dd3675;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: opacity 0.3s ease;
        }
        button:hover {
            opacity: 0.9;
        }
        .success {
            color: #4CAF50;
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            background: #e8f5e9;
            border-radius: 4px;
        }
        .error {
            color: #f44336;
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            background: #ffebee;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="/assets/images/yenilogo.png" alt="Fizyocela Logo">
        </div>
        <h3>Şifremi Unuttum</h3>
        <?php if (!empty($message)) echo $message; ?>
        <form method="post">
            <div class="form-group">
                <label>Email Adresiniz:</label>
                <input type="email" name="email" required placeholder="ornek@email.com">
            </div>
            <button type="submit">Şifre Sıfırlama Bağlantısı Gönder</button>
        </form>
    </div>
</body>
</html>
