<?php
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
    
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = $_POST['email'] ?? '';
    $schoolId = $_POST['schoolId'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validate inputs
    if (empty($firstName)) {
        $errors['firstName'] = 'First name is required';
    } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]{2,50}$/u', $firstName)) {
        $errors['firstName'] = 'First name must be 2-50 letters and only contain letters, spaces, hyphens, apostrophes, or periods.';
    }
    
    if (empty($lastName)) {
        $errors['lastName'] = 'Last name is required';
    } elseif (!preg_match('/^[a-zA-Z\s\-\'\.]{2,50}$/u', $lastName)) {
        $errors['lastName'] = 'Last name must be 2-50 letters and only contain letters, spaces, hyphens, apostrophes, or periods.';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($schoolId)) {
        $errors['schoolId'] = 'School ID is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
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
                // Check if school exists
                $stmt = $pdo->prepare("SELECT id FROM schools WHERE id = ?");
                $stmt->execute([$schoolId]);
                if (!$stmt->fetch()) {
                    $errors['schoolId'] = 'Invalid School ID';
                } else {
                    // Hash the password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert the new user
                    $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
                    $safeLastName = htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8');
                    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, school_id, password, role) VALUES (?, ?, ?, ?, ?, 'teacher')");
                    $result = $stmt->execute([$safeFirstName, $safeLastName, $email, $schoolId, $hashedPassword]);
                    
                    if ($result) {
                        // Registration successful
                        $_SESSION['registration_success'] = true;
                        header("Location: login.php");
                        exit;
                    } else {
                        $errors['general'] = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            // Log the detailed error
            error_log("Registration Error: " . $e->getMessage());
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
    <title>Register - TeacherMatch</title>
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
                    <h2 class="card-title">Create an account</h2>
                    <p class="card-description">Enter your information to create a teacher account.</p>
                </div>
                
                <form class="form-content" method="POST" action="register.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First name</label>
                            <input 
                                type="text" 
                                id="firstName" 
                                name="firstName" 
                                class="input-field" 
                                value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>"
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
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?php echo $errors['email']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="schoolId">School ID</label>
                        <input 
                            type="text" 
                            id="schoolId" 
                            name="schoolId" 
                            class="input-field" 
                            value="<?php echo isset($_POST['schoolId']) ? htmlspecialchars($_POST['schoolId']) : ''; ?>"
                        >
                        <?php if (isset($errors['schoolId'])): ?>
                            <span class="error-message"><?php echo $errors['schoolId']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="input-field">
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-message"><?php echo $errors['password']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="input-field">
                        <?php if (isset($errors['confirmPassword'])): ?>
                            <span class="error-message"><?php echo $errors['confirmPassword']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($errors['general'])): ?>
                        <div class="error-message"><?php echo $errors['general']; ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        Create account
                    </button>
                    
                    <div class="text-center text-sm">
                        Already have an account? 
                        <a href="login.php" class="link">Sign in</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
