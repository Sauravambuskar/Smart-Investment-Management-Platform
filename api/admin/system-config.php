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
        $action = $_GET['action'] ?? 'settings';
        
        if ($action === 'settings') {
            // Get all system settings grouped by category
            $stmt = $db->prepare("
                SELECT * FROM system_settings 
                ORDER BY category, setting_key
            ");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by category
            $grouped = [];
            foreach ($settings as $setting) {
                $grouped[$setting['category']][] = $setting;
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $grouped
            ]);
            
        } elseif ($action === 'system_info') {
            // Get system information
            $info = [
                'php_version' => phpversion(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'database_version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'timezone' => date_default_timezone_get(),
                'current_time' => date('Y-m-d H:i:s'),
                'disk_free_space' => disk_free_space('.'),
                'disk_total_space' => disk_total_space('.')
            ];
            
            echo json_encode([
                'success' => true,
                'system_info' => $info
            ]);
            
        } elseif ($action === 'maintenance') {
            // Get maintenance mode status
            $stmt = $db->prepare("
                SELECT setting_value FROM system_settings 
                WHERE setting_key = 'maintenance_mode'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'maintenance_mode' => $result['setting_value'] ?? 'off'
            ]);
            
        } elseif ($action === 'logs') {
            // Get system logs
            $logType = $_GET['type'] ?? 'error';
            $limit = $_GET['limit'] ?? 100;
            
            $stmt = $db->prepare("
                SELECT * FROM system_logs 
                WHERE log_type = ?
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$logType, (int)$limit]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
        }
        
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_setting') {
            // Update a system setting
            $settingKey = $_POST['setting_key'] ?? '';
            $settingValue = $_POST['setting_value'] ?? '';
            
            $stmt = $db->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ?
            ");
            $stmt->execute([$settingValue, $settingKey]);
            
            // Log the change
            $logStmt = $db->prepare("
                INSERT INTO system_logs (log_type, message, user_id)
                VALUES ('admin', ?, ?)
            ");
            $logStmt->execute([
                "Setting updated: {$settingKey} = {$settingValue}",
                1 // Admin user ID
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Setting updated successfully'
            ]);
            
        } elseif ($action === 'maintenance_toggle') {
            // Toggle maintenance mode
            $mode = $_POST['mode'] ?? 'off';
            
            $stmt = $db->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = 'maintenance_mode'
            ");
            $stmt->execute([$mode]);
            
            // Log the change
            $logStmt = $db->prepare("
                INSERT INTO system_logs (log_type, message, user_id)
                VALUES ('admin', ?, ?)
            ");
            $logStmt->execute([
                "Maintenance mode {$mode}",
                1
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => "Maintenance mode {$mode}"
            ]);
            
        } elseif ($action === 'clear_logs') {
            // Clear system logs
            $logType = $_POST['log_type'] ?? '';
            
            if ($logType) {
                $stmt = $db->prepare("DELETE FROM system_logs WHERE log_type = ?");
                $stmt->execute([$logType]);
            } else {
                $stmt = $db->prepare("DELETE FROM system_logs");
                $stmt->execute();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Logs cleared successfully'
            ]);
            
        } elseif ($action === 'backup_database') {
            // Create database backup
            $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = '../backups/' . $backupName;
            
            // This is a simplified backup - in production, use mysqldump
            $tables = ['users', 'investments', 'transactions', 'kyc_documents', 'system_settings', 'audit_logs'];
            $backup = "-- Database Backup Created: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $stmt = $db->prepare("SHOW CREATE TABLE {$table}");
                $stmt->execute();
                $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $backup .= "-- Table: {$table}\n";
                $backup .= $createTable['Create Table'] . ";\n\n";
                
                $stmt = $db->prepare("SELECT * FROM {$table}");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($db) {
                        return $db->quote($value);
                    }, array_values($row));
                    
                    $backup .= "INSERT INTO {$table} VALUES (" . implode(', ', $values) . ");\n";
                }
                $backup .= "\n";
            }
            
            file_put_contents($backupPath, $backup);
            
            // Log the backup
            $logStmt = $db->prepare("
                INSERT INTO system_logs (log_type, message, user_id)
                VALUES ('backup', ?, ?)
            ");
            $logStmt->execute([
                "Database backup created: {$backupName}",
                1
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Database backup created successfully',
                'backup_file' => $backupName
            ]);
            
        } elseif ($action === 'optimize_database') {
            // Optimize database tables
            $tables = ['users', 'investments', 'transactions', 'kyc_documents', 'system_settings', 'audit_logs'];
            $optimized = [];
            
            foreach ($tables as $table) {
                $stmt = $db->prepare("OPTIMIZE TABLE {$table}");
                $stmt->execute();
                $optimized[] = $table;
            }
            
            // Log the optimization
            $logStmt = $db->prepare("
                INSERT INTO system_logs (log_type, message, user_id)
                VALUES ('maintenance', ?, ?)
            ");
            $logStmt->execute([
                "Database optimized: " . implode(', ', $optimized),
                1
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Database optimized successfully',
                'optimized_tables' => $optimized
            ]);
        }
        
    } elseif ($method === 'PUT') {
        // Bulk update settings
        $input = json_decode(file_get_contents('php://input'), true);
        $settings = $input['settings'] ?? [];
        
        $db->beginTransaction();
        
        try {
            $stmt = $db->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ?
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            
            $db->commit();
            
            // Log the bulk update
            $logStmt = $db->prepare("
                INSERT INTO system_logs (log_type, message, user_id)
                VALUES ('admin', ?, ?)
            ");
            $logStmt->execute([
                "Bulk settings update: " . count($settings) . " settings updated",
                1
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully'
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
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