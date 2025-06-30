CREATE TABLE `product_substitutions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_product` varchar(255) NOT NULL,
  `substitute_product` varchar(255) NOT NULL,
  `expression_id` varchar(255) NOT NULL,
  `material_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `other_reason` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  KEY `expression_id` (`expression_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;