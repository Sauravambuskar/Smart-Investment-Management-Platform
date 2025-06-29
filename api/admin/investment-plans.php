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
        // Get all investment plans
        $stmt = $db->prepare("SELECT * FROM investment_plans ORDER BY created_at DESC");
        $stmt->execute();
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'plans' => $plans
        ]);
        
    } elseif ($method === 'POST') {
        // Create new investment plan
        $planName = $_POST['planName'] ?? '';
        $description = $_POST['description'] ?? '';
        $minAmount = $_POST['minAmount'] ?? 0;
        $maxAmount = $_POST['maxAmount'] ?? 0;
        $interestRate = $_POST['interestRate'] ?? 0;
        $duration = $_POST['duration'] ?? 0;
        $features = $_POST['features'] ?? '';
        
        // Validate input
        if (empty($planName) || empty($minAmount) || empty($maxAmount) || empty($interestRate) || empty($duration)) {
            throw new Exception('All required fields must be filled');
        }
        
        if ($minAmount >= $maxAmount) {
            throw new Exception('Maximum amount must be greater than minimum amount');
        }
        
        if ($interestRate <= 0 || $duration <= 0) {
            throw new Exception('Interest rate and duration must be positive numbers');
        }
        
        // Convert features to JSON array
        $featuresArray = array_filter(array_map('trim', explode("\n", $features)));
        $featuresJson = json_encode($featuresArray);
        
        // Insert investment plan
        $stmt = $db->prepare("
            INSERT INTO investment_plans (name, description, min_amount, max_amount, interest_rate, duration_months, features, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$planName, $description, $minAmount, $maxAmount, $interestRate, $duration, $featuresJson]);
        
        $planId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Investment plan created successfully',
            'plan_id' => $planId
        ]);
        
    } elseif ($method === 'PUT') {
        // Update investment plan (toggle active status, etc.)
        parse_str(file_get_contents("php://input"), $data);
        
        $action = $data['action'] ?? '';
        $planId = $data['plan_id'] ?? '';
        
        if ($action === 'toggle_status' && $planId) {
            // Toggle plan status
            $stmt = $db->prepare("UPDATE investment_plans SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$planId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Plan status updated successfully'
                ]);
            } else {
                throw new Exception('Plan not found');
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