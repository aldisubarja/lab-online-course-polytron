<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn()) {
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

// Vulnerable: No CSRF protection for approval actions
if (isset($_GET['action']) && isset($_GET['enrollment_id'])) {
    $action = $_GET['action'];
    $enrollmentId = $_GET['enrollment_id'];
    
    // Vulnerable: SQL injection and no authorization check
    if ($action === 'approve') {
        $updateQuery = "UPDATE enrollments SET status = 'confirmed' WHERE id = $enrollmentId";
        if ($conn->query($updateQuery)) {
            $success = "Enrollment approved successfully!";
        }
    } elseif ($action === 'reject') {
        $updateQuery = "UPDATE enrollments SET status = 'rejected' WHERE id = $enrollmentId";
        if ($conn->query($updateQuery)) {
            $success = "Enrollment rejected successfully!";
        }
    }
}

// Vulnerable: SQL injection in search
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$membersQuery = "SELECT e.*, c.title as course_title, u.name, u.email, u.phone 
                 FROM enrollments e 
                 JOIN courses c ON e.course_id = c.id 
                 JOIN users u ON e.user_id = u.id 
                 WHERE c.company_id = " . ($company['id'] ?? 0);

if ($search) {
    // Vulnerable: SQL injection
    $membersQuery .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR c.title LIKE '%$search%')";
}

if ($status) {
    // Vulnerable: SQL injection
    $membersQuery .= " AND e.status = '$status'";
}

$membersQuery .= " ORDER BY e.enrolled_at DESC";

$members = $conn->query($membersQuery);

$pageTitle = "Members - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-users"></i> Course Members</h1>
            <p class="text-muted">Manage students enrolled in your courses</p>
        </div>
    </div>

    <!-- Display success message -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <!-- Vulnerable: XSS in search value -->
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, email, or course..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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

    <!-- Members Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Enrolled Members</h5>
                </div>
                <div class="card-body">
                    <?php if ($members && $members->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Enrolled Date</th>
                                        <th>Payment Proof</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($member = $members->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <!-- Vulnerable: XSS in member name -->
                                                <strong><?php echo $member['name']; ?></strong>
                                                <br>
                                                <!-- Vulnerable: XSS in email -->
                                                <small class="text-muted"><?php echo $member['email']; ?></small>
                                                <br>
                                                <!-- Vulnerable: XSS in phone -->
                                                <small class="text-muted"><?php echo $member['phone']; ?></small>
                                            </td>
                                            <td>
                                                <!-- Vulnerable: XSS in course title -->
                                                <?php echo $member['course_title']; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                $statusIcon = '';
                                                switch ($member['status']) {
                                                    case 'confirmed':
                                                        $statusClass = 'bg-success';
                                                        $statusIcon = 'fas fa-check-circle';
                                                        break;
                                                    case 'pending':
                                                        $statusClass = 'bg-warning';
                                                        $statusIcon = 'fas fa-clock';
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'bg-danger';
                                                        $statusIcon = 'fas fa-times-circle';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <i class="<?php echo $statusIcon; ?>"></i> 
                                                    <?php echo ucfirst($member['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($member['enrolled_at'])); ?></td>
                                            <td>
                                                <?php if ($member['payment_proof']): ?>
                                                    <!-- Vulnerable: Direct file access without authorization -->
                                                    <a href="/pages/member/<?php echo $member['payment_proof']; ?>" 
                                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-file-image"></i> View Proof
                                                    </a>
                                                <?php else: ?>
                                                    <small class="text-muted">No proof uploaded</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($member['status'] === 'pending'): ?>
                                                    <!-- Vulnerable: No CSRF protection -->
                                                    <a href="?action=approve&enrollment_id=<?php echo $member['id']; ?>" 
                                                       class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="?action=reject&enrollment_id=<?php echo $member['id']; ?>" 
                                                       class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                <?php elseif ($member['status'] === 'confirmed'): ?>
                                                    <small class="text-success">Approved</small>
                                                <?php else: ?>
                                                    <small class="text-danger">Rejected</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No members found</h5>
                            <p class="text-muted">No students have enrolled in your courses yet, or no results match your search criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="fas fa-chart-pie"></i> Enrollment Statistics</h6>
                    <?php
                    // Get statistics
                    $statsQuery = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN e.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                                    SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN e.status = 'rejected' THEN 1 ELSE 0 END) as rejected
                                   FROM enrollments e 
                                   JOIN courses c ON e.course_id = c.id 
                                   WHERE c.company_id = " . ($company['id'] ?? 0);
                    $statsResult = $conn->query($statsQuery);
                    $stats = $statsResult ? $statsResult->fetch_assoc() : [];
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h4>
                            <small>Total Enrollments</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-success"><?php echo $stats['confirmed'] ?? 0; ?></h4>
                            <small>Confirmed</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-warning"><?php echo $stats['pending'] ?? 0; ?></h4>
                            <small>Pending Review</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="text-danger"><?php echo $stats['rejected'] ?? 0; ?></h4>
                            <small>Rejected</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   

<?php require_once '../../template/footer.php'; ?>