<?php
session_start();
require '../leblebi.php';

// Süper yöneticinin veya yöneticinin erişimini kontrol et
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    die('Yetkisiz erişim!');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['kullanici_id']);
    $action = $_POST['action'];
    $hedef_kullanici = null;

    // Hedef kullanıcının rolünü al
    $check_sql = "SELECT rol FROM kullanicilar WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $hedef_kullanici = $row;
    }

    // İzin kontrolleri
    if ($_SESSION['rol'] === 'admin') {
        // Yöneticiler süper yöneticileri veya diğer yöneticileri değiştiremez
        if ($hedef_kullanici['rol'] === 'superuser' || $hedef_kullanici['rol'] === 'admin') {
            $_SESSION['error'] = 'Bu kullanıcı üzerinde işlem yapamazsınız.';
            header("Location: kullanicilar.php");
            exit;
        }
    }

    if ($action === 'ban' || $action === 'unban') {
        $is_banned = ($action === 'ban') ? 1 : 0;
        $sql = "UPDATE kullanicilar SET 
                is_banned = ?, 
                banned_by = ?, 
                ban_date = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $current_time = date('Y-m-d H:i:s');
        $banned_by = $is_banned ? $_SESSION['user_id'] : null;
        $stmt->bind_param('iisi', $is_banned, $banned_by, $current_time, $id);
    } elseif ($action === 'rol_degistir') {
        $yeni_rol = $_POST['yeni_rol'];
        $izin_verilen_roller = ['admin', 'fizyoterapist', 'user'];
        
        // Sadece süper yöneticiler admin atayabilir
        if ($yeni_rol === 'admin' && $_SESSION['rol'] !== 'superuser') {
            $_SESSION['error'] = 'Sadece süper yöneticiler admin atayabilir.';
            header("Location: kullanicilar.php");
            exit;
        }
        
        // Sadece süper yöneticiler süper yöneticileri atayabilir
        if ($_SESSION['rol'] === 'superuser') {
            $izin_verilen_roller[] = 'superuser';
        }
        
        if (!in_array($yeni_rol, $izin_verilen_roller)) {
            $_SESSION['error'] = 'Geçersiz rol.';
            header("Location: kullanicilar.php");
            exit;
        }
        
        $sql = "UPDATE kullanicilar SET rol = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $yeni_rol, $id);
    } else {
        $_SESSION['error'] = 'Geçersiz işlem.';
        header("Location: kullanicilar.php");
        exit;
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = 'İşlem başarıyla gerçekleştirildi.';
    } else {
        $_SESSION['error'] = 'İşlem sırasında bir hata oluştu.';
    }

    header("Location: kullanicilar.php");
    exit;
}
?>