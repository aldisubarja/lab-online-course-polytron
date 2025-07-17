<?php
require_once '../../config/env.php';

startSession();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isLoggedIn() || !requireRole(['member'])) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();
$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['use_api'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $errors[] = "CSRF token is invalid or missing.";
    }else{
        // Fallback to traditional form processing if API fails
        $updateFields = [];
        $allowedFields = ['name', 'email', 'phone', 'avatar'];

        foreach ($_POST as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updateFields[$key] = $value;
            }
        }

        $errors = [];

        if (empty($updateFields['name'])) {
            $errors[] = "Name is required";
        } else {
            $updateFields['name'] = htmlspecialchars(trim($updateFields['name']), ENT_QUOTES, 'UTF-8');
        }

        if (empty($updateFields['email'])) {
            $errors[] = "Email is required";
        } else {
            $updateFields['email'] = htmlspecialchars(trim($updateFields['email']), ENT_QUOTES, 'UTF-8');
        }

        if (empty($updateFields['phone'])) {
            $errors[] = "Phone is required";
        } else {
            $updateFields['phone'] = htmlspecialchars(trim($updateFields['phone']), ENT_QUOTES, 'UTF-8');
        }
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 2 * 1024 * 1024;

            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = basename($_FILES['avatar']['name']);
            $fileSize = $_FILES['avatar']['size'];
            $fileType = mime_content_type($fileTmpPath);

            if (!in_array($fileType, $allowedMimeTypes)) {
                $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            }

            elseif ($fileSize > $maxFileSize) {
                $errors[] = "File size exceeds 2MB limit.";
            }

            elseif (preg_match('/[^\w\.\-\s]/', $fileName)) {
                $errors[] = "Invalid file name.";
            } else {
                $targetDir = 'uploads/avatars/';
                $targetFile = $targetDir . uniqid('avatar_', true) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);

                // Create directory if not exists with secure permissions
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if (move_uploaded_file($fileTmpPath, $targetFile)) {
                    $updateFields['avatar'] = $targetFile;
                } else {
                    $errors[] = "Failed to upload avatar";
                }
            }
        }

        if (empty($errors)) {
            $setParts = [];
            $params = [];
            $types = '';

            foreach ($updateFields as $field => $value) {
                $setParts[] = "$field = ?";
                $params[] = $value;
                $types .= 's';
            }

            $params[] = $_SESSION['user_id'];
            $types .= 'i';

            $updateQuery = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?";

            try {
                $stmt = $conn->prepare($updateQuery);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $success = "Profile updated successfully!";
                        $currentUser = getCurrentUser();
                    } else {
                        $errors[] = "Failed to update profile";
                    }
                    
                    $stmt->close();
                } else {
                    $errors[] = "Failed to update profile";
                }
            } catch (Exception $e) {
                $errors[] = "Failed to update profile";
            } catch (mysqli_sql_exception $e) {
                $errors[] = "Failed to update profile";
            } catch (Throwable $e) {
                $errors[] = "Failed to update profile";
            }
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
                    <h4><i class="fas fa-user-edit"></i> Edit Profile - <?php echo htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
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
                    
                    <form id="profileForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">                        
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
                                    <label for="avatar" class="form-label">Change Avatar</label>
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif">
                                  
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($currentUser['phone'], ENT_QUOTES, 'UTF-8'); ?>" 
                                           pattern="[0-9]+" required
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role: <span class="text-danger"><?php echo htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8'); ?></span> </label>                                    
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
