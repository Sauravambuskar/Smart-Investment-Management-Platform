<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                // Get user's investments
                $stmt = $db->prepare("
                    SELECT i.*, ip.name as plan_name, ip.interest_rate, ip.duration_months,
                           ip.minimum_amount, ip.maximum_amount, ip.description
                    FROM investments i
                    JOIN investment_plans ip ON i.plan_id = ip.id
                    WHERE i.user_id = ?
                    ORDER BY i.created_at DESC
                ");
                $stmt->execute([$userId]);
                $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'investments' => $investments
                ]);
                break;
                
            case 'plans':
                // Get available investment plans
                $stmt = $db->prepare("
                    SELECT * FROM investment_plans 
                    WHERE status = 'active'
                    ORDER BY minimum_amount ASC
                ");
                $stmt->execute();
                $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'plans' => $plans
                ]);
                break;
                
            case 'details':
                $investmentId = $_GET['investment_id'];
                
                // Get investment details with earnings
                $stmt = $db->prepare("
                    SELECT i.*, ip.name as plan_name, ip.interest_rate, ip.duration_months,
                           ip.description, 
                           COALESCE(SUM(e.amount), 0) as total_earnings
                    FROM investments i
                    JOIN investment_plans ip ON i.plan_id = ip.id
                    LEFT JOIN earnings e ON i.id = e.investment_id
                    WHERE i.id = ? AND i.user_id = ?
                    GROUP BY i.id
                ");
                $stmt->execute([$investmentId, $userId]);
                $investment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$investment) {
                    echo json_encode(['success' => false, 'message' => 'Investment not found']);
                    exit;
                }
                
                // Get earnings history
                $earningsStmt = $db->prepare("
                    SELECT * FROM earnings 
                    WHERE investment_id = ?
                    ORDER BY created_at DESC
                ");
                $earningsStmt->execute([$investmentId]);
                $earnings = $earningsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'investment' => $investment,
                    'earnings' => $earnings
                ]);
                break;
                
            case 'statistics':
                // Get investment statistics
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_investments,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_investments,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_investments,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_investments,
                        SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as active_amount,
                        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
                        AVG(CASE WHEN status = 'active' THEN amount ELSE NULL END) as average_investment
                    FROM investments 
                    WHERE user_id = ?
                ");
                $stmt->execute([$userId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get monthly investment trend (last 12 months)
                $trendStmt = $db->prepare("
                    SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as count,
                        SUM(amount) as total_amount
                    FROM investments 
                    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month ASC
                ");
                $trendStmt->execute([$userId]);
                $trend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'statistics' => $stats,
                    'monthly_trend' => $trend
                ]);
                break;
        }
        
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? 'create';
        
        switch ($action) {
            case 'create':
                $planId = $_POST['plan_id'];
                $amount = $_POST['amount'];
                
                // Validate plan
                $planStmt = $db->prepare("SELECT * FROM investment_plans WHERE id = ? AND status = 'active'");
                $planStmt->execute([$planId]);
                $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$plan) {
                    echo json_encode(['success' => false, 'message' => 'Invalid investment plan']);
                    exit;
                }
                
                // Validate amount
                if ($amount < $plan['minimum_amount'] || $amount > $plan['maximum_amount']) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "Amount must be between ₹{$plan['minimum_amount']} and ₹{$plan['maximum_amount']}"
                    ]);
                    exit;
                }
                
                // Check wallet balance
                $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
                $walletStmt->execute([$userId]);
                $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$wallet || $wallet['balance'] < $amount) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient wallet balance']);
                    exit;
                }
                
                $db->beginTransaction();
                
                try {
                    // Create investment
                    $investStmt = $db->prepare("
                        INSERT INTO investments (user_id, plan_id, amount, status, created_at)
                        VALUES (?, ?, ?, 'pending', NOW())
                    ");
                    $investStmt->execute([$userId, $planId, $amount]);
                    $investmentId = $db->lastInsertId();
                    
                    // Deduct from wallet
                    $updateWalletStmt = $db->prepare("
                        UPDATE wallets SET balance = balance - ? WHERE user_id = ?
                    ");
                    $updateWalletStmt->execute([$amount, $userId]);
                    
                    // Create transaction record
                    $transactionStmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, amount, status, description, created_at)
                        VALUES (?, 'investment', ?, 'completed', ?, NOW())
                    ");
                    $transactionStmt->execute([
                        $userId, 
                        $amount, 
                        "Investment in {$plan['name']} plan"
                    ]);
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Investment created successfully',
                        'investment_id' => $investmentId
                    ]);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
                
            case 'withdraw':
                $investmentId = $_POST['investment_id'];
                
                // Get investment details
                $investStmt = $db->prepare("
                    SELECT * FROM investments 
                    WHERE id = ? AND user_id = ? AND status = 'active'
                ");
                $investStmt->execute([$investmentId, $userId]);
                $investment = $investStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$investment) {
                    echo json_encode(['success' => false, 'message' => 'Investment not found or not active']);
                    exit;
                }
                
                $db->beginTransaction();
                
                try {
                    // Update investment status
                    $updateStmt = $db->prepare("
                        UPDATE investments 
                        SET status = 'withdrawn', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$investmentId]);
                    
                    // Add amount back to wallet (with penalty if applicable)
                    $penaltyRate = 0.05; // 5% penalty for early withdrawal
                    $returnAmount = $investment['amount'] * (1 - $penaltyRate);
                    
                    $updateWalletStmt = $db->prepare("
                        UPDATE wallets SET balance = balance + ? WHERE user_id = ?
                    ");
                    $updateWalletStmt->execute([$returnAmount, $userId]);
                    
                    // Create transaction record
                    $transactionStmt = $db->prepare("
                        INSERT INTO transactions (user_id, type, amount, status, description, created_at)
                        VALUES (?, 'withdrawal', ?, 'completed', ?, NOW())
                    ");
                    $transactionStmt->execute([
                        $userId, 
                        $returnAmount, 
                        "Early withdrawal from investment (5% penalty applied)"
                    ]);
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Investment withdrawn successfully',
                        'returned_amount' => $returnAmount,
                        'penalty_amount' => $investment['amount'] - $returnAmount
                    ]);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 