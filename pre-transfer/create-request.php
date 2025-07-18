<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';
require_once 'includes/notification_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = $_POST['target_id'] ?? '';
    
    if (!empty($target_id)) {
        try {
            // Get requester's school ID
            $stmt = $pdo->prepare("SELECT school_id FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $requester = $stmt->fetch();
            
            // Get target's school ID
            $stmt = $pdo->prepare("SELECT school_id FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $target = $stmt->fetch();
            
            if ($requester && $target) {
                // Check if there's already a pending request between these users
                $stmt = $pdo->prepare("
                    SELECT id FROM transfer_requests 
                    WHERE ((requester_id = ? AND target_id = ?) OR (requester_id = ? AND target_id = ?))
                    AND status = 'Pending'
                ");
                $stmt->execute([$_SESSION['user_id'], $target_id, $target_id, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() === 0) {
                    // Create the transfer request
                    $stmt = $pdo->prepare("
                        INSERT INTO transfer_requests (
                            requester_id, 
                            target_id, 
                            requester_school_id, 
                            target_school_id
                        ) VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $target_id,
                        $requester['school_id'],
                        $target['school_id']
                    ]);
                    
                    // Send notification to the target user
                    notifyNewTransferRequest($target_id, $_SESSION['user_id']);
                    
                    // Redirect with success message
                    header("Location: my-requests.php?success=1");
                    exit;
                } else {
                    // Redirect with error message
                    header("Location: my-requests.php?error=existing_request");
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error creating transfer request: " . $e->getMessage());
            header("Location: my-requests.php?error=db_error");
            exit;
        }
    }
}

// If we get here, something went wrong
header("Location: my-requests.php?error=invalid_request");
exit; 