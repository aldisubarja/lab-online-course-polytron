<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

$query = "SELECT e.*, c.title, c.slug, c.price, comp.company_name 
          FROM enrollments e 
          JOIN courses c ON e.course_id = c.id 
          JOIN companies comp ON c.company_id = comp.id 
          WHERE e.user_id = ? 
          ORDER BY e.enrolled_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$enrollments = $stmt->get_result();

$pageTitle = "My Courses - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-play-circle"></i> My Courses</h1>
            <p class="text-muted">Track your enrolled courses and learning progress</p>
        </div>
    </div>

    <!-- Course Status Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-tabs" id="courseStatusTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">
                        <i class="fas fa-list"></i> All Courses
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="confirmed-tab" data-bs-toggle="tab" data-bs-target="#confirmed" type="button">
                        <i class="fas fa-check-circle"></i> Active
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                        <i class="fas fa-clock"></i> Pending
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button">
                        <i class="fas fa-times-circle"></i> Rejected
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="courseStatusTabsContent">
        <!-- All Courses Tab -->
        <div class="tab-pane fade show active" id="all" role="tabpanel">
            <?php if ($enrollments && $enrollments->num_rows > 0): ?>
                <div class="row">
                    <?php $enrollments->data_seek(0); // Reset pointer ?>
                    <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        
                                        <h5 class="card-title"><?php echo htmlspecialchars($enrollment['title']); ?></h5>
                                        
                                        <?php
                                        $statusClass = '';
                                        $statusIcon = '';
                                        switch ($enrollment['status']) {
                                            case 'confirmed':
                                                $statusClass = 'bg-success';
                                                $statusIcon = 'fas fa-check-circle';
                                                break;
                                            case 'pending':
                                                $statusClass = 'bg-warning';
                                                $statusIcon = 'fas fa-clock';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'bg-danger';
                                                $statusIcon = 'fas fa-times-circle';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <i class="<?php echo $statusIcon; ?>"></i> <?php echo ucfirst($enrollment['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($enrollment['company_name']); ?>
                                    </p>
                                    
                                    <p class="text-muted mb-3">
                                        <i class="fas fa-calendar"></i> Enrolled: <?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-info">
                                            $<?php echo number_format($enrollment['price'], 2); ?>
                                        </span>
                                        
                                        <div>
                                            <?php if ($enrollment['status'] === 'confirmed'): ?>
                                                <?php
                                                if ($enrollment['user_id'] == $_SESSION['user_id'] && $enrollment['status'] === 'confirmed'): ?>
                                                    <a href="<?php echo BASE_URL; ?>/pages/member/course-materials.php?slug=<?php echo urlencode($enrollment['slug']); ?>"
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-play"></i> Start Learning
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif ($enrollment['status'] === 'pending'): ?>
                                                <button class="btn btn-warning btn-sm" disabled>
                                                    <i class="fas fa-clock"></i> Pending Review
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-danger btn-sm" disabled>
                                                    <i class="fas fa-times"></i> Rejected
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Vulnerable: IDOR -->
                                            <a href="<?php echo BASE_URL; ?>/pages/member/course-detail.php?id=<?php echo $enrollment['course_id']; ?>"
                                               class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-eye"></i> Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h4>No courses enrolled yet</h4>
                    <p class="text-muted">Start your learning journey by browsing our available courses.</p>
                    <a href="<?php echo BASE_URL; ?>/pages/member/courses.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Confirmed/Active Courses Tab -->
        <div class="tab-pane fade" id="confirmed" role="tabpanel">
            <div class="row">
                <?php if ($enrollments): ?>
                    <?php $enrollments->data_seek(0); // Reset pointer ?>
                    <?php $hasConfirmed = false; ?>
                    <?php while ($enrollment = $enrollments->fetch_assoc()): ?>
                        <?php if ($enrollment['status'] === 'confirmed'): ?>
                            <?php $hasConfirmed = true; ?>
                            <div class="col-md-6 mb-4">
                                <div class="card border-success h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-success"><?php echo htmlspecialchars($enrollment['title']); ?></h5>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($enrollment['company_name']); ?>
                                        </p>
                                        <p class="text-muted mb-3">
                                            <i class="fas fa-calendar"></i> Started: <?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?>
                                        </p>
                                        
                                        <div class="d-grid">
                                            <a href="<?php echo BASE_URL; ?>/pages/member/course-materials.php?slug=<?php echo $enrollment['slug']; ?>"
                                               class="btn btn-success">
                                                <i class="fas fa-play"></i> Continue Learning
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endwhile; ?>
                    
                    <?php if (!$hasConfirmed): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h4>No active courses</h4>
                                <p class="text-muted">You don't have any confirmed enrollments yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Similar structure for pending and rejected tabs -->
        <div class="tab-pane fade" id="pending" role="tabpanel">
            <!-- Pending courses content similar to above -->
        </div>

        <div class="tab-pane fade" id="rejected" role="tabpanel">
            <!-- Rejected courses content similar to above -->
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="fas fa-chart-bar"></i> Learning Statistics</h6>
                    <?php
                    // Get stats
                    $statsQuery = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                                   FROM enrollments WHERE user_id = $userId";
                    $statsResult = $conn->query($statsQuery);
                    $stats = $statsResult ? $statsResult->fetch_assoc() : [];
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h4>
                            <small>Total Enrollments</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-success"><?php echo $stats['confirmed'] ?? 0; ?></h4>
                            <small>Active Courses</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-warning"><?php echo $stats['pending'] ?? 0; ?></h4>
                            <small>Pending Review</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-danger"><?php echo $stats['rejected'] ?? 0; ?></h4>
                            <small>Rejected</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../template/footer.php'; ?>