<?php
require_once '../../config/env.php';
startSession();

// — Access control: must be logged in AND have “member” role —
if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();
// Double-check role field in case requireRole is misconfigured:
if (($currentUser['role'] ?? '') !== 'member') {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn   = getConnection();
$userId = (int)$_SESSION['user_id'];

// — 1. Count confirmed enrollments —
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
       FROM enrollments
      WHERE user_id = ?
        AND status = ?"
);
$statusConfirmed = 'confirmed';
$stmt->bind_param('is', $userId, $statusConfirmed);
$stmt->execute();
$enrolledCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// — 2. Count pending payments —
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
       FROM enrollments
      WHERE user_id = ?
        AND status = ?"
);
$statusPending = 'pending';
$stmt->bind_param('is', $userId, $statusPending);
$stmt->execute();
$pendingCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// — 3. Fetch 5 most-recent enrollments —
$stmt = $conn->prepare(
    "SELECT
       e.status,
       e.enrolled_at,
       c.title,
       c.slug,
       comp.company_name
     FROM enrollments AS e
     JOIN courses       AS c    ON e.course_id    = c.id
     JOIN companies     AS comp ON c.company_id    = comp.id
    WHERE e.user_id = ?
 ORDER BY e.enrolled_at DESC
    LIMIT 5"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentEnrollments = $stmt->get_result();
$stmt->close();

$pageTitle = "Member Dashboard – VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
  <!-- Welcome -->
  <div class="row mb-4">
    <div class="col-12">
      <h1>
        <i class="fas fa-tachometer-alt"></i>
        Welcome back, <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?>!
      </h1>
      <p class="text-muted">
        Manage your courses and track your learning progress
      </p>
    </div>
  </div>

  <!-- Stats -->
  <div class="row mb-4">
    <?php foreach ([
      ['count' => $enrolledCount, 'label' => 'Enrolled Courses',  'icon' => 'book',       'bg' => 'primary'],
      ['count' => $pendingCount,  'label' => 'Pending Payments', 'icon' => 'clock',      'bg' => 'warning'],
      ['count' => '100%',          'label' => 'Vulnerable Code',  'icon' => 'bug',        'bg' => 'success'],
      ['count' => '∞',             'label' => 'Security Flaws',   'icon' => 'shield-alt', 'bg' => 'danger'],
    ] as $stat): ?>
      <div class="col-md-3">
        <div class="card bg-<?= $stat['bg'] ?> text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h4><?= htmlspecialchars($stat['count'], ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="mb-0"><?= htmlspecialchars($stat['label'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <div class="align-self-center">
                <i class="fas fa-<?= $stat['icon'] ?> fa-2x"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Quick Actions -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <?php
            $actions = [
              ['url' => '/pages/member/courses.php',     'label' => 'Browse Courses',    'icon' => 'search',       'cls' => 'primary'],
              ['url' => '/pages/member/my-courses.php',  'label' => 'My Courses',        'icon' => 'play-circle',  'cls' => 'success'],
              ['url' => '/pages/member/profile.php',     'label' => 'Edit Profile',      'icon' => 'user-edit',    'cls' => 'info'],
              // leave in your XSS tester if you like, but dashboard will now escape it safely:
              ['url' => '/?message=<script>alert(1)</script>', 'label' => 'Test XSS', 'icon' => 'bug', 'cls' => 'warning'],
            ];
            foreach ($actions as $act): ?>
              <div class="col-md-3 mb-2">
                <a href="<?= htmlspecialchars(BASE_URL . $act['url'], ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-outline-<?= $act['cls'] ?> w-100">
                  <i class="fas fa-<?= $act['icon'] ?>"></i>
                  <?= htmlspecialchars($act['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Enrollments -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h5><i class="fas fa-history"></i> Recent Enrollments</h5>
        </div>
        <div class="card-body">
          <?php if ($recentEnrollments->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Course</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Enrolled Date</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $recentEnrollments->fetch_assoc()): ?>
                    <?php
                      $safeTitle   = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                      $safeCompany = htmlspecialchars($row['company_name'], ENT_QUOTES, 'UTF-8');
                      $safeSlug    = urlencode($row['slug']);
                      $status      = $row['status'];
                      switch ($status) {
                        case 'confirmed': $cls = 'success'; break;
                        case 'pending':   $cls = 'warning'; break;
                        default:          $cls = 'danger';  break;
                      }
                    ?>
                    <tr>
                      <td><?= $safeTitle ?></td>
                      <td><?= $safeCompany ?></td>
                      <td>
                        <span class="badge bg-<?= $cls ?>">
                          <?= ucfirst(htmlspecialchars($status, ENT_QUOTES, 'UTF-8')) ?>
                        </span>
                      </td>
                      <td><?= date('M d, Y', strtotime($row['enrolled_at'])) ?></td>
                      <td>
                        <?php if ($status === 'confirmed'): ?>
                          <a href="<?= htmlspecialchars(BASE_URL . "/pages/member/course-materials.php?slug={$safeSlug}", ENT_QUOTES, 'UTF-8') ?>"
                             class="btn btn-sm btn-primary">
                            <i class="fas fa-play"></i> Start Learning
                          </a>
                        <?php elseif ($status === 'pending'): ?>
                          <small class="text-muted">Awaiting approval</small>
                        <?php else: ?>
                          <small class="text-danger">Payment rejected</small>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
            <div class="text-center">
              <a href="<?= htmlspecialchars(BASE_URL . '/pages/member/my-courses.php', ENT_QUOTES, 'UTF-8') ?>"
                 class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> View All Courses
              </a>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="fas fa-book fa-3x text-muted mb-3"></i>
              <h5>No courses yet</h5>
              <p class="text-muted">Start your learning journey by browsing courses.</p>
              <a href="<?= htmlspecialchars(BASE_URL . '/pages/member/courses.php', ENT_QUOTES, 'UTF-8') ?>"
                 class="btn btn-primary">
                <i class="fas fa-search"></i> Browse Courses
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_GET['message'])): ?>
  <script>
    // JSON-encode to safely embed user input in JS
    alert(<?= json_encode($_GET['message'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS) ?>);
  </script>
<?php endif; ?>

<?php require_once '../../template/footer.php'; ?>
