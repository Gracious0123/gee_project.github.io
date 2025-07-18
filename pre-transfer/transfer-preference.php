<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$user_name = $_SESSION['user_name'] ?? 'User';

require_once 'database.php';

// Fetch user preferences from the database
$preferred_districts = $preferred_schools = $preferred_subject = $preferred_grade_level = 'N/A';
if (isset($_SESSION['user_id'])) {
    try {
        // Fetch preferred districts
        $stmt = $pdo->prepare("SELECT district FROM user_preferred_districts WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $preferred_districts = $districts ? implode(', ', $districts) : 'N/A';

        // Fetch preferred schools (names)
        $stmt = $pdo->prepare("SELECT s.name FROM user_preferred_schools ups JOIN schools s ON ups.school_id = s.id WHERE ups.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $schools = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $preferred_schools = $schools ? implode(', ', $schools) : 'N/A';

        // Fetch other preferences
        $stmt = $pdo->prepare("SELECT preferred_subject, preferred_grade_level FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        $preferred_subject = $prefs['preferred_subject'] ?: 'N/A';
        $preferred_grade_level = $prefs['preferred_grade_level'] ?: 'N/A';
    } catch (PDOException $e) {
        error_log('Error fetching preferences: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Preferences - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background:#4a5568;
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
        .header { display: flex; justify-content: center; align-items: center; position: relative; margin-bottom: 2rem; background-color: #25424e; padding: 0.1rem 2rem; border-radius: 0.5rem; color: #E2E8F0; }
        .welcome-text { font-size: 1.5rem; font-weight: 600; }
        .welcome-subtext { color:rgb(239, 241, 245); margin-top: 0.25rem; }
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
        /* Card styles for transfer preferences */
        .card-modern { max-width: 420px; width: 100%; margin: 2rem auto; border-radius: 18px; box-shadow: 0 4px 24px rgba(44,62,80,0.10); background: #fff; border-top: 6px solid #4CAF50; display: flex; flex-direction: column; align-items: center; padding: 2.5rem 2rem 2rem 2rem; position: relative; }
        .card-modern .icon-badge { background: #4CAF50; color: #fff; border-radius: 50%; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; font-size: 2rem; position: absolute; top: -32px; left: 50%; transform: translateX(-50%); box-shadow: 0 2px 8px rgba(44,62,80,0.10); }
        .card-modern .card-title { margin-top: 2.5rem; font-size: 1.5rem; font-weight: 700; color: #25424e; letter-spacing: 1px; text-align: center; }
        .card-modern .card-body { margin-top: 1.5rem; width: 100%; color: #25424e; }
        .info-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-top: 0.5rem;
            width: 100%;
        }
        .info-card {
            background: #e0f7ec;
            border-radius: 12px;
            padding: 1.1rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 4px rgba(44,62,80,0.04);
            width: 90%;
            min-width: 0;
        }
        .info-icon {
            border-radius: 50%;
            width: 2.2rem;
            height: 2.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        @media (max-width: 700px) {
            .info-cards-grid {
                grid-template-columns: 1fr;
            }
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
                <div style="text-align: center;">
                    <h1 class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?></h1>
                    <p class="welcome-subtext">Transfer Preferences</p>
                </div>
                <div class="user-profile" style="position: absolute; right: 2rem;">
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
                <div style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
                    <div class="card-modern">
                        <div class="icon-badge"><i class="fas fa-exchange-alt"></i></div>
                        <div class="card-title">Transfer Preferences</div>
                        <div class="card-body">
                            <div class="info-cards-grid">
                                <div class="info-card"><span class="info-icon" style="background: #4CAF50; color: #fff;"><i class="fas fa-map-marker-alt"></i></span><div><div style="font-size: 0.95rem; color: #404854;">Preferred Districts</div><div style="font-weight: 600; color: #25424e;"><?php echo htmlspecialchars($preferred_districts); ?></div></div></div>
                                <div class="info-card"><span class="info-icon" style="background: #43a047; color: #fff;"><i class="fas fa-school"></i></span><div><div style="font-size: 0.95rem; color: #404854;">Preferred Schools</div><div style="font-weight: 600; color: #25424e;"><?php echo htmlspecialchars($preferred_schools); ?></div></div></div>
                                <div class="info-card"><span class="info-icon" style="background: #388e3c; color: #fff;"><i class="fas fa-book"></i></span><div><div style="font-size: 0.95rem; color:#404854;">Preferred Subject</div><div style="font-weight: 600; color: #25424e;"><?php echo htmlspecialchars($preferred_subject); ?></div></div></div>
                                <div class="info-card"><span class="info-icon" style="background: #2e7d32; color: #fff;"><i class="fas fa-layer-group"></i></span><div><div style="font-size: 0.95rem; color:#404854;">Preferred Grade Level</div><div style="font-weight: 600; color: #25424e;"><?php echo htmlspecialchars($preferred_grade_level); ?></div></div></div>
                            </div>
                          
                        </div>
                        <div style="text-align: center; width: 100%; margin-top: 2rem;">
                            <a href="preferences.php" class="btn-pill" style="background: #4CAF50; color: #fff; text-decoration: none; padding: 0.85rem 1.5rem; border-radius: 999px; font-weight: 600; font-size: 1.08rem; transition: background 0.2s;">Edit Preferences</a>
                        </div>
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