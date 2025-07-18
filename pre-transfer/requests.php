<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Fetch all transfer requests
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
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Requests - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .back-link {
            color: #ffffff !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .back-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }

        .back-link i {
            font-size: 1rem;
        }

        .requests-list {
            display: grid;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .request-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .request-header {
            margin-bottom: 1rem;
        }

        .request-info h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .request-info p {
            margin: 0.25rem 0 0;
        }

        .request-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .status-pending {
            background: #F6AD55;
            color: white;
        }

        .status-approved {
            background: #4FD1C5;
            color: white;
        }

        .status-rejected {
            background: #FC8181;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: #718096;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 2rem auto;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        .request-details {
            margin-top: 1rem;
        }
        .detail-item {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .detail-label {
            color: #718096;
            min-width: 120px;
        }
        .detail-value {
            color: #2d3748;
        }
        @media (max-width: 640px) {
            .container {
                padding: 0 1rem;
            }
            .request-card {
                padding: 1rem;
            }
            .request-info h3 {
                font-size: 1.125rem;
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
            <div class="container mx-auto flex items-center">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="text-xl font-bold" style="margin: 0 auto; color: #ffffff;">Requests</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Transfer Requests</h2>
                    <p class="card-description">View all transfer requests in the system</p>
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
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 