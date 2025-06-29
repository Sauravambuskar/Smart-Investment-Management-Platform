<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

class ClientAuth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($email, $password, $remember = false) {
        try {
            // Get user details
            $stmt = $this->db->prepare("
                SELECT u.id, u.email, u.password, u.role, u.status, u.name,
                       c.first_name, c.last_name
                FROM users u
                LEFT JOIN clients c ON u.id = c.user_id
                WHERE u.email = ? AND u.role = 'client'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            if ($user['status'] !== 'active') {
                return ['success' => false, 'message' => 'Your account is not active. Please contact support.'];
            }
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Get user wallet balance
            $walletStmt = $this->db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $walletStmt->execute([$user['id']]);
            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create session
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'],
                    'wallet_balance' => $wallet['balance'] ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    public function register($data) {
        try {
            // Validate required fields
            $required = ['first_name', 'last_name', 'email', 'phone', 'password', 'confirm_password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Validate password match
            if ($data['password'] !== $data['confirm_password']) {
                return ['success' => false, 'message' => 'Passwords do not match'];
            }
            
            // Validate password strength
            if (strlen($data['password']) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
            }
            
            // Check if email already exists
            $checkStmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$data['email']]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            $this->db->beginTransaction();
            
            // Create user account
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $referralCode = $this->generateReferralCode();
            
            $userStmt = $this->db->prepare("
                INSERT INTO users (
                    name, email, phone, password, 
                    role, status, referral_code, created_at
                ) VALUES (?, ?, ?, ?, 'client', 'active', ?, NOW())
            ");
            $userStmt->execute([
                $data['first_name'] . ' ' . $data['last_name'],
                $data['email'],
                $data['phone'],
                $hashedPassword,
                $referralCode
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Create client profile
            $clientStmt = $this->db->prepare("
                INSERT INTO clients (
                    user_id, first_name, last_name, created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            $clientStmt->execute([$userId, $data['first_name'], $data['last_name']]);
            
            // Create wallet
            $walletStmt = $this->db->prepare("
                INSERT INTO wallets (user_id, balance, created_at) 
                VALUES (?, 0.00, NOW())
            ");
            $walletStmt->execute([$userId]);
            
            // Handle referral if provided
            if (!empty($data['referral_code'])) {
                $this->processReferral($userId, $data['referral_code']);
            }
            
            // Create welcome notification
            $notificationStmt = $this->db->prepare("
                INSERT INTO notifications (user_id, title, message, type, priority, created_at)
                VALUES (?, 'Welcome to SJA Foundation!', 'Your account has been created successfully. Complete your KYC to start investing.', 'info', 'medium', NOW())
            ");
            $notificationStmt->execute([$userId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Registration successful! You can now login.',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    public function validateSession() {
        session_start();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
            return ['success' => false, 'message' => 'Not authenticated'];
        }
        
        // Verify user still exists and is active
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.name, u.role, u.status,
                   c.first_name, c.last_name
            FROM users u
            LEFT JOIN clients c ON u.id = c.user_id
            WHERE u.id = ? AND u.role = 'client'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['status'] !== 'active') {
            session_destroy();
            return ['success' => false, 'message' => 'User not found or inactive'];
        }
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => ($user['first_name'] && $user['last_name']) ? 
                    $user['first_name'] . ' ' . $user['last_name'] : $user['name'],
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'role' => $user['role']
            ]
        ];
    }
    
    public function logout() {
        session_start();
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    private function generateReferralCode() {
        do {
            $code = 'SJA' . strtoupper(substr(md5(uniqid()), 0, 6));
            $stmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());
        
        return $code;
    }
    
    private function processReferral($userId, $referralCode) {
        // Find referrer
        $referrerStmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = ?");
        $referrerStmt->execute([$referralCode]);
        $referrer = $referrerStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($referrer) {
            // Create referral record
            $referralStmt = $this->db->prepare("
                INSERT INTO referrals (referrer_id, referred_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $referralStmt->execute([$referrer['id'], $userId]);
            
            // Send notification to referrer
            $notificationStmt = $this->db->prepare("
                INSERT INTO notifications (user_id, title, message, type, priority, created_at)
                VALUES (?, 'New Referral!', 'You have successfully referred a new user. Start earning commissions when they invest!', 'success', 'medium', NOW())
            ");
            $notificationStmt->execute([$referrer['id']]);
        }
    }
}

// Handle API requests
try {
    $auth = new ClientAuth();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'login':
                $result = $auth->login(
                    $input['email'] ?? $_POST['email'],
                    $input['password'] ?? $_POST['password'],
                    $input['remember'] ?? $_POST['remember'] ?? false
                );
                break;
                
            case 'register':
                $result = $auth->register($input ?? $_POST);
                break;
                
            case 'validate':
                $result = $auth->validateSession();
                break;
                
            case 'logout':
                $result = $auth->logout();
                break;
                
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
        }
    } else {
        $result = ['success' => false, 'message' => 'Method not allowed'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}
?>