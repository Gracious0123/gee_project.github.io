<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Get pending transfer requests
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
    WHERE tr.status = 'pending'
    ORDER BY tr.created_at DESC
");
$stmt->execute();
$pendingRequests = $stmt->fetchAll();

// Get all users
$stmt = $pdo->prepare("
    SELECT u.*, s.name as school_name 
    FROM users u 
    LEFT JOIN schools s ON u.school_id = s.id 
    WHERE u.role != 'admin'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_user_status') {
        $userId = $_POST['user_id'];
        $newStatus = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        
        // Redirect to prevent form resubmission
        header("Location: admin-dashboard.php");
        exit;
    }
}

// Fetch all users
try {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $allUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching all users: " . $e->getMessage());
    $allUsers = [];
}

// Fetch requests by status
$requestsByStatus = [];
$statuses = ['Pending', 'Accepted', 'Approved', 'Rejected'];
foreach ($statuses as $status) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transfer_requests WHERE status = ?");
        $stmt->execute([$status]);
        $requestsByStatus[$status] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching requests by status ($status): " . $e->getMessage());
        $requestsByStatus[$status] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-bg: #2f5464;
            --secondary-bg: #25424e;
            --accent-color: #4ecdc4;
            --border-color: #1d3a45;
            --text-white: #ffffff;
            --text-gray-300: #cbd5e1;
            --text-gray-400: #94a3b8;
            --yellow: #eab308;
            --red: #ef4444;
        }

        body {
            background-color:#2f5464;
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .admin-header {
            background: var(--secondary-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-white);
            margin: 0;
        }

        .admin-header p {
            color: var(--text-gray-300);
            margin: 0;
        }

        .admin-section {
            background: var(--secondary-bg);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .admin-section h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-white);
            margin: 0 0 1.5rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        .table-responsive {
            overflow-x: auto;
            margin: 0 -1.5rem;
            padding: 0 1.5rem;
        }

        .admin-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }

        .admin-table th {
            background-color: var(--primary-bg);
            color: var(--text-white);
            font-weight: 600;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .admin-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-white);
            vertical-align: middle;
        }

        .admin-table tr:hover {
            background-color: rgba(78, 205, 196, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: var(--secondary-bg);
        }

        .btn-primary:hover {
            background-color: rgba(78, 205, 196, 0.8);
        }

        .btn-success {
            background-color: var(--accent-color);
            color: var(--secondary-bg);
        }

        .btn-success:hover {
            background-color: rgba(78, 205, 196, 0.8);
        }

        .btn-danger {
            background-color: var(--red);
            color: var(--text-white);
        }

        .btn-danger:hover {
            background-color: rgba(239, 68, 68, 0.8);
        }

        .status-select {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background-color: var(--primary-bg);
            color: var(--text-white);
            font-size: 0.875rem;
            min-width: 120px;
        }

        .status-select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(78, 205, 196, 0.1);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-active {
            background-color: rgba(78, 205, 196, 0.2);
            color: var(--accent-color);
        }

        .status-inactive {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--red);
        }

        .status-suspended {
            background-color: rgba(234, 179, 8, 0.2);
            color: var(--yellow);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-gray-300);
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--text-gray-400);
        }

        .admin-stats {
            display: flex;
            gap: 1.5rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-gray-300);
        }

        .stat-item i {
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .admin-stats {
                flex-direction: column;
                gap: 0.5rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 0.75rem;
            }
        }
    </style>
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
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </header>

        <main class="main-content">
            <div class="container">
                <div class="admin-header">
                    <div>
                        <h2>Admin Dashboard</h2>
                        <p>Welcome, <?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></p>
                    </div>
                    <div class="admin-stats">
                        <span class="stat-item">
                            <i class="fas fa-users"></i>
                            <?php echo count($users); ?> Total Users
                        </span>
                        <span class="stat-item">
                            <i class="fas fa-exchange-alt"></i>
                            <?php echo count($pendingRequests); ?> Pending Requests
                        </span>
                    </div>
                </div>

                <!-- Pending Transfer Requests Section -->
                <section class="admin-section">
                    <h3>
                        <i class="fas fa-exchange-alt"></i>
                        Pending Transfer Requests
                    </h3>
                    <?php if (empty($pendingRequests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No pending transfer requests at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Requester</th>
                                        <th>Target</th>
                                        <th>Requester School</th>
                                        <th>Target School</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['target_first_name'] . ' ' . $request['target_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['requester_school']); ?></td>
                                            <td><?php echo htmlspecialchars($request['target_school']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <a href="process-request.php?id=<?php echo $request['id']; ?>&action=approve" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                <?php endif; ?>
                                                <a href="process-request.php?id=<?php echo $request['id']; ?>&action=reject" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- User Management Section -->
                <section class="admin-section">
                    <h3>
                        <i class="fas fa-users"></i>
                        User Management
                    </h3>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>School</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['school_name'] ?? 'Not assigned'); ?></td>
                                        <td>
                                            <form method="POST" class="status-form">
                                                <input type="hidden" name="action" value="update_user_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        // Add confirmation for approve/reject actions
        document.querySelectorAll('.btn-danger, .btn-success').forEach(button => {
            button.addEventListener('click', function(e) {
                const action = this.classList.contains('btn-success') ? 'approve' : 'reject';
                if (!confirm(`Are you sure you want to ${action} this transfer request?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html> 