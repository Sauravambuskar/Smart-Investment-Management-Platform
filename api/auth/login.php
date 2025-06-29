<?php
/**
 * Login API
 * SJA Foundation Investment Management Platform
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (!isset($input['email']) || !isset($input['password'])) {
        throw new Exception('Email and password are required');
    }
    
    $email = trim($input['email']);
    $password = $input['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get user by email
    $stmt = $conn->prepare("
        SELECT u.*, c.first_name, c.last_name, c.profile_completed, c.kyc_status,
               w.balance, w.total_invested, w.total_earned
        FROM users u
        LEFT JOIN clients c ON u.id = c.user_id
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.email = ? AND u.status = 'active'
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Invalid email or password');
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Log failed login attempt
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, ip_address, user_agent, created_at) 
            VALUES (?, 'failed_login', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        throw new Exception('Invalid email or password');
    }
    
    // Update last login
    $stmt = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Log successful login
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, action, ip_address, user_agent, created_at) 
        VALUES (?, 'login', ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user['id'],
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['login_time'] = time();
    
    // Prepare user data for response (exclude password)
    $userData = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'level' => $user['level'],
        'referral_code' => $user['referral_code'],
        'total_referrals' => $user['total_referrals'],
        'status' => $user['status'],
        'email_verified' => (bool)$user['email_verified'],
        'phone_verified' => (bool)$user['phone_verified'],
        'photo' => $user['photo'],
        'created_at' => $user['created_at']
    ];
    
    // Add client-specific data if user is a client
    if ($user['role'] === 'client') {
        $userData['profile'] = [
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'profile_completed' => (bool)$user['profile_completed'],
            'kyc_status' => $user['kyc_status']
        ];
        
        $userData['wallet'] = [
            'balance' => (float)($user['balance'] ?? 0),
            'total_invested' => (float)($user['total_invested'] ?? 0),
            'total_earned' => (float)($user['total_earned'] ?? 0)
        ];
        
        // Get active investments count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM investments WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user['id']]);
        $investmentCount = $stmt->fetch();
        $userData['active_investments'] = (int)$investmentCount['count'];
        
        // Get pending notifications count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0 AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$user['id']]);
        $notificationCount = $stmt->fetch();
        $userData['unread_notifications'] = (int)$notificationCount['count'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => $userData,
        'session_id' => session_id()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 