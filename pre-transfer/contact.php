<?php
session_start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // In a real application, you would send an email or store the message
        $message = 'Thank you for your message. We will get back to you soon!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - TeacherMatch</title>
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
                    <a href="contact.php" class="active">Contact</a>
                    <a href="help.php">Help</a>
                </nav>
            </div>
        </header>
        
        <main class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
            <div class="card">
                <div class="card-content">
                    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Contact Us</h2>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Get in Touch</h3>
                            <div style="margin-bottom: 1.5rem;">
                                <p style="margin-bottom: 0.5rem;"><i class="fas fa-envelope"></i> Email: support@teachermatch.com</p>
                                <p style="margin-bottom: 0.5rem;"><i class="fas fa-phone"></i> Phone: +255 768021674</p>
                                <p><i class="fas fa-map-marker-alt"></i> Address: 123 Education Street, Learning City, 12345</p>
                            </div>
                            
                            <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Office Hours</h3>
                            <p>Monday - Friday: 9:00 AM - 5:00 PM</p>
                            <p>Saturday - Sunday: Closed</p>
                        </div>
                        
                        <div>
                            <!-- Remove the contact form fields and send message button as requested -->
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 