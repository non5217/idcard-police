-- SQL for creating LINE subscriptions table
CREATE TABLE IF NOT EXISTS `idcard_line_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_card_number` varchar(13) NOT NULL,
  `line_user_id` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_id_card_line` (`id_card_number`, `line_user_id`),
  INDEX (`id_card_number`),
  INDEX (`line_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
