<?php
// Çıkış buffer'ı başlat
ob_start();

// Güvenli erişim tanımlanmamışsa tanımla
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Session başlatılmadıysa başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin erişim kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin', 'fizyoterapist'])) {
    ob_end_clean(); // Çıktıyı temizle
    header('Location: ../limon.php?error=yetki');
    exit('Erişim reddedildi!');
}

// Gerekli dosyaları dahil et
if (!defined('CONFIG_INCLUDED')) {
    define('CONFIG_INCLUDED', true);
    require_once '../leblebi.php';
}

// Güvenli yönlendirme fonksiyonu tanımlanmamışsa tanımla
if (!function_exists('safeRedirect')) {
    function safeRedirect($url) {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit();
        }
        
        echo '<script>window.location.href="' . $url . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
        exit();
    }
}
?> 