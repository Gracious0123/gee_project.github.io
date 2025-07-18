<header>
    <div class="container header-content">
        <div class="logo-container">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                <path d="M6 12v5c3 3 9 3 12 0v-5" />
            </svg>
            <h1 class="logo-text">TeacherMatch</h1>
        </div>
        
        <!-- Mobile sidebar toggle button -->
        <button id="sidebar-toggle" class="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="header-actions">
            <!-- Notifications dropdown -->
            <?php
            require_once 'database.php';
            $unread_notification_count = 0;
            if (isset($_SESSION['user_id'])) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$_SESSION['user_id']]);
                    $unread_notification_count = $stmt->fetchColumn();
                } catch (PDOException $e) {
                    error_log('Error fetching unread notification count: ' . $e->getMessage());
                }
            }
            ?>
            <div class="notification-toggle" id="notifications-toggle">
                <i class="fas fa-bell"></i>
                <?php if ($unread_notification_count > 0 && basename($_SERVER['PHP_SELF']) !== 'notifications.php'): ?>
                    <span class="badge badge-primary notification-badge"><?php echo $unread_notification_count; ?></span>
                <?php endif; ?>
            </div>
            
            <div id="notifications-dropdown" class="notifications-dropdown hidden">
                <style>
                .notifications-dropdown {
                    width: 350px;
                    background: #fff;
                    border-radius: 1rem;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
                    overflow: hidden;
                    font-family: 'Inter', sans-serif;
                    color: #1A202C;
                    position: absolute;
                    right: 2rem;
                    top: 4rem;
                    z-index: 1000;
                }
                .dropdown-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem 1.25rem;
                    border-bottom: 1px solid #E2E8F0;
                    background: #F7FAFC;
                }
                .dropdown-title {
                    font-size: 1.1rem;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .view-all {
                    font-size: 0.95rem;
                    color: #4FD1C5;
                    text-decoration: none;
                    font-weight: 500;
                }
                .dropdown-content {
                    max-height: 350px;
                    overflow-y: auto;
                    padding: 0.5rem 0;
                }
                .notification-item {
                    display: flex;
                    align-items: flex-start;
                    gap: 1rem;
                    padding: 1rem 1.25rem;
                    border-bottom: 1px solid #F1F5F9;
                    background: #fff;
                    transition: background 0.2s;
                    text-decoration: none;
                }
                .notification-item:last-child {
                    border-bottom: none;
                }
                .notification-item.unread {
                    background: #F0FDFA;
                    border-left: 4px solid #4FD1C5;
                }
                .icon {
                    width: 2.5rem;
                    height: 2.5rem;
                    background: #E6FFFA;
                    color: #38B2AC;
                    border-radius: 0.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.3rem;
                    flex-shrink: 0;
                }
                .icon.message { background: #DBEAFE; color: #3B82F6; }
                .icon.request_accepted { background: #D1FAE5; color: #10B981; }
                .icon.request_declined { background: #FEE2E2; color: #EF4444; }
                .icon.system { background: #FEF3C7; color: #F59E0B; }
                .info { flex: 1; }
                .title { font-weight: 600; font-size: 1rem; margin-bottom: 0.2rem; }
                .message { font-size: 0.95rem; color: #4A5568; margin-bottom: 0.2rem; }
                .meta { font-size: 0.85rem; color: #A0AEC0; }
                .dropdown-footer {
                    padding: 0.75rem 1.25rem;
                    background: #F7FAFC;
                    text-align: center;
                }
                .see-all-btn {
                    color: #3182CE;
                    font-weight: 600;
                    text-decoration: none;
                    font-size: 0.98rem;
                    transition: color 0.2s;
                }
                .see-all-btn:hover { color: #2B6CB0; }
                .notification-item.empty {
                    justify-content: center;
                    color: #A0AEC0;
                    background: #F7FAFC;
                }
                </style>
                <div class="dropdown-header">
                    <h3 class="dropdown-title"><i class="fas fa-bell"></i> Notifications</h3>
                    <a href="notifications.php" class="view-all">View all</a>
                </div>
                <div class="dropdown-content">
                    <?php
                    require_once 'database.php';
                    try {
                        $stmt = $pdo->prepare("
                            SELECT 
                                n.type, n.title, n.message, n.created_at, n.link, n.is_read
                            FROM notifications n
                            WHERE n.user_id = ?
                            ORDER BY n.created_at DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $notifications = $stmt->fetchAll();
                        if (empty($notifications)): ?>
                            <div class="notification-item empty">
                                <div class="info">No new notifications</div>
                            </div>
                        <?php else:
                            foreach ($notifications as $notification):
                                $iconClass = 'fa-bell';
                                $iconBg = '';
                                switch ($notification['type']) {
                                    case 'request':
                                        $iconClass = 'fa-file-alt';
                                        $iconBg = '';
                                        break;
                                    case 'message':
                                        $iconClass = 'fa-comment';
                                        $iconBg = 'message';
                                        break;
                                    case 'request_accepted':
                                        $iconClass = 'fa-check';
                                        $iconBg = 'request_accepted';
                                        break;
                                    case 'request_declined':
                                        $iconClass = 'fa-times';
                                        $iconBg = 'request_declined';
                                        break;
                                    case 'system':
                                        $iconClass = 'fa-bell';
                                        $iconBg = 'system';
                                        break;
                                }
                                $unread = !$notification['is_read'] ? 'unread' : '';
                                ?>
                                <a href="<?php echo $notification['link']; ?>" class="notification-item <?php echo $unread; ?>">
                                    <div class="icon <?php echo $iconBg; ?>">
                                        <i class="fas <?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="info">
                                        <div class="title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="meta"><?php echo timeAgo($notification['created_at']); ?></div>
                                    </div>
                                </a>
                            <?php endforeach;
                        endif;
                    } catch (PDOException $e) {
                        error_log("Error fetching notifications: " . $e->getMessage());
                    }
                    ?>
                </div>
                <div class="dropdown-footer">
                    <a href="notifications.php" class="see-all-btn">See all notifications</a>
                </div>
            </div>
            
            <!-- User profile dropdown -->
            <div class="profile-toggle" id="profile-toggle">
                <div class="user-avatar">
                    <span class="user-initials">
                        <?php
                        $initials = '';
                        $name_parts = explode(' ', $_SESSION['user_name'] ?? 'User');
                        foreach ($name_parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        echo $initials;
                        ?>
                    </span>
                </div>
            </div>
            
            <div id="profile-dropdown" class="dropdown profile-dropdown hidden">
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                    <p class="user-email"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'user@example.com'); ?></p>
                </div>
                <div class="profile-menu">
                    <a href="profile.php" class="profile-menu-item">
                        <div class="menu-item-content">
                            <i class="fas fa-user menu-item-icon"></i>
                            <span>My Profile</span>
                        </div>
                    </a>
                    <a href="preferences.php" class="profile-menu-item">
                        <div class="menu-item-content">
                            <i class="fas fa-cog menu-item-icon"></i>
                            <span>Preferences</span>
                        </div>
                    </a>
                    <div class="menu-divider"></div>
                    <a href="logout.php" class="profile-menu-item">
                        <div class="menu-item-content danger">
                            <i class="fas fa-sign-out-alt menu-item-icon"></i>
                            <span>Logout</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<?php
// Add this function at the end of the file
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 172800) {
        return "Yesterday";
    } else {
        return date('M j, Y', $time);
    }
}
?>
