<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'notifications';
        
        if ($action === 'notifications') {
            // Get all notifications
            $type = $_GET['type'] ?? '';
            $status = $_GET['status'] ?? '';
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;
            
            $sql = "
                SELECT n.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as user_name,
                       u.email as user_email
                FROM notifications n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($type) {
                $sql .= " AND n.type = ?";
                $params[] = $type;
            }
            
            if ($status) {
                $sql .= " AND n.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM notifications n WHERE 1=1";
            $countParams = [];
            
            if ($type) {
                $countSql .= " AND n.type = ?";
                $countParams[] = $type;
            }
            
            if ($status) {
                $countSql .= " AND n.status = ?";
                $countParams[] = $status;
            }
            
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            
        } elseif ($action === 'templates') {
            // Get notification templates
            $stmt = $db->prepare("
                SELECT * FROM notification_templates 
                ORDER BY type, name
            ");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            
        } elseif ($action === 'stats') {
            // Get notification statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_notifications,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    COUNT(CASE WHEN type = 'email' THEN 1 END) as email_notifications,
                    COUNT(CASE WHEN type = 'sms' THEN 1 END) as sms_notifications,
                    COUNT(CASE WHEN type = 'push' THEN 1 END) as push_notifications,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_notifications
                FROM notifications
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            
        } elseif ($action === 'broadcast_history') {
            // Get broadcast history
            $stmt = $db->prepare("
                SELECT * FROM broadcast_messages 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute();
            $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'broadcasts' => $broadcasts
            ]);
        }
        
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_notification') {
            // Send single notification
            $userId = $_POST['user_id'] ?? '';
            $type = $_POST['type'] ?? 'email';
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $priority = $_POST['priority'] ?? 'normal';
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, priority, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$userId, $type, $title, $message, $priority]);
            
            $notificationId = $db->lastInsertId();
            
            // Simulate sending (in production, integrate with email/SMS services)
            $success = true; // This would be the result of actual sending
            
            if ($success) {
                $updateStmt = $db->prepare("
                    UPDATE notifications 
                    SET status = 'sent', sent_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$notificationId]);
            } else {
                $updateStmt = $db->prepare("
                    UPDATE notifications 
                    SET status = 'failed', error_message = 'Failed to send'
                    WHERE id = ?
                ");
                $updateStmt->execute([$notificationId]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification sent successfully',
                'notification_id' => $notificationId
            ]);
            
        } elseif ($action === 'broadcast') {
            // Send broadcast message
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'email';
            $targetRole = $_POST['target_role'] ?? 'all';
            $priority = $_POST['priority'] ?? 'normal';
            
            // Create broadcast record
            $stmt = $db->prepare("
                INSERT INTO broadcast_messages (title, message, type, target_role, priority, status)
                VALUES (?, ?, ?, ?, ?, 'processing')
            ");
            $stmt->execute([$title, $message, $type, $targetRole, $priority]);
            
            $broadcastId = $db->lastInsertId();
            
            // Get target users
            $userSql = "SELECT id FROM users WHERE status = 'active'";
            $userParams = [];
            
            if ($targetRole !== 'all') {
                $userSql .= " AND role = ?";
                $userParams[] = $targetRole;
            }
            
            $userStmt = $db->prepare($userSql);
            $userStmt->execute($userParams);
            $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create notifications for each user
            $notificationStmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, priority, broadcast_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $totalUsers = count($users);
            $sentCount = 0;
            
            foreach ($users as $user) {
                $notificationStmt->execute([
                    $user['id'], $type, $title, $message, $priority, $broadcastId
                ]);
                $sentCount++;
            }
            
            // Update broadcast status
            $updateStmt = $db->prepare("
                UPDATE broadcast_messages 
                SET status = 'completed', total_recipients = ?, sent_count = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$totalUsers, $sentCount, $broadcastId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Broadcast sent successfully',
                'broadcast_id' => $broadcastId,
                'total_recipients' => $totalUsers,
                'sent_count' => $sentCount
            ]);
            
        } elseif ($action === 'create_template') {
            // Create notification template
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? 'email';
            $subject = $_POST['subject'] ?? '';
            $content = $_POST['content'] ?? '';
            $variables = $_POST['variables'] ?? '';
            
            $stmt = $db->prepare("
                INSERT INTO notification_templates (name, type, subject, content, variables)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $type, $subject, $content, $variables]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template created successfully',
                'template_id' => $db->lastInsertId()
            ]);
            
        } elseif ($action === 'retry_failed') {
            // Retry failed notifications
            $stmt = $db->prepare("
                UPDATE notifications 
                SET status = 'pending', error_message = NULL, retry_count = retry_count + 1
                WHERE status = 'failed' AND retry_count < 3
            ");
            $stmt->execute();
            
            $retryCount = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Retrying {$retryCount} failed notifications"
            ]);
        }
        
    } elseif ($method === 'PUT') {
        // Update notification or template
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'update_template') {
            $templateId = $input['template_id'] ?? '';
            $name = $input['name'] ?? '';
            $subject = $input['subject'] ?? '';
            $content = $input['content'] ?? '';
            $variables = $input['variables'] ?? '';
            
            $stmt = $db->prepare("
                UPDATE notification_templates 
                SET name = ?, subject = ?, content = ?, variables = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $subject, $content, $variables, $templateId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template updated successfully'
            ]);
            
        } elseif ($action === 'mark_read') {
            $notificationIds = $input['notification_ids'] ?? [];
            
            if (!empty($notificationIds)) {
                $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
                $stmt = $db->prepare("
                    UPDATE notifications 
                    SET status = 'read', read_at = NOW()
                    WHERE id IN ({$placeholders})
                ");
                $stmt->execute($notificationIds);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Notifications marked as read'
            ]);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete notifications or templates
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'delete_notifications') {
            $notificationIds = $input['notification_ids'] ?? [];
            
            if (!empty($notificationIds)) {
                $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
                $stmt = $db->prepare("DELETE FROM notifications WHERE id IN ({$placeholders})");
                $stmt->execute($notificationIds);
                
                $deletedCount = $stmt->rowCount();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Deleted {$deletedCount} notifications"
                ]);
            }
            
        } elseif ($action === 'delete_template') {
            $templateId = $input['template_id'] ?? '';
            
            $stmt = $db->prepare("DELETE FROM notification_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 