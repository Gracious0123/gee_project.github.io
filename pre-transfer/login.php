<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin-dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.php';
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email)) {
        $error = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (empty($password)) {
        $error = 'Password is required';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role']; // Store the user's role

                // Fetch and store school info in session
                $schoolStmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
                $schoolStmt->execute([$user['school_id']]);
                $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
                if ($school) {
                    $_SESSION['school_name'] = $school['name'];
                    $_SESSION['school_district'] = $school['district'];
                    $_SESSION['school_region'] = $school['region'];
                    $_SESSION['school_address'] = $school['address'];
                    $_SESSION['school_contact'] = $school['contact_number'];
                    $_SESSION['school_email'] = $school['email'];
                    $_SESSION['school_principal'] = $school['principal_name'];
                } else {
                    $_SESSION['school_name'] = $_SESSION['school_district'] = $_SESSION['school_region'] = $_SESSION['school_address'] = $_SESSION['school_contact'] = $_SESSION['school_email'] = $_SESSION['school_principal'] = null;
                }
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin-dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                // Check if email exists in database
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if (!$stmt->fetch()) {
                    $error = 'Email not registered. Please <a href="register.php" style="color: var(--accent-color);">register</a> first.';
                } else {
                    $error = 'Invalid password';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="min-h-screen">
        <header>
            <div class="container header-content">
                <div class="logo-container">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg>
                    <h1 class="logo-text">TeacherMatch</h1>
                </div>
                <nav>
                    <a href="about.php">About</a>
                    <a href="contact.php">Contact</a>
                    <a href="help.php">Help</a>
                </nav>
            </div>
        </header>
        
        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <div class="card" style="max-width: 28rem; margin: 0 auto;">
                <div class="card-content">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <!-- Icon above welcome text -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon" style="margin-bottom: 0.5rem;">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                            <path d="M6 12v5c3 3 9 3 12 0v-5" />
                        </svg>
                        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Welcome back</h2>
                        <p style="color: var(--text-gray-300);">Sign in to access the teacher pre-transfer matching platform</p>
                    </div>
                    <form method="POST" action="login.php">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="input-field" 
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            >
                            <?php if ($error === 'Email is required' || $error === 'Please enter a valid email address'): ?>
                                <span class="error-message"><?php echo $error; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group" style="margin-top: 1rem;">
                            <div style="display: flex; justify-content: space-between;">
                                <label for="password">Password</label>
                                <a href="forgot-password.php" class="link text-sm">Forgot password?</a>
                            </div>
                            <input type="password" id="password" name="password" class="input-field">
                            <?php if ($error === 'Password is required'): ?>
                                <span class="error-message"><?php echo $error; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($error === 'Invalid password' || strpos($error, 'Email not registered') !== false): ?>
                            <div class="error-message" style="margin-top: 0.5rem;"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top: 1.5rem;">
                            Sign in
                        </button>
                        
                        <div style="text-align: center; margin-top: 1rem; font-size: 0.875rem;">
                            Don't have an account? 
                            <a href="register.php" class="link">Register</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
   
    <script>
        // Removed password pre-fill for security
    </script>
</body>
</html>
