<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from session
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Get user information
    $userStmt = $db->prepare("
        SELECT u.*, c.phone, c.address, c.date_of_birth, c.gender, c.occupation,
               w.balance as wallet_balance
        FROM users u 
        LEFT JOIN clients c ON u.id = c.user_id
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.id = ?
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Get investment statistics
    $investmentStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_investments,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_investments,
            SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as total_invested,
            SUM(CASE WHEN status = 'completed' THEN (amount + returns) ELSE 0 END) as total_returns
        FROM investments 
        WHERE user_id = ?
    ");
    $investmentStmt->execute([$userId]);
    $investmentStats = $investmentStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get earnings/commissions
    $earningsStmt = $db->prepare("
        SELECT 
            SUM(amount) as total_earnings,
            COUNT(*) as total_transactions
        FROM earnings 
        WHERE user_id = ? AND status = 'paid'
    ");
    $earningsStmt->execute([$userId]);
    $earnings = $earningsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent investments
    $recentInvestmentsStmt = $db->prepare("
        SELECT i.*, ip.name as plan_name, ip.interest_rate
        FROM investments i
        JOIN investment_plans ip ON i.plan_id = ip.id
        WHERE i.user_id = ?
        ORDER BY i.created_at DESC
        LIMIT 5
    ");
    $recentInvestmentsStmt->execute([$userId]);
    $recentInvestments = $recentInvestmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent transactions
    $recentTransactionsStmt = $db->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentTransactionsStmt->execute([$userId]);
    $recentTransactions = $recentTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get referral statistics
    $referralStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            COUNT(CASE WHEN referred.status = 'active' THEN 1 END) as active_referrals,
            SUM(CASE WHEN e.status = 'paid' THEN e.amount ELSE 0 END) as referral_earnings
        FROM referrals r
        LEFT JOIN users referred ON r.referred_id = referred.id
        LEFT JOIN earnings e ON r.referrer_id = e.user_id AND e.type = 'referral'
        WHERE r.referrer_id = ?
    ");
    $referralStmt->execute([$userId]);
    $referralStats = $referralStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get KYC status
    $kycStmt = $db->prepare("
        SELECT status, document_type, verified_at
        FROM kyc_documents 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $kycStmt->execute([$userId]);
    $kycStatus = $kycStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get notifications
    $notificationsStmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND status != 'read'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $notificationsStmt->execute([$userId]);
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate portfolio performance
    $portfolioValue = ($investmentStats['total_invested'] ?? 0) + ($earnings['total_earnings'] ?? 0);
    $portfolioGrowth = $investmentStats['total_invested'] > 0 ? 
        (($portfolioValue - $investmentStats['total_invested']) / $investmentStats['total_invested']) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'status' => $user['status'],
            'created_at' => $user['created_at'],
            'wallet_balance' => $user['wallet_balance'] ?? 0
        ],
        'statistics' => [
            'wallet_balance' => $user['wallet_balance'] ?? 0,
            'total_invested' => $investmentStats['total_invested'] ?? 0,
            'total_earned' => $earnings['total_earnings'] ?? 0,
            'active_investments' => $investmentStats['active_investments'] ?? 0,
            'total_investments' => $investmentStats['total_investments'] ?? 0,
            'portfolio_value' => $portfolioValue,
            'portfolio_growth' => round($portfolioGrowth, 2),
            'referral_earnings' => $referralStats['referral_earnings'] ?? 0,
            'total_referrals' => $referralStats['total_referrals'] ?? 0
        ],
        'recent_investments' => $recentInvestments,
        'recent_transactions' => $recentTransactions,
        'referral_stats' => $referralStats,
        'kyc_status' => $kycStatus,
        'notifications' => $notifications,
        'unread_notifications' => count($notifications)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 