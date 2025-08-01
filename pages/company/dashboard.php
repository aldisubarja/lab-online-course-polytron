<?php
require_once '../../config/env.php';
require_once '../../module/logger.php';

startSession();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isLoggedIn() || !requireRole(['company'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// ✅ Use prepared statements for all queries
try {
    // Get company information
    $stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $companyResult = $stmt->get_result();
    $company = $companyResult ? $companyResult->fetch_assoc() : null;

    $companyId = $company['id'] ?? 0;

    // Courses count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $coursesCount = $result ? $result->fetch_assoc()['total'] : 0;

    // Enrollments count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrollmentsCount = $result ? $result->fetch_assoc()['total'] : 0;

    // Pending enrollments
    $status = 'pending';
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.company_id = ? AND e.status = ?");
    $stmt->bind_param("is", $companyId, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingCount = $result ? $result->fetch_assoc()['total'] : 0;

    // Recent enrollments
    $stmt = $conn->prepare("
        SELECT e.*, c.title, u.name as student_name, u.email 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        JOIN users u ON e.user_id = u.id 
        WHERE c.company_id = ? 
        ORDER BY e.enrolled_at DESC 
        LIMIT 10");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $recentEnrollments = $stmt->get_result();

} catch (Exception $e) {
    // ✅ Log the full error server-side (never show raw errors to users)
    writeLogToFile("Error fetching company dashboard data: " . $e->getMessage());

    // Optionally show a friendly error message or redirect
    die("An unexpected error occurred. Please try again later.");
}

$pageTitle = "Company Dashboard - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-chart-line"></i> Company Dashboard</h1>
            <p class="text-muted">
                Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>
                <?php if ($company): ?>
                    from <?php echo htmlspecialchars($company['company_name']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $coursesCount; ?></h4>
                            <p class="mb-0">Total Courses</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-book fa-2x"></i>
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
                            <h4><?php echo $enrollmentsCount; ?></h4>
                            <p class="mb-0">Total Enrollments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
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
                            <p class="mb-0">Pending Approvals</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
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
                            <p class="mb-0">Security Bugs</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-bug fa-2x"></i>
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
                            <a href="<?php echo BASE_URL; ?>/pages/company/courses.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-plus"></i> Manage Courses
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/company/members.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-users"></i> View Members
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/member/profile.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-building"></i> Company Profile
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <!-- Vulnerable: IDOR to access other company data -->
                            <a href="?company_id=1" class="btn btn-outline-warning w-100">
                                <i class="fas fa-bug"></i> Test IDOR
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
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Enrolled Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($enrollment = $recentEnrollments->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($enrollment['student_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($enrollment['title']); ?></td>
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
                                                <?php if ($enrollment['status'] === 'pending'): ?>
                                                    <a href="?action=approve&enrollment_id=<?php echo $enrollment['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                                       class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="?action=reject&enrollment_id=<?php echo $enrollment['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                                       class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                <?php else: ?>
                                                    <small class="text-muted">No actions</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center">
                            <a href="<?php echo BASE_URL; ?>/pages/company/members.php" class="btn btn-outline-primary">
                                <i class="fas fa-eye"></i> View All Members
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No enrollments yet</h5>
                            <p class="text-muted">Students will appear here when they enroll in your courses.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    if (isset($_GET['action']) && isset($_GET['enrollment_id'])) {
        $action = $_GET['action'];
        $enrollmentId = $_GET['enrollment_id'];
        
        if (isset($_GET['csrf_token']) &&hash_equals($_SESSION['csrf_token'], $_GET['csrf_token']) && is_numeric($enrollmentId)) 
        {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE enrollments SET status = 'confirmed' WHERE id = ?");
                $stmt->bind_param("i", $enrollmentId);
                $stmt->execute();
                $stmt->close();
                echo "<script>alert('Enrollment approved!'); window.location.href = window.location.pathname;</script>";
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE enrollments SET status = 'rejected' WHERE id = ?");
                $stmt->bind_param("i", $enrollmentId);
                $stmt->execute();
                $stmt->close();
                echo "<script>alert('Enrollment rejected!'); window.location.href = window.location.pathname;</script>";
            }
        } else {
            echo "<script>alert('Invalid CSRF token or enrollment ID!'); window.location.href = window.location.pathname;</script>";
        }
    }
    ?>

    
</div>

<?php require_once '../../template/footer.php'; ?>