-- Advanced Schema Updates for SJA Foundation Investment Platform
-- Run this after the main schema.sql

-- User Sessions Table for JWT Authentication
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_at DATETIME NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_token (token(255)),
    INDEX idx_user_sessions_expires (expires_at)
);

-- Interest Payments Table for Daily Interest Tracking
CREATE TABLE IF NOT EXISTS interest_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    investment_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily_interest (investment_id, payment_date),
    INDEX idx_interest_user_date (user_id, payment_date),
    INDEX idx_interest_investment (investment_id)
);

-- Automation Logs Table
CREATE TABLE IF NOT EXISTS automation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_type VARCHAR(50) NOT NULL,
    results JSON,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time DECIMAL(8,3),
    status ENUM('success', 'failed', 'partial') DEFAULT 'success',
    error_message TEXT NULL,
    INDEX idx_automation_task_type (task_type),
    INDEX idx_automation_executed_at (executed_at)
);

-- Advanced Analytics Cache Table
CREATE TABLE IF NOT EXISTS analytics_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(255) NOT NULL UNIQUE,
    data JSON NOT NULL,
    period VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_analytics_cache_key (cache_key),
    INDEX idx_analytics_expires (expires_at)
);

-- Risk Management Table
CREATE TABLE IF NOT EXISTS risk_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    alert_type ENUM('high_value_transaction', 'suspicious_activity', 'multiple_failures', 'unusual_pattern') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    description TEXT NOT NULL,
    data JSON,
    status ENUM('open', 'investigating', 'resolved', 'false_positive') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_risk_alerts_user (user_id),
    INDEX idx_risk_alerts_type (alert_type),
    INDEX idx_risk_alerts_severity (severity),
    INDEX idx_risk_alerts_status (status)
);

-- API Rate Limiting Table
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    requests_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_blocked BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_ip_endpoint (ip_address, endpoint),
    INDEX idx_rate_limits_ip (ip_address),
    INDEX idx_rate_limits_window (window_start)
);

-- Investment Performance Tracking
CREATE TABLE IF NOT EXISTS investment_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    investment_id INT NOT NULL,
    date DATE NOT NULL,
    current_value DECIMAL(15,2) NOT NULL,
    daily_return DECIMAL(10,4) NOT NULL,
    cumulative_return DECIMAL(10,4) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_investment_date (investment_id, date),
    INDEX idx_performance_investment (investment_id),
    INDEX idx_performance_date (date)
);

-- User Activity Tracking (Enhanced)
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_data JSON,
    response_status INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_action (action),
    INDEX idx_activity_created_at (created_at),
    INDEX idx_activity_ip (ip_address)
);

-- Backup and Recovery Logs
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    backup_type ENUM('full', 'incremental', 'differential') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    compression_type VARCHAR(20),
    checksum VARCHAR(64),
    status ENUM('started', 'completed', 'failed') NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    INDEX idx_backup_type (backup_type),
    INDEX idx_backup_status (status),
    INDEX idx_backup_started_at (started_at)
);

-- Investment Plan Analytics
CREATE TABLE IF NOT EXISTS plan_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    date DATE NOT NULL,
    total_investments INT DEFAULT 0,
    total_amount DECIMAL(15,2) DEFAULT 0,
    new_investors INT DEFAULT 0,
    matured_investments INT DEFAULT 0,
    active_investments INT DEFAULT 0,
    average_investment DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES investment_plans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_plan_date (plan_id, date),
    INDEX idx_plan_analytics_plan (plan_id),
    INDEX idx_plan_analytics_date (date)
);

-- Commission Tracking Enhancement
CREATE TABLE IF NOT EXISTS commission_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    level_1_commission DECIMAL(15,2) DEFAULT 0,
    level_2_commission DECIMAL(15,2) DEFAULT 0,
    level_3_commission DECIMAL(15,2) DEFAULT 0,
    level_4_commission DECIMAL(15,2) DEFAULT 0,
    level_5_commission DECIMAL(15,2) DEFAULT 0,
    total_commission DECIMAL(15,2) DEFAULT 0,
    referrals_count INT DEFAULT 0,
    active_referrals INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date),
    INDEX idx_commission_analytics_user (user_id),
    INDEX idx_commission_analytics_date (date)
);

-- Add new columns to existing tables
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_login_attempt TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS is_email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS password_reset_expires TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) NULL,
ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE;

ALTER TABLE investments 
ADD COLUMN IF NOT EXISTS returns DECIMAL(15,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS maturity_date TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS auto_reinvest BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium';

ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS account_details TEXT NULL,
ADD COLUMN IF NOT EXISTS processed_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS fees DECIMAL(10,2) DEFAULT 0,
ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,4) DEFAULT 1.0000;

ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS broadcast_sent BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS error_message TEXT NULL,
ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP NULL;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_email_verified ON users(is_email_verified);
CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login);
CREATE INDEX IF NOT EXISTS idx_investments_maturity ON investments(maturity_date);
CREATE INDEX IF NOT EXISTS idx_investments_risk_level ON investments(risk_level);
CREATE INDEX IF NOT EXISTS idx_transactions_processed_at ON transactions(processed_at);
CREATE INDEX IF NOT EXISTS idx_transactions_payment_reference ON transactions(payment_reference);

