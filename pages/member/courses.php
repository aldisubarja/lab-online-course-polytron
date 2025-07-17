<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// Vulnerable: SQL injection in search and filters
$search = $_GET['search'] ?? '';
$company = $_GET['company'] ?? '';
$priceRange = $_GET['price_range'] ?? '';

$sql = "SELECT c.*, comp.company_name FROM courses c 
        JOIN companies comp ON c.company_id = comp.id 
        WHERE c.is_active = 1";

if ($search) {
    // Vulnerable: SQL injection
    $sql .= " AND (c.title LIKE '%$search%' OR c.description LIKE '%$search%')";
}

if ($company) {
    // Vulnerable: SQL injection
    $sql .= " AND comp.company_name = '$company'";
}

if ($priceRange) {
    switch ($priceRange) {
        case 'free':
            $sql .= " AND c.price = 0";
            break;
        case 'low':
            $sql .= " AND c.price > 0 AND c.price <= 50";
            break;
        case 'medium':
            $sql .= " AND c.price > 50 AND c.price <= 100";
            break;
        case 'high':
            $sql .= " AND c.price > 100";
            break;
    }
}

$sql .= " ORDER BY c.created_at DESC";

$courses = $conn->query($sql);

// Get companies for filter
$companiesQuery = "SELECT DISTINCT company_name FROM companies ORDER BY company_name";
$companies = $conn->query($companiesQuery);

$pageTitle = "Browse Courses - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-book"></i> Browse Courses</h1>
            <p class="text-muted">Discover our collection of security-focused courses</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <!-- Vulnerable: XSS in search value -->
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search courses..." value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="company" class="form-label">Company</label>
                                <select class="form-select" id="company" name="company">
                                    <option value="">All Companies</option>
                                    <?php while ($companyRow = $companies->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($companyRow['company_name']); ?>"
                                                <?php echo $company === $companyRow['company_name'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($companyRow['company_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="price_range" class="form-label">Price Range</label>
                                <select class="form-select" id="price_range" name="price_range">
                                    <option value="">All Prices</option>
                                    <option value="free" <?php echo $priceRange === 'free' ? 'selected' : ''; ?>>Free</option>
                                    <option value="low" <?php echo $priceRange === 'low' ? 'selected' : ''; ?>>$1 - $50</option>
                                    <option value="medium" <?php echo $priceRange === 'medium' ? 'selected' : ''; ?>>$51 - $100</option>
                                    <option value="high" <?php echo $priceRange === 'high' ? 'selected' : ''; ?>>$100+</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Grid -->
    <div class="row">
        <?php if ($courses && $courses->num_rows > 0): ?>
            <?php while ($course = $courses->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card course-card h-100">
                        <img src="https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=400" 
                             class="card-img-top" alt="Course thumbnail" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <!-- Vulnerable: XSS in course title -->
                            <h5 class="card-title"><?php echo $course['title']; ?></h5>
                            
                            <!-- Vulnerable: XSS in description -->
                            <p class="card-text flex-grow-1"><?php echo substr($course['description'], 0, 120) . '...'; ?></p>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">By <?php echo $course['company_name']; ?></small>
                                    <span class="badge bg-success fs-6">
                                        <?php echo $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?>
                                    </span>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <!-- Vulnerable: Direct object reference -->
                                    <a href="<?php echo BASE_URL; ?>/pages/member/course-detail.php?id=<?php echo $course['id']; ?>"
                                       class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>

                                    <!-- Vulnerable: IDOR and no CSRF -->
                                    <a href="<?php echo BASE_URL; ?>/pages/member/checkout.php?course_id=<?php echo $course['id']; ?>"
                                       class="btn btn-success">
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
                    <p>Try adjusting your search criteria or browse all available courses.</p>
                    <a href="<?php echo BASE_URL; ?>/pages/member/courses.php" class="btn btn-primary">
                        <i class="fas fa-refresh"></i> Clear Filters
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

   
</div>

<?php require_once '../../template/footer.php'; ?>