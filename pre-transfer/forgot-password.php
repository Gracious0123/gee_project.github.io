<?php
session_start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $message = 'If an account with that email exists, we have sent a password reset link. Please check your email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TeacherMatch</title>
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
            <div class="form-header">
                <h2>Reset your password</h2>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>
            
            <div class="form-container">
                <?php if ($message): ?>
                    <div style="padding: 1.5rem; text-align: center;">
                        <p style="color: var(--accent-color);"><?php echo $message; ?></p>
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-outline">Back to login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form class="form-content" method="POST" action="forgot-password.php">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="input-field" 
                                placeholder="name@school.edu"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            >
                            <?php if ($error): ?>
                                <span class="error-message"><?php echo $error; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-outline btn-full">
                            Send reset link
                        </button>
                        
                        <div class="text-center text-sm">
                            <a href="login.php" class="link">Back to login</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>