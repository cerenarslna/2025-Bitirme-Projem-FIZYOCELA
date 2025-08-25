<?php
define('SECURE_ACCESS', true);
session_start();
require '../leblebi.php';

// Check admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['superuser', 'admin'])) {
    die(json_encode(['success' => false, 'message' => 'Erişim reddedildi!']));
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM egzersiz_sayfalari WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sayfa bulunamadı!']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek!']);
} 