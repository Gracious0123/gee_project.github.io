<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Mark incoming requests as viewed by the target user
try {
    $stmt = $pdo->prepare("
        UPDATE transfer_requests 
        SET is_viewed_by_target = 1 
        WHERE target_id = ? AND is_viewed_by_target = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Error marking requests as viewed: " . $e->getMessage());
}

// Handle success and error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch user's transfer requests
try {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               u1.first_name as requester_first_name, 
               u1.last_name as requester_last_name,
               u2.first_name as target_first_name, 
               u2.last_name as target_last_name,
               s1.name as requester_school,
               s2.name as target_school
        FROM transfer_requests r
        JOIN users u1 ON r.requester_id = u1.id
        JOIN users u2 ON r.target_id = u2.id
        JOIN schools s1 ON u1.school_id = s1.id
        JOIN schools s2 ON u2.school_id = s2.id
        WHERE r.requester_id = ? OR r.target_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user requests: " . $e->getMessage());
    $requests = [];
}

// Fetch potential transfer partners
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, s.name as school_name
        FROM users u
        JOIN schools s ON u.school_id = s.id
        WHERE u.id != ? 
        AND u.school_id != (SELECT school_id FROM users WHERE id = ?)
        AND NOT EXISTS (
            SELECT 1 FROM transfer_requests 
            WHERE ((requester_id = ? AND target_id = u.id) OR (requester_id = u.id AND target_id = ?))
            AND status = 'Pending'
        )
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $potential_partners = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching potential partners: " . $e->getMessage());
    $potential_partners = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #2f4956;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }
        .back-link {
            color: #ffffff !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 1px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        .card {
            background: #18323a;
            border-radius: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            margin-bottom: 2.5rem;
            overflow: hidden;
            width: 100%;
        }
        .card-header {
            background: linear-gradient(90deg, var(--border-color) 0%, var(--accent-color) 100%);
            padding: 1.5rem 2rem 1rem 2rem;
        }
        .card-title {
            color: #3ed6c5;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .card-description {
            color: #b2e6e0;
            font-size: 1rem;
        }
        .card-content {
            padding: 1.5rem 2rem 2rem 2rem;
        }
        .new-request-form {
            background: #18323a;
            border-radius: 1.25rem;
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .form-label, .card-title {
            color: #3ed6c5;
            font-weight: 700;
        }
        .form-select, .btn-primary {
            background: #699eb4;
            color: #18323a;
            border: none;
            border-radius: 0.75rem;
            font-size: 1.1rem;
            font-weight: 500;
        }
        .form-select {
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
        }
        .btn-primary {
            padding: 0.75rem 1.5rem;
            margin-top: 0.5rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #2fd6c5;
        }
        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            margin-top: 1.5rem;
        }
        .request-card {
            background: #18323a;
            border-radius: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            padding: 1.5rem 2rem 1.5rem 2rem;
            border-left: 6px solid #3ed6c5;
            position: relative;
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        .request-info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .request-info .arrow {
            color: #3ed6c5;
            font-size: 1.2rem;
            margin: 0 0.5rem;
        }
        .request-info p {
            margin: 0.25rem 0 0;
            color: #b2e6e0;
            font-size: 1rem;
        }
        .request-status {
            margin-left: 1rem;
            display: flex;
            align-items: center;
        }
        .status-badge {
            display: inline-block;
            padding: 0.45em 1.2em;
            border-radius: 20px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            background: #fff;
            color: #18323a;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
        }
        .status-pending {
            background: #fff;
            color: #F6AD55;
        }
        .status-approved {
            background: #fff;
            color: #4FD1C5;
        }
        .status-rejected {
            background: #fff;
            color: #FC8181;
        }
        .request-details {
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .detail-item {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }
        .detail-label {
            color: #b2e6e0;
            min-width: 110px;
            font-weight: 500;
        }
        .detail-value {
            color: #fff;
        }
        .request-actions {
            display: flex;
            gap: 1rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }
        .request-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 2rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .request-actions .btn-success {
            background: #3ed6c5;
            color: #18323a;
        }
        .request-actions .btn-danger {
            background: #FC8181;
            color: #fff;
        }
        .request-actions .btn-cancel {
            background: #A0AEC0;
            color: #fff;
        }
        .request-actions .btn i {
            font-size: 1.1em;
        }
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: #b2e6e0;
            background: #18323a;
            border-radius: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 2rem auto;
        }
        .empty-state i {
            font-size: 3rem;
            color: #3ed6c5;
            margin-bottom: 1rem;
        }
        .alert {
            padding: 1.25rem 2rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #2f4956;
            color: #fff;
            border: 2px solid #FC8181;
        }
        .alert-success {
            background: #2f4956;
            border: 2px solid #10B981;
            color: #10B981;
        }
        .alert-error {
            background: #2f4956;
            border: 2px solid #FC8181;
            color: #FC8181;
        }
        @media (max-width: 640px) {
            .container {
                padding: 0 0.5rem;
            }
            .card-content, .card-header {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .new-request-form, .request-card {
                padding: 1rem;
            }
            .request-info h3 {
                font-size: 1.05rem;
            }
            .request-actions {
                flex-direction: column;
            }
            .request-actions .btn,
            .request-actions form {
                width: 100%;
            }
            .request-actions form button {
                width: 100%;
            }
            .request-status {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="min-h-screen">
        <header class="border-bottom-border-color py-3 px-6 bg-secondary-bg">
            <div class="flex items-center" style="gap: 1.5rem;">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="text-xl font-bold" style="color: #ffffff;">My Requests</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="new-request-form">
                <h2 class="card-title">Create New Request</h2>
                <p class="card-description">Select a teacher to request a transfer with</p>
                
                <form action="create-request.php" method="POST">
                    <div class="form-group">
                        <label for="target_id" class="form-label">Select Teacher</label>
                        <select name="target_id" id="target_id" class="form-select" required>
                            <option value="">Choose a teacher...</option>
                            <?php foreach ($potential_partners as $partner): ?>
                                <option value="<?php echo $partner['id']; ?>">
                                    <?php echo htmlspecialchars($partner['first_name'] . ' ' . $partner['last_name'] . ' (' . $partner['school_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Request
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Requests</h2>
                    <p class="card-description">View and manage your transfer requests</p>
                </div>

                <div class="requests-list">
                    <?php if (empty($requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <p>No transfer requests found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="request-info">
                                        <h3>
                                            <?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?>
                                            <span class="text-gray-400"><i class="fas fa-exchange-alt"></i></span>
                                            <?php echo htmlspecialchars($request['target_first_name'] . ' ' . $request['target_last_name']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-400">
                                            <?php echo htmlspecialchars($request['requester_school']); ?> 
                                            <span class="mx-1"><i class="fas fa-exchange-alt"></i></span>
                                            <?php echo htmlspecialchars($request['target_school']); ?>
                                        </p>
                                    </div>
                                    <div class="request-status">
                                        <span class="status-badge <?php echo strtolower($request['status']); ?>">
                                            <?php echo htmlspecialchars($request['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Requested On:</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></span>
                                    </div>
                                    <?php if ($request['status'] === 'Approved'): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Approved On:</span>
                                            <span class="detail-value"><?php echo date('M d, Y', strtotime($request['updated_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($request['status'] === 'Pending' && $request['target_id'] === $_SESSION['user_id']): ?>
                                    <div class="request-actions">
                                        <form method="POST" action="process-request.php" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success">Accept</button>
                                        </form>
                                        <form method="POST" action="process-request.php" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger">Reject</button>
                                        </form>
                                    </div>
                                <?php elseif ($request['status'] === 'Pending' && $request['requester_id'] === $_SESSION['user_id']): ?>
                                    <div class="request-actions">
                                        <form method="POST" action="cancel-request.php" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Cancel</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 