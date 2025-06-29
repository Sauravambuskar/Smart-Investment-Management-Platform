<?php
require_once __DIR__ . '/../config/database.php';

class NotificationServer {
    private $db;
    private $clients = [];
    private $userConnections = [];
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function start($host = '127.0.0.1', $port = 8080) {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($socket, $host, $port);
        socket_listen($socket);
        
        echo "WebSocket server started on {$host}:{$port}\n";
        
        while (true) {
            $read = array_merge([$socket], $this->clients);
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0, 10) < 1) {
                continue;
            }
            
            if (in_array($socket, $read)) {
                $client = socket_accept($socket);
                $this->clients[] = $client;
                $this->performHandshake($client);
                echo "New client connected\n";
            }
            
            foreach ($this->clients as $key => $client) {
                if (in_array($client, $read)) {
                    $data = socket_read($client, 1024);
                    
                    if ($data === false) {
                        $this->disconnectClient($key);
                        continue;
                    }
                    
                    $decodedData = $this->decode($data);
                    if ($decodedData) {
                        $this->handleMessage($client, $decodedData);
                    }
                }
            }
            
            // Check for new notifications to broadcast
            $this->checkAndBroadcastNotifications();
            
            usleep(10000); // 10ms delay
        }
    }
    
    private function performHandshake($client) {
        $request = socket_read($client, 5000);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $headers = "HTTP/1.1 101 Switching Protocols\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
        
        socket_write($client, $headers, strlen($headers));
    }
    
    private function decode($data) {
        $length = ord($data[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($data, 4, 4);
            $data = substr($data, 8);
        } elseif ($length == 127) {
            $masks = substr($data, 10, 4);
            $data = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $data = substr($data, 6);
        }
        
        $decoded = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $decoded .= $data[$i] ^ $masks[$i % 4];
        }
        
        return json_decode($decoded, true);
    }
    
    private function encode($message) {
        $data = json_encode($message);
        $length = strlen($data);
        
        if ($length < 126) {
            return chr(129) . chr($length) . $data;
        } elseif ($length < 65536) {
            return chr(129) . chr(126) . pack("n", $length) . $data;
        } else {
            return chr(129) . chr(127) . pack("N", 0) . pack("N", $length) . $data;
        }
    }
    
    private function handleMessage($client, $data) {
        if (isset($data['type'])) {
            switch ($data['type']) {
                case 'auth':
                    $this->authenticateClient($client, $data);
                    break;
                case 'ping':
                    $this->sendToClient($client, ['type' => 'pong']);
                    break;
            }
        }
    }
    
    private function authenticateClient($client, $data) {
        if (isset($data['user_id'])) {
            $this->userConnections[$data['user_id']] = $client;
            $this->sendToClient($client, [
                'type' => 'auth_success',
                'message' => 'Authenticated successfully'
            ]);
        }
    }
    
    private function sendToClient($client, $message) {
        $encoded = $this->encode($message);
        socket_write($client, $encoded, strlen($encoded));
    }
    
    private function sendToUser($userId, $message) {
        if (isset($this->userConnections[$userId])) {
            $this->sendToClient($this->userConnections[$userId], $message);
        }
    }
    
    private function broadcast($message) {
        foreach ($this->clients as $client) {
            $this->sendToClient($client, $message);
        }
    }
    
    private function checkAndBroadcastNotifications() {
        // Check for unbroadcast notifications
        $stmt = $this->db->prepare("
            SELECT n.*, u.role 
            FROM notifications n
            JOIN users u ON n.user_id = u.id
            WHERE n.broadcast_sent = 0 AND n.created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($notifications as $notification) {
            $message = [
                'type' => 'notification',
                'id' => $notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'priority' => $notification['priority'],
                'created_at' => $notification['created_at']
            ];
            
            if ($notification['broadcast_id']) {
                // Broadcast to all users
                $this->broadcast($message);
            } else {
                // Send to specific user
                $this->sendToUser($notification['user_id'], $message);
            }
            
            // Mark as broadcast
            $updateStmt = $this->db->prepare("
                UPDATE notifications 
                SET broadcast_sent = 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$notification['id']]);
        }
    }
    
    private function disconnectClient($key) {
        // Remove from user connections
        foreach ($this->userConnections as $userId => $client) {
            if ($client === $this->clients[$key]) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
        
        socket_close($this->clients[$key]);
        unset($this->clients[$key]);
        echo "Client disconnected\n";
    }
}

