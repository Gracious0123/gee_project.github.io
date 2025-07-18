<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Check if the request exists and is pending, and the user is the requester
        $stmt = $pdo->prepare("SELECT * FROM transfer_requests WHERE id = ? AND requester_id = ? AND status = 'Pending'");
        $stmt->execute([$request_id, $user_id]);
        $request = $stmt->fetch();

        if ($request) {
            // Cancel (delete) the request
            $stmt = $pdo->prepare("DELETE FROM transfer_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $_SESSION['success_message'] = "Transfer request has been cancelled successfully.";
            header("Location: my-requests.php?success=cancelled");
            exit;
        } else {
            header("Location: my-requests.php?error=cancel_not_allowed");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error cancelling request: " . $e->getMessage());
        header("Location: my-requests.php?error=db_error");
        exit;
    }
}
header("Location: my-requests.php?error=invalid_cancel");
exit; 