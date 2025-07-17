<?php
require_once '../../config/env.php';
startSession();

// — Access control —
if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// — Sanitize & cast incoming ID —
$courseId = (int)($_GET['id'] ?? 0);

// — Fetch course + company info via prepared statement —
$stmt = $conn->prepare("
    SELECT 
      c.*,
      comp.company_name,
      comp.description AS company_description
    FROM courses AS c
    JOIN companies AS comp
      ON c.company_id = comp.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/courses.php?message=' . urlencode('Course not found'));
    exit;
}

$course = $result->fetch_assoc();
$stmt->close();

// — Get count of materials —
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
      FROM course_materials
     WHERE course_id = ?
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$materialsCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// — Check enrollment status —
$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT status
      FROM enrollments
     WHERE user_id = ?
       AND course_id = ?
");
$stmt->bind_param('ii', $userId, $courseId);
$stmt->execute();
$enrRes     = $stmt->get_result();
$isEnrolled = $enrRes->num_rows > 0;
$enrollment = $isEnrolled ? $enrRes->fetch_assoc() : null;
$stmt->close();

// — Preview of course materials —
$stmt = $conn->prepare("
    SELECT title, order_number
      FROM course_materials
     WHERE course_id = ?
  ORDER BY order_number ASC
");
$stmt->bind_param('i', $courseId);
$stmt->execute();
$materialsPreview = $stmt->get_result();
$stmt->close();

// — Page metadata & header/nav includes —
$pageTitle = htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') . " - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
  <div class="row">
    <div class="col-md-8">
      <!-- Course Header -->
      <div class="card mb-4">
        <img src="https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=800"
             class="card-img-top"
             alt="Course thumbnail"
             style="height:300px;object-fit:cover;">
        <div class="card-body">
          <h1 class="card-title">
            <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <div class="d-flex align-items-center mb-3">
            <span class="badge bg-primary me-2">
              <i class="fas fa-building"></i>
              <?= htmlspecialchars($course['company_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="badge bg-success me-2">
              <i class="fas fa-tag"></i>
              <?= $course['price'] == 0
                    ? 'Free'
                    : '$' . number_format($course['price'], 2) ?>
            </span>
            <span class="badge bg-info">
              <i class="fas fa-book-open"></i>
              <?= htmlspecialchars($materialsCount, ENT_QUOTES, 'UTF-8') ?> Materials
            </span>
          </div>
          <p class="card-text">
            <?= nl2br(
                  htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8')
                ) ?>
          </p>

          <!-- Enrollment Status -->
          <?php if ($isEnrolled): ?>
            <?php
              $statusClass = [
                'confirmed' => 'success',
                'pending'   => 'warning',
              ][$enrollment['status']] ?? 'danger';
            ?>
            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i>
              <strong>Enrollment Status:</strong>
              <span class="badge bg-<?= $statusClass ?>">
                <?= ucfirst(htmlspecialchars($enrollment['status'], ENT_QUOTES, 'UTF-8')) ?>
              </span>

              <?php if ($enrollment['status'] === 'confirmed'): ?>
                <div class="mt-2">
                  <a href="<?= BASE_URL . '/pages/member/course-materials.php?slug='
                             . urlencode($course['slug']) ?>"
                     class="btn btn-success">
                    <i class="fas fa-play"></i> Start Learning
                  </a>
                </div>
              <?php elseif ($enrollment['status'] === 'pending'): ?>
                <div class="mt-2 small text-muted">
                  Your payment is being reviewed. You’ll get access once approved.
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Course Content Preview -->
      <div class="card mb-4">
        <div class="card-header">
          <h5><i class="fas fa-list"></i> Course Content</h5>
        </div>
        <div class="card-body">
          <?php if ($materialsPreview->num_rows > 0): ?>
            <div class="list-group">
              <?php while ($mat = $materialsPreview->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <i class="fas fa-play-circle text-primary me-2"></i>
                    <?= htmlspecialchars($mat['title'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <span class="badge bg-secondary">
                    <?= (int)$mat['order_number'] ?>
                  </span>
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
          <h5><i class="fas fa-graduation-cap"></i> Enroll</h5>
        </div>
        <div class="card-body text-center">
          <h3 class="text-success mb-3">
            <?= $course['price'] == 0
                 ? 'Free'
                 : '$' . number_format($course['price'], 2) ?>
          </h3>

          <?php if (! $isEnrolled): ?>
            <a href="<?= BASE_URL . '/pages/member/checkout.php?course_id='
                       . $courseId ?>"
               class="btn btn-success w-100 mb-2">
              <i class="fas fa-shopping-cart"></i> Enroll Now
            </a>
          <?php elseif ($enrollment['status'] === 'confirmed'): ?>
            <a href="<?= BASE_URL . '/pages/member/course-materials.php?slug='
                       . urlencode($course['slug']) ?>"
               class="btn btn-primary w-100 mb-2">
              <i class="fas fa-play"></i> Continue Learning
            </a>
          <?php else: ?>
            <button class="btn btn-secondary w-100 mb-2" disabled>
              <i class="fas fa-clock"></i>
              <?= $enrollment['status'] === 'pending'
                    ? 'Pending Approval'
                    : 'Enrollment Rejected' ?>
            </button>
          <?php endif; ?>

          <a href="<?= BASE_URL ?>/pages/member/courses.php"
             class="btn btn-outline-secondary w-100">
            <i class="fas fa-arrow-left"></i> Back to Courses
          </a>
        </div>
      </div>

      <!-- Company Info -->
      <div class="card">
        <div class="card-header">
          <h6><i class="fas fa-building"></i> About the Company</h6>
        </div>
        <div class="card-body small">
          <h6><?= htmlspecialchars($course['company_name'], ENT_QUOTES, 'UTF-8') ?></h6>
          <p>
            <?= nl2br(
                  htmlspecialchars(
                    $course['company_description'] ?: 'No description available.',
                    ENT_QUOTES,
                    'UTF-8'
                  )
                ) ?>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_GET['message'])): ?>
  <script>
    // Safely JSON-encode message into JS
    alert(<?= json_encode($_GET['message'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP) ?>);
  </script>
<?php endif; ?>

<?php require_once '../../template/footer.php'; ?>
