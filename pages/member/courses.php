<?php
require_once '../../config/env.php';
startSession();

if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// Gather & trim inputs
$search     = trim($_GET['search']     ?? '');
$company    = trim($_GET['company']    ?? '');
$priceRange = trim($_GET['price_range'] ?? '');

// Build dynamic WHERE clauses safely
$whereClauses = ['c.is_active = 1'];
$types        = '';
$params       = [];

// 1. Search filter
if ($search !== '') {
    $whereClauses[] = '(c.title LIKE ? OR c.description LIKE ?)';
    $likeSearch     = "%{$search}%";
    $types         .= 'ss';
    $params[]       = &$likeSearch;
    $params[]       = &$likeSearch;
}

// 2. Company filter
if ($company !== '') {
    $whereClauses[] = 'comp.company_name = ?';
    $types         .= 's';
    $params[]       = &$company;
}

// 3. Price-range filter (no user input injected here)
switch ($priceRange) {
    case 'free':
        $whereClauses[] = 'c.price = 0';
        break;
    case 'low':
        $whereClauses[] = 'c.price > 0 AND c.price <= 50';
        break;
    case 'medium':
        $whereClauses[] = 'c.price > 50 AND c.price <= 100';
        break;
    case 'high':
        $whereClauses[] = 'c.price > 100';
        break;
    default:
        // no price filter
        break;
}

// Final SQL
$sql = "
    SELECT 
      c.*, 
      comp.company_name 
    FROM courses AS c
    JOIN companies AS comp 
      ON c.company_id = comp.id
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY c.created_at DESC
";

$stmt = $conn->prepare($sql);

// Bind parameters if any
if ($types !== '') {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}

$stmt->execute();
$courses = $stmt->get_result();
$stmt->close();

// Fetch companies for the company filter dropdown
$companiesStmt = $conn->prepare("
    SELECT DISTINCT company_name 
      FROM companies
  ORDER BY company_name
");
$companiesStmt->execute();
$companies = $companiesStmt->get_result();
$companiesStmt->close();

$pageTitle = "Browse Courses – VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
  <div class="row mb-3">
    <div class="col-12">
      <h1><i class="fas fa-book"></i> Browse Courses</h1>
      <p class="text-muted">Discover our collection of security-focused courses</p>
    </div>
  </div>

  <!-- Filters -->
  <div class="row mb-4">
    <div class="col-12">
      <form method="GET" action="">
        <div class="row g-3">
          <div class="col-md-4">
            <label for="search" class="form-label">Search</label>
            <input 
              type="text" 
              id="search" 
              name="search" 
              class="form-control" 
              placeholder="Search courses…" 
              value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            >
          </div>
          <div class="col-md-3">
            <label for="company" class="form-label">Company</label>
            <select id="company" name="company" class="form-select">
              <option value="">All Companies</option>
              <?php while ($row = $companies->fetch_assoc()): ?>
                <?php $cn = $row['company_name']; ?>
                <option 
                  value="<?= htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') ?>"
                  <?= $company === $cn ? 'selected' : '' ?>
                >
                  <?= htmlspecialchars($cn, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label for="price_range" class="form-label">Price Range</label>
            <select id="price_range" name="price_range" class="form-select">
              <option value="">All Prices</option>
              <option value="free"   <?= $priceRange==='free'   ? 'selected' : '' ?>>Free</option>
              <option value="low"    <?= $priceRange==='low'    ? 'selected' : '' ?>>$1 – $50</option>
              <option value="medium" <?= $priceRange==='medium' ? 'selected' : '' ?>>$51 – $100</option>
              <option value="high"   <?= $priceRange==='high'   ? 'selected' : '' ?>>$100+</option>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary mt-4">
              <i class="fas fa-search"></i> Filter
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Courses Grid -->
  <div class="row">
    <?php if ($courses && $courses->num_rows > 0): ?>
      <?php while ($c = $courses->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <img 
              src="https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=400"
              class="card-img-top" 
              alt="Course thumbnail"
              style="height:200px;object-fit:cover;"
            >
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">
                <?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') ?>
              </h5>
              <p class="card-text flex-grow-1">
                <?= nl2br(htmlspecialchars(substr($c['description'], 0, 120), ENT_QUOTES, 'UTF-8')) ?>…
              </p>
              <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <small class="text-muted">
                    By <?= htmlspecialchars($c['company_name'], ENT_QUOTES, 'UTF-8') ?>
                  </small>
                  <span class="badge bg-success fs-6">
                    <?= $c['price']==0 ? 'Free' : '$'.number_format($c['price'],2) ?>
                  </span>
                </div>
                <div class="d-grid gap-2">
                  <a 
                    href="<?= BASE_URL ?>/pages/member/course-detail.php?id=<?= (int)$c['id'] ?>"
                    class="btn btn-outline-primary"
                  >
                    <i class="fas fa-eye"></i> View Details
                  </a>
                  <a 
                    href="<?= BASE_URL ?>/pages/member/checkout.php?course_id=<?= (int)$c['id'] ?>"
                    class="btn btn-success"
                  >
                    <i class="fas fa-shopping-cart"></i> Enroll Now
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-warning text-center">
          <i class="fas fa-search fa-3x mb-3"></i>
          <h4>No courses found</h4>
          <p>Try adjusting your filters or <a href="<?= BASE_URL ?>/pages/member/courses.php">clear filters</a>.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../../template/footer.php'; ?>
