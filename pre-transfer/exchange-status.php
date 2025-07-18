<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$user_name = $_SESSION['user_name'] ?? 'User';
require_once 'database.php';

// Fetch the latest approved transfer request for the current user
$approvedRequest = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM transfer_requests
        WHERE (requester_id = :uid1 OR target_id = :uid2)
          AND status = 'Approved'
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    $stmt->execute(['uid1' => $_SESSION['user_id'], 'uid2' => $_SESSION['user_id']]);
    $approvedRequest = $stmt->fetch();
}

// Fetch all approved transfer requests for the current user
$approvedRequests = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT tr.*, 
               u1.first_name AS requester_first_name, u1.last_name AS requester_last_name, s1.name AS requester_school,
               u2.first_name AS target_first_name, u2.last_name AS target_last_name, s2.name AS target_school
        FROM transfer_requests tr
        JOIN users u1 ON tr.requester_id = u1.id
        JOIN users u2 ON tr.target_id = u2.id
        JOIN schools s1 ON u1.school_id = s1.id
        JOIN schools s2 ON u2.school_id = s2.id
        WHERE (tr.requester_id = :uid1 OR tr.target_id = :uid2)
          AND tr.status = 'Approved'
        ORDER BY tr.updated_at DESC
    ");
    $stmt->execute(['uid1' => $_SESSION['user_id'], 'uid2' => $_SESSION['user_id']]);
    $approvedRequests = $stmt->fetchAll();
}

