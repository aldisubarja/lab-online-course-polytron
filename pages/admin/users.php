<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['company'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $userId = $_GET['user_id'];
    
    if ($action === 'delete') {
        // Prepare the statement
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        // Bind the integer parameter
        $stmt->bind_param("i", $userId);
        
        // Execute and check
        if ($stmt->execute()) {
            $success = "User deleted successfully!";
        } else {
            $error = "Delete failed: " . $stmt->error;
        }
        $stmt->close();

    } elseif ($action === 'toggle_verify') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = NOT is_verified WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $success = "User verification status updated!";
        } else {
            $error = "Toggle failed: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Build search query safely:
$searchTerm = trim($_GET['search'] ?? '');
$roleTerm   = trim($_GET['role'] ?? '');
$search = trim($_GET['search'] ?? '');
$sql  = "SELECT u.*, c.company_name
         FROM users u
         LEFT JOIN companies c ON u.id = c.user_id
         WHERE 1=1";
$params = [];
$types  = '';

// If searching:
if ($searchTerm !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%{$searchTerm}%";
    // bind the same value three times
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($roleTerm !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $roleTerm;
    $types .= 's';
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    // dynamic param binding
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

$pageTitle = "Manage Users - Admin";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            <p class="text-muted">View and manage all system users</p>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, email, or phone..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="member" <?php echo $role === 'member' ? 'selected' : ''; ?>>Member</option>
                                    <option value="company" <?php echo $role === 'company' ? 'selected' : ''; ?>>Company</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Users</h5>
                </div>
                <div class="card-body">
                    <?php if ($users && $users->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Company</th>
                                        <th>Verified</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'company' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['company_name'] ? htmlspecialchars($user['company_name']) : '-'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_verified'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="?action=toggle_verify&user_id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="fas fa-toggle-on"></i>
                                                </a>
                                                <a href="?action=delete&user_id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No users found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../template/footer.php'; ?>