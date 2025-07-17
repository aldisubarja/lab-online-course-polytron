<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['admin'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$conn = getConnection();

// Vulnerable: No CSRF protection for system actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clear_sessions') {
        // Vulnerable: SQL injection
        $clearQuery = "DELETE FROM sessions";
        if ($conn->query($clearQuery)) {
            $success = "All sessions cleared!";
        }
    } elseif ($action === 'reset_otp') {
        // Vulnerable: SQL injection
        $resetQuery = "UPDATE users SET otp_code = NULL, otp_expires = NULL";
        if ($conn->query($resetQuery)) {
            $success = "All OTP codes reset!";
        }
    }
}

// Get system info
$phpVersion = phpversion();
$mysqlVersion = $conn->server_info ?? 'Unknown';

// Get database statistics
$tablesQuery = "SHOW TABLES";
$tablesResult = $conn->query($tablesQuery);
$tableCount = $tablesResult ? $tablesResult->num_rows : 0;

$pageTitle = "System Settings - Admin";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <p class="text-muted">System information and administrative tools</p>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- System Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> System Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?php echo $phpVersion; ?></td>
                        </tr>
                        <tr>
                            <td><strong>MySQL Version:</strong></td>
                            <td><?php echo $mysqlVersion; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database Tables:</strong></td>
                            <td><?php echo $tableCount; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Debug Mode:</strong></td>
                            <td><span class="badge bg-danger"><?php echo DEBUG ? 'Enabled' : 'Disabled'; ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Upload Directory:</strong></td>
                            <td><?php echo UPLOAD_DIR; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tools"></i> System Actions</h5>
                </div>
                <div class="card-body">
                    <!-- Vulnerable: No CSRF protection -->
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="clear_sessions">
                        <button type="submit" class="btn btn-warning w-100" 
                                onclick="return confirm('Clear all user sessions?')">
                            <i class="fas fa-trash"></i> Clear All Sessions
                        </button>
                    </form>

                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="reset_otp">
                        <button type="submit" class="btn btn-info w-100"
                                onclick="return confirm('Reset all OTP codes?')">
                            <i class="fas fa-key"></i> Reset All OTP Codes
                        </button>
                    </form>

                    <a href="?phpinfo=1" class="btn btn-danger w-100" target="_blank">
                        <i class="fas fa-code"></i> View PHP Info (Dangerous!)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Tables -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-database"></i> Database Tables</h5>
                </div>
                <div class="card-body">
                    <?php if ($tablesResult): ?>
                        <div class="row">
                            <?php while ($table = $tablesResult->fetch_array()): ?>
                                <div class="col-md-3 mb-2">
                                    <span class="badge bg-secondary"><?php echo $table[0]; ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Vulnerability Warning -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h6><i class="fas fa-bug"></i> System Vulnerabilities</h6>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>No CSRF protection on system actions</li>
                        <li>SQL injection in all database operations</li>
                        <li>Exposed PHP info and system details</li>
                        <li>No proper admin authentication</li>
                        <li>Debug mode enabled in production</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vulnerable: Expose PHP info -->
<?php if (isset($_GET['phpinfo'])): ?>
    <div style="margin-top: 50px;">
        <?php phpinfo(); ?>
    </div>
<?php endif; ?>

<?php require_once '../../template/footer.php'; ?>