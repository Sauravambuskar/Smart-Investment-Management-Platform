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
        // Get all transactions
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = $_GET['limit'] ?? 50;
        
        $sql = "
            SELECT t.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as user_name,
                   u.email as user_email
            FROM transactions t 
            JOIN users u ON t.user_id = u.id 
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($type) {
            $sql .= " AND t.type = ?";
            $params[] = $type;
        }
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get transaction statistics
        $statsStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as total_withdrawals
            FROM transactions
        ");
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
            'stats' => $stats
        ]);
        
    } elseif ($method === 'POST') {
        // Handle transaction approval/rejection
        $action = $_POST['action'] ?? '';
        $transactionId = $_POST['transaction_id'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        
        if (!$action || !$transactionId) {
            throw new Exception('Action and transaction ID are required');
        }
        
        $db->beginTransaction();
        
        try {
            if ($action === 'approve') {
                // Get transaction details
                $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
                $stmt->execute([$transactionId]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$transaction) {
                    throw new Exception('Transaction not found or already processed');
                }
                
                // Update transaction status
                $stmt = $db->prepare("
                    UPDATE transactions 
                    SET status = 'completed', admin_remarks = ?, processed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$remarks, $transactionId]);
                
                // Update wallet balance based on transaction type
                if ($transaction['type'] === 'deposit') {
                    $stmt = $db->prepare("
                        UPDATE wallets 
                        SET balance = balance + ?, updated_at = NOW() 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                } elseif ($transaction['type'] === 'withdrawal') {
                    $stmt = $db->prepare("
                        UPDATE wallets 
                        SET balance = balance - ?, updated_at = NOW() 
                        WHERE user_id = ? AND balance >= ?
                    ");
                    $stmt->execute([$transaction['amount'], $transaction['user_id'], $transaction['amount']]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('Insufficient wallet balance for withdrawal');
                    }
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Transaction approved successfully'
                ]);
                
            } elseif ($action === 'reject') {
                if (empty($remarks)) {
                    throw new Exception('Rejection reason is required');
                }
                
                $stmt = $db->prepare("
                    UPDATE transactions 
                    SET status = 'rejected', admin_remarks = ?, processed_at = NOW() 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$remarks, $transactionId]);
                
                if ($stmt->rowCount() > 0) {
                    $db->commit();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transaction rejected successfully'
                    ]);
                } else {
                    throw new Exception('Transaction not found or already processed');
                }
            } else {
                throw new Exception('Invalid action');
            }
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 