-- Create views for common queries
CREATE OR REPLACE VIEW user_investment_summary AS
SELECT 
    u.id as user_id,
    u.first_name,
    u.last_name,
    u.email,
    COUNT(i.id) as total_investments,
    SUM(CASE WHEN i.status = 'active' THEN i.amount ELSE 0 END) as active_investment_amount,
    SUM(CASE WHEN i.status = 'completed' THEN i.amount + i.returns ELSE 0 END) as completed_investment_value,
    COALESCE(w.balance, 0) as wallet_balance,
    SUM(CASE WHEN e.status = 'paid' THEN e.amount ELSE 0 END) as total_earnings
FROM users u
LEFT JOIN investments i ON u.id = i.user_id
LEFT JOIN wallets w ON u.id = w.user_id
LEFT JOIN earnings e ON u.id = e.user_id
WHERE u.role = 'client'
GROUP BY u.id;

CREATE OR REPLACE VIEW daily_platform_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(CASE WHEN table_name = 'users' THEN 1 END) as new_users,
    COUNT(CASE WHEN table_name = 'investments' THEN 1 END) as new_investments,
    SUM(CASE WHEN table_name = 'investments' THEN amount ELSE 0 END) as investment_volume,
    COUNT(CASE WHEN table_name = 'transactions' AND type = 'deposit' THEN 1 END) as deposits,
    SUM(CASE WHEN table_name = 'transactions' AND type = 'deposit' THEN amount ELSE 0 END) as deposit_volume
FROM (
    SELECT 'users' as table_name, created_at, 0 as amount, '' as type FROM users
    UNION ALL
    SELECT 'investments' as table_name, created_at, amount, '' as type FROM investments
    UNION ALL
    SELECT 'transactions' as table_name, created_at, amount, type FROM transactions
) combined_data
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS CalculateUserROI(IN user_id INT)
BEGIN
    SELECT 
        u.first_name,
        u.last_name,
        SUM(i.amount) as total_invested,
        SUM(i.returns) as total_returns,
        (SUM(i.returns) / SUM(i.amount) * 100) as roi_percentage,
        COUNT(i.id) as total_investments
    FROM users u
    JOIN investments i ON u.id = i.user_id
    WHERE u.id = user_id AND i.status = 'completed'
    GROUP BY u.id;
END //

CREATE PROCEDURE IF NOT EXISTS GetTopPerformers(IN limit_count INT)
BEGIN
    SELECT 
        u.first_name,
        u.last_name,
        u.email,
        SUM(e.amount) as total_earnings,
        COUNT(r.id) as total_referrals,
        (SUM(e.amount) / COUNT(r.id)) as avg_earning_per_referral
    FROM users u
    JOIN earnings e ON u.id = e.user_id
    JOIN referrals r ON u.id = r.referrer_id
    WHERE e.status = 'paid' AND e.type = 'commission'
    GROUP BY u.id
    ORDER BY total_earnings DESC
    LIMIT limit_count;
END //

DELIMITER ;

-- Insert default data for new features
INSERT IGNORE INTO system_settings (category, setting_key, setting_value, description) VALUES
('automation', 'daily_processing_enabled', 'true', 'Enable daily automated processing'),
('automation', 'commission_processing_delay', '24', 'Hours to wait before processing commissions'),
('automation', 'interest_calculation_method', 'simple', 'Interest calculation method (simple/compound)'),
('security', 'max_login_attempts', '5', 'Maximum failed login attempts before lockout'),
('security', 'lockout_duration', '15', 'Account lockout duration in minutes'),
('security', 'jwt_secret_rotation', 'false', 'Enable JWT secret rotation'),
('analytics', 'cache_duration', '3600', 'Analytics cache duration in seconds'),
('analytics', 'real_time_updates', 'true', 'Enable real-time analytics updates'),
('risk', 'high_value_threshold', '50000', 'High value transaction threshold'),
('risk', 'suspicious_activity_threshold', '10', 'Suspicious activity threshold'),
('backup', 'auto_backup_enabled', 'true', 'Enable automatic backups'),
('backup', 'backup_retention_days', '30', 'Backup retention period in days');

-- Create triggers for audit logging
DELIMITER //

CREATE TRIGGER IF NOT EXISTS audit_user_changes
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.role != NEW.role THEN
        INSERT INTO user_activity_logs (user_id, action, entity_type, entity_id, request_data)
        VALUES (NEW.id, 'profile_update', 'users', NEW.id, 
                JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status,
                           'old_role', OLD.role, 'new_role', NEW.role));
    END IF;
END //

CREATE TRIGGER IF NOT EXISTS audit_investment_changes
AFTER UPDATE ON investments
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO user_activity_logs (user_id, action, entity_type, entity_id, request_data)
        VALUES (NEW.user_id, 'investment_status_change', 'investments', NEW.id,
                JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status,
                           'amount', NEW.amount));
    END IF;
END //

DELIMITER ;

-- Optimize existing tables
OPTIMIZE TABLE users, investments, transactions, notifications, earnings, commissions;

-- Update table statistics
ANALYZE TABLE users, investments, transactions, notifications, earnings, commissions;

COMMIT; 