// Fetch all pending transfer requests for the current user
$pendingRequests = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT tr.*, 
               u1.first_name AS requester_first_name, u1.last_name AS requester_last_name, s1.name AS requester_school,
               u2.first_name AS target_first_name, u2.last_name AS target_last_name, s2.name AS target_school
        FROM transfer_requests tr
        JOIN users u1 ON tr.requester_id = u1.id
        JOIN users u2 ON tr.target_id = u2.id
        JOIN schools s1 ON u1.school_id = s1.id
        JOIN schools s2 ON u2.school_id = s2.id
        WHERE (tr.requester_id = :uid1 OR tr.target_id = :uid2)
          AND tr.status = 'Pending'
        ORDER BY tr.created_at DESC
    ");
    $stmt->execute(['uid1' => $_SESSION['user_id'], 'uid2' => $_SESSION['user_id']]);
    $pendingRequests = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange Status - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #4a5568;
            font-family: 'Inter', sans-serif;
            color: #25424e;
        }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background-color: #25424e; color: white; padding: 1.5rem; position: fixed; height: 100vh; left: 0; top: 0; }
        .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; color: white; text-decoration: none; }
        .nav-section { margin-bottom: 2rem; }
        .nav-section-title { font-size: 0.75rem; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 0.75rem; }
        .nav-items { list-style: none; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; color: white; text-decoration: none; padding: 0.75rem; border-radius: 0.5rem; transition: background-color 0.2s; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.1); }
        .badge { background-color: #4FD1C5; color: #25424e; padding: 0.25rem 0.5rem; border-radius: 1rem; font-size: 0.75rem; margin-left: auto; }
        .main-content { margin-left: 250px; padding: 2rem; width: calc(100% - 250px); }
        .header { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; position: relative; margin-bottom: 2rem; background-color: #25424e; padding: 0.1rem 2rem; border-radius: 0.5rem; color: #E2E8F0; }
        .welcome-text { font-size: 1.5rem; font-weight: 600; }
        .welcome-subtext { color:rgb(240, 243, 246); margin-top: 0.25rem; }
        .user-profile { display: flex; align-items: center; gap: 1rem; position: relative; }
        .notification-icon { color: #718096; font-size: 1.25rem; position: relative; cursor: pointer; transition: color 0.2s; }
        .notification-icon:hover { color: #4FD1C5; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background-color: #4FD1C5; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: white; }
        .profile-avatar { width: 40px; height: 40px; background-color: #4FD1C5; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; cursor: pointer; transition: transform 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .profile-avatar:hover { transform: scale(1.05); }
        .profile-dropdown { position: absolute; top: 100%; right: 0; background-color: white; border-radius: 0.5rem; padding: 0.5rem; margin-top: 0.5rem; min-width: 200px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; z-index: 1000; }
        .profile-dropdown.show { display: block; }
        .dropdown-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; color: #25424e; text-decoration: none; border-radius: 0.25rem; transition: background-color 0.2s; }
        .dropdown-item:hover { background-color: rgba(79,209,197,0.1); }
        .dropdown-divider { height: 1px; background-color: #4A5568; margin: 0.5rem 0; }
        /* Card styles from previous step */
        .card-modern { max-width: 420px; width: 100%; margin: 2rem auto; border-radius: 18px; box-shadow: 0 4px 24px rgba(44,62,80,0.10); background: #fff; border-top: 6px solid #E57373; display: flex; flex-direction: column; align-items: center; padding: 2.5rem 2rem 2rem 2rem; position: relative; }
        .card-modern .icon-badge { background: #E57373; color: #fff; border-radius: 50%; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; font-size: 2rem; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); box-shadow: 0 2px 8px rgba(44,62,80,0.10); }
        .card-modern .card-title { margin-top: 2.5rem; font-size: 1.5rem; font-weight: 700; color: #25424e; letter-spacing: 1px; text-align: center; }
        .card-modern .card-body { margin-top: 1.5rem; width: 100%; color: #25424e; }
        .card-modern .card-body p { margin-bottom: 1rem; font-size: 1.08rem; display: flex; justify-content: space-between; }
        .card-modern .card-body strong { color: #E57373; min-width: 120px; display: inline-block; }
        .card-modern .btn-pill { display: block; width: 100%; margin-top: 2rem; background: #E57373; color: #fff; border: none; border-radius: 999px; padding: 0.85rem 0; font-weight: 600; font-size: 1.08rem; transition: background 0.2s; text-align: center; text-decoration: none; }
        .card-modern .btn-pill:hover { background: #b71c1c; }
        
        /* New styles for better layout */
        .status-card { max-width: 800px; width: 100%; margin: 2rem auto; border-radius: 18px; box-shadow: 0 4px 24px rgba(44,62,80,0.10); background: #fff; border-top: 6px solid #E57373; padding: 2.5rem 2rem 2rem 2rem; position: relative; }
        .status-card .icon-badge { background: #E57373; color: #fff; border-radius: 50%; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; font-size: 2rem; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); box-shadow: 0 2px 8px rgba(44,62,80,0.10); }
        .status-card .card-title { margin-top: 2.5rem; font-size: 1.5rem; font-weight: 700; color: #25424e; letter-spacing: 1px; text-align: center; margin-bottom: 2rem; }
        .status-card .card-body { width: 100%; color: #25424e; }
        .status-card .card-body p { margin-bottom: 1rem; font-size: 1.08rem; display: flex; justify-content: space-between; align-items: center; }
        .status-card .card-body strong { color: #E57373; min-width: 120px; display: inline-block; }
        .status-card .btn-pill { display: inline-block; background: #E57373; color: #fff; border: none; border-radius: 999px; padding: 0.75rem 1.5rem; font-weight: 600; font-size: 1rem; transition: background 0.2s; text-align: center; text-decoration: none; margin-top: 1rem; }
        .status-card .btn-pill:hover { background: #b71c1c; }
        
        .status-info { background: #f8f9fa; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .status-badge { display: inline-block; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.na { background: #f8d7da; color: #721c24; }
        
        .requests-section { margin-top: 2rem; }
        .requests-section .section-title { font-size: 1.25rem; font-weight: 600; color: #25424e; margin-bottom: 1rem; text-align: center; }
        .request-item { background: #f8f9fa; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; border-left: 4px solid #E57373; }
        .request-item.pending { border-left-color: #ffc107; background: #fffbf0; }
        .request-item.approved { border-left-color: #28a745; background: #f0fff4; }
        .request-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
        .request-icon { font-size: 1.25rem; }
        .request-icon.pending { color: #ffc107; }
        .request-icon.approved { color: #28a745; }
        .request-name { font-weight: 600; color: #25424e; font-size: 1.1rem; }
        .request-details { display: grid; grid-template-columns: 1fr auto 1fr; gap: 1rem; align-items: center; margin-bottom: 0.5rem; }
        .school-info { text-align: center; }
        .school-label { font-size: 0.85rem; color: #6c757d; margin-bottom: 0.25rem; }
        .school-name { font-weight: 600; color: #25424e; }
        .exchange-arrow { color: #E57373; font-size: 1.5rem; }
        .request-date { font-size: 0.9rem; color: #6c757d; text-align: center; }
        
        .no-requests { text-align: center; padding: 2rem; color: #6c757d; }
        .no-requests p { margin-bottom: 1.5rem; font-size: 1.1rem; }
        
        @media (max-width: 768px) {
            .request-details { grid-template-columns: 1fr; gap: 0.5rem; }
            .exchange-arrow { transform: rotate(90deg); }
            .status-card { padding: 2rem 1rem 1.5rem 1rem; }
        }
        @media (max-width: 600px) { 
            .main-content { margin-left: 0; width: 100%; padding: 1rem; } 
            .sidebar { position: static; width: 100%; height: auto; } 
            .card-modern { padding: 1.5rem 0.5rem 1.5rem 0.5rem; } 
            .card-modern .card-title { font-size: 1.15rem; } 
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
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
                            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-home"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
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
                            <a href="search.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : ''; ?>">
                                <i class="fas fa-search"></i>
                                <span>Search Options</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="my-requests.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-requests.php' ? 'active' : ''; ?>">
                                <i class="fas fa-exchange-alt"></i>
                                My Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="messages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
                                <i class="fas fa-envelope"></i>
                                <span>Messages</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="request-history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'request-history.php' ? 'active' : ''; ?>">
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
            <header class="header">
                <div></div>
                <div style="text-align: center;">
                    <h1 class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?></h1>
                    <p class="welcome-subtext">Exchange Status</p>
                </div>
                <div class="user-profile" style="justify-self: end;">
                    <a href="notifications.php" class="notification-icon">
                        <i class="fas fa-bell"></i>
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
            <div class="container mx-auto p-4">
                <div class="status-card">
                    <div class="icon-badge"><i class="fas fa-handshake"></i></div>
                    <div class="card-title">Exchange Status</div>
                    <div class="card-body">
                        <?php 
                        $hasAnyRequests = !empty($pendingRequests) || !empty($approvedRequests);
                        $currentStatus = 'N/A';
                        $statusClass = 'na';
                        if (!empty($pendingRequests)) {
                            $currentStatus = 'Pending';
                            $statusClass = 'pending';
                        } elseif (!empty($approvedRequests)) {
                            $currentStatus = 'Approved';
                            $statusClass = 'approved';
                        }
                        ?>
                        
                        <div class="status-info">
                            <p>
                                <strong>Current Status:</strong> 
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($currentStatus); ?></span>
                            </p>
                            
                            <?php if ($currentStatus === 'Pending'): ?>
                                <p style="margin-top: 1rem; margin-bottom: 0;">You have pending transfer requests. Check <a href="my-requests.php" style="color: #E57373; text-decoration: underline;">My Requests</a> for details.</p>
                            <?php elseif ($currentStatus === 'Approved'): ?>
                                <div style="display: flex; align-items: center; gap: 0.75rem; background: #d4edda; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                                    <span style="color: #28a745; font-size: 1.5rem;"><i class='fas fa-check-circle'></i></span>
                                    <span style="color: #155724; font-weight: 600;">Congratulations! Your exchange request has been approved.</span>
                                </div>
                                <?php if (!empty($approvedRequest)): ?>
                                    <p style="margin: 1rem 0 0 0; color: #155724;">
                                        <strong>Approved On:</strong> <?php echo date('M d, Y', strtotime($approvedRequest['updated_at'])); ?>
                                    </p>
                                <?php endif; ?>
                                <p style="margin-top: 1rem; margin-bottom: 0;">Please check <a href="my-requests.php" style="color: #28a745; text-decoration: underline;">My Requests</a> for next steps.</p>
                            <?php elseif (!$hasAnyRequests): ?>
                                <div class="no-requests">
                                    <p>You currently have no active exchange requests. Ready to find a match?</p>
                                    <a href="search.php" class="btn-pill">Find Exchange Options</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($pendingRequests)): ?>
                        <div class="requests-section">
                            <div class="section-title">Pending Exchange Requests</div>
                            <?php foreach ($pendingRequests as $req): ?>
                                <div class="request-item pending">
                                    <div class="request-header">
                                        <i class="fas fa-user-clock request-icon pending"></i>
                                        <span class="request-name">
                                            <?php
                                                $isRequester = ($req['requester_id'] == $_SESSION['user_id']);
                                                $otherName = $isRequester
                                                    ? $req['target_first_name'] . ' ' . $req['target_last_name']
                                                    : $req['requester_first_name'] . ' ' . $req['requester_last_name'];
                                                echo htmlspecialchars($otherName);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="request-details">
                                        <div class="school-info">
                                            <div class="school-label">Your School</div>
                                            <div class="school-name">
                                                <?php echo htmlspecialchars($isRequester ? $req['requester_school'] : $req['target_school']); ?>
                                            </div>
                                        </div>
                                        <div class="exchange-arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                        <div class="school-info">
                                            <div class="school-label">Other School</div>
                                            <div class="school-name">
                                                <?php echo htmlspecialchars($isRequester ? $req['target_school'] : $req['requester_school']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="request-date">
                                        <strong>Requested:</strong> <?php echo date('M d, Y', strtotime($req['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($approvedRequests)): ?>
                        <div class="requests-section">
                            <div class="section-title">Approved Exchange Requests</div>
                            <?php foreach ($approvedRequests as $req): ?>
                                <div class="request-item approved">
                                    <div class="request-header">
                                        <i class="fas fa-user-check request-icon approved"></i>
                                        <span class="request-name">
                                            <?php
                                                $isRequester = ($req['requester_id'] == $_SESSION['user_id']);
                                                $otherName = $isRequester
                                                    ? $req['target_first_name'] . ' ' . $req['target_last_name']
                                                    : $req['requester_first_name'] . ' ' . $req['requester_last_name'];
                                                echo htmlspecialchars($otherName);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="request-details">
                                        <div class="school-info">
                                            <div class="school-label">Your School</div>
                                            <div class="school-name">
                                                <?php echo htmlspecialchars($isRequester ? $req['requester_school'] : $req['target_school']); ?>
                                            </div>
                                        </div>
                                        <div class="exchange-arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                        <div class="school-info">
                                            <div class="school-label">Other School</div>
                                            <div class="school-name">
                                                <?php echo htmlspecialchars($isRequester ? $req['target_school'] : $req['requester_school']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="request-date">
                                        <strong>Approved:</strong> <?php echo date('M d, Y', strtotime($req['updated_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        function toggleProfileDropdown() {
            const profileDropdown = document.getElementById('profileDropdown');
            profileDropdown.classList.toggle('show');
        }
        document.addEventListener('click', (event) => {
            const profileDropdown = document.getElementById('profileDropdown');
            const profileAvatar = document.querySelector('.profile-avatar');
            if (!profileAvatar.contains(event.target) && !profileDropdown.contains(event.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html> 