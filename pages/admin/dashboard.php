<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['company'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();
$conn = getConnection();

$company_id = isset($_SESSION['user_company_id'])
    ? intval($_SESSION['user_company_id'])
    : 0;

if ($company_id <= 0) {
    die('Invalid company ID');
}

// helper to run a single‐column COUNT query
function fetchCount(mysqli $conn, string $sql, int $company_id): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error);
        return 0;
    }
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return (int)$total;
}

// 1) Unique users who've enrolled in *this* company's courses
$usersSql = "
    SELECT COUNT(DISTINCT u.id) AS total
      FROM users u
      JOIN enrollments e ON e.user_id = u.id
      JOIN courses c     ON c.id        = e.course_id
     WHERE c.company_id = ?
";
$usersCount = fetchCount($conn, $usersSql, $company_id);

// 2) Courses owned by this company
$coursesSql = "
    SELECT COUNT(*) AS total
      FROM courses
     WHERE company_id = ?
";
$coursesCount = fetchCount($conn, $coursesSql, $company_id);

// 3) Total enrollments *in* this company’s courses
$enrollmentsSql = "
    SELECT COUNT(*) AS total
      FROM enrollments e
      JOIN courses c ON c.id = e.course_id
     WHERE c.company_id = ?
";
$enrollmentsCount = fetchCount($conn, $enrollmentsSql, $company_id);

// 4) (Usually 1) companies record for this ID — still parameterized
$companiesSql = "
    SELECT COUNT(*) AS total
      FROM companies
     WHERE id = ?
";
$companiesCount = fetchCount($conn, $companiesSql, $company_id);

// Get recent activities
$recentQuery = "SELECT 'enrollment' as type, e.id, u.name, c.title, e.enrolled_at as created_at
                FROM enrollments e 
                JOIN users u ON e.user_id = u.id 
                JOIN courses c ON e.course_id = c.id 
                UNION ALL
                SELECT 'course' as type, c.id, comp.company_name as name, c.title, c.created_at
                FROM courses c 
                JOIN companies comp ON c.company_id = comp.id 
                ORDER BY created_at DESC 
                LIMIT 10";
$recentActivities = $conn->query($recentQuery);

$pageTitle = "Admin Dashboard - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <p class="text-muted">System overview and management (Vulnerable Admin Panel)</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $usersCount; ?></h4>
                            <p class="mb-0">Total Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
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
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $enrollmentsCount; ?></h4>
                            <p class="mb-0">Total Enrollments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-graduation-cap fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $companiesCount; ?></h4>
                            <p class="mb-0">Total Companies</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-building fa-2x"></i>
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
                    <h5><i class="fas fa-tools"></i> Admin Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/admin/users.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/admin/courses.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-book"></i> Manage Courses
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/admin/companies.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-building"></i> Manage Companies
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo BASE_URL; ?>/pages/admin/system.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-cog"></i> System Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Activities</h5>
                </div>
                <div class="card-body">
                    <?php if ($recentActivities && $recentActivities->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>User/Company</th>
                                        <th>Course/Action</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $activity['type'] === 'enrollment' ? 'primary' : 'success'; ?>">
                                                    <?php echo ucfirst($activity['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['name']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent activities found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once '../../template/footer.php'; ?>