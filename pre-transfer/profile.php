<?php
// Enable error reporting at the top of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

$success_message = '';
$error_message = '';

// Add phone column if it doesn't exist
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    }
} catch (PDOException $e) {
    error_log("Error checking/adding phone column: " . $e->getMessage());
}

// Fetch user data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.name as school_name, s.district as school_district, s.region as school_region,
               s.address as school_address, s.contact_number as school_contact,
               s.email as school_email, s.principal_name as school_principal
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Fetch all available schools for the dropdown
    $stmt = $pdo->prepare("SELECT id, name, district FROM schools WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $schools = $stmt->fetchAll();

    // Fetch user's current preferences from normalized tables
    $stmt = $pdo->prepare("SELECT district FROM user_preferred_districts WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $districts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $preferred_districts = implode(', ', $districts);

    $stmt = $pdo->prepare("SELECT s.name FROM user_preferred_schools ups JOIN schools s ON ups.school_id = s.id WHERE ups.user_id = ?");
    $stmt->execute([$user['id']]);
    $schools = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $preferred_schools = implode(', ', $schools);
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $error_message = "Error loading profile data. Please try again.";
    $user = [];
    $schools = [];
    $preferred_districts = $preferred_schools = '';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log the raw POST data
        error_log("Raw POST data: " . print_r($_POST, true));
        
        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Email is already taken by another user");
        }

        // Update user profile
        try {
            error_log("=== Profile Update Start ===");
            error_log("User ID: " . $_SESSION['user_id']);
        error_log("POST data: " . print_r($_POST, true));

        // Validate required fields
        $required_fields = ['firstName', 'lastName', 'email', 'school_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Required field missing: " . $field);
            }
        }

            // First, verify the school exists
            $checkSchool = $pdo->prepare("SELECT id, name FROM schools WHERE id = ?");
            $checkSchool->execute([$_POST['school_id']]);
            $school = $checkSchool->fetch(PDO::FETCH_ASSOC);
            error_log("Selected school: " . print_r($school, true));
            
            if (!$school) {
                throw new Exception("Invalid school selected");
            }

            // Get current user data
            $currentUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $currentUser->execute([$_SESSION['user_id']]);
            $userBefore = $currentUser->fetch(PDO::FETCH_ASSOC);
            error_log("User data before update: " . print_r($userBefore, true));

        $stmt = $pdo->prepare("
            UPDATE users 
                SET 
                    first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                school_id = ?,
                    updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $params = [
            $_POST['firstName'],
            $_POST['lastName'],
            $_POST['email'],
            $_POST['phone'] ?? null,
            $_POST['school_id'],
            $_SESSION['user_id']
        ];

        error_log("Update parameters: " . print_r($params, true));
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            error_log("Database error: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Database error occurred while updating profile: " . implode(", ", $stmt->errorInfo()));
        }
        
            // Verify the update with school information
            $verifyStmt = $pdo->prepare("
                SELECT 
                    u.*,
                    s.id as school_id,
                    s.name as school_name,
                    s.district as school_district,
                    s.region as school_region,
                    s.address as school_address,
                    s.contact_number as school_contact,
                    s.email as school_email,
                    s.principal_name as school_principal
            FROM users u
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE u.id = ?
        ");
            $verifyStmt->execute([$_SESSION['user_id']]);
            $userAfter = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            error_log("User data after update: " . print_r($userAfter, true));
        
            // Update session with new data
            $_SESSION['first_name'] = $userAfter['first_name'];
            $_SESSION['last_name'] = $userAfter['last_name'];
            $_SESSION['email'] = $userAfter['email'];
            $_SESSION['phone'] = $userAfter['phone'];
            $_SESSION['school_id'] = $userAfter['school_id'];
            $_SESSION['school_name'] = $userAfter['school_name'];
            $_SESSION['school_district'] = $userAfter['school_district'];
            $_SESSION['school_region'] = $userAfter['school_region'];
            $_SESSION['school_address'] = $userAfter['school_address'];
            $_SESSION['school_contact'] = $userAfter['school_contact'];
            $_SESSION['school_email'] = $userAfter['school_email'];
            $_SESSION['school_principal'] = $userAfter['school_principal'];
            
            error_log("Updated session data: " . print_r($_SESSION, true));
            error_log("=== Profile Update End ===");
            
            $_SESSION['success'] = "Profile updated successfully!";
        
            // Redirect to dashboard instead of profile page
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
            header("Location: profile.php");
            exit();
        }

        // Update preferred districts
        $pdo->prepare("DELETE FROM user_preferred_districts WHERE user_id = ?")->execute([$user['id']]);
        $districts = array_filter(array_map('trim', explode(',', $_POST['preferredDistricts'] ?? '')));
        foreach ($districts as $district) {
            $pdo->prepare("INSERT INTO user_preferred_districts (user_id, district) VALUES (?, ?)")->execute([$user['id'], $district]);
        }
        // Update preferred schools
        $pdo->prepare("DELETE FROM user_preferred_schools WHERE user_id = ?")->execute([$user['id']]);
        $school_names = array_filter(array_map('trim', explode(',', $_POST['preferredSchools'] ?? '')));
        foreach ($school_names as $school_name) {
            // Find school ID by name (flexible matching)
            $stmt = $pdo->prepare("SELECT id FROM schools WHERE LOWER(name) LIKE LOWER(?)");
            $stmt->execute(['%' . $school_name . '%']);
            $school_id = $stmt->fetchColumn();
            if ($school_id) {
                $pdo->prepare("INSERT INTO user_preferred_schools (user_id, school_id) VALUES (?, ?)")->execute([$user['id'], $school_id]);
            } else {
                error_log("School not found: " . $school_name);
            }
        }
    } catch (Exception $e) {
        error_log("Profile Update Error: " . $e->getMessage());
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        header("Location: profile.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TeacherMatch</title>
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
                <h1 class="text-xl font-bold" style="color: #ffffff;">My Profile</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <div style="max-width: 48rem; margin: 0 auto;">
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
                
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="height: 5rem; width: 5rem; border-radius: 9999px; background-color: var(--accent-color); color: var(--secondary-bg); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h1 style="font-size: 1.5rem; font-weight: 700;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                        <p style="color: var(--text-gray-300);"><?php echo htmlspecialchars($user['preferred_subject'] ?? 'Teacher'); ?>, <?php echo htmlspecialchars($user['school_name'] ?? 'No School Assigned'); ?></p>
                    </div>
                </div>

                <form id="profile-form" method="POST" action="profile.php">
                    <div class="card" style="margin-bottom: 1.5rem;">
                        <div class="card-header">
                            <h2 class="card-title">Personal Information</h2>
                            <p class="card-description">Update your personal details</p>
                        </div>
                        <div class="card-content">
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                <div class="form-group">
                                    <label for="firstName" style="color: var(--accent-color);">First Name</label>
                                    <input 
                                        type="text" 
                                        id="firstName" 
                                        name="firstName" 
                                        value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                        class="input-field"
                                        required
                                    >
                                </div>
                                <div class="form-group">
                                    <label for="lastName" style="color: var(--accent-color);">Last Name</label>
                                    <input 
                                        type="text" 
                                        id="lastName" 
                                        name="lastName" 
                                        value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                        class="input-field"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 1rem;">
                                <label for="email" style="color: var(--accent-color);">Email</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo htmlspecialchars($user['email']); ?>" 
                                    class="input-field"
                                    required
                                >
                            </div>

                            <div class="form-group" style="margin-top: 1rem;">
                                <label for="phone" style="color: var(--accent-color);">Phone Number (Optional)</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                    class="input-field"
                                >
                            </div>
                        </div>
                    </div>
                    <!-- Removed the Current School Information card as requested -->

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Transfer Preferences</h2>
                            <p class="card-description">Set your preferences for teacher transfers</p>
                        </div>
                        <div class="card-content">
                            <div class="form-group">
                                <label for="preferredDistricts" style="color: var(--accent-color);">Preferred Districts</label>
                                <input 
                                    type="text" 
                                    id="preferredDistricts" 
                                    name="preferredDistricts" 
                                    value="<?php echo htmlspecialchars($preferred_districts); ?>" 
                                    class="input-field"
                                >
                            </div>

                            <div class="form-group" style="margin-top: 1rem;">
                                <label for="preferredSchools" style="color: var(--accent-color);">Preferred Schools</label>
                                <textarea 
                                    id="preferredSchools" 
                                    name="preferredSchools" 
                                    placeholder="Enter specific schools (optional)" 
                                    class="input-field" 
                                    style="min-height: 5rem;"
                                ><?php echo htmlspecialchars($preferred_schools); ?></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem;">
                                <div class="form-group">
                                    <label for="preferredSubject" style="color: var(--accent-color);">Preferred Subject</label>
                                    <input 
                                        type="text" 
                                        id="preferredSubject" 
                                        name="preferredSubject" 
                                        value="<?php echo htmlspecialchars($user['preferred_subject'] ?? ''); ?>" 
                                        class="input-field"
                                    >
                                </div>
                                <div class="form-group">
                                    <label for="preferredGradeLevel" style="color: var(--accent-color);">Preferred Grade Level</label>
                                    <input 
                                        type="text" 
                                        id="preferredGradeLevel" 
                                        name="preferredGradeLevel" 
                                        value="<?php echo htmlspecialchars($user['preferred_grade_level'] ?? ''); ?>" 
                                        class="input-field"
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-full">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
