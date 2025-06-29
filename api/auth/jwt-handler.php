<?php
require_once __DIR__ . '/../config/database.php';

class JWTHandler {
    private $secret_key = "SJA_FOUNDATION_SECRET_2024";
    private $algorithm = 'HS256';
    
    public function generateToken($userId, $role, $email) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode([
            'user_id' => $userId,
            'role' => $role,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);
        
        $headerEncoded = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $this->secret_key, true);
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    public function validateToken($token) {
        if (!$token) return false;
        
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) return false;
        
        $header = $this->base64UrlDecode($tokenParts[0]);
        $payload = $this->base64UrlDecode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];
        
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $this->secret_key, true)
        );
        
        if ($signatureProvided !== $expectedSignature) return false;
        
        $payloadData = json_decode($payload, true);
        if ($payloadData['exp'] < time()) return false;
        
        return $payloadData;
    }
    
    public function refreshToken($oldToken) {
        $payload = $this->validateToken($oldToken);
        if (!$payload) return false;
        
        return $this->generateToken($payload['user_id'], $payload['role'], $payload['email']);
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

class SecurityManager {
    private $db;
    private $jwtHandler;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->jwtHandler = new JWTHandler();
    }
    
    public function authenticateUser($email, $password, $rememberMe = false) {
        try {
            // Check login attempts
            if ($this->isAccountLocked($email)) {
                return ['success' => false, 'message' => 'Account temporarily locked due to multiple failed attempts'];
            }
            
            $stmt = $this->db->prepare("
                SELECT id, email, password, role, status, first_name, last_name, 
                       failed_login_attempts, last_login_attempt
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->recordFailedLogin($email);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            if ($user['status'] !== 'active') {
                return ['success' => false, 'message' => 'Account is not active'];
            }
            
            // Reset failed attempts on successful login
            $this->resetFailedLogins($email);
            
            // Generate JWT token
            $token = $this->jwtHandler->generateToken($user['id'], $user['role'], $user['email']);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Create session record
            $sessionId = $this->createSession($user['id'], $token, $rememberMe);
            
            return [
                'success' => true,
                'token' => $token,
                'session_id' => $sessionId,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'name' => $user['first_name'] . ' ' . $user['last_name']
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }
    }
    
    public function validateSession($token) {
        $payload = $this->jwtHandler->validateToken($token);
        if (!$payload) return false;
        
        // Check if session exists and is active
        $stmt = $this->db->prepare("
            SELECT id, user_id, expires_at 
            FROM user_sessions 
            WHERE token = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $session ? $payload : false;
    }
    
    public function logout($token) {
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = 0, logout_at = NOW()
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        return true;
    }
    
    private function isAccountLocked($email) {
        $stmt = $this->db->prepare("
            SELECT failed_login_attempts, last_login_attempt
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) return false;
        
        // Lock account for 15 minutes after 5 failed attempts
        if ($user['failed_login_attempts'] >= 5) {
            $lockTime = strtotime($user['last_login_attempt']) + (15 * 60);
            return time() < $lockTime;
        }
        
        return false;
    }
    
    private function recordFailedLogin($email) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_login_attempt = NOW()
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    private function resetFailedLogins($email) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = 0,
                last_login_attempt = NULL
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    private function createSession($userId, $token, $rememberMe) {
        $expiresAt = $rememberMe ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expiresAt]);
        
        return $this->db->lastInsertId();
    }
}
?> 