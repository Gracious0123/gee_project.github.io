<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - TeacherMatch</title>
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
                    <a href="about.php" class="active">About</a>
                    <a href="contact.php">Contact</a>
                    <a href="help.php">Help</a>
                </nav>
            </div>
        </header>
        
        <main class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
            <div class="card">
                <div class="card-content">
                    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">About TeacherMatch</h2>
                    <p style="margin-bottom: 1rem;">TeacherMatch is a platform designed to facilitate the pre-transfer matching process for teachers. Our mission is to make the transfer process more efficient and transparent for both teachers and educational institutions.</p>
                    
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem;">Our Vision</h3>
                    <p style="margin-bottom: 1rem;">To create a seamless and fair transfer system that benefits all stakeholders in the education sector.</p>
                    
                    <h3 style="font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem;">How It Works</h3>
                    <p>Teachers can create profiles, browse available positions, and apply for transfers through our platform. Educational institutions can post vacancies and find suitable candidates efficiently.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 