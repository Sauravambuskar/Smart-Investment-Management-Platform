<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

class ClientTransactions {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getTransactions($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM transactions 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'transactions' => $transactions
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to load transactions'];
        }
    }
    
    public function createDeposit($userId, $amount, $paymentMethod = 'online') {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }
            
            $this->db->beginTransaction();
            
            // Create transaction record
            $transactionStmt = $this->db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, payment_method, created_at)
                VALUES (?, 'deposit', ?, 'pending', ?, ?, NOW())
            ");
            $transactionStmt->execute([
                $userId, 
                $amount, 
                "Wallet deposit of ₹{$amount}",
                $paymentMethod
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            // For demo purposes, auto-approve the deposit
            // In production, this would be handled by payment gateway callback
            $this->approveDeposit($transactionId, $userId, $amount);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Deposit successful',
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Deposit failed. Please try again.'];
        }
    }
    
    private function approveDeposit($transactionId, $userId, $amount) {
        // Update transaction status
        $updateStmt = $this->db->prepare("
            UPDATE transactions 
            SET status = 'completed', processed_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$transactionId]);
        
        // Add to wallet
        $walletStmt = $this->db->prepare("
            UPDATE wallets 
            SET balance = balance + ?
            WHERE user_id = ?
        ");
        $walletStmt->execute([$amount, $userId]);
    }
    
    public function createWithdrawal($userId, $amount) {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }
            
            // Check wallet balance
            $walletStmt = $this->db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $walletStmt->execute([$userId]);
            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$wallet || $wallet['balance'] < $amount) {
                return ['success' => false, 'message' => 'Insufficient wallet balance'];
            }
            
            $this->db->beginTransaction();
            
            // Create withdrawal transaction
            $transactionStmt = $this->db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at)
                VALUES (?, 'withdrawal', ?, 'pending', ?, NOW())
            ");
            $transactionStmt->execute([
                $userId, 
                $amount, 
                "Withdrawal request of ₹{$amount}"
            ]);
            
            $transactionId = $this->db->lastInsertId();
            
            // Deduct from wallet (lock the amount)
            $walletStmt = $this->db->prepare("
                UPDATE wallets 
                SET balance = balance - ?, locked_balance = locked_balance + ?
                WHERE user_id = ?
            ");
            $walletStmt->execute([$amount, $amount, $userId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Withdrawal failed. Please try again.'];
        }
    }
}

// Handle API requests
try {
    $transactions = new ClientTransactions();
    
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
                $result = $transactions->getTransactions($userId);
                break;
                
            default:
                $result = ['success' => false, 'message' => 'Invalid action'];
        }
        
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'deposit':
                $amount = $_POST['amount'] ?? 0;
                $paymentMethod = $_POST['payment_method'] ?? 'online';
                $result = $transactions->createDeposit($userId, $amount, $paymentMethod);
                break;
                
            case 'withdraw':
                $amount = $_POST['amount'] ?? 0;
                $result = $transactions->createWithdrawal($userId, $amount);
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