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
    if (isset($data['mark_all']) && $data['mark_all']) {
        // Mark all notifications as read
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } elseif (isset($data['notification_id'])) {
        // Mark single notification as read
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$data['notification_id'], $_SESSION['user_id']]);
    } else {
        throw new Exception('Invalid request');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Error marking notifications as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} 