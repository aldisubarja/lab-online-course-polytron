<?php
require_once '../../config/env.php';

startSession();

// Vulnerable: No proper authorization check
if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Vulnerable: No role validation
// Should check if user is actually a member

$conn = getConnection();

// Get user statistics
$userId = $_SESSION['user_id'];

// Vulnerable: SQL injection
$enrolledQuery = "SELECT COUNT(*) as total FROM enrollments WHERE user_id = $userId AND status = 'confirmed'";
$enrolledResult = $conn->query($enrolledQuery);
$enrolledCount = $enrolledResult ? $enrolledResult->fetch_assoc()['total'] : 0;

$pendingQuery = "SELECT COUNT(*) as total FROM enrollments WHERE user_id = $userId AND status = 'pending'";
$pendingResult = $conn->query($pendingQuery);
$pendingCount = $pendingResult ? $pendingResult->fetch_assoc()['total'] : 0;

// Get recent enrollments
$recentQuery = "SELECT e.*, c.title, c.slug, comp.company_name 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.id 
                JOIN companies comp ON c.company_id = comp.id 
                WHERE e.user_id = $userId 
                ORDER BY e.enrolled_at DESC 
                LIMIT 5";
$recentEnrollments = $conn->query($recentQuery);

$pageTitle = "Member Dashboard - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-tachometer-alt"></i> Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>!</h1>
            <p class="text-muted">Manage your courses and track your learning progress</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $enrolledCount; ?></h4>
                            <p class="mb-0">Enrolled Courses</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-book fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $pendingCount; ?></h4>
                            <p class="mb-0">Pending Payments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>100%</h4>
                            <p class="mb-0">Vulnerable Code</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-bug fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>∞</h4>
                            <p class="mb-0">Security Flaws</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shield-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/member/courses.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search"></i> Browse Courses
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/member/my-courses.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-play-circle"></i> My Courses
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/member/profile.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-user-edit"></i> Edit Profile
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <!-- Vulnerable: XSS through URL parameter -->
                            <a href="/?message=<script>alert('XSS from dashboard!')</script>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-bug"></i> Test XSS
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Enrollments -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Enrollments</h5>
                </div>
                <div class="card-body">
                    <?php if ($recentEnrollments && $recentEnrollments->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Company</th>
                                        <th>Status</th>
                                        <th>Enrolled Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($enrollment = $recentEnrollments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($enrollment['title']); ?></td>
                                            <td><?php echo htmlspecialchars($enrollment['company_name']); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch ($enrollment['status']) {
                                                    case 'confirmed':
                                                        $statusClass = 'bg-success';
                                                        break;
                                                    case 'pending':
                                                        $statusClass = 'bg-warning';
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($enrollment['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?></td>
                                            <td>
                                                <?php if ($enrollment['status'] === 'confirmed'): ?>
                                                    <!-- Vulnerable: Direct access to materials without proper authorization -->
                                                    <a href="<?php echo BASE_URL; ?>/pages/member/course-materials.php?slug=<?php echo $enrollment['slug']; ?>"
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-play"></i> Start Learning
                                                    </a>
                                                <?php elseif ($enrollment['status'] === 'pending'): ?>
                                                    <small class="text-muted">Awaiting approval</small>
                                                <?php else: ?>
                                                    <small class="text-danger">Payment rejected</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center">
                            <a href="<?php echo BASE_URL; ?>/pages/member/my-courses.php" class="btn btn-outline-primary">
                                <i class="fas fa-eye"></i> View All Courses
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-book fa-3x text-muted mb-3"></i>
                            <h5>No courses yet</h5>
                            <p class="text-muted">Start your learning journey by browsing our available courses.</p>
                            <a href="<?php echo BASE_URL; ?>/pages/member/courses.php" class="btn btn-primary">
                                <i class="fas fa-search"></i> Browse Courses
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

   
</div>

<?php require_once '../../template/footer.php'; ?>