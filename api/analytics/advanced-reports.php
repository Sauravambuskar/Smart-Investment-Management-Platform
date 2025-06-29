<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class AdvancedAnalytics {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getUserGrowthAnalytics($period = '12months') {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(*) as new_users,
                COUNT(CASE WHEN role = 'client' THEN 1 END) as new_clients,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as new_admins,
                SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as cumulative_users
            FROM users 
            WHERE {$dateCondition}
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getInvestmentAnalytics($period = '12months') {
        $dateCondition = $this->getDateCondition($period);
        
        // Investment trends
        $trendStmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(i.created_at, '%Y-%m') as period,
                COUNT(i.id) as investment_count,
                SUM(i.amount) as total_amount,
                AVG(i.amount) as average_amount,
                COUNT(DISTINCT i.user_id) as unique_investors,
                COUNT(CASE WHEN i.status = 'active' THEN 1 END) as active_investments,
                COUNT(CASE WHEN i.status = 'completed' THEN 1 END) as completed_investments
            FROM investments i
            WHERE {$dateCondition}
            GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
            ORDER BY period ASC
        ");
        $trendStmt->execute();
        $trends = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Plan performance
        $planStmt = $this->db->prepare("
            SELECT 
                ip.name as plan_name,
                COUNT(i.id) as investment_count,
                SUM(i.amount) as total_amount,
                AVG(i.amount) as average_amount,
                ip.interest_rate,
                (SUM(i.amount) * ip.interest_rate / 100) as projected_returns
            FROM investments i
            JOIN investment_plans ip ON i.plan_id = ip.id
            WHERE {$dateCondition}
            GROUP BY ip.id, ip.name, ip.interest_rate
            ORDER BY total_amount DESC
        ");
        $planStmt->execute();
        $planPerformance = $planStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'trends' => $trends,
            'plan_performance' => $planPerformance
        ];
    }
    
    public function getTransactionAnalytics($period = '12months') {
        $dateCondition = $this->getDateCondition($period);
        
        // Transaction volume analysis
        $volumeStmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                type,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
            FROM transactions
            WHERE {$dateCondition}
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), type
            ORDER BY period ASC, type
        ");
        $volumeStmt->execute();
        $volume = $volumeStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Payment method analysis
        $paymentStmt = $this->db->prepare("
            SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transactions WHERE {$dateCondition})) as percentage
            FROM transactions
            WHERE {$dateCondition} AND payment_method IS NOT NULL
            GROUP BY payment_method
            ORDER BY total_amount DESC
        ");
        $paymentStmt->execute();
        $paymentMethods = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'volume_analysis' => $volume,
            'payment_methods' => $paymentMethods
        ];
    }
    
    public function getMLMAnalytics($period = '12months') {
        $dateCondition = $this->getDateCondition($period);
        
        // Commission analytics
        $commissionStmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(c.created_at, '%Y-%m') as period,
                c.level,
                COUNT(*) as commission_count,
                SUM(c.amount) as total_commission,
                AVG(c.amount) as average_commission,
                COUNT(DISTINCT c.user_id) as unique_earners
            FROM commissions c
            WHERE {$dateCondition}
            GROUP BY DATE_FORMAT(c.created_at, '%Y-%m'), c.level
            ORDER BY period ASC, c.level
        ");
        $commissionStmt->execute();
        $commissions = $commissionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top performers
        $topPerformersStmt = $this->db->prepare("
            SELECT 
                u.first_name,
                u.last_name,
                u.email,
                COUNT(r.id) as total_referrals,
                SUM(c.amount) as total_commission_earned,
                COUNT(DISTINCT c.level) as active_levels
            FROM users u
            LEFT JOIN referrals r ON u.id = r.referrer_id
            LEFT JOIN commissions c ON u.id = c.user_id
            WHERE u.role = 'client' AND c.{$dateCondition}
            GROUP BY u.id
            HAVING total_commission_earned > 0
            ORDER BY total_commission_earned DESC
            LIMIT 10
        ");
        $topPerformersStmt->execute();
        $topPerformers = $topPerformersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Network depth analysis
        $networkStmt = $this->db->prepare("
            SELECT 
                level,
                COUNT(*) as user_count,
                AVG(amount) as average_commission
            FROM commissions c
            WHERE {$dateCondition}
            GROUP BY level
            ORDER BY level
        ");
        $networkStmt->execute();
        $networkDepth = $networkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'commission_trends' => $commissions,
            'top_performers' => $topPerformers,
            'network_depth' => $networkDepth
        ];
    }
    
    public function getKYCAnalytics($period = '12months') {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                status,
                document_type,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(reviewed_at, NOW()))) as avg_processing_hours
            FROM kyc_documents
            WHERE {$dateCondition}
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), status, document_type
            ORDER BY period ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getFinancialOverview($period = '12months') {
        $dateCondition = $this->getDateCondition($period);
        
        // Revenue analysis
        $revenueStmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
                SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals,
                SUM(CASE WHEN type = 'investment' AND status = 'completed' THEN amount ELSE 0 END) as investments,
                SUM(CASE WHEN type = 'commission' AND status = 'completed' THEN amount ELSE 0 END) as commissions_paid,
                (SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) - 
                 SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END)) as net_flow
            FROM transactions
            WHERE {$dateCondition}
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period ASC
        ");
        $revenueStmt->execute();
        $revenue = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Wallet statistics
        $walletStmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_wallets,
                SUM(balance) as total_balance,
                AVG(balance) as average_balance,
                MAX(balance) as max_balance,
                COUNT(CASE WHEN balance > 0 THEN 1 END) as active_wallets
            FROM wallets
        ");
        $walletStmt->execute();
        $walletStats = $walletStmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'revenue_trends' => $revenue,
            'wallet_statistics' => $walletStats
        ];
    }
    
    public function getRiskAnalysis() {
        // High-value transactions
        $highValueStmt = $this->db->prepare("
            SELECT 
                t.*,
                u.first_name,
                u.last_name,
                u.email
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.amount > 50000 AND t.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY t.amount DESC
            LIMIT 20
        ");
        $highValueStmt->execute();
        $highValueTransactions = $highValueStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Suspicious activity patterns
        $suspiciousStmt = $this->db->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                COUNT(t.id) as transaction_count,
                SUM(t.amount) as total_amount,
                COUNT(DISTINCT DATE(t.created_at)) as active_days
            FROM users u
            JOIN transactions t ON u.id = t.user_id
            WHERE t.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY u.id
            HAVING transaction_count > 10 OR total_amount > 100000
            ORDER BY total_amount DESC
        ");
        $suspiciousStmt->execute();
        $suspiciousActivity = $suspiciousStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Failed transaction analysis
        $failedStmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as failed_count,
                SUM(amount) as failed_amount,
                COUNT(DISTINCT user_id) as affected_users
            FROM transactions
            WHERE status = 'rejected' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $failedStmt->execute();
        $failedTransactions = $failedStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'high_value_transactions' => $highValueTransactions,
            'suspicious_activity' => $suspiciousActivity,
            'failed_transactions' => $failedTransactions
        ];
    }
    
    public function generatePredictiveAnalytics() {
        // User growth prediction (simple linear regression)
        $growthStmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                COUNT(*) as new_users
            FROM users
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period ASC
        ");
        $growthStmt->execute();
        $growthData = $growthStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Investment trend prediction
        $investmentTrendStmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period,
                SUM(amount) as total_investment
            FROM investments
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY period ASC
        ");
        $investmentTrendStmt->execute();
        $investmentTrend = $investmentTrendStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Simple trend calculation
        $userGrowthTrend = $this->calculateTrend($growthData, 'new_users');
        $investmentGrowthTrend = $this->calculateTrend($investmentTrend, 'total_investment');
        
        return [
            'user_growth_prediction' => $userGrowthTrend,
            'investment_growth_prediction' => $investmentGrowthTrend,
            'next_month_users' => $this->predictNextValue($growthData, 'new_users'),
            'next_month_investment' => $this->predictNextValue($investmentTrend, 'total_investment')
        ];
    }
    
    private function getDateCondition($period) {
        switch ($period) {
            case '7days':
                return "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30days':
                return "created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '3months':
                return "created_at > DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            case '6months':
                return "created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            case '12months':
            default:
                return "created_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        }
    }
    
    private function calculateTrend($data, $valueField) {
        if (count($data) < 2) return 0;
        
        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($data as $i => $point) {
            $x = $i + 1;
            $y = floatval($point[$valueField]);
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        return $slope;
    }
    
    private function predictNextValue($data, $valueField) {
        if (count($data) < 2) return 0;
        
        $trend = $this->calculateTrend($data, $valueField);
        $lastValue = floatval(end($data)[$valueField]);
        
        return max(0, $lastValue + $trend);
    }
}

// Handle API requests
try {
    $analytics = new AdvancedAnalytics();
    $action = $_GET['action'] ?? 'overview';
    $period = $_GET['period'] ?? '12months';
    
    switch ($action) {
        case 'user_growth':
            $result = $analytics->getUserGrowthAnalytics($period);
            break;
            
        case 'investments':
            $result = $analytics->getInvestmentAnalytics($period);
            break;
            
        case 'transactions':
            $result = $analytics->getTransactionAnalytics($period);
            break;
            
        case 'mlm':
            $result = $analytics->getMLMAnalytics($period);
            break;
            
        case 'kyc':
            $result = $analytics->getKYCAnalytics($period);
            break;
            
        case 'financial':
            $result = $analytics->getFinancialOverview($period);
            break;
            
        case 'risk':
            $result = $analytics->getRiskAnalysis();
            break;
            
        case 'predictions':
            $result = $analytics->generatePredictiveAnalytics();
            break;
            
        case 'overview':
        default:
            $result = [
                'user_growth' => $analytics->getUserGrowthAnalytics($period),
                'investments' => $analytics->getInvestmentAnalytics($period),
                'transactions' => $analytics->getTransactionAnalytics($period),
                'financial' => $analytics->getFinancialOverview($period),
                'predictions' => $analytics->generatePredictiveAnalytics()
            ];
            break;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'period' => $period,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 