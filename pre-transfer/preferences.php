<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

$user_id = $_SESSION['user_id'];

// Fetch user's current preferences from normalized tables
try {
    // Preferred districts
    $stmt = $pdo->prepare("SELECT district FROM user_preferred_districts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $preferred_districts = implode(', ', $districts);

    // Preferred schools (get school names)
    $stmt = $pdo->prepare("SELECT s.name FROM user_preferred_schools ups JOIN schools s ON ups.school_id = s.id WHERE ups.user_id = ?");
    $stmt->execute([$user_id]);
    $schools = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $preferred_schools = implode(', ', $schools);

    // Other preferences
    $stmt = $pdo->prepare("SELECT preferred_subject, preferred_grade_level, school_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $preferred_subject = $user['preferred_subject'] ?? '';
    $preferred_grade_level = $user['preferred_grade_level'] ?? '';
} catch (PDOException $e) {
    error_log("Error fetching user preferences: " . $e->getMessage());
    $preferred_districts = $preferred_schools = $preferred_subject = $preferred_grade_level = '';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preferred_districts = $_POST['preferred_districts'] ?? '';
    $preferred_schools = $_POST['preferred_schools'] ?? '';
    $preferred_subject = $_POST['preferred_subject'] ?? '';
    $preferred_grade_level = $_POST['preferred_grade_level'] ?? '';
    
    try {
        // Update preferred districts
        $pdo->prepare("DELETE FROM user_preferred_districts WHERE user_id = ?")->execute([$user_id]);
        $districts = array_filter(array_map('trim', explode(',', $preferred_districts)));
        foreach ($districts as $district) {
            $pdo->prepare("INSERT INTO user_preferred_districts (user_id, district) VALUES (?, ?)")->execute([$user_id, $district]);
        }

        // Update preferred schools
        $pdo->prepare("DELETE FROM user_preferred_schools WHERE user_id = ?")->execute([$user_id]);
        $school_names = array_filter(array_map('trim', explode(',', $preferred_schools)));
        foreach ($school_names as $school_name) {
            // Find school ID by name (flexible matching)
            $stmt = $pdo->prepare("SELECT id FROM schools WHERE LOWER(name) LIKE LOWER(?)");
            $stmt->execute(['%' . $school_name . '%']);
            $school_id = $stmt->fetchColumn();
            if ($school_id) {
                $pdo->prepare("INSERT INTO user_preferred_schools (user_id, school_id) VALUES (?, ?)")->execute([$user_id, $school_id]);
            } else {
                error_log("School not found: " . $school_name);
            }
        }

        // Update other preferences
        $stmt = $pdo->prepare("UPDATE users SET preferred_subject = ?, preferred_grade_level = ? WHERE id = ?");
        $stmt->execute([$preferred_subject, $preferred_grade_level, $user_id]);

        // Find potential matches using new normalized tables
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.*, s.name as school_name, s.district as school_district
            FROM users u
            JOIN schools s ON u.school_id = s.id
            WHERE u.id != ?
            AND u.school_id != ?
            AND (
                EXISTS (
                    SELECT 1 FROM user_preferred_districts upd1 
                    JOIN user_preferred_districts upd2 ON upd1.district = upd2.district 
                    WHERE upd1.user_id = ? AND upd2.user_id = u.id
                )
                OR EXISTS (
                    SELECT 1 FROM user_preferred_schools ups1 
                    JOIN user_preferred_schools ups2 ON ups1.school_id = ups2.school_id 
                    WHERE ups1.user_id = ? AND ups2.user_id = u.id
                )
                OR u.preferred_subject = ?
                OR u.preferred_grade_level = ?
            )
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $user['school_id'],
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $preferred_subject,
            $preferred_grade_level
        ]);
        
        $matches = $stmt->fetchAll();
        
        // Create notifications for matches
        foreach ($matches as $match) {
            // Check if notification already exists for the user updating preferences
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? 
                AND target_user_id = ? 
                AND type = 'match'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute([$_SESSION['user_id'], $match['id']]);
            $exists = $stmt->fetch()['count'] > 0;
            
            if (!$exists) {
                // Create notification for the user updating preferences
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, target_user_id, type, message, created_at)
                    VALUES (?, ?, 'match', ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $match['id'],
                    "Potential match found: {$match['first_name']} {$match['last_name']} from {$match['school_name']}"
                ]);
            }

            // Check if notification already exists for the matched user
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? 
                AND target_user_id = ? 
                AND type = 'match'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute([$match['id'], $_SESSION['user_id']]);
            $exists_for_match = $stmt->fetch()['count'] > 0;

            if (!$exists_for_match) {
                // Fetch current user's name and school for the message
                $current_user_name = $user['first_name'] ?? '';
                $current_user_last = $user['last_name'] ?? '';
                $current_user_school = $user['school_name'] ?? '';
                $current_user_full = trim($current_user_name . ' ' . $current_user_last);
                $message_for_match = "Potential match found: $current_user_full from $current_user_school";
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, target_user_id, type, message, created_at)
                    VALUES (?, ?, 'match', ?, NOW())
                ");
                $stmt->execute([
                    $match['id'],
                    $_SESSION['user_id'],
                    $message_for_match
                ]);
            }
        }
        
        header("Location: dashboard.php?success=1&t=" . time());
        exit;
    } catch (PDOException $e) {
        error_log("Error updating preferences: " . $e->getMessage());
        $error = "An error occurred while saving your preferences. Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferences - TeacherMatch</title>
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
            --success-color: #10b981;
            --error-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-white);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .back-link {
            color: var(--text-white) !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease;
            font-weight: 500;
        }

        .back-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: var(--text-white) !important;
            align-items: left;
        }

        .back-link i {
            font-size: 1rem;
        }

        .header {
            background-color: var(--secondary-bg);
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: flex-start; /* Changed from space-between to flex-start for left alignment */
            gap: 1.5rem; /* Add gap for spacing */
            padding-left: 0; /* Remove left padding */
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-white);
            margin: 0;
            padding-left: 500px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            color: var(--text-gray-300);
            font-size: 1.1rem;
        }

        .card {
            background-color: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background-color: rgba(78, 205, 196, 0.1);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: var(--text-gray-300);
            font-size: 0.95rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1.5rem;
            background-color: rgba(0, 0, 0, 0.1);
            border-top: 1px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-white);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            background-color: var(--primary-bg);
            color: var(--text-white);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-gray-400);
        }

        .form-group small {
            display: block;
            color: var(--text-gray-400);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 3rem;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: var(--secondary-bg);
        }

        .btn-primary:hover {
            background-color: #3db8b0;
            transform: translateY(-1px);
        }

        .btn-full {
            width: 100%;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .preferences-grid {
            display: grid;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 0.75rem;
            }

            .dashboard-header h1 {
                font-size: 1.5rem;
            }

            .card-header,
            .card-content,
            .card-footer {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="min-h-screen">
        <header class="header">
            <div class="header-content" style="padding-left: 0;">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="page-title">Preferences</h1>
            </div>
        </header>

        <main class="container">
            <div class="dashboard-header">
                <h1><i class="fas fa-sliders-h"></i> Transfer Preferences</h1>
                <p>Set your desired teaching position and preferences</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="preferences-grid">
                <!-- Preferred Location Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Preferred Location
                        </h2>
                        <p class="card-description">Where would you like to teach?</p>
                    </div>
                    <div class="card-content">
                        <div class="form-group">
                            <label for="preferred_districts">
                                <i class="fas fa-building"></i>
                                Preferred Districts
                            </label>
                            <input type="text" 
                                   id="preferred_districts" 
                                   name="preferred_districts" 
                                   value="<?php echo htmlspecialchars($preferred_districts); ?>"
                                   placeholder="Enter district names separated by commas">
                            <small>Example: Ilemela District, Nyamagana District</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="preferred_schools">
                                <i class="fas fa-school"></i>
                                Preferred Schools
                            </label>
                            <input type="text" 
                                   id="preferred_schools" 
                                   name="preferred_schools"
                                   value="<?php echo htmlspecialchars($preferred_schools); ?>"
                                   placeholder="Enter school names separated by commas">
                            <small>Example: Isenga Primary School, Nyakahoja Primary School</small>
                        </div>
                    </div>
                </div>
                
                <!-- Teaching Preferences Card -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Teaching Preferences
                        </h2>
                        <p class="card-description">What would you like to teach?</p>
                    </div>
                    <div class="card-content">
                        <div class="form-group">
                            <label for="preferred_subject">
                                <i class="fas fa-book"></i>
                                Subject
                            </label>
                            <select id="preferred_subject" name="preferred_subject">
                                <option value="">Select a subject</option>
                                <option value="Mathematics" <?php echo ($preferred_subject === 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Science" <?php echo ($preferred_subject === 'Science') ? 'selected' : ''; ?>>Science</option>
                                <option value="English" <?php echo ($preferred_subject === 'English') ? 'selected' : ''; ?>>English</option>
                                <option value="History" <?php echo ($preferred_subject === 'History') ? 'selected' : ''; ?>>History</option>
                                <option value="Physics <?php echo ($preferred_subject === 'Physics') ? 'selected' : ''; ?>>Physics</option>
                                <option value="Kiswahili" <?php echo ($preferred_subject === 'Kiswahili') ? 'selected' : ''; ?>>Kiswahili</option>
                                <option value="Chemistry" <?php echo ($preferred_subject === 'Chemistry') ? 'selected' : ''; ?>>Chemistry</option>
                                <option value="Biology" <?php echo ($preferred_subject === 'Biology') ? 'selected' : ''; ?>>Biology</option>
                                <option value="Literature" <?php echo ($preferred_subject === 'Literature') ? 'selected' : ''; ?>>Literature</option>
                                <option value="Civics" <?php echo ($preferred_subject === 'Civics') ? 'selected' : ''; ?>>Civics</option>
                                <option value="Geography" <?php echo ($preferred_subject === 'Geography') ? 'selected' : ''; ?>>Geography</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="preferred_grade_level">
                                <i class="fas fa-layer-group"></i>
                                Grade Level
                            </label>
                            <select id="preferred_grade_level" name="preferred_grade_level">
                                <option value="">Select a grade level</option>
                                <option value="K-3" <?php echo ($preferred_grade_level === 'K-3') ? 'selected' : ''; ?>>K-3 (Kindergarten to 3rd Grade)</option>
                                <option value="4-5" <?php echo ($preferred_grade_level === '4-5') ? 'selected' : ''; ?>>4-5 (4th to 5th Grade)</option>
                                <option value="6-7" <?php echo ($preferred_grade_level === '6-7') ? 'selected' : ''; ?>>6-7 (6th to 7th Grade)</option>
                                <option value="F1-F2" <?php echo ($preferred_grade_level === 'F1-F2') ? 'selected' : ''; ?>>F1-F2 (Form 1 to Form 2)</option>
                                <option value="F2-F4" <?php echo ($preferred_grade_level === 'F2-F4') ? 'selected' : ''; ?>>F2-F4 (Form 2 to Form 4)</option>
                                <option value="F3-F4" <?php echo ($preferred_grade_level === 'F3-F4') ? 'selected' : ''; ?>>F3-F4 (Form 3 to Form 4)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button Card -->
                <div class="card">
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fas fa-save"></i>
                            Save Preferences
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</body>
</html>