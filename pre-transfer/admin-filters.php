<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Initialize variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$school_filter = isset($_GET['school']) ? $_GET['school'] : '';
$district_filter = isset($_GET['district']) ? $_GET['district'] : '';
$region_filter = isset($_GET['region']) ? $_GET['region'] : '';

// Build the query
$query = "
    SELECT r.*, 
           u1.first_name as requester_first_name, 
           u1.last_name as requester_last_name,
           u2.first_name as target_first_name, 
           u2.last_name as target_last_name,
           s1.name as requester_school,
           s1.district as requester_district,
           s1.region as requester_region,
           s2.name as target_school,
           s2.district as target_district,
           s2.region as target_region
    FROM transfer_requests r
    JOIN users u1 ON r.requester_id = u1.id
    JOIN users u2 ON r.target_id = u2.id
    JOIN schools s1 ON r.requester_school_id = s1.id
    JOIN schools s2 ON r.target_school_id = s2.id
    WHERE 1=1
";

$params = [];

if ($status_filter) {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $query .= " AND DATE(r.created_at) = ?";
    $params[] = $date_filter;
}

if ($school_filter) {
    $query .= " AND (s1.id = ? OR s2.id = ?)";
    $params[] = $school_filter;
    $params[] = $school_filter;
}

if ($district_filter) {
    $query .= " AND (s1.district = ? OR s2.district = ?)";
    $params[] = $district_filter;
    $params[] = $district_filter;
}

if ($region_filter) {
    $query .= " AND (s1.region = ? OR s2.region = ?)";
    $params[] = $region_filter;
    $params[] = $region_filter;
}

$query .= " ORDER BY r.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Get list of schools for filter
    $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
    $schools = $stmt->fetchAll();

    // Get list of districts for filter
    $stmt = $pdo->query("SELECT DISTINCT district FROM schools WHERE district IS NOT NULL ORDER BY district");
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get list of regions for filter
    $stmt = $pdo->query("SELECT DISTINCT region FROM schools WHERE region IS NOT NULL ORDER BY region");
    $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching admin filters data: " . $e->getMessage());
    $requests = [];
    $schools = [];
    $districts = [];
    $regions = [];
}

// Handle request status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE transfer_requests SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['request_id']]);
        
        // Create notification for both users
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            SELECT user_id, 'request_' || ?, 'Transfer Request ' || ?, 
                   'Your transfer request has been ' || ? || '.',
                   '/request-history.php'
            FROM (
                SELECT requester_id as user_id FROM transfer_requests WHERE id = ?
                UNION
                SELECT target_id as user_id FROM transfer_requests WHERE id = ?
            ) as users
        ");
        $stmt->execute([
            strtolower($_POST['status']),
            $_POST['status'],
            strtolower($_POST['status']),
            $_POST['request_id'],
            $_POST['request_id']
        ]);
        
        header("Location: admin-filters.php?success=1");
        exit;
    } catch (PDOException $e) {
        error_log("Error updating request status: " . $e->getMessage());
        $error_message = "Error updating request status. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Filters - TeacherMatch</title>
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

        .filters-container {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filters-form {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 500;
            color: #2d3748;
        }

        .filter-select {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background-color: white;
        }

        .filter-input {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .requests-list {
            display: grid;
            gap: 1.5rem;
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
            color: #718096;
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

        .request-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
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

        .request-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-primary {
            background-color: #4FD1C5;
            color: white;
            border: none;
        }

        .btn-success {
            background-color: #68D391;
            color: white;
            border: none;
        }

        .btn-danger {
            background-color: #FC8181;
            color: white;
            border: none;
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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid #10B981;
            color: #10B981;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid #EF4444;
            color: #EF4444;
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 1rem;
            }

            .filters-container {
                padding: 1rem;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .request-card {
                padding: 1rem;
            }

            .request-info h3 {
                font-size: 1.125rem;
            }

            .detail-item {
                flex-direction: column;
                gap: 0.25rem;
            }

            .detail-label {
                min-width: auto;
            }

            .request-actions {
                flex-direction: column;
            }

            .request-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="min-h-screen bg-primary-bg">
        <header class="border-bottom-border-color py-3 px-6 bg-secondary-bg">
            <div class="container mx-auto flex items-center">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="text-xl font-bold" style="margin: 0 auto; color: #ffffff;">Admin Filters</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Request status updated successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="filters-container">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="status" class="filter-label">Status</label>
                        <select name="status" id="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date" class="filter-label">Date</label>
                        <input type="date" name="date" id="date" class="filter-input" value="<?php echo $date_filter; ?>">
                    </div>

                    <div class="filter-group">
                        <label for="school" class="filter-label">School</label>
                        <select name="school" id="school" class="filter-select">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo $school_filter == $school['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="district" class="filter-label">District</label>
                        <select name="district" id="district" class="filter-select">
                            <option value="">All Districts</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo $district; ?>" <?php echo $district_filter === $district ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="region" class="filter-label">Region</label>
                        <select name="region" id="region" class="filter-select">
                            <option value="">All Regions</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?php echo $region; ?>" <?php echo $region_filter === $region ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($region); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group" style="align-self: end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter mr-2"></i>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <div class="requests-list">
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-filter"></i>
                        <p>No requests found</p>
                        <p class="text-sm text-gray-400">Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div class="request-info">
                                    <h3>
                                        <?php echo htmlspecialchars($request['requester_first_name'] . ' ' . $request['requester_last_name']); ?>
                                        <span class="text-gray-400">→</span>
                                        <?php echo htmlspecialchars($request['target_first_name'] . ' ' . $request['target_last_name']); ?>
                                    </h3>
                                    <p class="text-sm">
                                        <?php echo htmlspecialchars($request['requester_school']); ?> 
                                        <span class="mx-1">→</span>
                                        <?php echo htmlspecialchars($request['target_school']); ?>
                                    </p>
                                    <span class="request-status status-<?php echo strtolower($request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="request-details">
                                <div class="detail-item">
                                    <span class="detail-label">Requested On:</span>
                                    <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($request['created_at'])); ?></span>
                                </div>
                                <?php if ($request['status'] !== 'Pending'): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Updated On:</span>
                                        <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($request['updated_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label">Requester School:</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($request['requester_school']); ?>
                                        (<?php echo htmlspecialchars($request['requester_district']); ?>, 
                                        <?php echo htmlspecialchars($request['requester_region']); ?>)
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Target School:</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($request['target_school']); ?>
                                        (<?php echo htmlspecialchars($request['target_district']); ?>, 
                                        <?php echo htmlspecialchars($request['target_region']); ?>)
                                    </span>
                                </div>
                            </div>

                            <?php if ($request['status'] === 'Accepted' || $request['status'] === 'Pending'): ?>
                                <div class="request-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-check mr-2"></i>
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-times mr-2"></i>
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 