<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../notifications/websocket-server.php';

class InvestmentProcessor {
    private $db;
    private $notificationManager;
    private $autoTriggers;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->notificationManager = new NotificationManager();
        $this->autoTriggers = new AutoNotificationTriggers();
    }
    
    public function processMaturedInvestments() {
        echo "Processing matured investments...\n";
        
        $stmt = $this->db->prepare("
            SELECT i.*, ip.name as plan_name, ip.interest_rate, ip.duration_months,
                   u.first_name, u.last_name, u.email
            FROM investments i
            JOIN investment_plans ip ON i.plan_id = ip.id
            JOIN users u ON i.user_id = u.id
            WHERE i.status = 'active' 
            AND DATE_ADD(i.created_at, INTERVAL ip.duration_months MONTH) <= NOW()
        ");
        $stmt->execute();
        $maturedInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($maturedInvestments as $investment) {
            $this->processMaturedInvestment($investment);
        }
        
        echo "Processed " . count($maturedInvestments) . " matured investments\n";
        return count($maturedInvestments);
    }
    
    private function processMaturedInvestment($investment) {
        $this->db->beginTransaction();
        
        try {
            // Calculate returns
            $principal = $investment['amount'];
            $interestRate = $investment['interest_rate'];
            $duration = $investment['duration_months'];
            
            // Simple interest calculation (can be modified for compound interest)
            $returns = ($principal * $interestRate * $duration) / (12 * 100);
            $totalAmount = $principal + $returns;
            
            // Update investment status
            $updateStmt = $this->db->prepare("
                UPDATE investments 
                SET status = 'completed', 
                    returns = ?, 
                    maturity_date = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$returns, $investment['id']]);
            
            // Add amount to user's wallet
            $walletStmt = $this->db->prepare("
                INSERT INTO wallets (user_id, balance, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                balance = balance + ?,
                updated_at = NOW()
            ");
            $walletStmt->execute([$investment['user_id'], $totalAmount, $totalAmount]);
            
            // Create transaction record
            $transactionStmt = $this->db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at)
                VALUES (?, 'maturity', ?, 'completed', ?, NOW())
            ");
            $transactionStmt->execute([
                $investment['user_id'],
                $totalAmount,
                "Investment maturity: {$investment['plan_name']} (Principal: ₹{$principal}, Returns: ₹{$returns})"
            ]);
            
            // Process MLM commissions
            $this->processMLMCommissions($investment['user_id'], $returns);
            
            // Send notification
            $this->notificationManager->sendNotification(
                $investment['user_id'],
                'Investment Matured',
                "Your investment in {$investment['plan_name']} has matured. Total amount ₹{$totalAmount} has been credited to your wallet.",
                'success',
                'high'
            );
            
            $this->db->commit();
            
            echo "Processed investment ID: {$investment['id']} for user: {$investment['email']}\n";
            
        } catch (Exception $e) {
            $this->db->rollback();
            echo "Error processing investment ID: {$investment['id']} - " . $e->getMessage() . "\n";
        }
    }
    
    public function processRecurringCommissions() {
        echo "Processing recurring commissions...\n";
        
        // Get active investments for commission calculation
        $stmt = $this->db->prepare("
            SELECT i.*, u.email, r.referrer_id
            FROM investments i
            JOIN users u ON i.user_id = u.id
            LEFT JOIN referrals r ON i.user_id = r.referred_id
            WHERE i.status = 'active'
            AND DATEDIFF(NOW(), i.created_at) % 30 = 0  -- Monthly commission
            AND r.referrer_id IS NOT NULL
        ");
        $stmt->execute();
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($investments as $investment) {
            $this->processInvestmentCommission($investment);
        }
        
        echo "Processed " . count($investments) . " recurring commissions\n";
        return count($investments);
    }
    
    private function processInvestmentCommission($investment) {
        // MLM commission structure (can be configured)
        $commissionRates = [
            1 => 5.0,   // Level 1: 5%
            2 => 3.0,   // Level 2: 3%
            3 => 2.0,   // Level 3: 2%
            4 => 1.0,   // Level 4: 1%
            5 => 0.5    // Level 5: 0.5%
        ];
        
        $currentUserId = $investment['referrer_id'];
        $investmentAmount = $investment['amount'];
        $level = 1;
        
        while ($currentUserId && $level <= 5) {
            if (isset($commissionRates[$level])) {
                $commissionAmount = ($investmentAmount * $commissionRates[$level]) / 100;
                
                // Create commission record
                $this->createCommissionRecord(
                    $currentUserId,
                    $investment['user_id'],
                    $commissionAmount,
                    $level,
                    'recurring',
                    $investment['id']
                );
            }
            
            // Get next level referrer
            $nextStmt = $this->db->prepare("
                SELECT referrer_id FROM referrals WHERE referred_id = ?
            ");
            $nextStmt->execute([$currentUserId]);
            $nextReferrer = $nextStmt->fetch(PDO::FETCH_ASSOC);
            
            $currentUserId = $nextReferrer ? $nextReferrer['referrer_id'] : null;
            $level++;
        }
    }
    
    private function processMLMCommissions($userId, $amount) {
        // One-time commission on investment returns
        $commissionRates = [
            1 => 10.0,  // Level 1: 10%
            2 => 5.0,   // Level 2: 5%
            3 => 3.0,   // Level 3: 3%
            4 => 2.0,   // Level 4: 2%
            5 => 1.0    // Level 5: 1%
        ];
        
        // Get referrer chain
        $referrerStmt = $this->db->prepare("
            SELECT referrer_id FROM referrals WHERE referred_id = ?
        ");
        $referrerStmt->execute([$userId]);
        $referrer = $referrerStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$referrer) return;
        
        $currentUserId = $referrer['referrer_id'];
        $level = 1;
        
        while ($currentUserId && $level <= 5) {
            if (isset($commissionRates[$level])) {
                $commissionAmount = ($amount * $commissionRates[$level]) / 100;
                
                // Create commission record
                $this->createCommissionRecord(
                    $currentUserId,
                    $userId,
                    $commissionAmount,
                    $level,
                    'maturity'
                );
            }
            
            // Get next level referrer
            $nextStmt = $this->db->prepare("
                SELECT referrer_id FROM referrals WHERE referred_id = ?
            ");
            $nextStmt->execute([$currentUserId]);
            $nextReferrer = $nextStmt->fetch(PDO::FETCH_ASSOC);
            
            $currentUserId = $nextReferrer ? $nextReferrer['referrer_id'] : null;
            $level++;
        }
    }
    
    private function createCommissionRecord($userId, $referredUserId, $amount, $level, $type, $investmentId = null) {
        try {
            // Insert commission record
            $commissionStmt = $this->db->prepare("
                INSERT INTO commissions (user_id, referred_user_id, amount, level, type, investment_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $commissionStmt->execute([$userId, $referredUserId, $amount, $level, $type, $investmentId]);
            
            // Create earnings record
            $earningsStmt = $this->db->prepare("
                INSERT INTO earnings (user_id, type, amount, status, referred_user_id, created_at)
                VALUES (?, 'commission', ?, 'pending', ?, NOW())
            ");
            $earningsStmt->execute([$userId, $amount, $referredUserId]);
            
            echo "Created commission: User $userId, Level $level, Amount ₹$amount\n";
            
        } catch (Exception $e) {
            echo "Error creating commission: " . $e->getMessage() . "\n";
        }
    }
    
    public function processCommissionPayouts() {
        echo "Processing commission payouts...\n";
        
        // Get pending commissions that meet payout criteria
        $stmt = $this->db->prepare("
            SELECT c.*, u.first_name, u.last_name, u.email
            FROM commissions c
            JOIN users u ON c.user_id = u.id
            WHERE c.status = 'pending'
            AND c.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)  -- 24 hour delay
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();
        $pendingCommissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pendingCommissions as $commission) {
            $this->processCommissionPayout($commission);
        }
        
        echo "Processed " . count($pendingCommissions) . " commission payouts\n";
        return count($pendingCommissions);
    }
    
    private function processCommissionPayout($commission) {
        $this->db->beginTransaction();
        
        try {
            // Update commission status
            $updateCommissionStmt = $this->db->prepare("
                UPDATE commissions 
                SET status = 'paid', paid_at = NOW()
                WHERE id = ?
            ");
            $updateCommissionStmt->execute([$commission['id']]);
            
            // Update earnings status
            $updateEarningsStmt = $this->db->prepare("
                UPDATE earnings 
                SET status = 'paid', paid_at = NOW()
                WHERE user_id = ? AND referred_user_id = ? AND amount = ? AND status = 'pending'
                LIMIT 1
            ");
            $updateEarningsStmt->execute([
                $commission['user_id'],
                $commission['referred_user_id'],
                $commission['amount']
            ]);
            
            // Add to wallet
            $walletStmt = $this->db->prepare("
                INSERT INTO wallets (user_id, balance, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                balance = balance + ?,
                updated_at = NOW()
            ");
            $walletStmt->execute([
                $commission['user_id'],
                $commission['amount'],
                $commission['amount']
            ]);
            
            // Create transaction record
            $transactionStmt = $this->db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at)
                VALUES (?, 'commission', ?, 'completed', ?, NOW())
            ");
            $transactionStmt->execute([
                $commission['user_id'],
                $commission['amount'],
                "Level {$commission['level']} commission from referral activity"
            ]);
            
            // Send notification
            $this->autoTriggers->onCommissionEarned(
                $commission['user_id'],
                $commission['amount'],
                "Level {$commission['level']} referral"
            );
            
            $this->db->commit();
            
            echo "Paid commission: ₹{$commission['amount']} to {$commission['email']}\n";
            
        } catch (Exception $e) {
            $this->db->rollback();
            echo "Error processing commission payout: " . $e->getMessage() . "\n";
        }
    }
    
    public function generateInterestPayments() {
        echo "Generating interest payments...\n";
        
        // Get active investments for daily interest calculation
        $stmt = $this->db->prepare("
            SELECT i.*, ip.name as plan_name, ip.interest_rate, ip.duration_months,
                   u.first_name, u.last_name, u.email
            FROM investments i
            JOIN investment_plans ip ON i.plan_id = ip.id
            JOIN users u ON i.user_id = u.id
            WHERE i.status = 'active'
            AND DATE_ADD(i.created_at, INTERVAL ip.duration_months MONTH) > NOW()
        ");
        $stmt->execute();
        $activeInvestments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($activeInvestments as $investment) {
            $this->generateDailyInterest($investment);
        }
        
        echo "Generated interest for " . count($activeInvestments) . " investments\n";
        return count($activeInvestments);
    }
    
    private function generateDailyInterest($investment) {
        // Check if interest already paid today
        $checkStmt = $this->db->prepare("
            SELECT id FROM interest_payments 
            WHERE investment_id = ? AND DATE(payment_date) = CURDATE()
        ");
        $checkStmt->execute([$investment['id']]);
        
        if ($checkStmt->fetch()) {
            return; // Already paid today
        }
        
        // Calculate daily interest
        $principal = $investment['amount'];
        $annualRate = $investment['interest_rate'];
        $dailyRate = $annualRate / 365 / 100;
        $dailyInterest = $principal * $dailyRate;
        
        try {
            // Record interest payment
            $interestStmt = $this->db->prepare("
                INSERT INTO interest_payments (investment_id, user_id, amount, payment_date, created_at)
                VALUES (?, ?, ?, CURDATE(), NOW())
            ");
            $interestStmt->execute([
                $investment['id'],
                $investment['user_id'],
                $dailyInterest
            ]);
            
            // Add to earnings
            $earningsStmt = $this->db->prepare("
                INSERT INTO earnings (user_id, type, amount, status, investment_id, created_at)
                VALUES (?, 'interest', ?, 'paid', ?, NOW())
            ");
            $earningsStmt->execute([
                $investment['user_id'],
                $dailyInterest,
                $investment['id']
            ]);
            
            echo "Generated daily interest: ₹{$dailyInterest} for investment {$investment['id']}\n";
            
        } catch (Exception $e) {
            echo "Error generating interest: " . $e->getMessage() . "\n";
        }
    }
    
    public function runDailyTasks() {
        echo "=== Starting Daily Automated Tasks ===\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
        
        $results = [
            'matured_investments' => $this->processMaturedInvestments(),
            'commission_payouts' => $this->processCommissionPayouts(),
            'interest_payments' => $this->generateInterestPayments(),
            'recurring_commissions' => $this->processRecurringCommissions()
        ];
        
        echo "\n=== Daily Tasks Completed ===\n";
        echo "Matured Investments: {$results['matured_investments']}\n";
        echo "Commission Payouts: {$results['commission_payouts']}\n";
        echo "Interest Payments: {$results['interest_payments']}\n";
        echo "Recurring Commissions: {$results['recurring_commissions']}\n";
        
        // Log the results
        $this->logAutomationResults($results);
        
        return $results;
    }
    
    private function logAutomationResults($results) {
        $logStmt = $this->db->prepare("
            INSERT INTO automation_logs (
                task_type, 
                results, 
                executed_at
            ) VALUES ('daily_tasks', ?, NOW())
        ");
        $logStmt->execute([json_encode($results)]);
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $processor = new InvestmentProcessor();
    
    $task = $argv[1] ?? 'daily';
    
    switch ($task) {
        case 'matured':
            $processor->processMaturedInvestments();
            break;
        case 'commissions':
            $processor->processCommissionPayouts();
            break;
        case 'interest':
            $processor->generateInterestPayments();
            break;
        case 'recurring':
            $processor->processRecurringCommissions();
            break;
        case 'daily':
        default:
            $processor->runDailyTasks();
            break;
    }
}
?> 