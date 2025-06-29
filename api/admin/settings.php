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
    $type = $_GET['type'] ?? '';
    
    if ($method === 'GET') {
        // Get settings by type
        $stmt = $db->prepare("SELECT * FROM settings WHERE category = ?");
        $stmt->execute([$type]);
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settingsData = [];
        foreach ($settings as $setting) {
            $settingsData[$setting['setting_key']] = $setting['setting_value'];
        }
        
        echo json_encode([
            'success' => true,
            'settings' => $settingsData
        ]);
        
    } elseif ($method === 'POST') {
        // Save settings
        if (empty($type)) {
            throw new Exception('Settings type is required');
        }
        
        $settingsToSave = [];
        
        // Process different setting types
        switch ($type) {
            case 'general':
                $settingsToSave = [
                    'platform_name' => $_POST['platformName'] ?? '',
                    'admin_email' => $_POST['adminEmail'] ?? '',
                    'support_phone' => $_POST['supportPhone'] ?? '',
                    'currency' => $_POST['currency'] ?? 'INR',
                    'platform_description' => $_POST['platformDescription'] ?? ''
                ];
                break;
                
            case 'security':
                $settingsToSave = [
                    'session_timeout' => $_POST['sessionTimeout'] ?? 30,
                    'max_login_attempts' => $_POST['maxLoginAttempts'] ?? 5,
                    'two_factor_auth' => isset($_POST['twoFactorAuth']) ? '1' : '0',
                    'email_verification' => isset($_POST['emailVerification']) ? '1' : '0',
                    'strong_password' => isset($_POST['strongPassword']) ? '1' : '0',
                    'audit_logging' => isset($_POST['auditLogging']) ? '1' : '0',
                    'allowed_ips' => $_POST['allowedIPs'] ?? ''
                ];
                break;
                
            case 'mlm':
                $settingsToSave = [
                    'level1_commission' => $_POST['level1Commission'] ?? 3.00,
                    'level2_commission' => $_POST['level2Commission'] ?? 2.50,
                    'level3_commission' => $_POST['level3Commission'] ?? 2.00,
                    'level4_commission' => $_POST['level4Commission'] ?? 1.50,
                    'level5_commission' => $_POST['level5Commission'] ?? 1.25,
                    'level6_commission' => $_POST['level6Commission'] ?? 1.00,
                    'level7_commission' => $_POST['level7Commission'] ?? 0.75,
                    'level8_commission' => $_POST['level8Commission'] ?? 0.50,
                    'level9_commission' => $_POST['level9Commission'] ?? 0.40,
                    'level10_commission' => $_POST['level10Commission'] ?? 0.30,
                    'level11_commission' => $_POST['level11Commission'] ?? 0.25,
                    'min_withdrawal' => $_POST['minWithdrawal'] ?? 100,
                    'payout_schedule' => $_POST['payoutSchedule'] ?? 'monthly'
                ];
                break;
                
            case 'notifications':
                $settingsToSave = [
                    'email_new_user' => isset($_POST['emailNewUser']) ? '1' : '0',
                    'email_new_investment' => isset($_POST['emailNewInvestment']) ? '1' : '0',
                    'email_withdrawal' => isset($_POST['emailWithdrawal']) ? '1' : '0',
                    'email_kyc' => isset($_POST['emailKYC']) ? '1' : '0',
                    'sms_login' => isset($_POST['smsLogin']) ? '1' : '0',
                    'sms_transaction' => isset($_POST['smsTransaction']) ? '1' : '0',
                    'smtp_server' => $_POST['smtpServer'] ?? '',
                    'smtp_port' => $_POST['smtpPort'] ?? 587
                ];
                break;
                
            default:
                throw new Exception('Invalid settings type');
        }
        
        // Save settings to database
        $db->beginTransaction();
        
        foreach ($settingsToSave as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO settings (category, setting_key, setting_value, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$type, $key, $value]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 