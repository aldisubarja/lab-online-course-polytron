<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// Vulnerable: No CSRF protection for user actions
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $userId = $_GET['user_id'];
    
    // Vulnerable: SQL injection
    if ($action === 'delete') {
        $deleteQuery = "DELETE FROM users WHERE id = $userId";
        if ($conn->query($deleteQuery)) {
            $success = "User deleted successfully!";
        }
    } elseif ($action === 'toggle_verify') {
        $toggleQuery = "UPDATE users SET is_verified = NOT is_verified WHERE id = $userId";
        if ($conn->query($toggleQuery)) {
            $success = "User verification status updated!";
        }
    }
}

// Get all users with search
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';

$usersQuery = "SELECT u.*, c.company_name FROM users u 
               LEFT JOIN companies c ON u.id = c.user_id 
               WHERE 1=1";

if ($search) {
    // Vulnerable: SQL injection
    $usersQuery .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%')";
}

if ($role) {
    // Vulnerable: SQL injection
    $usersQuery .= " AND u.role = '$role'";
}

$usersQuery .= " ORDER BY u.created_at DESC";
$users = $conn->query($usersQuery);

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
                                <!-- Vulnerable: XSS in search value -->
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, email, or phone..." 
                                       value="<?php echo $search; ?>">
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
                                            <!-- Vulnerable: XSS in user data -->
                                            <td><?php echo $user['name']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td><?php echo $user['phone']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'company' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['company_name'] ?? '-'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_verified'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <!-- Vulnerable: No CSRF protection -->
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