<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get all users
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name,
                   k.status as kyc_status
            FROM users u 
            LEFT JOIN kyc_documents k ON u.id = k.user_id 
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
        
    } elseif ($method === 'POST') {
        // Create new user
        $firstName = $_POST['firstName'] ?? '';
        $lastName = $_POST['lastName'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? 'client';
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            throw new Exception('All required fields must be filled');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Generate referral code
        $referralCode = strtoupper(substr($firstName, 0, 2) . substr($lastName, 0, 2) . rand(1000, 9999));
        
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password, role, referral_code, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword, $role, $referralCode]);
        
        $userId = $db->lastInsertId();
        
        // Create wallet for the user
        $stmt = $db->prepare("
            INSERT INTO wallets (user_id, balance, created_at) 
            VALUES (?, 0.00, NOW())
        ");
        $stmt->execute([$userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userId
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 