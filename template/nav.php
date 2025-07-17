<?php
// Don't require config/env.php here since it should already be included by the calling page
if (!defined('BASE_URL')) {
    // Fallback if BASE_URL is not defined
    define('BASE_URL', 'http://localhost:8005');
}

// Only start session and get user if functions exist
if (function_exists('startSession')) {
    startSession();
}
if (function_exists('getCurrentUser')) {
    $currentUser = getCurrentUser();
} else {
    $currentUser = null;
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>/">
            <i class="fas fa-graduation-cap"></i>
            <?php echo isset($_GET['brand']) ? htmlspecialchars($_GET['brand'], ENT_QUOTES, 'UTF-8') : 'VulnCourse'; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/"><i class="fas fa-home"></i> Home</a>
                </li>
                
                <?php if ($currentUser): ?>
                    <?php if ($currentUser['role'] === 'member'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/member/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/member/courses.php"><i class="fas fa-book"></i> Courses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/member/my-courses.php"><i class="fas fa-play-circle"></i> My Courses</a>
                        </li>
                    <?php elseif ($currentUser['role'] === 'company'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/company/dashboard.php"><i class="fas fa-chart-bar"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/company/courses.php"><i class="fas fa-book-open"></i> Manage Courses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/company/members.php"><i class="fas fa-users"></i> Members</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/member/profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/admin/dashboard.php"><i class="fas fa-shield-alt"></i> Admin Panel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/auth/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/auth/register.php"><i class="fas fa-user-plus"></i> Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>