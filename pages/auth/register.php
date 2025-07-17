<?php
require_once '../../config/env.php';
require_once '../../module/logger.php';

startSession();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = 'CSRF token is invalid or missing.';
    }else{
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'member';
        
        $conn = getConnection();
        
        if (empty($name) || empty($email) || empty($phone)) {
            $error = "All fields are required";
        } else {
            $checkQuery = "SELECT * FROM users WHERE phone = ? OR email = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $phone, $email);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult && $checkResult->num_rows > 0) {
                $error = "Phone number or email already exists";
            } else {
                $insertQuery = "INSERT INTO users (name, email, phone, role, is_verified) VALUES (?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("ssss", $name, $email, $phone, $role);
                
                if ($stmt->execute()) {
                    $userId = $conn->insert_id;
                    
                    if ($role === 'company') {
                        $companyName = $_POST['company_name'] ?? $name . ' Company';
                        $companyQuery = "INSERT INTO companies (user_id, company_name) VALUES (?, ?)";
                        $companyStmt = $conn->prepare($companyQuery);
                        $companyStmt->bind_param("is", $userId, $companyName);
                        $companyStmt->execute();
                        $companyStmt->close();
                    }
                    
                    $success = "Registration successful! You can now login.";
                    logToFile($phone . "Registered.");
                } else {
                    $error = "Registration failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$pageTitle = "Register - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-user-plus"></i> Register for VulnCourse</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <br><a href="<?php echo BASE_URL; ?>/pages/auth/login.php">Click here to login</a>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   placeholder="e.g., 081234567890" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" onchange="toggleCompanyField()">
                                <option value="member" <?php echo ($_POST['role'] ?? '') === 'member' ? 'selected' : ''; ?>>Member</option>
                                <option value="company" <?php echo ($_POST['role'] ?? '') === 'company' ? 'selected' : ''; ?>>Company</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="company_field" style="display: none;">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-user-plus"></i> Register
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        <p>Already have an account? <a href="<?php echo BASE_URL; ?>/pages/auth/login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCompanyField() {
    const role = document.getElementById('role').value;
    const companyField = document.getElementById('company_field');
    
    if (role === 'company') {
        companyField.style.display = 'block';
        document.getElementById('company_name').required = true;
    } else {
        companyField.style.display = 'none';
        document.getElementById('company_name').required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleCompanyField();
});
</script>

<?php require_once '../../template/footer.php'; ?>