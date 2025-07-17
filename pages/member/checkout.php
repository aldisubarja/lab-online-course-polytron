<?php
require_once '../../config/env.php';
startSession();

// --- Access control ---
if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

// --- CSRF token generation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$conn = getConnection();
$currentUser = getCurrentUser();

// Sanitize & cast
$courseId = (int)($_GET['course_id'] ?? 0);

// --- Fetch course with prepared statement ---
$stmt = $conn->prepare(
    "SELECT c.*, comp.company_name
       FROM courses c
       JOIN companies comp ON c.company_id = comp.id
      WHERE c.id = ?"
);
$stmt->bind_param('i', $courseId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/courses.php?message=' . urlencode('Course not found'));
    exit;
}
$course = $result->fetch_assoc();
$stmt->close();

// --- Check enrollment with prepared statement ---
$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ?"
);
$stmt->bind_param('ii', $userId, $courseId);
$stmt->execute();
$enrolled = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($enrolled) {
    header("Location: " . BASE_URL . "/pages/member/course-detail.php?id={$courseId}&message=" . urlencode('Already enrolled'));
    exit;
}

// --- Handle form submit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        die('Invalid CSRF token');
    }

    if ($course['price'] == 0) {
        // Free course — use prepared INSERT
        $stmt = $conn->prepare(
            "INSERT INTO enrollments (user_id, course_id, status)
             VALUES (?, ?, 'confirmed')"
        );
        $stmt->bind_param('ii', $userId, $courseId);
        if ($stmt->execute()) {
            header("Location: " . BASE_URL . "/pages/member/course-detail.php?id={$courseId}&message=" . urlencode('Successfully enrolled!'));
            exit;
        }
        $error = "Failed to enroll: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
        $stmt->close();
    } else {
        // Paid course — validate file
        if (empty($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
            $error = "Payment proof is required.";
        } else {
            $fileTmp  = $_FILES['payment_proof']['tmp_name'];
            $fileName = basename($_FILES['payment_proof']['name']);
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $mime     = mime_content_type($fileTmp);

            // 1. MIME & extension whitelist
            $allowed = ['jpg','jpeg','png'];
            $allowedMimes = ['image/jpeg','image/png'];
            if (!in_array($fileExt, $allowed, true) || !in_array($mime, $allowedMimes, true)) {
                $error = "Only JPG/PNG images allowed.";
            }
            // 2. Size limit
            elseif (filesize($fileTmp) > 5 * 1024 * 1024) {
                $error = "Max file size is 5 MB.";
            } else {
                // 3. Safe filename & directory
                $safeName = bin2hex(random_bytes(16)) . ".$fileExt";
                $uploadDir = __DIR__ . '/uploads/payments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $dest = $uploadDir . $safeName;

                if (move_uploaded_file($fileTmp, $dest)) {
                    $relativePath = 'uploads/payments/' . $safeName;
                    // 4. Prepared INSERT
                    $stmt = $conn->prepare(
                        "INSERT INTO enrollments (user_id, course_id, payment_proof, status)
                         VALUES (?, ?, ?, 'pending')"
                    );
                    $stmt->bind_param('iis', $userId, $courseId, $relativePath);
                    if ($stmt->execute()) {
                        header("Location: " . BASE_URL . "/pages/member/course-detail.php?id={$courseId}&message=" . urlencode('Payment submitted for review'));
                        exit;
                    }
                    $error = "Submission failed: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
                    $stmt->close();
                } else {
                    $error = "Upload failed.";
                }
            }
        }
    }
}
$pageTitle = "Checkout - " . htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8');
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
          <!-- Error message -->
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <!-- Course info -->
          <div class="card mb-4">
            <div class="card-body">
              <h5><?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></h5>
              <p class="text-muted">By <?= htmlspecialchars($course['company_name'], ENT_QUOTES, 'UTF-8') ?></p>
              <p><?= nl2br(htmlspecialchars(substr($course['description'],0,200), ENT_QUOTES, 'UTF-8')) ?>…</p>
              <h3 class="text-success">
                <?= $course['price']==0 ? 'Free' : '$'.number_format($course['price'],2) ?>
              </h3>
            </div>
          </div>

          <!-- Checkout form -->
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <!-- Student info -->
            <div class="card mb-4">
              <div class="card-header"><h6><i class="fas fa-user"></i> Student Information</h6></div>
              <div class="card-body row">
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                </div>
              </div>
            </div>

            <?php if ($course['price'] > 0): ?>
              <!-- Payment info -->
              <div class="card mb-4">
                <div class="card-header"><h6><i class="fas fa-credit-card"></i> Payment Information</h6></div>
                <div class="card-body">
                  <p><strong>Bank:</strong> VulnBank<br>
                     <strong>Acc No:</strong> 1234567890<br>
                     <strong>Amount:</strong> $<?= number_format($course['price'],2) ?>
                  </p>
                  <div class="mb-3">
                    <label class="form-label" for="payment_proof">Payment Proof (JPG/PNG only)</label>
                    <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png" required>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Summary & actions -->
            <div class="card mb-4">
              <div class="card-header"><h6><i class="fas fa-receipt"></i> Order Summary</h6></div>
              <div class="card-body">
                <div class="d-flex justify-content-between">
                  <span>Course Price:</span><span><?= $course['price']==0 ? 'Free' : '$'.number_format($course['price'],2) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                  <span>Processing Fee:</span><span>$0.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between h5">
                  <span>Total:</span><span class="text-success"><?= $course['price']==0 ? 'Free' : '$'.number_format($course['price'],2) ?></span>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-between">
              <a href="<?= BASE_URL ?>/pages/member/course-detail.php?id=<?= $courseId ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
              </a>
              <button type="submit" class="btn btn-success">
                <i class="fas fa-check"></i>
                <?= $course['price']==0 ? 'Enroll for Free' : 'Submit Payment' ?>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../template/footer.php'; ?>
