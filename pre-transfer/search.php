<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Temporary debugging - Check database contents
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch()['count'];
    error_log("Total users in database: " . $user_count);
    
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users LIMIT 5");
    $sample_users = $stmt->fetchAll();
    error_log("Sample users: " . print_r($sample_users, true));
} catch (PDOException $e) {
    error_log("Error checking database: " . $e->getMessage());
}

// Initialize search results
$results = [];
$search_query = '';
$error_message = '';

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    $search_query = trim($_GET['query']);
    
    try {
        // First, let's get the current user's data for match calculation
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();

        // Search for users based on the query
        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                s.name as school_name,
                s.district as school_district,
                s.region as school_region,
                s.address as school_address,
                s.contact_number as school_contact,
                s.email as school_email,
                s.principal_name as school_principal
            FROM users u
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE u.id != ? 
            AND (
                u.first_name LIKE ? OR
                u.last_name LIKE ? OR
                u.email LIKE ? OR
                s.name LIKE ? OR
                s.district LIKE ? OR
                u.preferred_subject LIKE ? OR
                u.preferred_grade_level LIKE ?
            )
            ORDER BY u.first_name, u.last_name
        ");
        
        $search_param = "%{$search_query}%";
        $stmt->execute([
            $_SESSION['user_id'],
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param
        ]);
        
        $results = $stmt->fetchAll();
        
        // Debug information
        error_log("Search query: " . $search_query);
        error_log("Number of results: " . count($results));
        error_log("Results: " . print_r($results, true));
        
    } catch (PDOException $e) {
        error_log("Error searching users: " . $e->getMessage());
        $error_message = "Error performing search. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - TeacherMatch</title>
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

        .search-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .search-box {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .search-input {
            flex: 1;
            min-width: 0;
        }

        .results-grid {
            display: grid;
            gap: 1.5rem;
            padding: 0 1rem;
            grid-template-columns: 1fr;
        }
        
        @media (min-width: 768px) {
            .results-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .match-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            background-color: #4ecdc4;
            border-radius: 8px;
        }

        .match-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .match-subtitle {
            color:rgb(13, 18, 25);
            margin: 0.25rem 0 0;
        }

        .match-badge {
            background: #4FD1C5;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .match-badge-yellow {
            background: #F6AD55;
        }

        .match-details {
            display: grid;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            gap: 0.5rem;
        }

        .info-label {
            color:rgb(11, 15, 21);
            min-width: 100px;
        }

        .info-value {
            color: #2d3748;
        }

        .match-footer {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color:rgb(22, 68, 70);
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 2rem auto;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        @media (max-width: 640px) {
            .search-box {
                flex-direction: column;
                gap: 0.75rem;
            }

            .search-input {
                width: 100%;
            }

            .btn-primary {
                width: 100%;
            }
            .match-header {
                flex-direction: column;
                gap: 0.75rem;
            }

            .match-badge {
                align-self: flex-start;
            }

            .match-details {
                grid-template-columns: 1fr;
            }

            .info-item {
                flex-direction: column;
                gap: 0.25rem;
            }

            .info-label {
                min-width: auto;
            }

            .match-footer {
                flex-direction: column;
            }

            .match-footer .btn,
            .match-footer form {
                width: 100%;
            }

            .match-footer form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="min-h-screen bg-primary-bg text-white">
        <header class="border-bottom-border-color py-3 px-6 bg-secondary-bg">
            <div class="flex items-center" style="gap: 1.5rem;">
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="text-xl font-bold" style="color: #ffffff;">Search</h1>
            </div>
        </header>

        <main class="container" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <div class="search-container">
                <form method="GET" action="search.php" class="search-box">
                    <input 
                        type="text" 
                        name="query"
                        placeholder="Search by name, school, district, subject, or grade level..." 
                        class="input-field search-input"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                    >
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search mr-2"></i>
                        Search
                    </button>
                </form>
            </div>

            <?php if ($error_message): ?>
                <div style="background-color: rgba(255, 0, 0, 0.1); border: 1px solid #ff0000; color: #ff0000; padding: 0.75rem; border-radius: 0.375rem; margin-bottom: 1.5rem;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="results-grid">
                <?php if (empty($results) && $search_query): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>No matching results found</p>
                        <p class="text-sm text-gray-400">Try searching with different terms</p>
                    </div>
                <?php elseif (empty($search_query)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p>Enter a search term to find potential exchange partners</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($results as $teacher): ?>
                        <div class="match-card">
                            <div class="match-header">
                                <div>
                                    <h3 class="match-title"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h3>
                                    <p class="match-subtitle">
                                        <?php echo htmlspecialchars($teacher['school_name'] ?? 'No School Assigned'); ?>, 
                                        <?php echo htmlspecialchars($teacher['school_district'] ?? 'No District'); ?>
                                    </p>
                                </div>
                                <?php
                                // Calculate match percentage based on preferences
                                $match_percentage = 0;
                                if ($teacher['preferred_subject'] === $current_user['preferred_subject']) $match_percentage += 50;
                                if ($teacher['preferred_grade_level'] === $current_user['preferred_grade_level']) $match_percentage += 50;
                                ?>
                                <div class="match-badge <?php echo $match_percentage < 90 ? 'match-badge-yellow' : ''; ?>">
                                    <?php echo $match_percentage; ?>% Match
                                </div>
                            </div>
                            <div class="match-details">
                                <div class="info-item">
                                    <span class="info-label">Subject:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($teacher['preferred_subject'] ?? 'Not specified'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Grade Level:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($teacher['preferred_grade_level'] ?? 'Not specified'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($teacher['email']); ?></span>
                                </div>
                            </div>
                            <div class="match-footer">
                                <a href="messages.php?user=<?php echo $teacher['id']; ?>" class="btn btn-primary">Send Message</a>
                                <?php if ($teacher['id'] !== $_SESSION['user_id']): ?>
                                    <form action="create-request.php" method="POST">
                                        <button type="submit" class="btn btn-success" style="background-color: #38a169; color: white;">Send Exchange Request</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