// Real-time notification manager
class NotificationManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function sendNotification($userId, $title, $message, $type = 'info', $priority = 'medium') {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, title, message, type, priority, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $title, $message, $type, $priority]);
        
        return $this->db->lastInsertId();
    }
    
    public function sendBroadcast($title, $message, $type = 'info', $priority = 'medium', $targetRole = null) {
        // Create broadcast record
        $broadcastStmt = $this->db->prepare("
            INSERT INTO broadcast_messages (title, message, type, priority, target_role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $broadcastStmt->execute([$title, $message, $type, $priority, $targetRole]);
        $broadcastId = $this->db->lastInsertId();
        
        // Get target users
        $userQuery = "SELECT id FROM users WHERE status = 'active'";
        if ($targetRole) {
            $userQuery .= " AND role = ?";
            $userStmt = $this->db->prepare($userQuery);
            $userStmt->execute([$targetRole]);
        } else {
            $userStmt = $this->db->prepare($userQuery);
            $userStmt->execute();
        }
        
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create individual notifications
        foreach ($users as $user) {
            $notificationStmt = $this->db->prepare("
                INSERT INTO notifications (user_id, title, message, type, priority, broadcast_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $notificationStmt->execute([$user['id'], $title, $message, $type, $priority, $broadcastId]);
        }
        
        return $broadcastId;
    }
    
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET status = 'read', read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        
        return $stmt->rowCount() > 0;
    }
    
    public function getUnreadCount($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND status = 'unread'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'];
    }
}

// Auto-notification triggers
class AutoNotificationTriggers {
    private $notificationManager;
    private $db;
    
    public function __construct() {
        $this->notificationManager = new NotificationManager();
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function onInvestmentCreated($userId, $amount, $planName) {
        $this->notificationManager->sendNotification(
            $userId,
            'Investment Created',
            "Your investment of ₹{$amount} in {$planName} has been created successfully.",
            'success',
            'high'
        );
    }
    
    public function onTransactionApproved($userId, $type, $amount) {
        $this->notificationManager->sendNotification(
            $userId,
            'Transaction Approved',
            "Your {$type} of ₹{$amount} has been approved and processed.",
            'success',
            'high'
        );
    }
    
    public function onKYCStatusUpdate($userId, $status, $remarks = '') {
        $message = "Your KYC verification status has been updated to: {$status}";
        if ($remarks) {
            $message .= ". Remarks: {$remarks}";
        }
        
        $this->notificationManager->sendNotification(
            $userId,
            'KYC Status Update',
            $message,
            $status === 'approved' ? 'success' : 'warning',
            'high'
        );
    }
    
    public function onCommissionEarned($userId, $amount, $referredUserName) {
        $this->notificationManager->sendNotification(
            $userId,
            'Commission Earned',
            "You've earned ₹{$amount} commission from {$referredUserName}'s activity.",
            'success',
            'medium'
        );
    }
    
    public function onSystemMaintenance($message, $scheduledTime) {
        $this->notificationManager->sendBroadcast(
            'System Maintenance',
            "Scheduled maintenance: {$message}. Time: {$scheduledTime}",
            'warning',
            'high'
        );
    }
}

// Start WebSocket server if run directly
if (php_sapi_name() === 'cli') {
    $server = new NotificationServer();
    $server->start();
}
?> 