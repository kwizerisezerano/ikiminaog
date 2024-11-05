-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2024 at 08:56 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ikimina`
--

-- --------------------------------------------------------

--
-- Table structure for table `tontine`
--

CREATE TABLE `tontine` (
  `id` int(11) NOT NULL,
  `tontine_name` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `join_date` date NOT NULL,
  `province` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  `sector` varchar(100) NOT NULL,
  `total_contributions` decimal(10,2) NOT NULL,
  `occurrence` enum('Daily','Weekly','Monthly') NOT NULL,
  `time` time DEFAULT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `role` varchar(30) NOT NULL,
  `member_list` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `rules` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tontine`
--

INSERT INTO `tontine` (`id`, `tontine_name`, `logo`, `join_date`, `province`, `district`, `sector`, `total_contributions`, `occurrence`, `time`, `day`, `date`, `created_at`, `user_id`, `role`, `member_list`, `purpose`, `rules`) VALUES
(30, 'dufashanye', 'uploads/6727be086829e.png', '2024-11-03', 'East', 'Bugesera', 'Shyara', 700.00, 'Weekly', '00:00:00', 'Sunday', '0000-00-00', '2024-11-03 18:16:40', 89, 'Admin', 'mukamana,mine', 'done well', 'done'),
(32, 'UBUMWE bwacu', 'uploads/6727e61ea7f83.png', '2024-11-03', 'Kigali', 'Gasabo', 'Rutunga', 600.00, 'Monthly', '00:00:00', '', '2024-11-03', '2024-11-03 21:07:42', 97, 'Admin', 'Love,mine,koko,kamana,mukamana ', 'raise each other', 'contribute on time bro'),
(35, 'INSHUTI Z\'UMURYANGO', 'uploads/6728e83325121.png', '2024-11-04', 'East', 'Bugesera', 'Shyara', 8.00, 'Daily', '16:57:00', '', '0000-00-00', '2024-11-04 15:28:51', 89, 'Admin', '', '', ''),
(36, 'INSHUTI Z\'UMURYANGO', 'uploads/6728e90a86905.png', '2024-11-04', 'East', 'Bugesera', 'GISENYI', 100.00, 'Monthly', '00:00:00', '', '2024-11-04', '2024-11-04 15:32:26', 89, 'Admin', '', 'kORA', 'kORA');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `otp` int(11) DEFAULT 0,
  `verified` tinyint(4) NOT NULL DEFAULT 0,
  `otp_used` tinyint(1) NOT NULL DEFAULT 0,
  `otp_login` int(11) NOT NULL DEFAULT 0,
  `otp_forgot` int(11) NOT NULL,
  `terms` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `phone_number`, `password`, `image`, `otp`, `verified`, `otp_used`, `otp_login`, `otp_forgot`, `terms`, `created_at`, `updated_at`) VALUES
(88, 'kwizerisezerano  thab', 'miss m', '0790989831', '$2y$10$9C9/0314JIQBWlEjzLOi0.nSb0wCeY3PCL1vX9E4rKAk.jo7uS1Vu', 'uploads/67215959566a9.jpg', 413453, 1, 1, 595313, 0, 1, '2024-10-28 19:36:20', '2024-10-29 21:53:29'),
(89, 'NIYONSHUTI Yves', 'Mine', '0790989830', '$2y$10$8zYzQ6ZXTdFe7yOjlf0CJufamCSlG7UffnAsrNWIW0fp0zCop45zu', 'uploads/672871d743d4a.jpg', 694438, 1, 1, 716453, 0, 1, '2024-10-29 00:20:04', '2024-11-04 11:19:32'),
(90, 'Twagiziman0', 'Immacule0000', '0798909821', '$2y$10$BrWhLWVvadUJ8GtTdzYjY.UFxSilVGSgot8ht3bWLqDhPkC6froQu', 'uploads/290609516_1210185779746053_8092756087571678185_n.jpg', 408447, 1, 1, 725475, 0, 1, '2024-10-29 16:54:05', '2024-10-29 19:54:42'),
(91, 'Twahirwa', 'Fabrice', '0787714717', '$2y$10$JG2sgQgH0TDUitUlRFOqFuK8.wkj5lu7UBGUQv.al.6NdgNRBUbvG', 'uploads/6721fbed4333e.jpg', 613169, 1, 1, 639375, 0, 1, '2024-10-30 09:24:22', '2024-10-30 09:27:09'),
(92, 'UKOBIZABA', 'Jean Baptiste', '0788474304', '$2y$10$p2logdzap22OXxlcyz9ukudG2yKmp9mBCv1Msipmp/UGqSzzmwjj6', 'uploads/6723b11613b81.jpg', 810707, 1, 1, 591575, 0, 1, '2024-10-31 16:26:31', '2024-10-31 16:32:22'),
(93, 'l0', 'p0', '079098983', '$2y$10$ai3fTlBK4QOVQMXKm0oSq.sMbMMYx.l0cQuNoOB9T9bc9SPThmLxK', '', 948584, 0, 0, 0, 0, 1, '2024-11-03 10:23:54', '2024-11-03 10:23:54'),
(94, 'ko0', 'ko0', '0790989836', '$2y$10$mPTa/H3h77PIwtqP6yk2FOe/realRk0FdlC7/3jxkD//9y.ES9Jcm', '', 305296, 0, 0, 0, 0, 1, '2024-11-03 20:41:50', '2024-11-03 20:41:50'),
(95, 'K0', 'k0', '079098980', '$2y$10$WInE6K33a.UrNiT8cyHIfu9bPhXfMdyuvCKpbSQxxWYHkEJ/pM.5y', '', 469091, 0, 0, 0, 0, 1, '2024-11-03 20:42:45', '2024-11-03 20:42:45'),
(96, 'MUGIRANEZA Laurent', 'love you', '0733282490', '$2y$10$AnJHXFWTzgw3rrocjapv8eNAhSB/ft0FSfQJFuWHByS21MkzRwoOi', '', 803587, 0, 0, 0, 0, 1, '2024-11-03 20:50:49', '2024-11-03 20:50:49'),
(97, 'KWIZERISEZERANO', 'x', '0785058032', '$2y$10$RR1zg0.KGbWLnNCXf2AUmOd3BWYAwlYA4EobOma/ZC4Z5m.DcEG06', 'uploads/6727e48978b64.jpg', 385747, 1, 1, 793133, 0, 1, '2024-11-03 20:53:56', '2024-11-03 21:02:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tontine`
--
ALTER TABLE `tontine`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tontine`
--
ALTER TABLE `tontine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
