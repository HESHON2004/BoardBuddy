-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 14, 2025 at 07:02 AM
-- Server version: 9.1.0
-- PHP Version: 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `user_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Owner','User') NOT NULL DEFAULT 'User',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `email_2` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Amiru', 'Pinsara', 'amiru@gmail.com', '+94147852369', '$2y$12$OdTc06wHWL.5o0bfJ475n.0WXgBS30uQ9oOMPXWjLevR6bFjbcd5q', 'Admin', 'Active', '2025-09-05 20:42:31', '2025-09-10 19:07:29'),
(4, 'sahan', 'doe', 'sahan@gmail.com', '+94147852811', '$2y$12$DGpBAYRcl8yQRd6zWJU9PO.Swidbfxw3f7pLQOEan53cNdqGps9Di', 'User', 'Active', '2025-09-05 20:52:02', '2025-09-10 18:17:04'),
(5, 'Udith', 'Sam', 'udith@gmail.com', '+94147852901', '$2y$12$Dv.Em5WG5ncn4uVEhZ4nvewu/Bi7RyA72gHDFPNBSSS6bZxH35.fy', 'Owner', 'Active', '2025-09-05 20:53:45', '2025-09-10 18:16:23'),
(6, 'Ganidu', 'Kavi', 'ganidu@gmail.com', '+94147852643', '$2y$12$dieCoMMYqiE6ni22wM7Tz.BJroyAIHowhkfBkFuW8yhOcuMGeVudC', 'Owner', 'Active', '2025-09-05 20:56:18', '2025-09-10 18:15:31'),
(13, 'Sachin', 'Perera', 'sachin@gmail.com', '+94713457890', '$2y$12$rfLqVIoUN0wY1n5ql5MblOCGZTYBhi/pekctFaLNn0tUacK4NFye2', 'Admin', 'Active', '2025-09-10 18:20:35', '2025-09-10 18:21:38'),
(16, 'Kevin', 'fon', 'kevin@gmail.com', '+94713452727', '$2y$12$1raDeYnvUxaK/rDgYkLjl.bYFytAgeN9XgKyQGTS4.R2JYM1w8hMO', 'Owner', 'Active', '2025-09-10 19:12:00', '2025-09-14 06:52:28'),
(10, 'Shehan', 'abc', 'shehan@gmail.com', '+94147852369', '$2y$12$Tjwp7WSKOE0YGzugZ0GhtOXmOwXq094sQZX9hw1zAUjXgorbNVpCW', 'User', 'Active', '2025-09-06 15:37:35', '2025-09-10 18:14:43'),
(11, 'Heshan', 'Fonseka', 'fon@gmail.com', '+94147111100', '$2y$12$2PYLZ86Kg8TlCmafTyeTJOgdv4z6ajiy951w4nzqzQwdAV0MQB3Iu', 'User', 'Active', '2025-09-06 16:53:31', '2025-09-10 18:13:05');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
