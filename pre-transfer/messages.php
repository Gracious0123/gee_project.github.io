<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';
require_once 'includes/notification_functions.php';

// Function to verify and create messages table structure
function verifyMessagesTable($pdo) {
    try {
        // Check if messages table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
        if ($stmt->rowCount() == 0) {
            // Create messages table
            $pdo->exec("CREATE TABLE messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_read BOOLEAN DEFAULT FALSE,
                reply_to INT NULL,
                deleted_by_sender BOOLEAN DEFAULT FALSE,
                deleted_by_recipient BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (sender_id) REFERENCES users(id),
                FOREIGN KEY (recipient_id) REFERENCES users(id)
            )");
            error_log("Created messages table");
        } else {
            // Check for required columns
            $columns = [
                'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
                'sender_id' => 'INT NOT NULL',
                'recipient_id' => 'INT NOT NULL',
                'message' => 'TEXT NOT NULL',
                'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'is_read' => 'BOOLEAN DEFAULT FALSE',
                'reply_to' => 'INT NULL',
                'deleted_by_sender' => 'BOOLEAN DEFAULT FALSE',
                'deleted_by_recipient' => 'BOOLEAN DEFAULT FALSE'
            ];
            
            $stmt = $pdo->query("SHOW COLUMNS FROM messages");
            $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($columns as $column => $definition) {
                if (!in_array($column, $existing_columns)) {
                    $pdo->exec("ALTER TABLE messages ADD COLUMN $column $definition");
                    error_log("Added column $column to messages table");
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error verifying messages table: " . $e->getMessage());
        throw $e;
    }
}

// Verify messages table structure
try {
    verifyMessagesTable($pdo);
} catch (PDOException $e) {
    error_log("Failed to verify messages table: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $recipient_id = $_POST['recipient_id'] ?? '';
    $message = trim($_POST['message'] ?? '');
    $reply_to = !empty($_POST['reply_to']) ? $_POST['reply_to'] : null;
    
    // Log the incoming data
    error_log("Attempting to send message:");
    error_log("Recipient ID: " . $recipient_id);
    error_log("Message: " . $message);
    error_log("Reply to: " . $reply_to);
    error_log("Sender ID: " . $_SESSION['user_id']);
    
    if (!empty($recipient_id) && !empty($message)) {
        try {
            // If reply_to is set, verify the message exists
            if ($reply_to !== null) {
                $stmt = $pdo->prepare("SELECT id FROM messages WHERE id = ?");
                $stmt->execute([$reply_to]);
                if ($stmt->rowCount() === 0) {
                    $reply_to = null; // Reset reply_to if message doesn't exist
                }
            }

            // Log the SQL query
            $sql = "INSERT INTO messages (sender_id, recipient_id, message, reply_to) VALUES (?, ?, ?, ?)";
            error_log("SQL Query: " . $sql);
            error_log("Parameters: " . print_r([$_SESSION['user_id'], $recipient_id, $message, $reply_to], true));
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$_SESSION['user_id'], $recipient_id, $message, $reply_to]);
            
            if (!$result) {
                error_log("Database error: " . print_r($stmt->errorInfo(), true));
                throw new Exception("Database error occurred while sending message: " . implode(", ", $stmt->errorInfo()));
            }
            
            // Send notification to the recipient
            notifyNewMessage($recipient_id, $_SESSION['user_id'], $message);
            $success_message = 'Message sent successfully!';
            
            // Redirect to prevent form resubmission
            header("Location: messages.php?user=" . $recipient_id . "&success=1");
            exit;
        } catch (PDOException $e) {
            error_log("Error sending message: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            error_log("Error trace: " . $e->getTraceAsString());
            
            // Check if it's a foreign key constraint error
            if ($e->getCode() == 23000) {
                $error_message = "Error: The message you're replying to no longer exists.";
            } else {
                $error_message = "Error sending message. Please try again.";
            }
        } catch (Exception $e) {
            error_log("General error sending message: " . $e->getMessage());
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = "Please enter a message.";
    }
}

// Show success message if redirected after sending
if (isset($_GET['success'])) {
    $success_message = 'Message sent successfully!';
}

// Fetch conversations
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN m.sender_id = ? THEN m.recipient_id 
                ELSE m.sender_id 
            END as other_user_id,
            u.first_name,
            u.last_name,
            s.name as school_name,
            (
                SELECT message 
                FROM messages 
                WHERE ((sender_id = ? AND recipient_id = other_user_id AND deleted_by_sender = 0)
                   OR (sender_id = other_user_id AND recipient_id = ? AND deleted_by_recipient = 0))
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM messages 
                WHERE ((sender_id = ? AND recipient_id = other_user_id AND deleted_by_sender = 0)
                   OR (sender_id = other_user_id AND recipient_id = ? AND deleted_by_recipient = 0))
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*)
                FROM messages
                WHERE sender_id = other_user_id
                AND recipient_id = ?
                AND is_read = 0
                AND deleted_by_recipient = 0
            ) as unread_count
        FROM messages m
        JOIN users u ON u.id = CASE 
            WHEN m.sender_id = ? THEN m.recipient_id 
            ELSE m.sender_id 
        END
        JOIN schools s ON u.school_id = s.id
        WHERE (m.sender_id = ? AND m.deleted_by_sender = 0) OR (m.recipient_id = ? AND m.deleted_by_recipient = 0)
        ORDER BY last_message_time DESC
    ");
    
    $stmt->execute([
        $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'],
        $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'],
        $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']
    ]);
    
    $conversations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
    $conversations = [];
}

// Handle soft delete conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation_user_id'])) {
    $other_user_id = intval($_POST['delete_conversation_user_id']);
    $current_user_id = $_SESSION['user_id'];
    try {
        // Mark as deleted for the current user
        // If current user is sender, set deleted_by_sender; if recipient, set deleted_by_recipient
        $stmt = $pdo->prepare("
            UPDATE messages
            SET deleted_by_sender = CASE WHEN sender_id = ? AND recipient_id = ? THEN 1 ELSE deleted_by_sender END,
                deleted_by_recipient = CASE WHEN sender_id = ? AND recipient_id = ? THEN 1 ELSE deleted_by_recipient END
            WHERE (sender_id = ? AND recipient_id = ?)
               OR (sender_id = ? AND recipient_id = ?)
        ");
        $stmt->execute([
            $current_user_id, $other_user_id, // for deleted_by_sender
            $other_user_id, $current_user_id, // for deleted_by_recipient
            $current_user_id, $other_user_id, // for WHERE
            $other_user_id, $current_user_id  // for WHERE
        ]);
        // Redirect to messages page (no user selected)
        header('Location: messages.php');
        exit;
    } catch (PDOException $e) {
        error_log('Error soft deleting conversation: ' . $e->getMessage());
        $error_message = 'Failed to delete conversation.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --accent-color: #4ECDC4;
            --secondary-bg:rgb(18, 17, 17);
            --text-gray-300: #9CA3AF;
        }

        .conversation-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: #ffffff;
            transition: background-color 0.2s ease;
        }

        .conversation-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .conversation-item.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .conversation-info h3 {
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .conversation-info p {
            color: var(--text-gray-300);
            font-size: 0.875rem;
        }

        .last-message {
            color: var(--text-gray-300);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .conversation-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .conversation-time {
            color: var(--text-gray-300);
            font-size: 0.75rem;
        }

        .unread-badge {
            background-color: var(--accent-color);
            color: #000000;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
        }

        .messages-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 4rem);
        }

        .conversations-list {
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            overflow-y: auto;
        }

        .messages-chat {
            display: flex;
            flex-direction: column;
        }

        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .message-form {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .card{
        background-color: var(--secondary-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        flex: 1;
        width: auto;
        }
        .card-header {
            padding: 1rem 1rem 0.5rem 1rem;
            background: linear-gradient(45deg, #2f5464, transparent);
        }
        .card-title {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: white;
            font-size: 0.875rem;
        }

        .reply-button {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px 8px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            transition: opacity 0.2s ease;
        }

        .reply-button:hover {
            opacity: 0.8;
            text-decoration: none;
        }

        .reply-to-message {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--accent-color);
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .reply-to-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-gray-300);
            margin-bottom: 4px;
        }

        .reply-to-content {
            font-size: 0.875rem;
            color: white;
            margin: 0;
        }

        .reply-preview {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 8px;
        }

        .reply-preview-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #ffffff;
        }

        .cancel-reply {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px;
            transition: opacity 0.2s ease;
        }

        .cancel-reply:hover {
            opacity: 0.8;
        }

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

        .message-content {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .message.sent .message-content {
            background: var(--accent-color);
            color: #000000;
        }

        .message.received .message-content {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .message-sender {
            font-weight: 500;
            color: #ffffff;
        }

        .message-time {
            font-size: 0.75rem;
            color: white;
        }

        .message.sent .message-sender,
        .message.sent .message-time {
            color: white;
        }
        .message.sent .message-content {
            background: #2f5464;
}
        .message.sent .message-sender{
            color: #ffffff;
}
        .message-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            resize: none;
            min-height: 60px;
            margin-bottom: 12px;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .message-input::placeholder {
            color: var(--text-gray-300);
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: #000000;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.2s ease;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            padding: 32px;
            color: var(--text-gray-300);
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 16px;
            color: var(--accent-color);
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
                <h1 class="text-xl font-bold" style="color: #ffffff;">Messages</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <?php if ($success_message): ?>
                <div style="background-color: rgba(78, 205, 196, 0.2); border: 1px solid var(--accent-color); color: var(--accent-color); padding: 0.75rem; border-radius: 0.375rem; margin-bottom: 1.5rem;">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="background-color: rgba(255, 0, 0, 0.1); border: 1px solid #ff0000; color: #ff0000; padding: 0.75rem; border-radius: 0.375rem; margin-bottom: 1.5rem;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="messages-container">
                <div class="conversations-list">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Conversations</h2>
                            <p class="card-description">Your message history</p>
                        </div>

                        <div class="conversations">
                            <?php if (empty($conversations)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comments"></i>
                                    <p>No conversations yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conversation): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <a href="?user=<?php echo $conversation['other_user_id']; ?>" 
                                           class="conversation-item <?php echo isset($_GET['user']) && $_GET['user'] == $conversation['other_user_id'] ? 'active' : ''; ?>">
                                            <div class="conversation-info">
                                                <h3><?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?></h3>
                                                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($conversation['school_name']); ?></p>
                                                <p class="last-message"><?php echo htmlspecialchars($conversation['last_message']); ?></p>
                                            </div>
                                            <div class="conversation-meta">
                                                <div class="conversation-time">
                                                    <?php echo date('M d', strtotime($conversation['last_message_time'])); ?>
                                                </div>
                                                <?php if ($conversation['unread_count'] > 0 && (!isset($_GET['user']) || $_GET['user'] != $conversation['other_user_id'])): ?>
                                                    <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this conversation?');" style="margin-left: 8px;">
                                            <input type="hidden" name="delete_conversation_user_id" value="<?php echo $conversation['other_user_id']; ?>">
                                            <button type="submit" style="background: none; border: none; color: #ff4d4f; cursor: pointer; font-size: 1.1rem;" title="Delete Conversation">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['user'])): ?>
                    <div class="messages-chat">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Chat</h2>
                                <p class="card-description">Send and receive messages</p>
                            </div>

                            <div class="messages-list" id="messagesList">
                                <?php
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT m.*, 
                                               u.first_name, 
                                               u.last_name,
                                               u.school_id,
                                               s.name as school_name,
                                               r.message as reply_to_message,
                                               r.created_at as reply_to_time,
                                               ru.first_name as reply_to_first_name,
                                               ru.last_name as reply_to_last_name
                                        FROM messages m
                                        JOIN users u ON u.id = m.sender_id
                                        LEFT JOIN schools s ON u.school_id = s.id
                                        LEFT JOIN messages r ON m.reply_to = r.id
                                        LEFT JOIN users ru ON r.sender_id = ru.id
                                        WHERE ((m.sender_id = ? AND m.recipient_id = ? AND m.deleted_by_sender = 0)
                                           OR (m.sender_id = ? AND m.recipient_id = ? AND m.deleted_by_recipient = 0))
                                        ORDER BY m.created_at ASC
                                    ");
                                    $stmt->execute([
                                        $_SESSION['user_id'],
                                        $_GET['user'],
                                        $_GET['user'],
                                        $_SESSION['user_id']
                                    ]);
                                    $messages = $stmt->fetchAll();

                                    // Mark messages as read
                                    $stmt = $pdo->prepare("
                                        UPDATE messages 
                                        SET is_read = 1 
                                        WHERE sender_id = ? 
                                        AND recipient_id = ? 
                                        AND is_read = 0
                                        AND deleted_by_recipient = 0
                                    ");
                                    $stmt->execute([$_GET['user'], $_SESSION['user_id']]);
                                } catch (PDOException $e) {
                                    error_log("Error fetching messages: " . $e->getMessage());
                                    $messages = [];
                                }
                                ?>

                                <?php if (empty($messages)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-comments"></i>
                                        <p>No messages yet</p>
                                        <p class="text-sm text-gray-400">Start the conversation!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received'; ?>" id="message-<?php echo $message['id']; ?>">
                                            <div class="message-content">
                                                <div class="message-header">
                                                    <span class="message-sender">
                                                        <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                                                        <?php if ($message['school_name']): ?>
                                                            <span class="text-sm text-gray-400">
                                                                (<?php echo htmlspecialchars($message['school_name']); ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="message-time">
                                                        <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <?php if ($message['reply_to_message']): ?>
                                                    <div class="reply-to-message">
                                                        <div class="reply-to-header">
                                                            <span class="reply-to-sender">
                                                                <?php echo htmlspecialchars($message['reply_to_first_name'] . ' ' . $message['reply_to_last_name']); ?>
                                                            </span>
                                                            <span class="reply-to-time">
                                                                <?php echo date('h:i A', strtotime($message['reply_to_time'])); ?>
                                                            </span>
                                                        </div>
                                                        <p class="reply-to-content"><?php echo htmlspecialchars($message['reply_to_message']); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                <p><?php echo htmlspecialchars($message['message']); ?></p>
                                                <?php if ($message['sender_id'] != $_SESSION['user_id']): ?>
                                                    <button class="reply-button" onclick="replyToMessage(<?php echo $message['id']; ?>, '<?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>')">
                                                        <i class="fas fa-reply"></i> Reply
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <form method="POST" class="message-form">
                                <input type="hidden" name="recipient_id" value="<?php echo $_GET['user']; ?>">
                                <input type="hidden" name="reply_to" id="reply_to" value="">
                                <div id="reply-preview" class="reply-preview" style="display: none;">
                                    <div class="reply-preview-content">
                                        <span id="reply-to-name"></span>
                                        <button type="button" onclick="cancelReply()" class="cancel-reply">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <textarea 
                                    name="message" 
                                    placeholder="Type your message..." 
                                    required
                                    class="message-input"
                                    id="message-input"
                                ></textarea>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                    Send
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Scroll to bottom of messages
        const messagesList = document.getElementById('messagesList');
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        // Auto-resize textarea
        const textarea = document.querySelector('.message-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }

        function replyToMessage(messageId, senderName) {
            document.getElementById('reply_to').value = messageId;
            document.getElementById('reply-to-name').textContent = 'Replying to ' + senderName;
            document.getElementById('reply-preview').style.display = 'block';
            document.getElementById('message-input').focus();
        }

        function cancelReply() {
            document.getElementById('reply_to').value = '';
            document.getElementById('reply-preview').style.display = 'none';
        }
    </script>
</body>
</html> 