<?php
/**
 * Test Database Connection API
 * SJA Foundation Investment Management Platform
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required = ['host', 'port', 'name', 'user'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize input
    $config = [
        'host' => trim($input['host']),
        'port' => (int)$input['port'],
        'name' => trim($input['name']),
        'user' => trim($input['user']),
        'password' => $input['password'] ?? ''
    ];
    
    // Test connection
    $database = new Database();
    $result = $database->testConnection($config);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 