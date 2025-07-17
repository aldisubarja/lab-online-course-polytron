<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();
$courseId = $_GET['course_id'] ?? 0;
$currentUser = getCurrentUser();

// Vulnerable: SQL injection
$query = "SELECT c.*, comp.company_name FROM courses c 
          JOIN companies comp ON c.company_id = comp.id 
          WHERE c.id = $courseId";

$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/courses.php?message=Course not found');
    exit;
}

$course = $result->fetch_assoc();

// Check if already enrolled
$userId = $_SESSION['user_id'];
$enrollmentQuery = "SELECT * FROM enrollments WHERE user_id = $userId AND course_id = $courseId";
$enrollmentResult = $conn->query($enrollmentQuery);
$isEnrolled = $enrollmentResult && $enrollmentResult->num_rows > 0;

if ($isEnrolled) {
    header("Location: " . BASE_URL . "/pages/member/course-detail.php?id=$courseId&message=Already enrolled");
    exit;
}

// Vulnerable: No CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    if ($course['price'] == 0) {
        // Free course - auto approve
        $insertQuery = "INSERT INTO enrollments (user_id, course_id, status) 
                        VALUES ($userId, $courseId, 'confirmed')";
        
        if ($conn->query($insertQuery)) {
            header("Location: " . BASE_URL . "/pages/member/course-detail.php?id=$courseId&message=Successfully enrolled!");
            exit;
        } else {
            $error = "Failed to enroll: " . $conn->error;
        }
    } else {
        // Paid course - handle payment proof upload
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            // Vulnerable: No file validation
            $fileName = $_FILES['payment_proof']['name'];
            $targetDir = 'uploads/payments/';
            
            // Vulnerable: Directory traversal
            $targetFile = $targetDir . basename($fileName);
            
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true); // Vulnerable: Permissive permissions
            }
            
            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetFile)) {
                // Vulnerable: SQL injection
                $insertQuery = "INSERT INTO enrollments (user_id, course_id, payment_proof, status) 
                                VALUES ($userId, $courseId, '$targetFile', 'pending')";
                
                if ($conn->query($insertQuery)) {
                    header("Location: " . BASE_URL . "/pages/member/course-detail.php?id=$courseId&message=Payment submitted for review");
                    exit;
                } else {
                    $error = "Failed to submit payment: " . $conn->error;
                }
            } else {
                $error = "Failed to upload payment proof";
            }
        } else {
            $error = "Payment proof is required for paid courses";
        }
    }
}

$pageTitle = "Checkout - " . $course['title'];
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4><i class="fas fa-shopping-cart"></i> Checkout</h4>
                </div>
                <div class="card-body">
                    <!-- Display errors -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Course Information -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Vulnerable: XSS in course title -->
                                    <h5><?php echo $course['title']; ?></h5>
                                    <p class="text-muted">By <?php echo htmlspecialchars($course['company_name']); ?></p>
                                    <!-- Vulnerable: XSS in description -->
                                    <p><?php echo substr($course['description'], 0, 200) . '...'; ?></p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <h3 class="text-success">
                                        <?php echo $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Checkout Form -->
                    <!-- Vulnerable: No CSRF token -->
                    <form method="POST" enctype="multipart/form-data">
                        <!-- User Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-user"></i> Student Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Name</label>
                                        <!-- Vulnerable: XSS in user data -->
                                        <input type="text" class="form-control" value="<?php echo $currentUser['name']; ?>" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <!-- Vulnerable: XSS in user data -->
                                        <input type="email" class="form-control" value="<?php echo $currentUser['email']; ?>" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Information -->
                        <?php if ($course['price'] > 0): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6><i class="fas fa-credit-card"></i> Payment Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-university"></i> Bank Transfer Details</h6>
                                        <p><strong>Bank:</strong> VulnBank</p>
                                        <p><strong>Account Number:</strong> 1234567890</p>
                                        <p><strong>Account Name:</strong> VulnCourse Platform</p>
                                        <p><strong>Amount:</strong> $<?php echo number_format($course['price'], 2); ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="payment_proof" class="form-label">Payment Proof</label>
                                        <!-- Vulnerable: No file type validation -->
                                        <input type="file" class="form-control" id="payment_proof" name="payment_proof" required>
                                        <div class="form-text">Upload your payment receipt/screenshot (any file type accepted - vulnerable!)</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Order Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6><i class="fas fa-receipt"></i> Order Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <span>Course Price:</span>
                                    <span class="fw-bold">
                                        <?php echo $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Processing Fee:</span>
                                    <span>$0.00</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between h5">
                                    <span>Total:</span>
                                    <span class="text-success">
                                        <?php echo $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>/pages/member/course-detail.php?id=<?php echo $course['id']; ?>"
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Course
                            </a>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> 
                                <?php echo $course['price'] == 0 ? 'Enroll for Free' : 'Submit Payment'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            

        </div>
    </div>
</div>

<?php require_once '../../template/footer.php'; ?>