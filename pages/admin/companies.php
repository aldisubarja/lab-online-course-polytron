<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['company'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// Get all companies
$companiesQuery = "SELECT c.*, u.name as owner_name, u.email, u.phone,
                   (SELECT COUNT(*) FROM courses WHERE company_id = c.id) as course_count
                   FROM companies c 
                   JOIN users u ON c.user_id = u.id 
                   ORDER BY c.created_at DESC";
$companies = $conn->query($companiesQuery);

$pageTitle = "Manage Companies - Admin";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-building"></i> Manage Companies</h1>
            <p class="text-muted">View and manage all companies in the system</p>
        </div>
    </div>

    <!-- Companies Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Companies</h5>
                </div>
                <div class="card-body">
                    <?php if ($companies && $companies->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>Owner</th>
                                        <th>Contact</th>
                                        <th>Courses</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($company = $companies->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                                <?php if ($company['description']): ?>
                                                    <br><small class="text-muted"><?php echo substr($company['description'], 0, 100) . '...'; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($company['owner_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($company['email']); ?>
                                                <br><small><?php echo htmlspecialchars($company['phone']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $company['course_count']; ?> courses</span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>/pages/company/dashboard.php?company_id=<?php echo $company['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h4>No companies found</h4>
                            <p class="text-muted">No companies have been registered yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../template/footer.php'; ?>