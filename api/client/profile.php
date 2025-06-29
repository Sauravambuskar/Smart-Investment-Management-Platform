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
    $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? 1;
    
    if ($method === 'GET') {
        // Get user profile information
        $stmt = $db->prepare("
            SELECT u.*, c.phone, c.address, c.date_of_birth, c.gender, c.occupation,
                   c.emergency_contact_name, c.emergency_contact_phone,
                   w.balance as wallet_balance
            FROM users u 
            LEFT JOIN clients c ON u.id = c.user_id
            LEFT JOIN wallets w ON u.id = w.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            echo json_encode(['success' => false, 'message' => 'Profile not found']);
            exit;
        }
        
        // Get KYC status
        $kycStmt = $db->prepare("
            SELECT status, document_type, verified_at, admin_remarks
            FROM kyc_documents 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $kycStmt->execute([$userId]);
        $kycStatus = $kycStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get referral information
        $referralStmt = $db->prepare("
            SELECT referral_code, 
                   (SELECT COUNT(*) FROM referrals WHERE referrer_id = ?) as total_referrals,
                   (SELECT SUM(amount) FROM earnings WHERE user_id = ? AND type = 'referral') as referral_earnings
            FROM users WHERE id = ?
        ");
        $referralStmt->execute([$userId, $userId, $userId]);
        $referralInfo = $referralStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'profile' => $profile,
            'kyc_status' => $kycStatus,
            'referral_info' => $referralInfo
        ]);
        
    } elseif ($method === 'POST' || $method === 'PUT') {
        $action = $_POST['action'] ?? 'update';
        
        switch ($action) {
            case 'update':
                $firstName = $_POST['first_name'];
                $lastName = $_POST['last_name'];
                $phone = $_POST['phone'];
                $address = $_POST['address'];
                $dateOfBirth = $_POST['date_of_birth'];
                $gender = $_POST['gender'];
                $occupation = $_POST['occupation'];
                $emergencyContactName = $_POST['emergency_contact_name'];
                $emergencyContactPhone = $_POST['emergency_contact_phone'];
                
                $db->beginTransaction();
                
                try {
                    // Update users table
                    $userStmt = $db->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $userStmt->execute([$firstName, $lastName, $userId]);
                    
                    // Update or insert client details
                    $clientCheckStmt = $db->prepare("SELECT id FROM clients WHERE user_id = ?");
                    $clientCheckStmt->execute([$userId]);
                    $clientExists = $clientCheckStmt->fetch();
                    
                    if ($clientExists) {
                        $clientStmt = $db->prepare("
                            UPDATE clients 
                            SET phone = ?, address = ?, date_of_birth = ?, gender = ?, 
                                occupation = ?, emergency_contact_name = ?, emergency_contact_phone = ?,
                                updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $clientStmt->execute([
                            $phone, $address, $dateOfBirth, $gender, $occupation,
                            $emergencyContactName, $emergencyContactPhone, $userId
                        ]);
                    } else {
                        $clientStmt = $db->prepare("
                            INSERT INTO clients (user_id, phone, address, date_of_birth, gender, 
                                               occupation, emergency_contact_name, emergency_contact_phone, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $clientStmt->execute([
                            $userId, $phone, $address, $dateOfBirth, $gender, $occupation,
                            $emergencyContactName, $emergencyContactPhone
                        ]);
                    }
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Profile updated successfully'
                    ]);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                
                // Verify current password
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($currentPassword, $user['password'])) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    exit;
                }
                
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$hashedPassword, $userId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Password changed successfully'
                ]);
                break;
                
            case 'upload_kyc':
                $documentType = $_POST['document_type'];
                $documentNumber = $_POST['document_number'];
                
                // Handle file upload (simplified for demo)
                $documentUrl = '';
                if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../uploads/kyc/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = $userId . '_' . $documentType . '_' . time() . '.' . pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                    $uploadPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadPath)) {
                        $documentUrl = 'uploads/kyc/' . $fileName;
                    }
                }
                
                // Insert KYC document
                $kycStmt = $db->prepare("
                    INSERT INTO kyc_documents (user_id, document_type, document_number, document_url, status, created_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $kycStmt->execute([$userId, $documentType, $documentNumber, $documentUrl]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'KYC document uploaded successfully'
                ]);
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