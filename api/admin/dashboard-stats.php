<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get dashboard statistics
    $stats = [];
    
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $stats['totalUsers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stmt->execute();
    $stats['activeUsers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending KYC
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM kyc_documents WHERE status = 'pending'");
    $stmt->execute();
    $stats['pendingKyc'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // New users today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stats['newUsersToday'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total investments amount
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM investments WHERE status IN ('active', 'matured')");
    $stmt->execute();
    $stats['totalInvestments'] = number_format($stmt->fetch(PDO::FETCH_ASSOC)['total'], 2);
    
    // Active investments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM investments WHERE status = 'active'");
    $stmt->execute();
    $stats['activeInvestments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending approvals
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM investments WHERE status = 'pending'");
    $stmt->execute();
    $stats['pendingApprovals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Matured investments this month
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM investments WHERE status = 'matured' AND MONTH(maturity_date) = MONTH(CURDATE()) AND YEAR(maturity_date) = YEAR(CURDATE())");
    $stmt->execute();
    $stats['maturedInvestments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard statistics: ' . $e->getMessage()
    ]);
}
?> 