<?php
// Vulnerable configuration file
define('DB_HOST', $_ENV['DB_HOST'] ?? 'lab_online_course_polytron');
#define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'root');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'db_lab_online_course_polytron');

// Base URL
define('BASE_URL', 'http://localhost:8005');

// Vulnerable: Exposed sensitive information
define('SECRET_KEY', 'vulnerable_secret_123');
define('UPLOAD_DIR', 'uploads/');

define('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io');
define('MAILTRAP_PORT', 2525);
define('MAILTRAP_USERNAME', 'c422d05e0331d3');
define('MAILTRAP_PASSWORD', '76eae054995016');

// Vulnerable: Debug mode enabled in production
define('DEBUG', true);
define('SHOW_ERRORS', true);

if (SHOW_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Vulnerable database connection with no error handling
function getConnection() {
    static $connection = null;

    if ($connection === null) {
        // Use MySQLi with proper error reporting
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

            // Optional: enforce UTF-8 charset
            $connection->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            // Secure: don't expose raw error details to users
            error_log("Database connection error: " . $e->getMessage());
            die("A server error occurred. Please try again later.");
        }
    }

    return $connection;
}

// Vulnerable session management
function startSession() {
    // Only configure session settings if no session is active and headers not sent
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        // Vulnerable: Weak session configuration
        ini_set('session.cookie_httponly', 1); // XSS vulnerable
        ini_set('session.cookie_secure', 1);   // Not HTTPS only
        ini_set('session.use_strict_mode', 1); // Session fixation vulnerable
        session_start();
    } elseif (session_status() === PHP_SESSION_NONE && headers_sent()) {
        // If headers already sent (e.g., command line), just start session without ini_set
        @session_start();
    }
}

// Vulnerable: No CSRF protection
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Vulnerable: No role validation
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $conn = getConnection();
    $userId = $_SESSION['user_id'];
    
    // Vulnerable: SQL injection
    $query = "SELECT * FROM users WHERE id = $userId";
    $result = $conn->query($query);
    
    return $result ? $result->fetch_assoc() : null;
}

// Vulnerable file upload function
function uploadFile($file, $destination) {
    // Vulnerable: No file type validation
    // Vulnerable: No file size limits
    // Vulnerable: Directory traversal possible
    
    $targetFile = UPLOAD_DIR . $destination;
    
    // Create directory if not exists (vulnerable to directory traversal)
    $targetDir = dirname($targetFile);
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true); // Vulnerable: Permissive permissions
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $targetFile;
    }
    
    return false;
}

function requireRole(array $allowedRoles)
{
    // not logged in or role not in allowed list
    if (
        empty($_SESSION['user_role']) ||
        ! in_array($_SESSION['user_role'], $allowedRoles, true)
    ) {
        return false;
    }else{
        return true;
    }
}
?>
