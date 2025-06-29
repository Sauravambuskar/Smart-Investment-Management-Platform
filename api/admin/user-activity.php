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
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'activity';
        
        if ($action === 'activity') {
            // Get user activity logs
            $userId = $_GET['user_id'] ?? '';
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;
            
            $sql = "
                SELECT al.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as user_name,
                       u.email as user_email
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($userId) {
                $sql .= " AND al.user_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM audit_logs al WHERE 1=1";
            $countParams = [];
            
            if ($userId) {
                $countSql .= " AND al.user_id = ?";
                $countParams[] = $userId;
            }
            
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'activities' => $activities,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } elseif ($action === 'online_users') {
            // Get currently online users (active in last 15 minutes)
            $stmt = $db->prepare("
                SELECT u.id, 
                       CONCAT(u.first_name, ' ', u.last_name) as name,
                       u.email,
                       u.role,
                       MAX(al.created_at) as last_activity
                FROM users u
                JOIN audit_logs al ON u.id = al.user_id
                WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                GROUP BY u.id
                ORDER BY last_activity DESC
            ");
            $stmt->execute();
            $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'online_users' => $onlineUsers
            ]);
            
        } elseif ($action === 'login_history') {
            // Get login history
            $userId = $_GET['user_id'] ?? '';
            $limit = $_GET['limit'] ?? 20;
            
            $sql = "
                SELECT al.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as user_name,
                       u.email
                FROM audit_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.action IN ('login', 'logout', 'login_failed')
            ";
            
            $params = [];
            
            if ($userId) {
                $sql .= " AND al.user_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT ?";
            $params[] = (int)$limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'login_history' => $loginHistory
            ]);
            
        } elseif ($action === 'stats') {
            // Get activity statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_activities,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_activities,
                    COUNT(CASE WHEN action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_logins,
                    COUNT(CASE WHEN action = 'login_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_failed_logins,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN user_id END) as online_users
                FROM audit_logs
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        }
        
    } elseif ($method === 'POST') {
        // Log admin action
        $action = $_POST['action'] ?? '';
        $tableName = $_POST['table_name'] ?? '';
        $recordId = $_POST['record_id'] ?? '';
        $oldValues = $_POST['old_values'] ?? '';
        $newValues = $_POST['new_values'] ?? '';
        
        // Get admin user ID from session/token
        $adminId = 1; // This should come from authentication
        
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->execute([
            $adminId,
            $action,
            $tableName,
            $recordId,
            $oldValues,
            $newValues,
            $ipAddress,
            $userAgent
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Activity logged successfully'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 