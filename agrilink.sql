-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 10, 2026 at 08:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `agrilink`
--

-- --------------------------------------------------------

--
-- Table structure for table `contributions`
--

CREATE TABLE `contributions` (
  `contribution_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `contribution_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contributions`
--

INSERT INTO `contributions` (`contribution_id`, `user_id`, `quantity`, `contribution_date`) VALUES
(1, 7, 2.00, '2026-02-20');

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `id` int(11) NOT NULL,
  `ngo_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `donated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`id`, `ngo_id`, `amount`, `purpose`, `notes`, `donated_at`) VALUES
(1, 12, 250.00, 'Equipment & Tools', '', '2026-05-08 05:33:43');

-- --------------------------------------------------------

--
-- Table structure for table `finance`
--

CREATE TABLE `finance` (
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `transaction_type` int(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `finance`
--

INSERT INTO `finance` (`user_id`, `amount`, `date`, `balance`, `transaction_type`, `description`) VALUES
(7, 500.00, '2026-03-15', 0.00, 0, NULL),
(7, 1000.00, '2026-03-15', 0.00, 0, NULL),
(7, 1000.00, '2026-03-15', 0.00, 0, NULL),
(7, 5000.00, '2026-03-15', 0.00, 0, NULL),
(7, 5000.00, '2026-03-15', 0.00, 0, NULL),
(7, 8000.00, '2026-03-15', 0.00, 0, NULL),
(7, 8000.00, '2026-03-15', 0.00, 0, NULL),
(7, 10000.00, '2026-03-17', 0.00, 0, 'profit distribution'),
(7, 500.00, '2026-03-15', 0.00, 0, NULL),
(7, 1000.00, '2026-03-15', 0.00, 0, NULL),
(7, 1000.00, '2026-03-15', 0.00, 0, NULL),
(7, 5000.00, '2026-03-15', 0.00, 0, NULL),
(7, 5000.00, '2026-03-15', 0.00, 0, NULL),
(7, 8000.00, '2026-03-15', 0.00, 0, NULL),
(7, 8000.00, '2026-03-15', 0.00, 0, NULL),
(7, 10000.00, '2026-03-17', 0.00, 0, 'profit distribution'),
(22, -20000.00, '2026-04-19', 0.00, 1, 'Purchase from Supplier: bee suits (Qty: 1)'),
(22, -20000.00, '2026-04-19', 0.00, 1, 'Purchase from Supplier: bee suits (Qty: 1)');

-- --------------------------------------------------------

--
-- Table structure for table `hives`
--

CREATE TABLE `hives` (
  `hive_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `hive_count` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hives`
--

INSERT INTO `hives` (`hive_id`, `user_id`, `location`, `hive_count`) VALUES
(1, 7, 'thunda', 2),
(2, 20, 'Kajoni', 5),
(3, 7, 'Kajoni', 3);

-- --------------------------------------------------------

--
-- Table structure for table `inspections`
--

CREATE TABLE `inspections` (
  `inspection_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scheduled_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `health_status` text DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `hive_id` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspections`
--

INSERT INTO `inspections` (`inspection_id`, `user_id`, `scheduled_date`, `actual_date`, `health_status`, `findings`, `hive_id`, `status`, `notes`) VALUES
(8, 7, '2026-05-10', NULL, NULL, NULL, '2', 'needs immediate treatment', 'pests');

-- --------------------------------------------------------

--
-- Table structure for table `markets`
--

CREATE TABLE `markets` (
  `market_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `market_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `markets`
--

INSERT INTO `markets` (`market_id`, `location`, `market_date`, `description`, `created_at`) VALUES
(1, 'kasungu trade fair', '2026-05-30', 'all kinds of farm products.', '2026-05-06 01:50:24');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_role` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `created_at`, `target_role`) VALUES
(1, 'general meeting', 'we write to ask everyone to show this coming thursday. on that day we will have election on the new board executives of this cooperative', '2026-04-02 21:16:27', NULL),
(2, 'market schedule', 'on 8th April the market is starting, make sure you are on time.', '2026-04-03 09:55:55', NULL),
(3, 'New Stock: cains', 'Supplier janice has added 1 units of \'cains\' to the inventory.', '2026-04-20 08:31:34', 'admin_treasurer'),
(4, 'Payment Received: cains', 'The cooperative has purchased 1 units of cains. A payout of MK 100 has been initiated to your mobile wallet.', '2026-04-20 08:36:43', 'supplier_10'),
(5, 'Purchase Successful', 'Successfully purchased 1 units of cains from janice. Supplier payout triggered.', '2026-04-20 08:36:43', 'admin_treasurer'),
(6, 'Payment Received: cains', 'The cooperative has purchased 1 units of cains. A payout of MK 100 has been initiated to your mobile wallet.', '2026-05-03 19:33:10', 'supplier_10'),
(7, 'Purchase Successful', 'Successfully purchased 1 units of cains from janice. Supplier payout triggered.', '2026-05-03 19:33:10', 'admin_treasurer'),
(8, 'Cooperative Contact: failure to buy shares', 'Admin (livingstoniaagrilink@gmail.com) has sent a cooperative message:\n\ni\'m failing to buy shares, its locked so how do i buy the', '2026-05-07 09:13:52', 'admin_treasurer'),
(9, 'Hive Inspection Scheduled', 'A hive inspection has been scheduled for you on 2026-05-16. Please check your portal for details.', '2026-05-07 09:14:02', 'member'),
(10, 'Cooperative Contact: failure to buy shares', 'Admin (livingstoniaagrilink@gmail.com) has sent a cooperative message:\n\ni\'m failing to buy shares, its locked so how do i buy the', '2026-05-07 09:56:34', 'admin_treasurer'),
(11, 'New Stock: cains', 'Supplier janice has added 15 units of \'cains\' to the inventory.', '2026-05-08 12:49:35', 'admin'),
(12, 'New Stock: cains', 'Supplier janice has added 15 units of \'cains\' to the inventory.', '2026-05-08 12:49:35', 'treasurer'),
(13, 'Hive Inspection Scheduled', 'A hive inspection has been scheduled for you on 2026-05-11. Please check your portal for details.', '2026-05-10 06:27:38', 'member');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `item` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `paychangu_ref` varchar(255) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(2, 7, 'fd3b30f2a8d750f60be952e60e92630a704e8cea5977998d', '2026-04-03 10:47:54', '2026-04-03 07:47:54'),
(3, 7, 'ba30a690aabd48ebbee417bc478fadb1e605ec7da2aa9ca1', '2026-04-03 10:53:20', '2026-04-03 07:53:20');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('available','hidden') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `stock`, `image_path`, `status`, `created_at`) VALUES
(1, 'honey', '20 bottles available currently', 5000.00, 20, NULL, 'available', '2026-04-08 06:42:17'),
(2, 'refined honey', '5 bottles left.', 40.00, 5, 'uploads/products/product_1775802022_69d896a6e4146.jpeg', 'available', '2026-04-10 06:20:22'),
(3, 'unrefined honey', '', 5000.00, 2, '', 'available', '2026-04-10 06:24:00'),
(4, 'honey', '', 100.00, 3, '', 'available', '2026-05-08 12:54:45');

