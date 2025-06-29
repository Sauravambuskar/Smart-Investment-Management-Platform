<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $reportType = $_GET['type'] ?? '';
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
    
    switch ($reportType) {
        case 'user_growth':
            echo json_encode(getUserGrowthReport($db, $startDate, $endDate));
            break;
            
        case 'investment_summary':
            echo json_encode(getInvestmentSummaryReport($db, $startDate, $endDate));
            break;
            
        case 'transaction_summary':
            echo json_encode(getTransactionSummaryReport($db, $startDate, $endDate));
            break;
            
        case 'commission_report':
            echo json_encode(getCommissionReport($db, $startDate, $endDate));
            break;
            
        case 'kyc_report':
            echo json_encode(getKYCReport($db, $startDate, $endDate));
            break;
            
        case 'financial_overview':
            echo json_encode(getFinancialOverview($db, $startDate, $endDate));
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getUserGrowthReport($db, $startDate, $endDate) {
    // Daily user registrations
    $stmt = $db->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as new_users
        FROM users 
        WHERE created_at BETWEEN ? AND ? 
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$startDate, $endDate]);
    $dailyGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // User statistics by role
    $stmt = $db->prepare("
        SELECT 
            role,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM users 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY role
    ");
    $stmt->execute([$startDate, $endDate]);
    $roleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'success' => true,
        'daily_growth' => $dailyGrowth,
        'role_statistics' => $roleStats,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}

function getInvestmentSummaryReport($db, $startDate, $endDate) {
    // Investment statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_investments,
            SUM(amount) as total_amount,
            AVG(amount) as average_amount,
            SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as active_amount,
            SUM(CASE WHEN status = 'matured' THEN amount ELSE 0 END) as matured_amount,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
        FROM investments 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Investment by plan
    $stmt = $db->prepare("
        SELECT 
            ip.name as plan_name,
            COUNT(i.id) as investment_count,
            SUM(i.amount) as total_amount
        FROM investments i
        JOIN investment_plans ip ON i.plan_id = ip.id
        WHERE i.created_at BETWEEN ? AND ?
        GROUP BY ip.id, ip.name
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $planStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'success' => true,
        'statistics' => $stats,
        'plan_breakdown' => $planStats,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}

function getTransactionSummaryReport($db, $startDate, $endDate) {
    // Transaction statistics
    $stmt = $db->prepare("
        SELECT 
            type,
            COUNT(*) as count,
            SUM(amount) as total_amount,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
        FROM transactions 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY type
    ");
    $stmt->execute([$startDate, $endDate]);
    $typeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily transaction volume
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
            SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals
        FROM transactions 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$startDate, $endDate]);
    $dailyVolume = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'success' => true,
        'type_statistics' => $typeStats,
        'daily_volume' => $dailyVolume,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}

function getCommissionReport($db, $startDate, $endDate) {
    // Commission statistics
    $stmt = $db->prepare("
        SELECT 
            level,
            COUNT(*) as count,
            SUM(amount) as total_amount,
            AVG(amount) as average_amount
        FROM commissions 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY level
        ORDER BY level
    ");
    $stmt->execute([$startDate, $endDate]);
    $levelStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top earners
    $stmt = $db->prepare("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.email,
            SUM(c.amount) as total_commission
        FROM commissions c
        JOIN users u ON c.user_id = u.id
        WHERE c.created_at BETWEEN ? AND ?
        GROUP BY c.user_id
        ORDER BY total_commission DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topEarners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'success' => true,
        'level_statistics' => $levelStats,
        'top_earners' => $topEarners,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}

function getKYCReport($db, $startDate, $endDate) {
    // KYC statistics
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            document_type,
            COUNT(*) as type_count
        FROM kyc_documents 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status, document_type
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processing time analysis
    $stmt = $db->prepare("
        SELECT 
            AVG(DATEDIFF(reviewed_at, created_at)) as avg_processing_days,
            status
        FROM kyc_documents 
        WHERE reviewed_at IS NOT NULL 
        AND created_at BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$startDate, $endDate]);
    $processingTime = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'success' => true,
        'statistics' => $stats,
        'processing_time' => $processingTime,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}

function getFinancialOverview($db, $startDate, $endDate) {
    // Overall financial summary
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN t.type = 'investment' AND t.status = 'completed' THEN t.amount ELSE 0 END) as total_investments,
            SUM(CASE WHEN c.amount IS NOT NULL THEN c.amount ELSE 0 END) as total_commissions,
            (SELECT SUM(balance) FROM wallets) as total_wallet_balance
        FROM transactions t
        LEFT JOIN commissions c ON DATE(c.created_at) = DATE(t.created_at)
        WHERE t.created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $financial = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'success' => true,
        'financial_summary' => $financial,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ];
}
?> 