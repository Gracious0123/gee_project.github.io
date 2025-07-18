<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.php';
    
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $adminKey = $_POST['adminKey'] ?? ''; // Admin registration key
    
    // Validate inputs
    if (empty($firstName)) {
        $errors['firstName'] = 'First name is required';
    }
    
    if (empty($lastName)) {
        $errors['lastName'] = 'Last name is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
    }
    
    // Validate admin key
    if (empty($adminKey) || $adminKey !== 'ADMIN_KEY_123') { // Replace with your secure admin key
        $errors['adminKey'] = 'Invalid admin registration key';
    }
    
    // If no errors, process registration
    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already registered';
            } else {
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert the new admin user
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        first_name, 
                        last_name, 
                        email, 
                        password, 
                        role, 
                        status
                    ) VALUES (?, ?, ?, ?, 'admin', 'active')
                ");
                $result = $stmt->execute([
                    $firstName, 
                    $lastName, 
                    $email, 
                    $hashedPassword
                ]);
                
                if ($result) {
                    // Registration successful
                    $_SESSION['registration_success'] = true;
                    header("Location: login.php");
                    exit;
                } else {
                    $errors['general'] = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            // Log the detailed error
            error_log("Admin Registration Error: " . $e->getMessage());
            $errors['general'] = 'Registration failed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="min-h-screen flex flex-col">
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
                    <a href="#">About</a>
                    <a href="#">Contact</a>
                    <a href="#">Help</a>
                </nav>
            </div>
        </header>
        
        <main class="main-content">
            <div class="form-container" style="max-width: 32rem;">
                <div style="padding: 1.5rem;">
                    <h2 class="card-title">Create Admin Account</h2>
                    <p class="card-description">Enter your information to create an administrator account.</p>
                </div>
                
                <form class="form-content" method="POST" action="register-admin.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First name</label>
                            <input 
                                type="text" 
                                id="firstName" 
                                name="firstName" 
                                class="input-field" 
                                value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>"
                                required
                            >
                            <?php if (isset($errors['firstName'])): ?>
                                <span class="error-message"><?php echo $errors['firstName']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">Last name</label>
                            <input 
                                type="text" 
                                id="lastName" 
                                name="lastName" 
                                class="input-field" 
                                value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>"
                                required
                            >
                            <?php if (isset($errors['lastName'])): ?>
                                <span class="error-message"><?php echo $errors['lastName']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="input-field" 
                            placeholder="admin@example.com" 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            required
                        >
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?php echo $errors['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="input-field"
                            required
                        >
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-message"><?php echo $errors['password']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm password</label>
                        <input 
                            type="password" 
                            id="confirmPassword" 
                            name="confirmPassword" 
                            class="input-field"
                            required
                        >
                        <?php if (isset($errors['confirmPassword'])): ?>
                            <span class="error-message"><?php echo $errors['confirmPassword']; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="adminKey">Admin Registration Key</label>
                        <input 
                            type="password" 
                            id="adminKey" 
                            name="adminKey" 
                            class="input-field"
                            required
                        >
                        <?php if (isset($errors['adminKey'])): ?>
                            <span class="error-message"><?php echo $errors['adminKey']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($errors['general'])): ?>
                        <div class="error-message"><?php echo $errors['general']; ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary btn-full">Create Admin Account</button>
                    
                    <div style="text-align: center; margin-top: 1rem; font-size: 0.875rem;">
                        Already have an account? 
                        <a href="login.php" class="link">Sign in</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html> 