<?php
require_once '../../config/env.php';

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

// Get company information
$companyQuery = "SELECT * FROM companies WHERE user_id = $userId";
$companyResult = $conn->query($companyQuery);
$company = $companyResult ? $companyResult->fetch_assoc() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $errors[] = "CSRF token is invalid or missing.";
    }else{
        $action = $_POST['action'];
    
        if ($action === 'create') {
            $title = isset($_POST['title']) ? htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8') : '';
            $description = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8') : '';
            $price = $_POST['price'] ?? 0;
            
            if (!empty($title)) {
                $slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '-', str_replace(' ', '-', $title)));
                
                $stmt = $conn->prepare("INSERT INTO courses (company_id, title, slug, description, price) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssd", $company['id'], $title, $slug, $description, $price);
                
                if ($stmt->execute()) {
                    $success = "Course created successfully!";
                } else {
                    $error = "Failed to create course";
                }
                $stmt->close();
            } else {
                $error = "Title is required";
            }
        } elseif ($action === 'update') {
            $courseId = $_POST['course_id'] ?? 0;
            $title = isset($_POST['title']) ? htmlspecialchars(trim($_POST['title']), ENT_QUOTES, 'UTF-8') : '';
            $description = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8') : '';
            $price = $_POST['price'] ?? 0;
            
            $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, price = ? WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ssdi", $title, $description, $price, $courseId);
            
            if ($stmt->execute()) {
                $success = "Course updated successfully!";
            } else {
                $error = "Failed to update course: " . $stmt->error;
            }
            $stmt->close();
        } elseif ($action === 'delete') {
            $courseId = $_POST['course_id'] ?? 0;
            
            $deleteQuery = "DELETE FROM courses WHERE id = ? AND company_id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("ii", $courseId, $company['id']);
            $deleteSuccess = $stmt->execute();
            $stmt->close();
            
            if ($conn->query($deleteQuery)) {
                $success = "Course deleted successfully!";
            } else {
                $error = "Failed to delete course: " . $conn->error;
            }
        }
    }
}

// Get courses for this company
$coursesQuery = "SELECT * FROM courses WHERE company_id = " . ($company['id'] ?? 0) . " ORDER BY created_at DESC";
$courses = $conn->query($coursesQuery);

$pageTitle = "Manage Courses - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-book-open"></i> Manage Courses</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                    <i class="fas fa-plus"></i> Create New Course
                </button>
            </div>
        </div>
    </div>

    <!-- Display messages -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Courses List -->
    <div class="row">
        <?php if ($courses && $courses->num_rows > 0): ?>
            <?php while ($course = $courses->fetch_assoc()): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            
                            <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 150)) . '...'; ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success fs-6">
                                    $<?php echo number_format($course['price'], 2); ?>
                                </span>
                                <span class="badge bg-<?php echo $course['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" 
                                        onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                    <i class="fas fa-edit"></i> Edit Course
                                </button>
                                
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this course?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-trash"></i> Delete Course
                                    </button>
                                </form>
                                
                                <!-- Vulnerable: Direct access link -->
                                <a href="<?php echo BASE_URL; ?>/pages/member/course-detail.php?id=<?php echo $course['id']; ?>"
                                   class="btn btn-outline-secondary" target="_blank">
                                    <i class="fas fa-eye"></i> View Public
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
                    <h4>No courses yet</h4>
                    <p class="text-muted">Create your first course to get started.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                        <i class="fas fa-plus"></i> Create Your First Course
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Create New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Course Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price" class="form-label">Price ($)</label>
                        <input type="number" class="form-control" id="price" name="price" 
                               min="0" step="0.01" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCourseForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Course Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Price ($)</label>
                        <input type="number" class="form-control" id="edit_price" name="price" 
                               min="0" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vulnerability Information -->
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h6><i class="fas fa-bug"></i> Security Vulnerabilities in this Page</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="small mb-0">
                                <li>SQL injection in all database operations</li>
                                <li>No CSRF protection on forms</li>
                                <li>XSS in course data display</li>
                                <li>No input validation or sanitization</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="small mb-0">
                                <li>No authorization checks (can edit any course)</li>
                                <li>Insecure direct object references</li>
                                <li>No rate limiting on actions</li>
                                <li>Exposed error messages</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Vulnerable: JavaScript function without input validation
function editCourse(course) {
    // Vulnerable: Direct DOM manipulation without sanitization
    document.getElementById('edit_course_id').value = course.id;
    document.getElementById('edit_title').value = course.title;
    document.getElementById('edit_description').value = course.description;
    document.getElementById('edit_price').value = course.price;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('editCourseModal')).show();
}

// Vulnerable: No CSRF protection on AJAX calls
function toggleCourseStatus(courseId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=toggle&course_id=${courseId}`
    });
}
</script>

<?php require_once '../../template/footer.php'; ?>