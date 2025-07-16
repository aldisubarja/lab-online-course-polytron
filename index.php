<?php
require_once 'config/env.php';

// Vulnerable: SQL injection in search
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$conn = getConnection();

// Vulnerable: Direct concatenation without sanitization
$sql = "SELECT c.*, comp.company_name FROM courses c 
        JOIN companies comp ON c.company_id = comp.id 
        WHERE c.is_active = 1";

if ($searchQuery) {
    // Vulnerable: SQL injection
    $sql .= " AND (c.title LIKE '%$searchQuery%' OR c.description LIKE '%$searchQuery%')";
}

$sql .= " ORDER BY c.created_at DESC LIMIT 6";
$courses = $conn->query($sql);

$pageTitle = "Home - VulnCourse";
require_once 'template/header.php';
require_once 'template/nav.php';
?>

<div class="container mt-4">
    <!-- Hero Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="jumbotron bg-primary text-white p-5 rounded">
                <h1 class="display-4">Welcome to VulnCourse Platform</h1>
                <p class="lead">Learn cybersecurity through hands-on vulnerable applications. This platform intentionally contains security flaws for educational purposes.</p>
                
                <!-- Vulnerable: XSS through GET parameter -->
                <?php if (isset($_GET['welcome'])): ?>
                    <div class="alert alert-info">
                        <?php echo $_GET['welcome']; ?>
                    </div>
                <?php endif; ?>
                
                <hr class="my-4">
                <p>Explore our courses and discover various security vulnerabilities!</p>
                <a class="btn btn-light btn-lg" href="<?php echo BASE_URL; ?>/pages/member/courses.php" role="button">
                    <i class="fas fa-book"></i> Browse Courses
                </a>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <form method="GET" action="">
                <div class="input-group">
                    <!-- Vulnerable: XSS in search value -->
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search courses..." 
                           value="<?php echo $searchQuery; ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Featured Courses -->
    <div class="row mb-5">
        <div class="col-12">
            <h2><i class="fas fa-star"></i> Featured Courses</h2>
            <p class="text-muted">Discover our most popular security-focused courses</p>
        </div>
    </div>

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
                            <p class="card-text flex-grow-1"><?php echo substr($course['description'], 0, 100) . '...'; ?></p>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">By <?php echo $course['company_name']; ?></small>
                                    <span class="badge bg-success">$<?php echo number_format($course['price'], 2); ?></span>
                                </div>
                                
                                <!-- Vulnerable: Direct object reference -->
                                <a href="<?php echo BASE_URL; ?>/pages/member/course-detail.php?id=<?php echo $course['id']; ?>"
                                   class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    No courses found. Try a different search term.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Section -->
    <div class="row mt-5 mb-5">
        <div class="col-md-3 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                    <h4>500+</h4>
                    <p>Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <i class="fas fa-book fa-3x text-success mb-3"></i>
                    <h4>50+</h4>
                    <p>Courses</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <i class="fas fa-bug fa-3x text-danger mb-3"></i>
                    <h4>100+</h4>
                    <p>Vulnerabilities</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <i class="fas fa-shield-alt fa-3x text-warning mb-3"></i>
                    <h4>24/7</h4>
                    <p>Learning</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>