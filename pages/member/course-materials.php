<?php
require_once '../../config/env.php';
startSession();

// — Access control: must be logged in & a member —
if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn      = getConnection();
$currentUser = getCurrentUser();
$userId    = (int)$_SESSION['user_id'];

// — Validate & sanitize slug parameter —
$slug = trim($_GET['slug'] ?? '');
if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
    header('Location: ' . BASE_URL . '/pages/member/my-courses.php?message='
           . urlencode('Invalid course identifier'));
    exit;
}

// — Fetch course by slug (prepared statement) —
$stmt = $conn->prepare("
    SELECT 
      c.id,
      c.title,
      c.description,
      comp.company_name
    FROM courses AS c
    JOIN companies AS comp
      ON c.company_id = comp.id
    WHERE c.slug = ?
      AND c.is_active = 1
    LIMIT 1
");
$stmt->bind_param('s', $slug);
$stmt->execute();
$courseResult = $stmt->get_result();
if ($courseResult->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/my-courses.php?message='
           . urlencode('Course not found'));
    exit;
}
$course = $courseResult->fetch_assoc();
$stmt->close();

// — Enrollment check: only confirmed members may view materials —
$stmt = $conn->prepare("
    SELECT 1
      FROM enrollments
     WHERE user_id = ?
       AND course_id = ?
       AND status = 'confirmed'
    LIMIT 1
");
$stmt->bind_param('ii', $userId, $course['id']);
$stmt->execute();
$enrRes = $stmt->get_result();
$stmt->close();

if ($enrRes->num_rows === 0) {
    header('Location: ' . BASE_URL . '/pages/member/my-courses.php?message='
           . urlencode('You must be enrolled to view materials'));
    exit;
}

// — Load all materials for sidebar —
$stmt = $conn->prepare("
    SELECT id, title, order_number
      FROM course_materials
     WHERE course_id = ?
  ORDER BY order_number ASC
");
$stmt->bind_param('i', $course['id']);
$stmt->execute();
$materials = $stmt->get_result();
$stmt->close();

// — Determine selected material ID (if any) and validate it —
$materialId = isset($_GET['material']) ? (int)$_GET['material'] : 0;
$currentMaterial = null;

if ($materialId > 0) {
    // — Fetch this material, ensuring it belongs to this course —
    $stmt = $conn->prepare("
        SELECT id, title, content, file_path, order_number
          FROM course_materials
         WHERE id = ?
           AND course_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $materialId, $course['id']);
    $stmt->execute();
    $matRes = $stmt->get_result();
    $currentMaterial = $matRes->fetch_assoc() ?: null;
    $stmt->close();
}

// — Page setup —
$pageTitle = "Course Materials – " . htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8');
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container-fluid mt-4">
  <div class="row">
    <!-- Sidebar: Materials List -->
    <div class="col-md-3">
      <div class="card sticky-top" style="top:20px;">
        <div class="card-header bg-primary text-white">
          <h6 class="mb-0">
            <i class="fas fa-list"></i> Materials
          </h6>
        </div>
        <div class="list-group list-group-flush">
          <?php if ($materials->num_rows > 0): ?>
            <?php while ($mat = $materials->fetch_assoc()): ?>
              <?php $active = $mat['id'] === $materialId ? 'active' : ''; ?>
              <a href="?slug=<?= urlencode($slug) ?>&material=<?= $mat['id'] ?>"
                 class="list-group-item list-group-item-action <?= $active ?>">
                <div class="d-flex justify-content-between">
                  <small><?= htmlspecialchars($mat['title'], ENT_QUOTES, 'UTF-8') ?></small>
                  <span class="badge bg-secondary"><?= (int)$mat['order_number'] ?></span>
                </div>
              </a>
            <?php endwhile; ?>
          <?php else: ?>
            <p class="p-3 text-muted mb-0">No materials available.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-9">
      <!-- Course Header -->
      <div class="card mb-4">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h2><?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-0">
              <i class="fas fa-building"></i>
              <?= htmlspecialchars($course['company_name'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>
          <a href="<?= BASE_URL ?>/pages/member/my-courses.php"
             class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to My Courses
          </a>
        </div>
      </div>

      <?php if ($materialId && $currentMaterial): ?>
        <!-- Selected Material -->
        <div class="card mb-4">
          <div class="card-header">
            <h4>
              <i class="fas fa-play-circle text-primary"></i>
              <?= htmlspecialchars($currentMaterial['title'], ENT_QUOTES, 'UTF-8') ?>
            </h4>
          </div>
          <div class="card-body">
            <div class="material-content">
              <?= nl2br(htmlspecialchars($currentMaterial['content'], ENT_QUOTES, 'UTF-8')) ?>
            </div>

            <?php if ($currentMaterial['file_path']): ?>
              <hr>
              <div>
                <h6><i class="fas fa-file-download"></i> Resources</h6>
                <a href="<?= htmlspecialchars(BASE_URL . '/' . $currentMaterial['file_path'], ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-outline-primary" target="_blank">
                  <i class="fas fa-download"></i> Download
                </a>
              </div>
            <?php endif; ?>

            <!-- Prev / Next Navigation -->
            <hr>
            <div class="d-flex justify-content-between">
              <?php
              // Previous material
              $prevStmt = $conn->prepare("
                SELECT id
                  FROM course_materials
                 WHERE course_id = ?
                   AND order_number < ?
                 ORDER BY order_number DESC
                 LIMIT 1
              ");
              $prevStmt->bind_param('ii', $course['id'], $currentMaterial['order_number']);
              $prevStmt->execute();
              $prev = $prevStmt->get_result()->fetch_assoc() ?: null;
              $prevStmt->close();

              // Next material
              $nextStmt = $conn->prepare("
                SELECT id
                  FROM course_materials
                 WHERE course_id = ?
                   AND order_number > ?
                 ORDER BY order_number ASC
                 LIMIT 1
              ");
              $nextStmt->bind_param('ii', $course['id'], $currentMaterial['order_number']);
              $nextStmt->execute();
              $next = $nextStmt->get_result()->fetch_assoc() ?: null;
              $nextStmt->close();
              ?>

              <div>
                <?php if ($prev): ?>
                  <a href="?slug=<?= urlencode($slug) ?>&material=<?= $prev['id'] ?>"
                     class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Previous
                  </a>
                <?php endif; ?>
              </div>
              <div>
                <?php if ($next): ?>
                  <a href="?slug=<?= urlencode($slug) ?>&material=<?= $next['id'] ?>"
                     class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- Course Overview -->
        <div class="card text-center py-5">
          <div class="card-body">
            <i class="fas fa-play-circle fa-4x text-primary mb-3"></i>
            <h4>Welcome to <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="text-muted"><?= nl2br(htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8')) ?></p>
            <?php
              // Link to first lesson
              if ($materials->data_seek(0)) {
                  $first = $materials->fetch_assoc();
              }
            ?>
            <?php if (!empty($first)): ?>
              <a href="?slug=<?= urlencode($slug) ?>&material=<?= $first['id'] ?>"
                 class="btn btn-primary btn-lg">
                <i class="fas fa-play"></i> Start First Lesson
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once '../../template/footer.php'; ?>
