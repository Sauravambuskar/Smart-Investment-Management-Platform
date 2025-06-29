<?php
/**
 * Registration API
 * SJA Foundation Investment Management Platform
 */

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
    $required = ['firstName', 'lastName', 'email', 'phone', 'password'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $firstName = trim($input['firstName']);
    $lastName = trim($input['lastName']);
    $email = trim($input['email']);
    $phone = trim($input['phone']);
    $password = $input['password'];
    $referralCode = isset($input['referralCode']) ? trim($input['referralCode']) : null;
    $marketing = isset($input['marketing']) ? $input['marketing'] : false;
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Validate phone format (10 digits)
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        throw new Exception('Phone number must be 10 digits');
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email address is already registered');
    }
    
    // Check if phone already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        throw new Exception('Phone number is already registered');
    }
    
    $parentId = null;
    $level = 1;
    
    // Validate referral code if provided
    if ($referralCode) {
        $stmt = $conn->prepare("SELECT id, level FROM users WHERE referral_code = ? AND status = 'active'");
        $stmt->execute([$referralCode]);
        $referrer = $stmt->fetch();
        
        if (!$referrer) {
            throw new Exception('Invalid referral code');
        }
        
        $parentId = $referrer['id'];
        $level = $referrer['level'] + 1;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Insert user first without referral code
        $stmt = $conn->prepare("
            INSERT INTO users (name, role, email, phone, password, parent_id, level, status, created_at) 
            VALUES (?, 'client', ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $fullName = $firstName . ' ' . $lastName;
        $stmt->execute([
            $fullName,
            $email,
            $phone,
            $hashedPassword,
            $parentId,
            $level
        ]);
        
        $userId = $conn->lastInsertId();
        
        // Generate unique referral code for new user
        do {
            $newReferralCode = strtoupper(substr(md5(time() . $email . $userId . rand()), 0, 8));
            $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$newReferralCode]);
        } while ($stmt->fetch());
        
        // Update user with referral code
        $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
        $stmt->execute([$newReferralCode, $userId]);
        
        // Insert client profile
        $stmt = $conn->prepare("
            INSERT INTO clients (user_id, first_name, last_name, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $firstName, $lastName]);
        
        // Create wallet
        $stmt = $conn->prepare("
            INSERT INTO wallets (user_id, balance, last_updated) 
            VALUES (?, 0.00, NOW())
        ");
        
        $stmt->execute([$userId]);
        
        // Create referral relationship if referral code was used
        if ($parentId) {
            $stmt = $conn->prepare("
                INSERT INTO referrals (referrer_id, referred_id, level, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([$parentId, $userId, 1]);
            
            // Update referrer's total referrals count
            $stmt = $conn->prepare("UPDATE users SET total_referrals = total_referrals + 1 WHERE id = ?");
            $stmt->execute([$parentId]);
            
            // Create notification for referrer
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, 'New Referral', 'Congratulations! You have successfully referred a new member: {$fullName}', 'success', NOW())
            ");
            
            $stmt->execute([$parentId]);
        }
        
        // Send welcome notification to new user
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at) 
            VALUES (?, 'Welcome to SJA Foundation', 'Welcome to SJA Foundation! Your account has been created successfully. Please complete your KYC verification to start investing.', 'info', NOW())
        ");
        
        $stmt->execute([$userId]);
        
        // Log registration
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, ip_address, user_agent, created_at) 
            VALUES (?, 'register', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        $conn->commit();
        
        // Send email verification (simulated)
        // In production, you would send an actual email here
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Please check your email for verification.',
            'user_id' => $userId,
            'referral_code' => $newReferralCode
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 