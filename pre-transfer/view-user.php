<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header("Location: admin-dashboard.php");
    exit;
}

$userId = $_GET['id'];

try {
    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.*, s.name as school_name 
        FROM users u 
        LEFT JOIN schools s ON u.school_id = s.id 
        WHERE u.id = ? AND u.role != 'admin'
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get user's transfer history
    $stmt = $pdo->prepare("
        SELECT tr.*, 
               u1.first_name as requester_first_name, 
               u1.last_name as requester_last_name,
               u2.first_name as target_first_name, 
               u2.last_name as target_last_name,
               s1.name as requester_school,
               s2.name as target_school
        FROM transfer_requests tr
        JOIN users u1 ON tr.requester_id = u1.id
        JOIN users u2 ON tr.target_id = u2.id
        JOIN schools s1 ON u1.school_id = s1.id
        JOIN schools s2 ON u2.school_id = s2.id
        WHERE tr.requester_id = ? OR tr.target_id = ?
        ORDER BY tr.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $transferHistory = $stmt->fetchAll();

} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: admin-dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="min-h-screen flex flex-col">
        <header>
            <div class="container header-content">
                <div class="logo-container">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg>
                    <h1 class="logo-text">TeacherMatch</h1>
                </div>
                <nav>
                    <a href="admin-dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </header>

        <main class="main-content">
            <div class="container">
                <div class="admin-header">
                    <h2>User Details</h2>
                </div>

                <!-- User Information Section -->
                <section class="admin-section user-info-section">
                    <h3>User Information</h3>
                    <div class="user-details">
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">School:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($user['school_name'] ?? 'Not assigned'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <form method="POST" action="admin-dashboard.php" class="status-form">
                                    <input type="hidden" name="action" value="update_user_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="status-select">
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </form>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Joined:</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </section>

                <!-- Transfer History Section -->
                <section class="admin-section transfer-history-card" style="margin-top: 2.5rem;">
                    <div class="transfer-history-header">
                        <i class="fas fa-history"></i>
                        <span>Transfer History</span>
                    </div>
                    <?php if (empty($transferHistory)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <p>No transfer history found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table transfer-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Requester</th>
                                        <th>Target</th>
                                        <th>Requester School</th>
                                        <th>Target School</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transferHistory as $transfer): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($transfer['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['requester_first_name'] . ' ' . $transfer['requester_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['target_first_name'] . ' ' . $transfer['target_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['requester_school']); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['target_school']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($transfer['status']); ?>">
                                                    <?php echo ucfirst($transfer['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <style>
        .user-details {
            display: grid;
            gap: 1rem;
            padding: 1rem;
           background: #25424e;
            border-radius: 8px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 100px;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            
        }
        
        .status-pending {
            background-color:rgb(42, 39, 25);
            color: #92400e;
        }
        
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-select {
             background-color:#1e3a43
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-secondary:hover {
            background-color:rgb(65, 73, 89);
        }
        .transfer-history-card {
            background: #18323a;
            border-radius: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
            margin-bottom: 2.5rem;
            padding: 0;
        }
        .transfer-history-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: linear-gradient(90deg, #25424e 0%, #2f5464 100%);
            color: #fff;
            font-size: 1.25rem;
            font-weight: 700;
            padding: 1.25rem 2rem 1rem 2rem;
            border-top-left-radius: 1.25rem;
            border-top-right-radius: 1.25rem;
        }
        .transfer-history-header i {
            color: #3ed6c5;
            font-size: 1.5rem;
        }
        .transfer-history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #18323a;
            color: #fff;
            font-size: 1rem;
            border-radius: 0 0 1.25rem 1.25rem;
            overflow: hidden;
        }
        .transfer-history-table th {
            background: #25424e;
            color: #3ed6c5;
            font-weight: 600;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #2fd6c5;
        }
        .transfer-history-table td {
            padding: 1rem;
            border-bottom: 1px solid #25424e;
            color: #fff;
            vertical-align: middle;
        }
        .transfer-history-table tr:nth-child(even) {
            background: #1e3a43;
        }
        .transfer-history-table tr:nth-child(odd) {
            background: #18323a;
        }
        .status-badge {
            padding: 0.35em 1em;
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
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
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
        @media (max-width: 800px) {
            .transfer-history-header, .transfer-history-table th, .transfer-history-table td {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            .transfer-history-header {
                font-size: 1.1rem;
            }
        }
        @media (max-width: 600px) {
            .transfer-history-header {
                font-size: 1rem;
                padding: 1rem;
            }
            .transfer-history-table th, .transfer-history-table td {
                padding: 0.5rem;
                font-size: 0.95rem;
            }
        }
        .user-info-section {
            margin-bottom: 2.5rem;
        }
    </style>
</body>
</html> 