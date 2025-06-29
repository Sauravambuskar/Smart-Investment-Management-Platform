<?php
/**
 * Installation API
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
    
    // Validate database configuration
    if (!isset($input['database']) || !is_array($input['database'])) {
        throw new Exception('Database configuration is required');
    }
    
    // Validate admin configuration
    if (!isset($input['admin']) || !is_array($input['admin'])) {
        throw new Exception('Admin configuration is required');
    }
    
    $dbConfig = $input['database'];
    $adminConfig = $input['admin'];
    
    // Validate required database fields
    $requiredDbFields = ['host', 'port', 'name', 'user'];
    foreach ($requiredDbFields as $field) {
        if (!isset($dbConfig[$field]) || empty($dbConfig[$field])) {
            throw new Exception("Missing database field: $field");
        }
    }
    
    // Validate required admin fields
    $requiredAdminFields = ['firstName', 'lastName', 'email', 'phone', 'password'];
    foreach ($requiredAdminFields as $field) {
        if (!isset($adminConfig[$field]) || empty($adminConfig[$field])) {
            throw new Exception("Missing admin field: $field");
        }
    }
    
    $database = new Database();
    
    // Step 1: Test and create database
    $database->createDatabase($dbConfig);
    
    // Step 2: Execute schema
    $database->executeSchema($dbConfig);
    
    // Step 3: Save database configuration
    $database->saveConfig($dbConfig);
    
    // Step 4: Create admin account
    $conn = $database->getConnection();
    
    // Hash password
    $hashedPassword = password_hash($adminConfig['password'], PASSWORD_DEFAULT);
    
    // Insert admin user first without referral code
    $stmt = $conn->prepare("
        INSERT INTO users (name, role, email, phone, password, level, status, email_verified, created_at) 
        VALUES (?, 'admin', ?, ?, ?, 1, 'active', 1, NOW())
    ");
    
    $fullName = $adminConfig['firstName'] . ' ' . $adminConfig['lastName'];
    $stmt->execute([
        $fullName,
        $adminConfig['email'],
        $adminConfig['phone'],
        $hashedPassword
    ]);
    
    $adminId = $conn->lastInsertId();
    
    // Generate and update referral code for admin
    $referralCode = 'ADM' . strtoupper(substr(md5(time() . $adminConfig['email'] . $adminId), 0, 7));
    $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $stmt->execute([$referralCode, $adminId]);
    
    // Insert admin client profile
    $stmt = $conn->prepare("
        INSERT INTO clients (user_id, first_name, last_name, profile_completed, created_at) 
        VALUES (?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([
        $adminId,
        $adminConfig['firstName'],
        $adminConfig['lastName']
    ]);
    
    // Create admin wallet
    $stmt = $conn->prepare("
        INSERT INTO wallets (user_id, balance, last_updated) 
        VALUES (?, 0.00, NOW())
    ");
    
    $stmt->execute([$adminId]);
    
    // Update investment plans with admin ID
    $stmt = $conn->prepare("UPDATE investment_plans SET created_by = ? WHERE created_by IS NULL");
    $stmt->execute([$adminId]);
    
    // Create installation complete marker
    $installFile = __DIR__ . '/../../config/installed.lock';
    file_put_contents($installFile, date('Y-m-d H:i:s'));
    
    // Send welcome notification
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at) 
        VALUES (?, 'Welcome to SJA Foundation', 'Your admin account has been created successfully. Welcome to the SJA Foundation Investment Management Platform!', 'success', NOW())
    ");
    
    $stmt->execute([$adminId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Installation completed successfully',
        'admin_id' => $adminId,
        'referral_code' => $referralCode
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 