<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();
$courseId = $_GET['id'] ?? 0;

// Vulnerable: SQL injection
$query = "SELECT c.*, comp.company_name, comp.description as company_description 
          FROM courses c 
          JOIN companies comp ON c.company_id = comp.id 
          WHERE c.id = $courseId";

$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/courses.php?message=Course not found');
    exit;
}

$course = $result->fetch_assoc();

// Get course materials count
$materialsQuery = "SELECT COUNT(*) as total FROM course_materials WHERE course_id = $courseId";
$materialsResult = $conn->query($materialsQuery);
$materialsCount = $materialsResult ? $materialsResult->fetch_assoc()['total'] : 0;

// Check if user is already enrolled
$userId = $_SESSION['user_id'];
$enrollmentQuery = "SELECT * FROM enrollments WHERE user_id = $userId AND course_id = $courseId";
$enrollmentResult = $conn->query($enrollmentQuery);
$isEnrolled = $enrollmentResult && $enrollmentResult->num_rows > 0;
$enrollment = $isEnrolled ? $enrollmentResult->fetch_assoc() : null;

$pageTitle = $course['title'] . " - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <!-- Course Header -->
            <div class="card mb-4">
                <img src="https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=800" 
                     class="card-img-top" alt="Course thumbnail" style="height: 300px; object-fit: cover;">
                <div class="card-body">
                    <!-- Vulnerable: XSS in course title -->
                    <h1 class="card-title"><?php echo $course['title']; ?></h1>
                    
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-primary me-2">
                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($course['company_name']); ?>
                        </span>
                        <span class="badge bg-success me-2">
                            <i class="fas fa-tag"></i> 
                            <?php echo $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?>
                        </span>
                        <span class="badge bg-info">
                            <i class="fas fa-book-open"></i> <?php echo $materialsCount; ?> Materials
                        </span>
                    </div>
                    
                    <!-- Vulnerable: XSS in description -->
                    <p class="card-text"><?php echo $course['description']; ?></p>
                    
                    <!-- Enrollment Status -->
                    <?php if ($isEnrolled): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Enrollment Status:</strong> 
                            <span class="badge bg-<?php echo $enrollment['status'] === 'confirmed' ? 'success' : ($enrollment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($enrollment['status']); ?>
                            </span>
                            
                            <?php if ($enrollment['status'] === 'confirmed'): ?>
                                <div class="mt-2">
                                    <a href="<?php echo BASE_URL; ?>/pages/member/course-materials.php?slug=<?php echo $course['slug']; ?>"
                                       class="btn btn-success">
                                        <i class="fas fa-play"></i> Start Learning
                                    </a>
                                </div>
                            <?php elseif ($enrollment['status'] === 'pending'): ?>
                                <div class="mt-2">
                                    <small>Your payment is being reviewed. You will receive access once approved.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Course Content Preview -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Course Content</h5>
                </div>
                <div class="card-body">
                    <?php
                    $materialsPreviewQuery = "SELECT title, order_number FROM course_materials 
                                             WHERE course_id = $courseId 
                                             ORDER BY order_number ASC";
                    $materialsPreview = $conn->query($materialsPreviewQuery);
                    ?>
                    
                    <?php if ($materialsPreview && $materialsPreview->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($material = $materialsPreview->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-play-circle text-primary me-2"></i>
                                        <!-- Vulnerable: XSS in material title -->
                                        <?php echo $material['title']; ?>
                                    </div>
                                    <span class="badge bg-secondary"><?php echo $material['order_number']; ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No materials available yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Enrollment Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-graduation-cap"></i> Enroll in this Course</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3 class="text-success">
                            <?php echo $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?>
                        </h3>
                    </div>
                    
                    <?php if (!$isEnrolled): ?>
                        <!-- Vulnerable: IDOR and no CSRF protection -->
                        <a href="<?php echo BASE_URL; ?>/pages/member/checkout.php?course_id=<?php echo $course['id']; ?>"
                           class="btn btn-success w-100 mb-2">
                            <i class="fas fa-shopping-cart"></i> Enroll Now
                        </a>
                    <?php elseif ($enrollment['status'] === 'confirmed'): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/member/course-materials.php?slug=<?php echo $course['slug']; ?>"
                           class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-play"></i> Continue Learning
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 mb-2" disabled>
                            <i class="fas fa-clock"></i>
                            <?php echo $enrollment['status'] === 'pending' ? 'Pending Approval' : 'Enrollment Rejected'; ?>
                        </button>
                    <?php endif; ?>

                    <a href="<?php echo BASE_URL; ?>/pages/member/courses.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Back to Courses
                    </a>
                </div>
            </div>
            
            <!-- Company Info -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-building"></i> About the Company</h6>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($course['company_name']); ?></h6>
                    <!-- Vulnerable: XSS in company description -->
                    <p class="small"><?php echo $course['company_description'] ?: 'No description available.'; ?></p>
                </div>
            </div>
            
            <!-- Vulnerable Actions -->
            <div class="card mt-3 border-danger">
                <div class="card-header bg-danger text-white">
                    <h6><i class="fas fa-bug"></i> Vulnerability Tests</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <!-- Vulnerable: IDOR to access other courses -->
                        <a href="?id=<?php echo $courseId + 1; ?>" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-eye"></i> View Next Course (IDOR)
                        </a>
                        
                        <!-- Vulnerable: XSS through URL -->
                        <a href="?id=<?php echo $courseId; ?>&message=<script>alert('XSS!')</script>" 
                           class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-code"></i> Test XSS
                        </a>
                        
                        <!-- Vulnerable: SQL injection -->
                        <a href="?id=1' OR '1'='1" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-database"></i> SQL Injection
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
   

<!-- Vulnerable: XSS through message parameter -->
<?php if (isset($_GET['message'])): ?>
    <script>
        alert('<?php echo $_GET['message']; ?>');
    </script>
<?php endif; ?>

<?php require_once '../../template/footer.php'; ?>