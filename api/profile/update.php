<?php
// Prevent any output before JSON
ob_start();
error_reporting(0); // Suppress errors for clean JSON output

require_once '../../config/env.php';

// Clear any previous output
ob_clean();

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

startSession();

// Vulnerable: No proper authentication check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Get JSON input or form data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// If JSON parsing failed, use POST data
if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
    $input = $_POST;
}

// If still empty, return error
if (empty($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No input data received',
        'debug' => [
            'raw_input' => $rawInput,
            'post_data' => $_POST,
            'json_error' => json_last_error_msg()
        ]
    ]);
    exit;
}

// Vulnerable: Mass assignment - accept all input fields without validation
$allowedFields = [
    'name', 'email', 'phone', 'role', 'avatar', 'is_verified', 
    'otp_code', 'otp_expires', 'created_at', 'updated_at'
];

$updateData = [];
$updateFields = [];

// Vulnerable: No input validation or sanitization
foreach ($input as $key => $value) {
    if (in_array($key, $allowedFields)) {
        $updateData[$key] = $value;
        // Vulnerable: Direct string concatenation for SQL injection
        $updateFields[] = "$key = '$value'";
    }
}

// Vulnerable: No CSRF protection
// Vulnerable: No rate limiting
// Vulnerable: No input length validation

if (empty($updateFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No valid fields to update'
    ]);
    exit;
}

// Vulnerable: SQL injection through mass assignment
$updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = $userId";

// Debug mode - shows actual SQL query (vulnerable)
if (isset($input['debug']) && $input['debug'] === true) {
    $debugInfo = [
        'query' => $updateQuery,
        'input_data' => $input,
        'update_fields' => $updateData,
        'user_id' => $userId
    ];
}

try {
    $result = $conn->query($updateQuery);
    
    if ($result) {
        // Get updated user data
        $userQuery = "SELECT * FROM users WHERE id = $userId";
        $userResult = $conn->query($userQuery);
        $updatedUser = $userResult->fetch_assoc();
        
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $updatedUser,
                'updated_fields' => array_keys($updateData)
            ]
        ];
        
        // Vulnerable: Information disclosure
        if (isset($updateData['role'])) {
            $response['message'] .= " Role changed to: " . $updateData['role'];
            $response['privilege_escalation'] = true;
        }
        
        // Add debug info if requested
        if (isset($debugInfo)) {
            $response['debug'] = $debugInfo;
        }
        
        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT);
        
    } else {
        throw new Exception($conn->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'sql_query' => $updateQuery, // Vulnerable: SQL query disclosure
        'debug' => isset($debugInfo) ? $debugInfo : null
    ], JSON_PRETTY_PRINT);
}
?>
