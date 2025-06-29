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
        // Get all KYC documents
        $status = $_GET['status'] ?? '';
        
        $sql = "
            SELECT k.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as user_name,
                   u.email as user_email,
                   u.phone as user_phone
            FROM kyc_documents k 
            JOIN users u ON k.user_id = u.id 
        ";
        
        $params = [];
        if ($status) {
            $sql .= " WHERE k.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY k.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $kycDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'kyc_documents' => $kycDocuments
        ]);
        
    } elseif ($method === 'POST') {
        // Handle KYC approval/rejection
        $action = $_POST['action'] ?? '';
        $kycId = $_POST['kyc_id'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        
        if (!$action || !$kycId) {
            throw new Exception('Action and KYC ID are required');
        }
        
        if ($action === 'approve') {
            $stmt = $db->prepare("
                UPDATE kyc_documents 
                SET status = 'approved', admin_remarks = ?, reviewed_at = NOW() 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$remarks, $kycId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'KYC document approved successfully'
                ]);
            } else {
                throw new Exception('KYC document not found or already processed');
            }
            
        } elseif ($action === 'reject') {
            if (empty($remarks)) {
                throw new Exception('Rejection reason is required');
            }
            
            $stmt = $db->prepare("
                UPDATE kyc_documents 
                SET status = 'rejected', admin_remarks = ?, reviewed_at = NOW() 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$remarks, $kycId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'KYC document rejected successfully'
                ]);
            } else {
                throw new Exception('KYC document not found or already processed');
            }
        } else {
            throw new Exception('Invalid action');
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 