<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Function to get time ago string
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 172800) {
        return "Yesterday";
    } else {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    }
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$_SESSION['user_id']]);
        header("Location: notifications.php");
        exit;
    } catch (PDOException $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
    }
}

// Fetch user's notifications
try {
    $stmt = $pdo->prepare("
        SELECT n.*, u.first_name, u.last_name, u.school_id, s.name as school_name
        FROM notifications n
        LEFT JOIN users u ON n.target_user_id = u.id
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $notifications = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .notification-header {
            background: #25424e;
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .notification-header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .back-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .notification-count {
            background-color: #4FD1C5;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .notification-actions {
            display: flex;
            gap: 1rem;
        }

        .action-button {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s ease;
        }

        .action-button:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .action-button i {
            font-size: 1rem;
        }

        @media (max-width: 640px) {
            .notification-header .container {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1rem;
            }

            .header-left {
                flex-direction: column;
                gap: 0.5rem;
            }

            .notification-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .action-button {
                width: 100%;
                justify-content: center;
            }

            .notification-list {
                padding: 0 1rem;
            }

            .notification-item {
                padding: 1rem;
            }

            .notification-actions-item {
                position: static;
                margin-top: 1rem;
                justify-content: flex-end;
            }

            .notification-content {
                padding-right: 0;
            }
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .notification-item {
            background: white;
            border-radius: 8px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            position: relative;
        }

        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .notification-item.unread {
            border-left-color: #4a90e2;
            background-color: #f8fafc;
        }

        .notification-content {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .notification-content p {
            margin: 0;
            color: #2d3748;
            line-height: 1.5;
        }

        .notification-time {
            color: #718096;
            font-size: 0.875rem;
        }

        .notification-actions-item {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .notification-action-btn {
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .notification-action-btn:hover {
            color: #4a90e2;
            background-color: rgba(74, 144, 226, 0.1);
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

        .empty-state h2 {
            margin: 1rem 0 0.5rem;
            color: #2d3748;
            font-size: 1.5rem;
        }

        .empty-state p {
            color: #718096;
            margin: 0;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #2d3748;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateY(100%);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast i {
            font-size: 1.25rem;
        }

        .toast.success {
            background: #48bb78;
        }

        .toast.error {
            background: #f56565;
        }
    </style>
</head>
<body>
    <header class="notification-header">
        <div class="flex items-center" style="gap: 1.5rem; padding: 0 1.5rem;">
            <div class="header-left">
                <a href="dashboard.php" class="back-link" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px;">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="header-title" style="font-size: 1.25rem; font-weight: 700;">Notifications</h1>
                <?php if (count($notifications) > 0): ?>
                    <span class="notification-count"><?php echo count($notifications); ?></span>
                <?php endif; ?>
            </div>
            <div class="notification-actions">
                <button class="action-button" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i>
                    Mark All as Read
                </button>
                <button class="action-button" onclick="clearAllNotifications()">
                    <i class="fas fa-trash"></i>
                    Clear All
                </button>
            </div>
        </div>
    </header>

    <main class="container" style="padding: 2rem 1.5rem;">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h2>No Notifications</h2>
                <p>You don't have any notifications yet.</p>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                         data-id="<?php echo $notification['id']; ?>">
                        <div class="notification-content">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small class="notification-time">
                                <?php echo getTimeAgo($notification['created_at']); ?>
                            </small>
                        </div>
                        <div class="notification-actions-item">
                            <?php if (!$notification['is_read']): ?>
                                <button class="notification-action-btn mark-read" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button class="notification-action-btn delete" title="Delete notification">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="toast" class="toast">
        <i class="fas fa-info-circle"></i>
        <span id="toast-message"></span>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            
            toast.className = `toast ${type}`;
            toastMessage.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        async function markAllAsRead() {
            try {
                const response = await fetch('mark_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ mark_all: true })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.querySelector('.mark-read')?.remove();
                    });
                    showToast('All notifications marked as read');
                } else {
                    showToast('Failed to mark notifications as read', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            }
        }

        async function clearAllNotifications() {
            if (!confirm('Are you sure you want to clear all notifications?')) {
                return;
            }

            try {
                const response = await fetch('delete_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ delete_all: true })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.querySelector('.notification-list').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h2>No Notifications</h2>
                            <p>You don't have any notifications yet.</p>
                        </div>
                    `;
                    showToast('All notifications cleared');
                } else {
                    showToast('Failed to clear notifications', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            }
        }

        // Add event listeners for individual notification actions
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.mark-read').forEach(button => {
                button.addEventListener('click', async (e) => {
                    const notificationItem = e.target.closest('.notification-item');
                    const notificationId = notificationItem.dataset.id;
                    
                    try {
                        const response = await fetch('mark_notifications_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ notification_id: notificationId })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            notificationItem.classList.remove('unread');
                            button.remove();
                            showToast('Notification marked as read');
                        } else {
                            showToast('Failed to mark notification as read', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showToast('An error occurred', 'error');
                    }
                });
            });

            document.querySelectorAll('.delete').forEach(button => {
                button.addEventListener('click', async (e) => {
                    const notificationItem = e.target.closest('.notification-item');
                    const notificationId = notificationItem.dataset.id;
                    
                    try {
                        const response = await fetch('delete_notifications.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ notification_id: notificationId })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            notificationItem.remove();
                            showToast('Notification deleted');
                            
                            // Check if there are any notifications left
                            if (document.querySelectorAll('.notification-item').length === 0) {
                                document.querySelector('.notification-list').innerHTML = `
                                    <div class="empty-state">
                                        <i class="fas fa-bell-slash"></i>
                                        <h2>No Notifications</h2>
                                        <p>You don't have any notifications yet.</p>
                                    </div>
                                `;
                            }
                        } else {
                            showToast('Failed to delete notification', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showToast('An error occurred', 'error');
                    }
                });
            });
        });
    </script>
</body>
</html>
