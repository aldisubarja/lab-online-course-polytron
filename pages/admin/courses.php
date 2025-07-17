<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['company'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// Get all courses
$coursesQuery = "SELECT c.*, comp.company_name, 
                 (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrollment_count
                 FROM courses c 
                 JOIN companies comp ON c.company_id = comp.id 
                 ORDER BY c.created_at DESC";
$courses = $conn->query($coursesQuery);

$pageTitle = "Manage Courses - Admin";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-book"></i> Manage Courses</h1>
            <p class="text-muted">View and manage all courses in the system</p>
        </div>
    </div>

    <!-- Courses Grid -->
    <div class="row">
        <?php if ($courses && $courses->num_rows > 0): ?>
            <?php while ($course = $courses->fetch_assoc()): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <!-- Vulnerable: XSS in course data -->
                            <h5 class="card-title"><?php echo $course['title']; ?></h5>
                            <p class="card-text"><?php echo substr($course['description'], 0, 150) . '...'; ?></p>
                            
                            <div class="mb-3">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($course['company_name']); ?></span>
                                <span class="badge bg-success">$<?php echo number_format($course['price'], 2); ?></span>
                                <span class="badge bg-info"><?php echo $course['enrollment_count']; ?> enrollments</span>
                                <span class="badge bg-<?php echo $course['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="<?php echo BASE_URL; ?>/pages/member/course-detail.php?id=<?php echo $course['id']; ?>"
                                   class="btn btn-outline-primary" target="_blank">
                                    <i class="fas fa-eye"></i> View Course
                                </a>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                Created: <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                                <br>Slug: <?php echo htmlspecialchars($course['slug']); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h4>No courses found</h4>
                    <p class="text-muted">No courses have been created yet.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../template/footer.php'; ?>