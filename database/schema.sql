-- SJA Foundation Investment Management Platform Database Schema
-- Created: 2024
-- Version: 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: sja_foundation

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `role` enum('admin','client') NOT NULL DEFAULT 'client',
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT 1,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `referral_code` varchar(20) DEFAULT NULL,
  `total_referrals` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `parent_id` (`parent_id`),
  KEY `level` (`level`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `occupation` varchar(255) DEFAULT NULL,
  `annual_income` decimal(15,2) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_ifsc` varchar(20) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `aadhaar_number` varchar(20) DEFAULT NULL,
  `kyc_status` enum('pending','in_progress','approved','rejected') DEFAULT 'pending',
  `profile_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `kyc_status` (`kyc_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `type` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `start_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in months',
  `interest_rate` decimal(5,2) NOT NULL,
  `expected_return` decimal(15,2) NOT NULL,
  `status` enum('pending','active','matured','withdrawn','cancelled') DEFAULT 'pending',
  `approval_date` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `certificate_url` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `status` (`status`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investment_plans`
--

CREATE TABLE `investment_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in months',
  `features` json DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdrawal','investment','commission','bonus','penalty') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `reference_type` enum('investment','withdrawal','commission','manual','system') DEFAULT NULL,
  `status` enum('pending','completed','rejected','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `gateway_response` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `status` (`status`),
  KEY `processed_by` (`processed_by`),
  KEY `reference_id` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `locked_balance` decimal(15,2) DEFAULT 0.00,
  `total_invested` decimal(15,2) DEFAULT 0.00,
  `total_earned` decimal(15,2) DEFAULT 0.00,
  `total_withdrawn` decimal(15,2) DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kyc_documents`
--

CREATE TABLE `kyc_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_type` enum('aadhar','pan','passport','driving_license','voter_id') NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `document_url` varchar(255) NOT NULL,
  `aadhaar_front` varchar(255) DEFAULT NULL,
  `aadhaar_back` varchar(255) DEFAULT NULL,
  `pan_card` varchar(255) DEFAULT NULL,
  `passport` varchar(255) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `address_proof` varchar(255) DEFAULT NULL,
  `bank_passbook` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `video_kyc_url` varchar(255) DEFAULT NULL,
  `video_kyc_duration` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `verification_status` enum('pending','in_review','approved','rejected') DEFAULT 'pending',
  `verification_notes` text DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `verification_status` (`verification_status`),
  KEY `verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nominees`
--

CREATE TABLE `nominees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `date_of_birth` date NOT NULL,
  `relation` varchar(100) NOT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `id_proof` varchar(255) DEFAULT NULL,
  `share_percentage` decimal(5,2) DEFAULT 100.00,
  `is_primary` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `total_commission` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referrer_referred` (`referrer_id`, `referred_id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_id` (`referred_id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `investment_id` int(11) DEFAULT NULL,
  `level` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `investment_id` (`investment_id`),
  KEY `level` (`level`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `earnings`
--

CREATE TABLE `earnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `source` enum('referral','investment','bonus','penalty_refund') NOT NULL,
  `source_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `type` enum('commission','interest','bonus','reward') NOT NULL,
  `level` int(11) DEFAULT NULL,
  `from_user_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `source` (`source`),
  KEY `type` (`type`),
  KEY `status` (`status`),
  KEY `from_user_id` (`from_user_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','system','birthday','kyc','investment','withdrawal') DEFAULT 'info',
  `read_status` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `read_status` (`read_status`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `investment_id` int(11) DEFAULT NULL,
  `type` enum('regular','emergency','partial') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `penalty_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `bank_details` json DEFAULT NULL,
  `status` enum('pending','approved','rejected','processed','cancelled') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `investment_id` (`investment_id`),
  KEY `type` (`type`),
  KEY `status` (`status`),
  KEY `processed_by` (`processed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_levels`
--

CREATE TABLE `commission_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `min_investment` decimal(15,2) NOT NULL,
  `max_investment` decimal(15,2) DEFAULT NULL,
  `commission_rate` decimal(5,4) NOT NULL,
  `fixed_commission` decimal(15,2) DEFAULT NULL,
  `benefits` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level` (`level`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `table_name` (`table_name`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Insert default commission levels
--

INSERT INTO `commission_levels` (`level`, `title`, `min_investment`, `max_investment`, `commission_rate`, `fixed_commission`, `benefits`) VALUES
(1, 'Professional Ambassador', 100000.00, 2000000.00, 0.0025, 250.00, '["Basic support", "Monthly reports"]'),
(2, 'Rubies Ambassador', 3000000.00, NULL, 0.0037, 370.00, '["Priority support", "Quarterly reviews"]'),
(3, 'Topaz Ambassador', 4000000.00, NULL, 0.0050, 500.00, '["Dedicated support", "Monthly reviews"]'),
(4, 'Silver Ambassador', 5000000.00, NULL, 0.0070, 700.00, '["VIP support", "Bi-weekly reviews"]'),
(5, 'Golden Ambassador', 6000000.00, NULL, 0.0085, 850.00, '["Premium support", "Weekly reviews"]'),
(6, 'Platinum Ambassador', 7000000.00, NULL, 0.0100, 1000.00, '["Elite support", "Daily updates"]'),
(7, 'Diamond Ambassador', 8000000.00, NULL, 0.0125, 1250.00, '["Diamond support", "Real-time updates"]'),
(8, 'MTA', 9000000.00, NULL, 0.0150, 1500.00, '["MTA benefits", "Advanced analytics"]'),
(9, 'Channel Partner', 10000000.00, NULL, 0.0200, 2000.00, '["Channel partner benefits", "Full analytics"]'),
(10, 'Co-Director', 15000000.00, NULL, 0.0250, 2500.00, '["Co-Director benefits", "Management access"]'),
(11, 'Director/MD/CEO/CMD', 20000000.00, NULL, 0.0300, 3000.00, '["Executive benefits", "Full system access"]');

-- --------------------------------------------------------

--
-- Insert default system settings
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`) VALUES
('platform_name', 'SJA Foundation', 'string', 'general', 'Platform name displayed across the system'),
('admin_email', 'admin@sjafoundation.com', 'string', 'general', 'Primary admin email address'),
('support_phone', '+91-9876543210', 'string', 'general', 'Support phone number'),
('currency', 'INR', 'string', 'general', 'Default currency for the platform'),
('platform_description', 'Investment Management Platform for SJA Foundation with MLM referral system', 'string', 'general', 'Platform description'),

('session_timeout', '30', 'number', 'security', 'Session timeout in minutes'),
('max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout'),
('two_factor_auth', 'false', 'boolean', 'security', 'Enable two-factor authentication'),
('email_verification', 'true', 'boolean', 'security', 'Require email verification for new users'),
('strong_password', 'true', 'boolean', 'security', 'Enforce strong password policy'),
('audit_logging', 'true', 'boolean', 'security', 'Enable audit logging'),

('level_1_commission', '3.00', 'number', 'mlm', 'Level 1 commission percentage'),
('level_2_commission', '2.50', 'number', 'mlm', 'Level 2 commission percentage'),
('level_3_commission', '2.00', 'number', 'mlm', 'Level 3 commission percentage'),
('level_4_commission', '1.50', 'number', 'mlm', 'Level 4 commission percentage'),
('level_5_commission', '1.25', 'number', 'mlm', 'Level 5 commission percentage'),
('level_6_commission', '1.00', 'number', 'mlm', 'Level 6 commission percentage'),
('level_7_commission', '0.75', 'number', 'mlm', 'Level 7 commission percentage'),
('level_8_commission', '0.50', 'number', 'mlm', 'Level 8 commission percentage'),
('level_9_commission', '0.40', 'number', 'mlm', 'Level 9 commission percentage'),
('level_10_commission', '0.30', 'number', 'mlm', 'Level 10 commission percentage'),
('level_11_commission', '0.25', 'number', 'mlm', 'Level 11 commission percentage'),
('min_withdrawal', '500', 'number', 'mlm', 'Minimum withdrawal amount'),
('payout_schedule', 'weekly', 'string', 'mlm', 'Commission payout schedule'),

('email_notifications', 'true', 'boolean', 'notifications', 'Enable email notifications'),
('sms_notifications', 'false', 'boolean', 'notifications', 'Enable SMS notifications'),
('new_user_notification', 'true', 'boolean', 'notifications', 'Notify admin of new user registrations'),
('investment_notification', 'true', 'boolean', 'notifications', 'Notify admin of new investments'),
('withdrawal_notification', 'true', 'boolean', 'notifications', 'Notify admin of withdrawal requests'),
('kyc_notification', 'true', 'boolean', 'notifications', 'Notify admin of KYC submissions'),

('backup_schedule', 'daily', 'string', 'backup', 'Automated backup schedule'),
('backup_retention_days', '30', 'number', 'backup', 'Number of days to retain backups');

-- --------------------------------------------------------

--
-- Insert default investment plans
--

INSERT INTO `investment_plans` (`name`, `description`, `min_amount`, `max_amount`, `interest_rate`, `duration`, `features`, `status`, `created_by`) VALUES
('Basic Plan', 'Entry level investment plan with competitive returns', 10000.00, 100000.00, 12.00, 11, '["11 months maturity", "Emergency withdrawal", "Monthly interest"]', 'active', NULL),
('Premium Plan', 'Enhanced investment plan with higher returns', 100000.00, 1000000.00, 15.00, 11, '["Higher interest rates", "Priority support", "Enhanced referral benefits"]', 'active', NULL),
('Elite Plan', 'Premium investment plan for high-value investors', 1000000.00, 10000000.00, 18.00, 11, '["Maximum returns", "Dedicated relationship manager", "VIP benefits"]', 'active', NULL);

-- --------------------------------------------------------

--
-- Insert default system settings
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('site_name', 'SJA Foundation', 'string', 'Website name', 1),
('site_description', 'Investment Management Platform', 'string', 'Website description', 1),
('contact_email', 'info@sjafoundation.com', 'string', 'Contact email address', 1),
('contact_phone', '+91 9876543210', 'string', 'Contact phone number', 1),
('min_investment', '10000', 'number', 'Minimum investment amount', 1),
('max_investment', '10000000', 'number', 'Maximum investment amount', 1),
('withdrawal_penalty', '3', 'number', 'Early withdrawal penalty percentage', 0),
('kyc_required', 'true', 'boolean', 'KYC verification required', 1),
('referral_enabled', 'true', 'boolean', 'Referral system enabled', 1),
('video_kyc_enabled', 'true', 'boolean', 'Video KYC enabled', 1),
('maintenance_mode', 'false', 'boolean', 'Maintenance mode status', 0);

-- --------------------------------------------------------

--
-- Add foreign key constraints after all tables are created
--

ALTER TABLE `users` ADD CONSTRAINT `fk_users_parent` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `clients` ADD CONSTRAINT `fk_clients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `investments` ADD CONSTRAINT `fk_investments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `investments` ADD CONSTRAINT `fk_investments_plan` FOREIGN KEY (`plan_id`) REFERENCES `investment_plans` (`id`) ON DELETE SET NULL;
ALTER TABLE `investments` ADD CONSTRAINT `fk_investments_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `investment_plans` ADD CONSTRAINT `fk_plans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `transactions` ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `transactions` ADD CONSTRAINT `fk_transactions_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `wallets` ADD CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `kyc_documents` ADD CONSTRAINT `fk_kyc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `kyc_documents` ADD CONSTRAINT `fk_kyc_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `nominees` ADD CONSTRAINT `fk_nominees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `referrals` ADD CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `referrals` ADD CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `earnings` ADD CONSTRAINT `fk_earnings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `earnings` ADD CONSTRAINT `fk_earnings_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `withdrawals` ADD CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `withdrawals` ADD CONSTRAINT `fk_withdrawals_investment` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`id`) ON DELETE SET NULL;
ALTER TABLE `withdrawals` ADD CONSTRAINT `fk_withdrawals_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `system_settings` ADD CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `audit_logs` ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

COMMIT; 