-- --------------------------------------------------------

--
-- Table structure for table `profits`
--

CREATE TABLE `profits` (
  `profit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `distribution_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profits`
--

INSERT INTO `profits` (`profit_id`, `user_id`, `amount`, `distribution_date`) VALUES
(46, 7, 97.73, '2026-04-20'),
(47, 27, 2.27, '2026-04-20'),
(48, 7, 48.86, '2026-04-20'),
(49, 27, 1.14, '2026-04-20'),
(50, 7, 146.59, '2026-05-05'),
(51, 7, 146.59, '2026-05-05'),
(52, 27, 3.41, '2026-05-05'),
(53, 7, 98.84, '2026-05-05'),
(54, 27, 1.16, '2026-05-05');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `paychangu_ref` varchar(255) DEFAULT NULL,
  `processing_fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `buyer_id`, `supplier_id`, `item_id`, `quantity`, `total_price`, `purchase_date`, `status`, `paychangu_ref`, `processing_fee`) VALUES
(1, 11, 10, 1, 10, 200000.00, '2026-03-19 08:00:09', 'completed', NULL, 0.00),
(2, 11, 10, 2, 4, 20000.00, '2026-03-19 08:00:17', 'completed', NULL, 0.00),
(3, 11, 10, 3, 1, 25000.00, '2026-03-30 07:04:42', 'completed', NULL, 0.00),
(4, 22, 10, 1, 1, 20000.00, '2026-04-20 02:49:28', 'completed', NULL, 0.00),
(5, 22, 10, 1, 1, 20000.00, '2026-04-20 02:51:28', 'completed', NULL, 0.00),
(6, 22, 10, 5, 1, 15000.00, '2026-04-20 08:19:14', 'completed', 'STK-69E5E18274B0D-5', 300.00),
(8, 22, 10, 7, 1, 100.00, '2026-04-20 08:35:19', 'completed', 'STK-69E5E54700A27-7', 2.00),
(9, 22, 10, 6, 1, 100.00, '2026-05-03 19:29:59', 'completed', 'STK-69F7A23725B5F-6', 2.00),
(10, 22, 10, 2, 1, 5000.00, '2026-05-05 14:25:57', 'completed', 'STK-69F9FDF5EA4FA-2', 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `shares`
--

CREATE TABLE `shares` (
  `share_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shares` int(11) NOT NULL,
  `purchase_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_stock`
--

CREATE TABLE `supplier_stock` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `available` tinyint(1) DEFAULT 1,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_stock`
--

INSERT INTO `supplier_stock` (`id`, `supplier_id`, `item_name`, `quantity`, `price`, `description`, `available`, `date_added`) VALUES
(1, 10, 'bee suits', 8, 20000.00, '', 1, '2026-03-19 07:24:43'),
(2, 10, 'propolis', 46, 5000.00, '', 1, '2026-03-19 07:25:31'),
(3, 10, 'cains', 20, 25000.00, '', 1, '2026-03-30 07:02:27'),
(4, 10, 'cains', 5, 10000.00, '', 1, '2026-04-14 12:16:53'),
(5, 10, 'bee suits', 5, 15000.00, '', 1, '2026-04-20 01:22:14'),
(6, 10, 'cains', 0, 100.00, '', 1, '2026-04-20 08:25:23'),
(7, 10, 'cains', 0, 100.00, '', 1, '2026-04-20 08:31:34'),
(8, 10, 'cains', 15, 100.00, '', 1, '2026-05-08 12:47:28'),
(9, 10, 'cains', 15, 100.00, '', 1, '2026-05-08 12:49:35');

-- --------------------------------------------------------

--
-- Table structure for table `training_materials`
--

CREATE TABLE `training_materials` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_history`
--

CREATE TABLE `transfer_history` (
  `transfer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_method` enum('mobile_money','bank_transfer') DEFAULT 'mobile_money',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmation_date` timestamp NULL DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transfer_history`
--

INSERT INTO `transfer_history` (`transfer_id`, `user_id`, `amount`, `transaction_id`, `status`, `payment_method`, `created_at`, `confirmation_date`, `error_message`) VALUES
(13, 7, 97.73, 'PC-69D89C0325ECE', 'completed', 'mobile_money', '2026-04-10 06:43:31', NULL, NULL),
(17, 7, 97.73, 'PC-69E63A4EDF94A', 'completed', 'mobile_money', '2026-04-20 14:38:31', NULL, NULL),
(23, 7, 98.84, 'PC-69FA8FD5AB02B', 'completed', 'mobile_money', '2026-05-06 00:48:41', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('member','admin','external','secretary','treasurer') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','active','inactive') DEFAULT 'active',
  `phone` varchar(20) DEFAULT NULL,
  `hive_id` varchar(50) DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `stakeholder_type` enum('supplier','buyer','ngo') DEFAULT 'supplier',
  `fee_status` enum('pending','paid') DEFAULT 'pending',
  `payout_phone` varchar(20) DEFAULT NULL,
  `payout_method` enum('mobile_money','bank_transfer') DEFAULT 'mobile_money',
  `payout_operator` enum('airtel','tnm') DEFAULT 'airtel'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role`, `email`, `phone_number`, `full_name`, `national_id`, `created_at`, `status`, `phone`, `hive_id`, `date_joined`, `fee`, `profile_picture`, `stakeholder_type`, `fee_status`, `payout_phone`, `payout_method`, `payout_operator`) VALUES
(7, '', '$2y$10$A5Gm9CCYf2vfrOsMhW3rR.6w7oaH8/1R30qIYyU1Fy4H6Ih4RMQ.a', 'member', 'mwahimbaalinuswe@gmail.com', NULL, 'Alinuswe Mwahimba', NULL, '2026-02-19 20:48:28', 'active', '+265982292944', 'bwengu', '2026-02-19', 100.00, NULL, 'supplier', 'pending', NULL, 'mobile_money', 'airtel'),
(10, 'nicklaus471@gmail.com', '$2y$10$vT3bDVd..Snk/69j5aGkCufev0OgQySgMEYAs2dDF4upsFnI.kTMK', 'external', 'nicklaus471@gmail.com', NULL, 'Nikk', NULL, '2026-03-19 07:12:58', 'active', '0884106302', NULL, '2026-03-19', NULL, '', 'supplier', 'pending', '0981375917', 'mobile_money', 'airtel'),
(11, 'patriciamwahimba@gmail.com', '$2y$10$0CSQquatneq.TxJxrrnbUO/ke8R.TYQ9OpjDMrALHvT/L5D8hDAke', 'external', 'patriciamwahimba@gmail.com', NULL, 'Tuntu', NULL, '2026-03-19 07:57:10', 'active', '', NULL, '2026-03-19', NULL, '', 'buyer', 'pending', NULL, 'mobile_money', 'airtel'),
(12, 'roman2@gmail.com', '$2y$10$Ue02/Y.NdrDNLeW3gidlSeo7tnh94OuTaxcAOnAtWfUx3DEGa8Spm', 'external', 'roman2@gmail.com', NULL, 'Roman', NULL, '2026-03-19 07:58:34', 'active', NULL, NULL, '2026-03-19', NULL, NULL, 'ngo', 'pending', NULL, 'mobile_money', 'airtel'),
(13, 'fredrick@gmail.com', '$2y$10$CUMq8CvswYc9zFRkHvctTuz9Kv5sT6nFS/kKP/P/zJ67fYzZWIwiu', '', 'fredrick@gmail.com', NULL, 'Fredrick Mwahimba', NULL, '2026-03-30 07:37:01', 'inactive', NULL, NULL, '2026-03-30', NULL, NULL, 'supplier', 'pending', NULL, 'mobile_money', 'airtel'),
(14, 'zinnia5@gmail.com', '$2y$10$ybboMlYoyqL0fAPs1s9bq.wStNB1c.e.fbALEy5Z7pBAvXjIz/3rG', 'secretary', 'zinnia5@gmail.com', NULL, 'Zinnia', NULL, '2026-03-30 08:00:57', 'active', '', NULL, '2026-03-30', NULL, 'uploads/profile_pictures/member_14_1775211805.jpg', NULL, 'pending', NULL, 'mobile_money', 'airtel'),
(20, 'ousmanshakkira@gmail.com', '$2y$10$UTSSEA7CjBt.M.Fb7TCNt.exDoF0gpbaG9ZjLdpdEgwhPvY4a3V2O', 'treasurer', 'ousmanshakkira@gmail.com', NULL, 'Jayden', NULL, '2026-04-03 22:45:08', 'active', NULL, NULL, '2026-04-03', NULL, NULL, 'supplier', 'pending', NULL, 'mobile_money', 'airtel'),
(22, 'livingstoniaagrilink@gmail.com', '$2y$10$4caAQy6l7Kuu/.DvXHkFEO1bokxwqsOurpT2Wa6FrJkyNKyX4mXLe', 'admin', 'livingstoniaagrilink@gmail.com', NULL, 'Admin', NULL, '2026-04-05 18:16:41', 'active', '', NULL, '2026-04-05', NULL, 'uploads/profile_pictures/admin_22_1775958926.jpg', 'supplier', 'pending', NULL, 'mobile_money', 'airtel'),
(27, 'alinuswemwahimba5@gmail.com', '$2y$10$VKv7PSUjd.nneXu7twUL7O1Lk2Baiwc82Z/cxx95Rx6a0inOi0WPi', 'member', 'alinuswemwahimba5@gmail.com', NULL, 'Tuntufye', NULL, '2026-04-08 06:23:47', 'active', '0982292944', NULL, '2026-04-08', 1000.00, NULL, 'supplier', 'pending', NULL, 'mobile_money', 'airtel');

-- --------------------------------------------------------

--
-- Table structure for table `user_notif_cleared`
--

CREATE TABLE `user_notif_cleared` (
  `user_id` int(11) NOT NULL,
  `cleared_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notif_cleared`
--

INSERT INTO `user_notif_cleared` (`user_id`, `cleared_at`) VALUES
(9, '2026-04-05 08:41:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_notif_read`
--

CREATE TABLE `user_notif_read` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notif_read`
--

INSERT INTO `user_notif_read` (`id`, `user_id`, `notification_id`, `read_at`) VALUES
(1, 7, 2, '2026-04-05 16:07:22'),
(2, 27, 2, '2026-04-08 06:24:42'),
(3, 27, 1, '2026-04-08 06:24:43'),
(4, 20, 2, '2026-04-08 07:06:34'),
(5, 20, 1, '2026-04-08 07:06:35'),
(6, 7, 1, '2026-04-11 18:03:05'),
(7, 22, 2, '2026-04-20 02:21:26'),
(8, 22, 1, '2026-04-20 02:21:27'),
(10, 22, 5, '2026-04-20 08:52:14'),
(11, 22, 3, '2026-04-20 08:52:17'),
(14, 22, 4, '2026-04-20 08:52:49'),
(15, 22, 6, '2026-05-03 19:33:39'),
(16, 22, 7, '2026-05-03 19:33:42'),
(19, 22, 8, '2026-05-07 09:15:38'),
(20, 22, 9, '2026-05-07 09:15:56'),
(21, 22, 10, '2026-05-07 10:06:49'),
(22, 7, 9, '2026-05-07 13:15:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contributions`
--
ALTER TABLE `contributions`
  ADD PRIMARY KEY (`contribution_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ngo_id` (`ngo_id`);

--
-- Indexes for table `hives`
--
ALTER TABLE `hives`
  ADD PRIMARY KEY (`hive_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inspections`
--
ALTER TABLE `inspections`
  ADD PRIMARY KEY (`inspection_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `markets`
--
ALTER TABLE `markets`
  ADD PRIMARY KEY (`market_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `profits`
--
ALTER TABLE `profits`
  ADD PRIMARY KEY (`profit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `shares`
--
ALTER TABLE `shares`
  ADD PRIMARY KEY (`share_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `supplier_stock`
--
ALTER TABLE `supplier_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `training_materials`
--
ALTER TABLE `training_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `training_materials_ibfk_1` (`uploaded_by`);

--
-- Indexes for table `transfer_history`
--
ALTER TABLE `transfer_history`
  ADD PRIMARY KEY (`transfer_id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `national_id` (`national_id`);

--
-- Indexes for table `user_notif_cleared`
--
ALTER TABLE `user_notif_cleared`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_notif_read`
--
ALTER TABLE `user_notif_read`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`notification_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contributions`
--
ALTER TABLE `contributions`
  MODIFY `contribution_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hives`
--
ALTER TABLE `hives`
  MODIFY `hive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inspections`
--
ALTER TABLE `inspections`
  MODIFY `inspection_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `markets`
--
ALTER TABLE `markets`
  MODIFY `market_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `profits`
--
ALTER TABLE `profits`
  MODIFY `profit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `shares`
--
ALTER TABLE `shares`
  MODIFY `share_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `supplier_stock`
--
ALTER TABLE `supplier_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transfer_history`
--
ALTER TABLE `transfer_history`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `user_notif_read`
--
ALTER TABLE `user_notif_read`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contributions`
--
ALTER TABLE `contributions`
  ADD CONSTRAINT `contributions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `hives`
--
ALTER TABLE `hives`
  ADD CONSTRAINT `hives_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `inspections`
--
ALTER TABLE `inspections`
  ADD CONSTRAINT `inspections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `profits`
--
ALTER TABLE `profits`
  ADD CONSTRAINT `profits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `purchases_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `supplier_stock` (`id`);

--
-- Constraints for table `shares`
--
ALTER TABLE `shares`
  ADD CONSTRAINT `shares_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `supplier_stock`
--
ALTER TABLE `supplier_stock`
  ADD CONSTRAINT `supplier_stock_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `training_materials`
--
ALTER TABLE `training_materials`
  ADD CONSTRAINT `training_materials_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `transfer_history`
--
ALTER TABLE `transfer_history`
  ADD CONSTRAINT `transfer_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
