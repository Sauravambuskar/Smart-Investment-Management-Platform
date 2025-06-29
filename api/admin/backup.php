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
    $action = $_GET['action'] ?? '';
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'create') {
            // Create database backup
            $backupDir = '../../backups/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backupDir . $filename;
            
            // Get database connection details
            $config = json_decode(file_get_contents('../../config/database.json'), true);
            
            $host = $config['host'];
            $dbname = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Create mysqldump command
            $command = "mysqldump --host={$host} --user={$username} --password={$password} {$dbname} > {$filepath}";
            
            // Execute backup
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($filepath)) {
                // Log backup creation
                $stmt = $db->prepare("
                    INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                    VALUES (?, 'backup_created', ?, ?, NOW())
                ");
                $stmt->execute([1, "Database backup created: {$filename}", $_SERVER['REMOTE_ADDR'] ?? '']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'filename' => $filename,
                    'size' => filesize($filepath)
                ]);
            } else {
                throw new Exception('Failed to create backup');
            }
        } else {
            throw new Exception('Invalid action');
        }
        
    } elseif ($method === 'GET' && $action === 'download') {
        // Download latest backup
        $backupDir = '../../backups/';
        
        if (!is_dir($backupDir)) {
            throw new Exception('No backups found');
        }
        
        $files = glob($backupDir . 'backup_*.sql');
        if (empty($files)) {
            throw new Exception('No backup files found');
        }
        
        // Get the latest backup file
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $latestBackup = $files[0];
        $filename = basename($latestBackup);
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($latestBackup));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($latestBackup);
        exit;
        
    } elseif ($method === 'GET') {
        // Get backup list and settings
        $backupDir = '../../backups/';
        $backups = [];
        
        if (is_dir($backupDir)) {
            $files = glob($backupDir . 'backup_*.sql');
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'created' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
            
            // Sort by creation time (newest first)
            usort($backups, function($a, $b) {
                return strtotime($b['created']) - strtotime($a['created']);
            });
        }
        
        echo json_encode([
            'success' => true,
            'backups' => $backups,
            'backup_count' => count($backups)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 