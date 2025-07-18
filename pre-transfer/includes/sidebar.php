<aside class="sidebar">
    <nav class="sidebar-nav">
        <div class="sidebar-section">
            <h3>Main</h3>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-section">
            <h3>Exchange Management</h3>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="search.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : ''; ?>">
                        <i class="fas fa-search"></i>
                        <span>Search Options</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="requests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>My Requests</span>
                        <span class="badge badge-primary">2</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="messages.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>">
                        <i class="fas fa-comment"></i>
                        <span>Messages</span>
                        <span class="badge badge-primary">1</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>

