<?php
require_once '../../config/env.php';

startSession();

if (!isLoggedIn() || !requireRole(['member','company'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();
$conn = getConnection();

// Vulnerable: No CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['use_api'])) {
    
    // Fallback to traditional form processing if API fails
    $updateFields = [];
    $allowedFields = ['name', 'email', 'phone', 'role', 'avatar']; // role should not be user-editable!

    foreach ($_POST as $key => $value) {
        if (in_array($key, $allowedFields)) {
            $updateFields[$key] = $value;
        }
    }

    $errors = [];

    // Vulnerable: No input validation and XSS
    if (empty($updateFields['name'])) {
        $errors[] = "Name is required";
    }

    if (empty($updateFields['email'])) {
        $errors[] = "Email is required";
    }

    if (empty($updateFields['phone'])) {
        $errors[] = "Phone is required";
    }
    
    // Handle file upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        // Vulnerable: No file type validation
        // Vulnerable: No file size limits
        // Vulnerable: Arbitrary file upload

        $fileName = $_FILES['avatar']['name'];
        $targetDir = 'uploads/avatars/';

        // Vulnerable: Directory traversal
        $targetFile = $targetDir . basename($fileName);
        
        // Create directory if not exists
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true); // Vulnerable: Permissive permissions
        }

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
            $updateFields['avatar'] = $targetFile;
        } else {
            $errors[] = "Failed to upload avatar";
        }
    }

    if (empty($errors)) {
        // Vulnerable: Mass assignment + SQL injection
        $setParts = [];
        foreach ($updateFields as $field => $value) {
            // Vulnerable: No escaping, direct insertion into SQL
            $setParts[] = "$field = '$value'";
        }

        $updateQuery = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = " . $_SESSION['user_id'];

        if ($conn->query($updateQuery)) {
            $success = "Profile updated successfully!";

            // Show what was actually updated (vulnerable: information disclosure)
            if (isset($updateFields['role'])) {
                $success .= " Role changed to: " . $updateFields['role'];
            }

            // Refresh user data
            $currentUser = getCurrentUser();
        } else {
            $errors[] = "Failed to update profile: " . $conn->error;
        }
    }
}

$pageTitle = "Profile - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <!-- Vulnerable: XSS in user name -->
                    <h4><i class="fas fa-user-edit"></i> Edit Profile - <?php echo $currentUser['name']; ?></h4>
                </div>
                <div class="card-body">
                    <!-- Display errors -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Display success -->
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Vulnerable: No CSRF token -->
                    <form id="profileForm" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <!-- Current Avatar -->
                                <div class="mb-3">
                                    <?php if ($currentUser['avatar']): ?>
                                        <img src="<?php echo BASE_URL; ?>/pages/member/<?php echo $currentUser['avatar']; ?>" 
                                             class="img-thumbnail rounded-circle" 
                                             style="width: 150px; height: 150px; object-fit: cover;"
                                             alt="Avatar">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 150px; height: 150px; margin: 0 auto;">
                                            <i class="fas fa-user fa-3x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Avatar Upload -->
                                <div class="mb-3">
                                    <label for="avatar" class="form-label">Change Avatar <span class="text-danger">(File Upload Vulnerability)</span></label>
                                    <!-- Vulnerable: No file type restrictions, no size limits, directory traversal -->
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="*/*">
                                  
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <!-- Vulnerable: XSS in value attribute -->
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo $currentUser['name']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <!-- Vulnerable: XSS in value attribute -->
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $currentUser['email']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <!-- Vulnerable: XSS in value attribute -->
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo $currentUser['phone']; ?>" required>
                                </div>
                                
                                <!-- Vulnerable: Role field should not be user-editable + XSS in label -->
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role: <span class="text-danger"><?php echo $currentUser['role']; ?></span> </label>
                                    
                                </div>

                                <div class="mb-3">
                                    <label for="created_at" class="form-label">Member Since</label>
                                    <input type="text" class="form-control" id="created_at"
                                           value="<?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?>" disabled>
                                </div>

                                
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>/pages/member/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

           
        </div>
    </div>
</div>

<!-- Include Profile API JavaScript -->
<script>
    const BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>/assets/js/profile-api.js"></script>

<?php require_once '../../template/footer.php'; ?>
