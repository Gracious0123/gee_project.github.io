<?php
// Enable error reporting at the top of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();

// Debug session data
error_log("=== Dashboard Page Load ===");
error_log("Session Data: " . print_r($_SESSION, true));

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and has a role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Redirect admins to the admin dashboard
if ($_SESSION['role'] === 'admin') {
    header("Location: admin-dashboard.php");
    exit;
}

require_once __DIR__ . '/database.php';

// Debug database connection
error_log("Database connection established");

// Initialize counts and user data
$user_name = $_SESSION['user_name'] ?? 'User';
$request_count = 0;
$message_count = 0;
$notification_count = 0;
$unviewed_requests_count = 0;
$is_first_login = true;

try {
    // Fetch user's full details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Fetch preferred districts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferred_districts WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $district_count = $stmt->fetchColumn();

    // Fetch preferred schools
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_preferred_schools WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $school_count = $stmt->fetchColumn();

    $preferred_subject = $user['preferred_subject'] ?? '';
    $preferred_grade_level = $user['preferred_grade_level'] ?? '';
    $is_first_login = ($district_count == 0) && ($school_count == 0) && empty($preferred_subject) && empty($preferred_grade_level);

    // Fetch user's pending transfer requests count
    $stmt = $pdo->prepare("SELECT COUNT(*) as request_count FROM transfer_requests WHERE (requester_id = ? OR target_id = ?) AND status = 'Pending'");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $request_count = $stmt->fetch()['request_count'];

    // Fetch unread messages count
    $stmt = $pdo->prepare("SELECT COUNT(*) as message_count FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $message_count = $stmt->fetch()['message_count'];

    // Fetch unread notifications count
    $stmt = $pdo->prepare("SELECT COUNT(*) as notification_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $notification_count = $stmt->fetch()['notification_count'];

    // Fetch unviewed incoming requests count for the badge
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transfer_requests WHERE target_id = ? AND is_viewed_by_target = 0 AND status = 'Pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $unviewed_requests_count = $stmt->fetchColumn();

} catch (Exception $e) {
    error_log("Error in dashboard: " . $e->getMessage());
    // Fallback values are already initialized
}

// Handle success message from preferences update
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Your preferences have been updated successfully!";
}

// Get user information from session
$user_email = $_SESSION['user_email'] ?? '';

// Determine exchange status text and color
$exchange_status = $user['exchange_status'] ?? 'No Exchange Request';
$status_color = 'var(--text-gray-300)';
if ($exchange_status === 'Pending') {
    $status_color = '#F6AD55'; // Orange
} elseif ($exchange_status === 'Approved') {
    $status_color = '#68D391'; // Green
} elseif ($exchange_status === 'Rejected') {
    $status_color = '#FC8181'; // Red
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2A4B4B;
            --secondary-color: #1E3535;
            --accent-color: #4FD1C5;
            --background-color:#2f5464;
            --text-color: #E2E8F0;
            --sidebar-width: 250px;
            --header-height: 64px;
        }

        * {
            color: #ffffff;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: #25424e;
            color: white;
            padding: 1.5rem;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            color: white;
            text-decoration: none;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.75rem;
        }

        .nav-items {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white;
            text-decoration: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .badge {
            background-color: var(--accent-color);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            margin-left: auto;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background-color: #25424e;
            padding: 1.5rem 2rem;
            border-radius: 0.5rem;
            color: var(--text-color);
        }

        .welcome-text {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .welcome-subtext {
            color: #dfe4ea;
            margin-top: 0.25rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }

        .notification-icon {
            color: #718096;
            font-size: 1.25rem;
            position: relative;
            cursor: pointer;
            transition: color 0.2s;
        }

        .notification-icon:hover {
            color: var(--accent-color);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--accent-color);
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: white;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .profile-avatar:hover {
            transform: scale(1.05);
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background-color:#4fd1c5;
            border-radius: 0.5rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
            min-width: 200px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: rgba(9, 53, 48, 0.1);
        }

        .dropdown-divider {
            height: 1px;
            background-color: #4A5568;
            margin: 0.5rem 0;
        }

        /* New Styles for Clickable Dashboard Containers */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
            padding: 0 20px;
        }

        .dashboard-card {
            background: #25424e;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .card-header h3 {
            margin: 0;
            flex-grow: 1;
        }

        .card-body {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-content p {
            margin-bottom: 10px;
            color: var(--text-gray-300);
        }

        .card-content p strong {
            color: var(--accent-color);
        }

        .chevron-icon {
            transition: transform 0.3s ease;
        }

        .dashboard-card.active .chevron-icon {
            transform: rotate(180deg);
        }

        .dashboard-card .icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            color: var(--accent-color); /* Default icon color */
            transition: color 0.2s ease;
        }

        .dashboard-card.registration .icon { color: #5B6DCD; /* Example color for Registration */ }
        .dashboard-card.academic-records .icon { color: #4CAF50; /* Example color for Academic Records */ }
        .dashboard-card.field-projects .icon { color: #E57373; /* Example color for Field & Projects */ }
        .dashboard-card.accommodation .icon { color: #FFB74D; /* Example color for Accommodation */ }
        .dashboard-card.current-school .icon { color: #5B6DCD; } /* Using a shade of blue for school */
        .dashboard-card.transfer-preferences .icon { color: #4CAF50; } /* Using a shade of green for preferences */
        .dashboard-card.exchange-status .icon { color: #E57373; } /* Using a shade of red for status */

        .dashboard-card h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .dashboard-card .card-content p {
            margin-bottom: 8px;
        }
        
        .dashboard-card .card-content p strong {
            color: var(--accent-color); /* Revert to original color */
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: var(--secondary-color);
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #3CC7BA;
        }

        .btn-primary:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 209, 197, 0.5);
        }

        .btn-success {
            background-color: #68D391;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .btn-success:hover {
            background-color: #51B873;
        }

        .btn-danger {
            background-color: #FC8181;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .btn-danger:hover {
            background-color: #E55353;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-in-progress {
            background-color: rgba(79, 209, 197, 0.2);
            color: var(--accent-color);
        }

        /* Recent Activity Styles */
        .activity-card {
            background-color: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #E2E8F0;
            color: #1A202C;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            color: #fff;
            font-size: 0.8rem;
        }

        .activity-action {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
        }

        .school-info {
            padding: 1rem;
        }

        .school-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .school-header i {
            font-size: 2rem;
            color: var(--accent-color);
        }

        .school-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 0.75rem;
            background-color: var(--secondary-bg);
            border-radius: 0.5rem;
            transition: transform 0.2s ease;
        }

        .info-item:hover {
            transform: translateY(-2px);
        }

        .info-item i {
            font-size: 1.25rem;
            color: var(--accent-color);
            margin-top: 0.25rem;
        }

        .info-item .label {
            display: block;
            font-size: 0.875rem;
            color: var(--text-gray-300);
            margin-bottom: 0.25rem;
        }

        .info-item .value {
            display: block;
            font-weight: 500;
            color: var(--text-color);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-gray-300);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--text-gray-300);
            margin-bottom: 1rem;
        }

        .empty-state p {
            margin: 0;
        }

        .empty-state .text-sm {
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .school-header {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .school-header i {
                font-size: 1.75rem;
            }

            .school-header h3 {
                font-size: 1.25rem;
            }
        }

        /* Transfer Preferences Card Styles */
        .preferences-info {
            padding: 1.5rem;
        }

        .preference-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .preference-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background-color: #2f5464;
            border-radius: 0.5rem;
            transition: none; /* No transition needed for default state */
        }

        .preference-icon {
            width: 40px;
            height: 40px;
            background-color: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color); /* Icon color should contrast with accent */
            flex-shrink: 0;
        }

        .preference-content {
            flex-grow: 1;
        }

        .preference-content h4 {
            color: var(--accent-color);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preference-content p {
            color: var(--text-color);
            font-size: 1rem;
            line-height: 1.5;
            margin: 0;
        }

        .preferences-actions {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
        }

        .preferences-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .preferences-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Notification Bar Styles */
        .notification-bar {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 0;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .notification-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white;
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
            flex-grow: 1;
        }

        .notification-content:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
        }

        .notification-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }

        .notification-close:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -100vw;
                top: 0;
                width: 80vw;
                max-width: 320px;
                height: 100vh;
                z-index: 1001;
                transition: left 0.3s;
            }
            .sidebar.show {
                left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0,0,0,0.3);
                z-index: 1000;
            }
            .sidebar-overlay.show {
                display: block;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .btn, .btn-primary, .btn-success, .btn-danger {
                min-height: 44px;
                font-size: 1rem;
            }
        }
        .menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 2rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .menu-btn {
                display: block;
            }
        }
        .school-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .school-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #28444a;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            color: #fff;
        }
        .school-icon {
            background: #3ed6c5;
            color: #1a2e35;
            border-radius: 50%;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .school-label {
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #3ed6c5;
            margin-bottom: 0.15rem;
        }
        .school-value {
            font-size: 1.05rem;
            font-weight: 400;
            color: #fff;
        }
        @media (max-width: 600px) {
            .school-cards {
                gap: 0.75rem;
            }
            .school-card {
                padding: 0.75rem 1rem;
                font-size: 0.98rem;
            }
            .school-icon {
                width: 2rem;
                height: 2rem;
                font-size: 1rem;
            }
        }
        .exchange-status-card {
            margin-bottom: 2rem;
        }
        .exchange-status-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            font-size: 1.5rem;
            background: #e2e8f0;
            color: #4FD1C5;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.07);
        }
        .exchange-status-label {
            font-size: 1.15rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.15rem;
        }
        .exchange-status-desc {
            font-size: 1rem;
            color: var(--text-gray-300);
        }
        @media (max-width: 600px) {
            .exchange-status-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .exchange-status-icon {
                width: 2.25rem;
                height: 2.25rem;
                font-size: 1.1rem;
            }
            .exchange-status-label {
                font-size: 1rem;
            }
        }
        .info-group {
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: #2f5464;
            border-radius: 0.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: transform 0.2s ease;
        }

        .info-group:hover {
            transform: translateY(-2px);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background-color: #3ed6c5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-icon i {
            font-size: 1.25rem;
            color: #1a2e35;
        }

        .info-content {
            flex-grow: 1;
        }

        .info-content label {
            display: block;
            color: #3ed6c5;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
        }

        .info-content span {
            display: block;
            color: #fff;
            font-size: 1rem;
        }

        .text-muted {
            color: #718096;
            text-align: center;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <button class="menu-btn" onclick="toggleSidebar(false)"><i class="fas fa-times"></i></button>
            <a href="#" class="sidebar-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                    <path d="M6 12v5c3 3 9 3 12 0v-5" />
                </svg>
                <span>TeacherMatch</span>
            </a>

            <nav>
                <div class="nav-section">
                    <h3 class="nav-section-title">MAIN</h3>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <i class="fas fa-home"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="profile.php" class="nav-link">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="nav-section">
                    <h3 class="nav-section-title">EXCHANGE MANAGEMENT</h3>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a href="search.php" class="nav-link">
                                <i class="fas fa-search"></i>
                                <span>Search Options</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="my-requests.php" class="nav-link">
                                <i class="fas fa-exchange-alt"></i>
                                My Requests
                                <?php if ($unviewed_requests_count > 0): ?>
                                    <span class="badge"><?php echo $unviewed_requests_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="messages.php" class="nav-link">
                                <i class="fas fa-envelope"></i>
                                <span>Messages</span>
                                <?php if ($message_count > 0): ?>
                                    <span class="badge"><?php echo $message_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="request-history.php" class="nav-link">
                                <i class="fas fa-history"></i>
                                Request History
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <button class="menu-btn" onclick="toggleSidebar(true)"><i class="fas fa-bars"></i></button>
            <header class="header">
                <div>
                    <h1 class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?></h1>
                    <p class="welcome-subtext">Manage your transfer preferences and requests</p>
                </div>
                <div class="user-profile">
                    <a href="notifications.php" class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="notification-badge"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="profile-avatar" onclick="toggleProfileDropdown()">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            My Profile
                        </a>
                        <a href="preferences.php" class="dropdown-item">
                            <i class="fas fa-sliders-h"></i>
                            Preferences
                        </a>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </header>

            <!-- Success Message -->
            <?php if ($success_message): ?>
                <div class="alert alert-success" style="margin: 1rem 0;">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Notification Bar -->
            <?php if ($message_count > 0): ?>
            <div class="notification-bar" id="notificationBar">
                <div class="container">
                    <?php if ($message_count > 0): ?>
                    <a href="messages.php" class="notification-content">
                        <i class="fas fa-envelope"></i>
                        <span>You have <?php echo $message_count; ?> new message<?php echo $message_count > 1 ? 's' : ''; ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <button class="notification-close" onclick="closeNotification()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="container mx-auto p-4">
                

                <div class="dashboard-grid" style="margin-top: 2rem;">
                    <!-- Current School Information Card -->
                    <a class="dashboard-card current-school" href="current-school.php" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px;">
                        <i class="icon fas fa-school" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <span style="font-size: 1.1rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; color: #5B6DCD;">Current School</span>
                    </a>
                    <!-- Transfer Preferences Card -->
                    <a class="dashboard-card transfer-preferences" href="transfer-preference.php" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px;">
                        <i class="icon fas fa-exchange-alt" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <span style="font-size: 1.1rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; color: #4CAF50;">Transfer Preferences</span>
                    </a>
                    <!-- Exchange Status Card -->
                    <a class="dashboard-card exchange-status" href="exchange-status.php" style="text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px;">
                        <i class="icon fas fa-handshake" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <span style="font-size: 1.1rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; color: #E57373;">Exchange Status</span>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div class="card-content">
                        <?php
                        // Fetch recent activities
                        $stmt = $pdo->prepare("
                            SELECT 
                                'transfer_request' as activity_type,
                                tr.created_at as activity_time,
                                CASE 
                                    WHEN tr.requester_id = ? THEN CONCAT('Transfer request sent to ', u2.first_name, ' ', u2.last_name)
                                    ELSE CONCAT('Transfer request from ', u1.first_name, ' ', u1.last_name)
                                END as activity_description
                            FROM transfer_requests tr
                            JOIN users u1 ON tr.requester_id = u1.id
                            JOIN users u2 ON tr.target_id = u2.id
                            WHERE tr.requester_id = ? OR tr.target_id = ?
                            
                            UNION ALL
                            
                            SELECT 
                                'message' as activity_type,
                                m.created_at as activity_time,
                                CASE 
                                    WHEN m.sender_id = ? THEN CONCAT('New message has been sent to ', u2.first_name, ' ', u2.last_name)
                                    ELSE CONCAT('New message from ', u1.first_name, ' ', u1.last_name)
                                END as activity_description
                            FROM messages m
                            JOIN users u1 ON m.sender_id = u1.id
                            JOIN users u2 ON m.recipient_id = u2.id
                            WHERE (m.sender_id = ? AND m.deleted_by_sender = 0)
                               OR (m.recipient_id = ? AND m.deleted_by_recipient = 0)
                            
                            ORDER BY activity_time DESC
                            LIMIT 3
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $_SESSION['user_id'],
                            $_SESSION['user_id'],
                            $_SESSION['user_id'],
                            $_SESSION['user_id'],
                            $_SESSION['user_id']
                        ]);
                        $activities = $stmt->fetchAll();

                        if (empty($activities)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-description"><?php echo htmlspecialchars($activity['activity_description']); ?></div>
                                    <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['activity_time'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Profile dropdown functionality
        function toggleProfileDropdown() {
            const profileDropdown = document.getElementById('profileDropdown');
            profileDropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            const profileDropdown = document.getElementById('profileDropdown');
            const profileAvatar = document.querySelector('.profile-avatar');
            
            if (!profileAvatar.contains(event.target) && !profileDropdown.contains(event.target)) {
                profileDropdown.classList.remove('show');
            }
        });

        // Make sidebar links functional
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        });

        // Add this to your existing script
        function closeNotification() {
            const notificationBar = document.getElementById('notificationBar');
            if (notificationBar) {
                notificationBar.style.display = 'none';
            }
        }

        function toggleSidebar(show) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (show) {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            } else {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const mainContent = document.getElementById('mainContent'); // Assuming you have a main content area to shift

            hamburgerMenu.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('shifted'); // Add a class to shift content
            });

            // Close sidebar when clicking outside on mobile
            mainContent.addEventListener('click', function() {
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('shifted');
                }
            });

            // Debug message
            console.log('Script loaded');
            
            // Dashboard Card Click Functionality
            const cards = document.querySelectorAll('.dashboard-card');
            console.log('Found cards:', cards.length);
            
            cards.forEach(card => {
                card.addEventListener('click', function(e) {
                    console.log('Card clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Toggle active class
                    this.classList.toggle('active');
                    
                    // Toggle content visibility
                    const content = this.querySelector('.card-body');
                    if (content) {
                        content.style.display = this.classList.contains('active') ? 'block' : 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
  