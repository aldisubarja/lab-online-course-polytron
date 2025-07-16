<?php
require_once '../../config/env.php';

startSession();

// Vulnerable: No CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $role = $_POST['role'] ?? 'member';
    
    $conn = getConnection();
    
    if (empty($name) || empty($email) || empty($phone)) {
        $error = "All fields are required";
    } else {
        // Vulnerable: SQL injection
        $checkQuery = "SELECT * FROM users WHERE phone = '$phone' OR email = '$email'";
        $checkResult = $conn->query($checkQuery);
        
        if ($checkResult && $checkResult->num_rows > 0) {
            $error = "Phone number or email already exists";
        } else {
            // Vulnerable: SQL injection
            $insertQuery = "INSERT INTO users (name, email, phone, role, is_verified) 
                           VALUES ('$name', '$email', '$phone', '$role', 1)";
            
            if ($conn->query($insertQuery)) {
                $userId = $conn->insert_id;
                
                // If company role, create company record
                if ($role === 'company') {
                    $companyName = $_POST['company_name'] ?? $name . ' Company';
                    $companyQuery = "INSERT INTO companies (user_id, company_name) 
                                    VALUES ($userId, '$companyName')";
                    $conn->query($companyQuery);
                }
                
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed: " . $conn->error;
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
                    
                    <!-- Vulnerable: No CSRF token -->
                    <form method="POST" action="">
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