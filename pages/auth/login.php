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
        $phone = $_POST['phone'] ?? '';
        $step = $_POST['step'] ?? '1';
        $action = $_POST['action'] ?? '';

        $conn = getConnection();

        if ($step === '1' || $action === 'regenerate_otp') {
            // Step 1: Phone number validation and OTP generation
            if (empty($phone)) {
                $error = "Phone number is required";
            } else {
                $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ?");
                $stmt->bind_param("s", $phone); // "s" means the parameter is a string
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();

                    // Generate OTP (vulnerable: weak random)
                    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $otpExpires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

                    $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires = ? WHERE phone = ?");
                    $stmt->bind_param("sss", $otp, $otpExpires, $phone); // All parameters are strings
                    $stmt->execute();

                    // Vulnerable: OTP sent to SMS (simulated)
                    $smsSent = true; // In real app, send SMS here

                    if ($smsSent) {
                        if ($action === 'regenerate_otp') {
                            $success = "New OTP has been sent to your phone: " . $phone;
                        } else {
                            $success = "OTP sent to your phone: " . $phone;
                        }
                        $showOtpForm = true;
                        $generatedOtp = $otp; // Store OTP to display (vulnerable: should not display in real app)
                        writeLogToFile($phone . "OTP Sent to your phone. [regenerate_otp]");
                    } else {
                        $error = "Failed to send OTP";
                        writeLogToFile($phone . "Failed to send OTP.");
                    }
                } else {
                    $error = "Phone number not found";
                }
            }
        } elseif ($step === '2') {
            // Step 2: OTP verification
            $otp = $_POST['otp'] ?? '';
            
            if (empty($phone) || empty($otp)) {
                $error = "Phone number and OTP are required";
            } else {
                $stmt = $conn->prepare("SELECT users.*, companies.id AS company_id FROM users LEFT JOIN companies ON users.id = companies.user_id WHERE phone = ? AND otp_code = ? AND otp_expires > NOW()");
                $stmt->bind_param("ss", $phone, $otp); // Both inputs are strings

                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_company_id'] = $user['company_id'];
                    
                    // Clear OTP
                    $clearQuery = "UPDATE users SET otp_code = NULL, otp_expires = NULL WHERE id = ?";
                    $stmt = $conn->prepare($clearQuery);
                    $stmt->bind_param("s", $user['id']); // Both inputs are strings

                    $stmt->execute();
                    
                    writeLogToFile($phone . "Login and redirected to Dashboard.");

                    // Vulnerable: No session regeneration
                    if ($user['role'] === 'member') {
                        header('Location: ' . BASE_URL . '/pages/member/dashboard.php');
                    } elseif ($user['role'] === 'company') {
                        header('Location: ' . BASE_URL . '/pages/company/dashboard.php');
                    }
                    exit;
                } else {
                    $error = "Invalid or expired OTP";
                    writeLogToFile($phone . "Invalid or expired OTP.");

                }
            }
        }
    }
}

$pageTitle = "Login - VulnCourse";
require_once '../../template/header.php';
require_once '../../template/nav.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-sign-in-alt"></i> Login to VulnCourse</h4>
                </div>
                <div class="card-body">
                    <!-- Vulnerable: Error messages expose sensitive information -->
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!isset($showOtpForm)): ?>
                        <!-- Step 1: Phone Number -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="step" value="1">
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       placeholder="e.g., 081234567890" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                                <div class="form-text">Enter your registered phone number</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane"></i> Send OTP
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Step 2: OTP Verification -->

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="step" value="2">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">

                            <div class="mb-3">
                                <label for="phone_display" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label for="otp" class="form-label">OTP Code</label>
                                <input type="text" class="form-control" id="otp" name="otp"
                                       placeholder="Enter 6-digit OTP" maxlength="6" required>
                                <div class="form-text">Enter the OTP code sent to your phone</div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 mb-2">
                                <i class="fas fa-key"></i> Verify OTP
                            </button>
                        </form>

                        <!-- Regenerate OTP Form -->
                        <form method="POST" action="" class="mb-2">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="regenerate_otp">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                            <button type="submit" class="btn btn-outline-warning w-100">
                                <i class="fas fa-redo"></i> Generate New OTP
                            </button>
                        </form>

                        <a href="<?php echo BASE_URL; ?>/pages/auth/login.php" class="btn btn-secondary w-100">
                            <i class="fas fa-arrow-left"></i> Back to Phone Number
                        </a>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="text-center">
                        <h6>Demo Accounts:</h6>
                        <small class="text-muted">
                            Company: 081234567890<br>
                            Member: 081234567891
                        </small>
                    </div>
                </div>
            </div>
            
           
        </div>
    </div>
</div>

<?php require_once '../../template/footer.php'; ?>