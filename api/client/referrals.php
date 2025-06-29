<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class ClientReferrals {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getReferralData($userId) {
        try {
            // Get user's referral code
            $userStmt = $this->db->prepare("SELECT referral_code FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Get referral statistics
            $statsStmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_referrals,
                    COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_referrals,
                    COUNT(CASE WHEN r.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_referrals
                FROM referrals r
                JOIN users u ON r.referred_id = u.id
                WHERE r.referrer_id = ?
            ");
            $statsStmt->execute([$userId]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get total earnings from commissions
            $earningsStmt = $this->db->prepare("
                SELECT 
                    SUM(amount) as total_earnings,
                    SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) as monthly_earnings
                FROM commissions 
                WHERE user_id = ? AND status = 'paid'
            ");
            $earningsStmt->execute([$userId]);
            $earnings = $earningsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent referrals
            $recentStmt = $this->db->prepare("
                SELECT u.name, u.email, r.created_at, u.status
                FROM referrals r
                JOIN users u ON r.referred_id = u.id
                WHERE r.referrer_id = ?
                ORDER BY r.created_at DESC
                LIMIT 10
            ");
            $recentStmt->execute([$userId]);
            $recentReferrals = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'referral_code' => $user['referral_code'],
                'statistics' => [
                    'total_referrals' => $stats['total_referrals'] ?? 0,
                    'active_referrals' => $stats['active_referrals'] ?? 0,
                    'monthly_referrals' => $stats['monthly_referrals'] ?? 0,
                    'total_earnings' => $earnings['total_earnings'] ?? 0,
                    'monthly_earnings' => $earnings['monthly_earnings'] ?? 0
                ],
                'recent_referrals' => $recentReferrals
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to load referral data'];
        }
    }
}

// Handle API requests
try {
    $referrals = new ClientReferrals();
    
    // Get user ID from session
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $result = $referrals->getReferralData($userId);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred'
    ]);
}
?> 