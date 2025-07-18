<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    if (isset($data['delete_all']) && $data['delete_all']) {
        // Delete all notifications
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } elseif (isset($data['notification_id'])) {
        // Delete single notification
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$data['notification_id'], $_SESSION['user_id']]);
    } else {
        throw new Exception('Invalid request');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error deleting notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 