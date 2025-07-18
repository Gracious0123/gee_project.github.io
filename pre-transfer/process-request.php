<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';
require_once 'includes/notification_functions.php';

// If admin (GET), else teacher (POST)
if (
    isset($_SESSION['role']) && $_SESSION['role'] === 'admin' &&
    isset($_GET['id']) && isset($_GET['action'])
) {
    $requestId = $_GET['id'];
    $action = $_GET['action'];
    $redirect = 'admin-dashboard.php';
} elseif (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['request_id']) && isset($_POST['action'])
) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    $redirect = 'my-requests.php';
} else {
    // Invalid access
    $redirect = isset($_SESSION['role']) && $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'my-requests.php';
    header("Location: $redirect");
    exit;
}

try {
    // Get the transfer request details
    $stmt = $pdo->prepare("
        SELECT tr.*, 
               u1.id as requester_id,
               u2.id as target_id,
               u1.school_id as requester_school_id,
               u2.school_id as target_school_id
        FROM transfer_requests tr
        JOIN users u1 ON tr.requester_id = u1.id
        JOIN users u2 ON tr.target_id = u2.id
        WHERE tr.id = ? AND tr.status = 'Pending'
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        throw new Exception("Transfer request not found or already processed");
    }

    // If teacher, ensure only the target can respond
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        if ($request['target_id'] != $_SESSION['user_id']) {
            throw new Exception("You are not authorized to respond to this request.");
        }
    }

    // Start transaction
    $pdo->beginTransaction();

    if (strtolower($action) === 'approve') {
        if ($_SESSION['role'] === 'admin') {
            // Admin is approving, this is the final step
            $stmt = $pdo->prepare("UPDATE transfer_requests SET status = 'Approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$requestId]);

            // Swap the schools of the two teachers
            $stmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE id = ?");
            $stmt->execute([$request['target_school_id'], $request['requester_id']]);
            $stmt->execute([$request['requester_school_id'], $request['target_id']]);

            // Notify both users of the final approval
            createNotification($request['requester_id'], 'request_approved', 'Transfer Approved', 'Your transfer has been finalized by the administrator.', 'my-requests.php');
            createNotification($request['target_id'], 'request_approved', 'Transfer Approved', 'Your transfer has been finalized by the administrator.', 'my-requests.php');
        } else {
            // Teacher is accepting, but not final approval
            $stmt = $pdo->prepare("UPDATE transfer_requests SET status = 'Accepted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$requestId]);
            // Notify requester that the target has accepted
            createNotification($request['requester_id'], 'request_accepted', 'Request Accepted', 'Your transfer request has been accepted by the target teacher. It now awaits admin approval.', 'my-requests.php');
        }
    } elseif (strtolower($action) === 'reject') {
        $stmt = $pdo->prepare("UPDATE transfer_requests SET status = 'Rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        createNotification($request['requester_id'], 'request_declined', 'Request Declined', 'Your transfer request has been declined by the target teacher.', 'my-requests.php');
    } else {
        throw new Exception("Invalid action");
    }

    $pdo->commit();
    $_SESSION['success_message'] = "Transfer request has been " . (strtolower($action) === 'approve' ? 'approved' : 'rejected');
    header("Location: $redirect");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    header("Location: $redirect");
    exit;
} 