<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get all investments
        $stmt = $db->prepare("
            SELECT i.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as user_name,
                   u.email as user_email,
                   p.name as plan_name,
                   p.interest_rate,
                   p.duration_months,
                   (i.amount * (1 + p.interest_rate / 100)) as expected_return
            FROM investments i 
            JOIN users u ON i.user_id = u.id 
            JOIN investment_plans p ON i.plan_id = p.id 
            ORDER BY i.created_at DESC
        ");
        $stmt->execute();
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'investments' => $investments
        ]);
        
    } elseif ($method === 'POST') {
        // Handle investment approval or other actions
        $action = $_POST['action'] ?? '';
        $investmentId = $_POST['investment_id'] ?? '';
        
        if ($action === 'approve' && $investmentId) {
            // Approve investment
            $stmt = $db->prepare("UPDATE investments SET status = 'active', start_date = NOW() WHERE id = ? AND status = 'pending'");
            $stmt->execute([$investmentId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Investment approved successfully'
                ]);
            } else {
                throw new Exception('Investment not found or already processed');
            }
        } else {
            throw new Exception('Invalid action or missing parameters');
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 