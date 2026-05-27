-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 27, 2026 at 03:39 PM
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
-- Database: `lms`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_attempts`
--

CREATE TABLE `tbl_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(255) NOT NULL,
  `try_time` bigint(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_league`
--

CREATE TABLE `tbl_league` (
  `id` int(11) NOT NULL,
  `league_image` varchar(255) NOT NULL,
  `league_name` varchar(255) NOT NULL,
  `sport` varchar(255) NOT NULL,
  `division` varchar(255) NOT NULL,
  `descrip` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `format` varchar(255) NOT NULL,
  `start_date` varchar(255) NOT NULL,
  `end_date` varchar(255) NOT NULL,
  `regis_start` varchar(255) NOT NULL,
  `regis_end` varchar(255) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `max_team` varchar(255) NOT NULL,
  `max_player` varchar(255) NOT NULL,
  `regis_fee` varchar(255) NOT NULL,
  `add_info` varchar(255) NOT NULL,
  `stat` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_league`
--

INSERT INTO `tbl_league` (`id`, `league_image`, `league_name`, `sport`, `division`, `descrip`, `category`, `format`, `start_date`, `end_date`, `regis_start`, `regis_end`, `venue`, `max_team`, `max_player`, `regis_fee`, `add_info`, `stat`, `created`) VALUES
(7, '1779861896_test-image.jpeg', 'basketball', 'Volleyball', 'Under - 18', '654', 'Category 1', 'Single Elimination', '2026-05-27', '2026-05-27', '2026-05-27', '2026-05-27', '654', '16', '12', '654', '654', 'Upcoming', '2026-05-27 14:04:56');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_player`
--

CREATE TABLE `tbl_player` (
  `id` int(11) NOT NULL,
  `team_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `age` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `gender` varchar(255) NOT NULL,
  `jersey_number` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_player`
--

INSERT INTO `tbl_player` (`id`, `team_id`, `name`, `age`, `role`, `gender`, `jersey_number`, `created`) VALUES
(1, '2', 'tes', '54654', '231', 'Female', '211', '2026-05-27 15:21:06'),
(2, '2', 'teet', '321', '321', 'Male', '321', '2026-05-27 15:21:06'),
(3, '2', '321', '1', '32', 'Male', '1', '2026-05-27 15:21:06'),
(4, '2', '321', '321', '32', 'Male', '132', '2026-05-27 15:21:06'),
(5, '2', '321', '321', '321', 'Male', '55', '2026-05-27 15:21:06'),
(6, '3', 'dasd', '32', '123', 'Male', '412', '2026-05-27 18:17:02'),
(7, '3', 'assdda', '32', '123', 'Male', '23123', '2026-05-27 18:17:02'),
(8, '3', 'asd', '32', '123', 'Male', '123', '2026-05-27 18:17:02'),
(9, '3', 'as', '32', '123', 'Male', '12312312', '2026-05-27 18:17:02'),
(10, '3', 'daasdasds', '32', '123', 'Male', '3123', '2026-05-27 18:17:02');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_schedule`
--

CREATE TABLE `tbl_schedule` (
  `id` int(11) NOT NULL,
  `league_id` varchar(255) NOT NULL,
  `team_a` varchar(255) NOT NULL,
  `a_score` varchar(255) NOT NULL,
  `team_b` varchar(255) NOT NULL,
  `b_score` varchar(255) NOT NULL,
  `date` varchar(255) NOT NULL,
  `time` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `winner` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_schedule`
--

INSERT INTO `tbl_schedule` (`id`, `league_id`, `team_a`, `a_score`, `team_b`, `b_score`, `date`, `time`, `status`, `winner`, `created`) VALUES
(1, '7', '2', '', '3', '', '2026-05-27', '21:23', 'Upcoming', '', '2026-05-27 21:24:32'),
(2, '7', '3', '', '3', '', '2026-05-27', '21:38', 'Upcoming', '', '2026-05-27 21:38:20'),
(3, '7', '3', '', '2', '', '2026-05-30', '21:38', 'Upcoming', '', '2026-05-27 21:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_team`
--

CREATE TABLE `tbl_team` (
  `id` int(11) NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `team_image` varchar(255) NOT NULL,
  `league_id` varchar(255) NOT NULL,
  `bracket` varchar(255) NOT NULL,
  `team_repre` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_team`
--

INSERT INTO `tbl_team` (`id`, `team_name`, `team_image`, `league_id`, `bracket`, `team_repre`, `email`, `phone`, `status`, `user_id`, `created`) VALUES
(2, 'test', '1779866432_CITY_SPORTS-removebg-preview.png', '7', '', 'juan dela cruz', 'juan@gmail.com', '091234567897', 'rejected', '11', '2026-05-27 15:21:06'),
(3, 'test1', '1779877022_image5.avif', '7', '', 'team_repre', 'juan@test.com', '2536054654', 'Pending', '11', '2026-05-27 18:17:02');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `id` int(11) NOT NULL,
  `res_id` varchar(255) NOT NULL,
  `fname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `mname` varchar(255) NOT NULL,
  `suffix` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL COMMENT '1 = ORGANIZER\r\n2 = TEAM REPRE',
  `stat` varchar(255) NOT NULL DEFAULT '1',
  `pass_stat` int(11) NOT NULL COMMENT '0=change\r\n1 = hindi pa binabago',
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`id`, `res_id`, `fname`, `lname`, `mname`, `suffix`, `phone`, `email`, `pass`, `role`, `stat`, `pass_stat`, `created`) VALUES
(10, '', 'test', 'test', 'test', 'Sr.', '06546546546', 'admin@gmail.com', '$2y$10$9p/fIuFyjiym9QLwTtmdb.fGiyizX7pjusAn3HH/.f3K4cM7Pb4tC', '1', '1', 0, '2026-05-25 20:36:51'),
(11, '', 'admin', 'admin', 'admin', '', '06964565465', 'example@gmail.com', '$2y$10$yF09s9Oawe6ecqERcmnD/.cMVmlgsSgD7v95UAjx8corLDlUfMfYq', '2', '1', 0, '2026-05-25 20:37:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_attempts`
--
ALTER TABLE `tbl_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_league`
--
ALTER TABLE `tbl_league`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_player`
--
ALTER TABLE `tbl_player`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_schedule`
--
ALTER TABLE `tbl_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_team`
--
ALTER TABLE `tbl_team`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_attempts`
--
ALTER TABLE `tbl_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tbl_league`
--
ALTER TABLE `tbl_league`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_player`
--
ALTER TABLE `tbl_player`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_schedule`
--
ALTER TABLE `tbl_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_team`
--
ALTER TABLE `tbl_team`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
