<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Fetch user's transfer request history
try {
    $stmt = $pdo->prepare("
        SELECT 
            tr.*,
            u1.first_name as requester_first_name,
            u1.last_name as requester_last_name,
            u1.preferred_subject as requester_subject,
            s1.name as requester_school_name,
            s1.district as requester_school_district,
            u2.first_name as target_first_name,
            u2.last_name as target_last_name,
            u2.preferred_subject as target_subject,
            s2.name as target_school_name,
            s2.district as target_school_district
        FROM transfer_requests tr
        LEFT JOIN users u1 ON tr.requester_id = u1.id
        LEFT JOIN users u2 ON tr.target_id = u2.id
        LEFT JOIN schools s1 ON u1.school_id = s1.id
        LEFT JOIN schools s2 ON u2.school_id = s2.id
        WHERE tr.requester_id = ? OR tr.target_id = ?
        ORDER BY tr.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching request history: " . $e->getMessage());
    $error_message = "Error loading request history. Please try again.";
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request History - TeacherMatch</title>
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
            padding: 8px 1px;
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

        .request-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .request-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, var(--border-color) 0%, #2f5464 100%);
        
        }

        .request-content {
            padding: 1rem;
            background: linear-gradient(90deg, #2f5464 0%, #1d3a45 100%);
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .request-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .request-detail i {
            color: var(--accent-color);
            width: 1.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background-color: rgba(255, 171, 0, 0.2);
            color: #ffab00;
        }

        .status-approved {
            background-color: rgb(29 58 69);
            color: #24e03d;
        }

        .status-rejected {
            background-color: rgba(255, 0, 0, 0.1);
            color: #ff0000;
        }

        .status-cancelled {
            background-color: rgba(156, 163, 175, 0.2);
            color: #9ca3af;
        }

        .request-date {
            color: var(--text-gray-300);
            font-size: 0.875rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-gray-300);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--text-gray-200);
        }

        @media (max-width: 640px) {
            .request-details {
                grid-template-columns: 1fr;
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
                <h1 class="text-xl font-bold" style="color: #ffffff;">Request History</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <div style="max-width: 48rem; margin: 0 auto;">
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">No Request History</h2>
                        <p>You haven't made or received any transfer requests yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div>
                                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem;">
                                        <?php if ($request['requester_id'] == $_SESSION['user_id']): ?>
                                            Request to <?php echo htmlspecialchars($request['target_first_name'] . ' ' . $request['target_last_name']); ?>
                                        <?php else: ?>
                                            Request from <?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="request-date">
                                        <?php echo date('F j, Y g:i A', strtotime($request['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </div>
                            </div>
                            <div class="request-content">
                                <div class="request-details">
                                    <?php if ($request['requester_id'] == $_SESSION['user_id']): ?>
                                        <div class="request-detail">
                                            <i class="fas fa-user"></i>
                                            <div>
                                                <div style="font-weight: 500;">Target Teacher</div>
                                                <div><?php echo htmlspecialchars($request['target_first_name'] . ' ' . $request['target_last_name']); ?></div>
                                            </div>
                                        </div>
                                        <div class="request-detail">
                                            <i class="fas fa-book"></i>
                                            <div>
                                                <div style="font-weight: 500;">Subject</div>
                                                <div><?php echo htmlspecialchars($request['target_subject'] ?? 'Not specified'); ?></div>
                                            </div>
                                        </div>
                                        <div class="request-detail">
                                            <i class="fas fa-school"></i>
                                            <div>
                                                <div style="font-weight: 500;">Current School</div>
                                                <div><?php echo htmlspecialchars($request['target_school_name'] ?? 'Not specified'); ?></div>
                                            </div>
                                        </div>
                                        <div class="request-detail">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <div>
                                                <div style="font-weight: 500;">District</div>
                                                <div><?php echo htmlspecialchars($request['target_school_district'] ?? 'Not specified'); ?></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="request-detail">
                                            <i class="fas fa-user"></i>
                                            <div>
                                                <div style="font-weight: 500;">Requester</div>
                                                <div><?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?></div>
                                            </div>
                                        </div>
                                        <div class="request-detail">
                                            <i class="fas fa-book"></i>
                                            <div>
                                                <div style="font-weight: 500;">Subject</div>
                                                <div><?php echo htmlspecialchars($request['requester_subject'] ?? 'Not specified'); ?></div>
                                            </div>
                                        </div>
                                        <div class="request-detail">
                                            <i class="fas fa-school"></i>
                                            <div>
                                                <div style="font-weight: 500;">Current School</div>
                                                <div><?php echo htmlspecialchars($request['requester_school_name'] ?? 'Not specified'); ?></div>
                                            </div>
                                        </div>
                                        <div class="request-detail">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <div>
                                                <div style="font-weight: 500;">District</div>
                                                <div><?php echo htmlspecialchars($request['requester_school_district'] ?? 'Not specified'); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 