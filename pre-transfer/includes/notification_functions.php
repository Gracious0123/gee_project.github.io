<?php
require_once __DIR__ . '/../database.php';

/**
 * Create a new notification
 * 
 * @param int $userId The ID of the user to notify
 * @param string $type The type of notification (request, message, request_accepted, request_declined, system)
 * @param string $title The notification title
 * @param string $message The notification message
 * @param string|null $link Optional link for the notification
 * @return bool True if notification was created successfully, false otherwise
 */
function createNotification($userId, $type, $title, $message, $link = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $type, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for a new transfer request
 */
function notifyNewTransferRequest($targetId, $requesterId) {
    global $pdo;
    
    try {
        // Get requester's information
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, s.name as school_name 
            FROM users u 
            JOIN schools s ON u.school_id = s.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$requesterId]);
        $requester = $stmt->fetch();
        
        if ($requester) {
            $title = "New Exchange Request";
            $message = sprintf(
                "%s %s from %s has sent you an exchange request.",
                $requester['first_name'],
                $requester['last_name'],
                $requester['school_name']
            );
            
            return createNotification(
                $targetId,
                'request',
                $title,
                $message,
                'requests.php'
            );
        }
    } catch (PDOException $e) {
        error_log("Error creating transfer request notification: " . $e->getMessage());
    }
    return false;
}

/**
 * Create a notification for a new message
 */
function notifyNewMessage($recipientId, $senderId, $messageText) {
    global $pdo;
    
    try {
        // Get sender's information
        $stmt = $pdo->prepare("
            SELECT first_name, last_name 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$senderId]);
        $sender = $stmt->fetch();
        
        if ($sender) {
            $title = "New Message";
            $message = sprintf(
                "%s %s sent you a message: \"%s\"",
                $sender['first_name'],
                $sender['last_name'],
                substr($messageText, 0, 100) . (strlen($messageText) > 100 ? '...' : '')
            );
            
            return createNotification(
                $recipientId,
                'message',
                $title,
                $message,
                'messages.php'
            );
        }
    } catch (PDOException $e) {
        error_log("Error creating message notification: " . $e->getMessage());
    }
    return false;
}

/**
 * Create a notification for request status change
 */
function notifyRequestStatusChange($requesterId, $targetId, $status) {
    global $pdo;
    
    try {
        // Get target's information
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, s.name as school_name 
            FROM users u 
            JOIN schools s ON u.school_id = s.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        
        if ($target) {
            if ($status === 'Approved') {
                $title = "Request Accepted";
                $message = sprintf(
                    "%s %s from %s accepted your exchange request.",
                    $target['first_name'],
                    $target['last_name'],
                    $target['school_name']
                );
                $type = 'request_accepted';
            } else {
                $title = "Request Declined";
                $message = sprintf(
                    "%s %s from %s declined your exchange request.",
                    $target['first_name'],
                    $target['last_name'],
                    $target['school_name']
                );
                $type = 'request_declined';
            }
            
            return createNotification(
                $requesterId,
                $type,
                $title,
                $message,
                'requests.php?tab=sent'
            );
        }
    } catch (PDOException $e) {
        error_log("Error creating request status notification: " . $e->getMessage());
    }
    return false;
}

/**
 * Create a system notification
 */
function notifySystem($userId, $title, $message, $link = null) {
    return createNotification(
        $userId,
        'system',
        $title,
        $message,
        $link
    );
} 