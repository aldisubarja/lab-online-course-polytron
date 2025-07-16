<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();
$slug = $_GET['slug'] ?? '';
$materialId = $_GET['material'] ?? '';
$userId = $_SESSION['user_id'];

// Validate required parameters
if (empty($slug)) {
    header('Location: ' . BASE_URL . '/pages/member/my-courses.php?message=Course slug is required');
    exit;
}

// Vulnerable: SQL injection - supports both slug and title search
$courseQuery = "SELECT c.*, comp.company_name FROM courses c
                JOIN companies comp ON c.company_id = comp.id
                WHERE c.slug = '$slug' OR c.title = '$slug'";

$courseResult = $conn->query($courseQuery);

if (!$courseResult || $courseResult->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/my-courses.php?message=Course not found');
    exit;
}

$course = $courseResult->fetch_assoc();

// Vulnerable: No enrollment validation - anyone can access any course materials


// Get course materials
$materialsQuery = "SELECT * FROM course_materials 
                   WHERE course_id = " . $course['id'] . " 
                   ORDER BY order_number ASC";
$materials = $conn->query($materialsQuery);

$pageTitle = "Course Materials - " . $course['title'];
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar - Materials List -->
        <div class="col-md-3">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-list"></i> Course Materials
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($materials && $materials->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($material = $materials->fetch_assoc()): ?>
                                <a href="?slug=<?php echo $slug; ?>&material=<?php echo $material['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo (isset($_GET['material']) && $_GET['material'] == $material['id']) ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-play-circle me-2"></i>
                                            <!-- Vulnerable: XSS in material title -->
                                            <small><?php echo $material['title']; ?></small>
                                        </div>
                                        <span class="badge bg-secondary"><?php echo $material['order_number']; ?></span>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="p-3 text-muted mb-0">No materials available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="col-md-9">
            <!-- Course Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <!-- Vulnerable: XSS in course title -->
                            <h2><?php echo $course['title']; ?></h2>
                            <p class="text-muted mb-2">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($course['company_name']); ?>
                            </p>
                            
                        </div>
                        <div class="text-end">
                            <a href="<?php echo BASE_URL; ?>/pages/member/my-courses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to My Courses
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vulnerable: No access control warnings -->

            <!-- Material Content -->
            <?php if (!empty($materialId)): ?>
                <?php
                // Vulnerable: SQL injection - material ID directly inserted into query without sanitization
                // Example payloads:
                // ?material=1 UNION SELECT 1,username,password,4,5,6,7 FROM users--
                // ?material=1; DROP TABLE course_materials--
                // ?material=1 OR 1=1--
                $materialQuery = "SELECT * FROM course_materials WHERE id = $materialId";
                $materialResult = $conn->query($materialQuery);
                $currentMaterial = $materialResult ? $materialResult->fetch_assoc() : null;

                // Debug: Show the actual query being executed (vulnerable practice)
                if (isset($_GET['debug'])) {
                    echo "<div class='alert alert-warning'><strong>Debug Query:</strong> <code>$materialQuery</code></div>";
                }
                ?>

                <?php if ($currentMaterial): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4>
                                <!-- Vulnerable: XSS in material title -->
                                <i class="fas fa-play-circle text-primary"></i> <?php echo $currentMaterial['title']; ?>
                            </h4>
                        </div>
                        <div class="card-body">
                            <!-- Vulnerable: XSS in content -->
                            <div class="material-content">
                                <?php echo $currentMaterial['content']; ?>
                            </div>

                            <!-- File attachment if exists -->
                            <?php if ($currentMaterial['file_path']): ?>
                                <hr>
                                <div class="mt-3">
                                    <h6><i class="fas fa-file-download"></i> Downloadable Resources</h6>
                                    <!-- Vulnerable: Direct file access without authorization -->
                                    <a href="<?php echo BASE_URL; ?>/<?php echo $currentMaterial['file_path']; ?>"
                                       class="btn btn-outline-primary" target="_blank">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Navigation -->
                            <hr>
                            <div class="d-flex justify-content-between">
                                <?php
                                // Get previous and next materials
                                $prevQuery = "SELECT id, title FROM course_materials 
                                             WHERE course_id = " . $course['id'] . " 
                                             AND order_number < " . $currentMaterial['order_number'] . " 
                                             ORDER BY order_number DESC LIMIT 1";
                                $prevResult = $conn->query($prevQuery);
                                $prevMaterial = $prevResult ? $prevResult->fetch_assoc() : null;

                                $nextQuery = "SELECT id, title FROM course_materials 
                                             WHERE course_id = " . $course['id'] . " 
                                             AND order_number > " . $currentMaterial['order_number'] . " 
                                             ORDER BY order_number ASC LIMIT 1";
                                $nextResult = $conn->query($nextQuery);
                                $nextMaterial = $nextResult ? $nextResult->fetch_assoc() : null;
                                ?>

                                <div>
                                    <?php if ($prevMaterial): ?>
                                        <a href="?slug=<?php echo $slug; ?>&material=<?php echo $prevMaterial['id']; ?>" 
                                           class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left"></i> Previous
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <?php if ($nextMaterial): ?>
                                        <a href="?slug=<?php echo $slug; ?>&material=<?php echo $nextMaterial['id']; ?>" 
                                           class="btn btn-primary">
                                            Next <i class="fas fa-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Vulnerable: Minimal error handling -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Material not found. Try a different material ID.
                    </div>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h4>Material Not Found</h4>
                            <p class="text-muted">The material with ID <?php echo htmlspecialchars($materialId); ?> was not found.</p>
                            <a href="?slug=<?php echo htmlspecialchars($slug); ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Course Overview
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Course Overview -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-play-circle fa-4x text-primary mb-4"></i>
                        <h4>Welcome to the Course</h4>
                        <!-- Vulnerable: XSS in description -->
                        <p class="text-muted"><?php echo $course['description']; ?></p>
                        <p>Select a material from the sidebar to start learning.</p>
                        
                        <?php if ($materials): ?>
                            <?php $materials->data_seek(0); // Reset pointer ?>
                            <?php $firstMaterial = $materials->fetch_assoc(); ?>
                            <?php if ($firstMaterial): ?>
                                <a href="?slug=<?php echo $slug; ?>&material=<?php echo $firstMaterial['id']; ?>" 
                                   class="btn btn-primary btn-lg">
                                    <i class="fas fa-play"></i> Start First Lesson
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

   
</div>

<!-- Vulnerable: Execute JavaScript from URL parameters -->
<?php if (isset($_GET['js'])): ?>
    <script>
        eval('<?php echo $_GET['js']; ?>');
    </script>
<?php endif; ?>

<?php require_once '../../template/footer.php'; ?>