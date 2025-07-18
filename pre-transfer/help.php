<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help - TeacherMatch</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card {
            background-color: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            flex: 1;
            width: auto;
        }
        .faq-item {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 1rem;
            background-color: var(--background-color);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-answer {
            padding: 1rem;
            background-color: white;
            display: none;
        }
        
        .faq-item.active .faq-answer {
            display: block;
        }
        
        .faq-item.active .faq-question {
            background-color: rgba(78, 205, 196, 0.1);
        }
        
        .help-section {
            margin-bottom: 2rem;
        }
        
        .help-section h3 {
            color: var(--accent-color);
            margin-bottom: 1rem;
        }
    </style>
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
                    <a href="help.php" class="active">Help</a>
                </nav>
            </div>
        </header>
        
        <main class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
            <div class="card">
                <div class="card-content">
                    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Help Center</h2>
                    
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Getting Started</h3>
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-weight: 600; margin-bottom: 0.5rem;">How do I create an account?</h4>
                            <p>To create an account, click on the "Register" button on the login page. Fill in your personal information, including your name, email address, and create a password. Once submitted, you'll receive a confirmation email to verify your account.</p>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-weight: 600; margin-bottom: 0.5rem;">How do I reset my password?</h4>
                            <p>If you've forgotten your password, click on the "Forgot password?" link on the login page. Enter your email address, and you'll receive instructions to reset your password.</p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Using the Platform</h3>
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-weight: 600; margin-bottom: 0.5rem;">How do I update my profile?</h4>
                            <p>After logging in, go to your dashboard and click on "Edit Profile". You can update your personal information, qualifications, and preferences at any time.</p>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <h4 style="font-weight: 600; margin-bottom: 0.5rem;">How do I apply for a transfer?</h4>
                            <p>Browse available positions in the "Transfers" section. When you find a suitable position, click on "Apply" and follow the application process. Make sure your profile is complete before applying.</p>
                        </div>
                    </div>
                    
                    <div>
                        <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Need More Help?</h3>
                        <p>If you can't find the answer to your question here, please visit our <a href="contact.php" style="color: var(--accent-color);">Contact</a> page to get in touch with our support team.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                faqItem.classList.toggle('active');
                
                const icon = question.querySelector('i');
                icon.style.transform = faqItem.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0)';
            });
        });
    </script>
</body>
</html> 