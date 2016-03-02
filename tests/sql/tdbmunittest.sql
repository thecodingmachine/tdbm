-- phpMyAdmin SQL Dump
-- version 4.2.12deb2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 24, 2015 at 02:02 PM
-- Server version: 5.6.25-0ubuntu0.15.04.1
-- PHP Version: 5.6.10-1+deb.sury.org~utopic+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `tdbm_testcase`
--

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE IF NOT EXISTS `contact` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `contact`
--

INSERT INTO `contact` (`id`, `email`) VALUES
  (1, 'john@smith.com'),
  (2, 'jean@dupont.com'),
  (3, 'robert@marley.com'),
  (4, 'bill@shakespeare.com');

-- --------------------------------------------------------

--
-- Table structure for table `country`
--

CREATE TABLE IF NOT EXISTS `country` (
  `id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `country`
--

INSERT INTO `country` (`id`, `label`) VALUES
  (1, 'France'),
  (2, 'UK'),
  (3, 'Jamaica');

-- --------------------------------------------------------

--
-- Table structure for table `person`
--

CREATE TABLE IF NOT EXISTS `person` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` datetime NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `person`
--

INSERT INTO `person` (`id`, `name`, `created_at`) VALUES
  (1, 'John Smith', '2015-10-24 11:57:13'),
  (2, 'Jean Dupont', '2015-10-24 11:57:13'),
  (3, 'Robert Marley', '2015-10-24 11:57:13'),
  (4, 'Bill Shakespeare', '2015-10-24 11:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `rights`
--

CREATE TABLE IF NOT EXISTS `rights` (
  `label` varchar(255) NOT NULL COMMENT 'Non autoincrementable primary key'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `rights`
--

INSERT INTO `rights` (`label`) VALUES
  ('CAN_SING'),
  ('CAN_WRITE');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` date NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
  (1, 'Admins', '2015-10-24'),
  (2, 'Writers', '2015-10-24'),
  (3, 'Singers', '2015-10-24');

-- --------------------------------------------------------

--
-- Table structure for table `roles_rights`
--

CREATE TABLE IF NOT EXISTS `roles_rights` (
  `role_id` int(11) NOT NULL,
  `right_label` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `roles_rights`
--

INSERT INTO `roles_rights` (`role_id`, `right_label`) VALUES
  (1, 'CAN_SING'),
  (3, 'CAN_SING'),
  (1, 'CAN_WRITE'),
  (2, 'CAN_WRITE');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `login` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `country_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `login`, `password`, `status`, `country_id`) VALUES
  (1, 'john.smith', NULL, 'on', 2),
  (2, 'jean.dupont', NULL, 'on', 1),
  (3, 'robert.marley', NULL, 'off', 3),
  (4, 'bill.shakespeare', NULL, 'off', 2);

-- --------------------------------------------------------

--
-- Table structure for table `users_roles`
--

CREATE TABLE IF NOT EXISTS `users_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users_roles`
--

INSERT INTO `users_roles` (`id`, `user_id`, `role_id`) VALUES
  (6, 1, 1),
  (7, 2, 1),
  (8, 3, 3),
  (9, 4, 2),
  (10, 3, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contact`
--
ALTER TABLE `contact`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `country`
--
ALTER TABLE `country`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `person`
--
ALTER TABLE `person`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rights`
--
ALTER TABLE `rights`
ADD PRIMARY KEY (`label`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles_rights`
--
ALTER TABLE `roles_rights`
ADD PRIMARY KEY (`role_id`,`right_label`), ADD KEY `right_label` (`right_label`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
ADD PRIMARY KEY (`id`), ADD KEY `country_id` (`country_id`);

--
-- Indexes for table `users_roles`
--
ALTER TABLE `users_roles`
ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`), ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `country`
--
ALTER TABLE `country`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `person`
--
ALTER TABLE `person`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table `users_roles`
--
ALTER TABLE `users_roles`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=11;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `contact`
--
ALTER TABLE `contact`
ADD CONSTRAINT `contact_ibfk_1` FOREIGN KEY (`id`) REFERENCES `person` (`id`);

--
-- Constraints for table `roles_rights`
--
ALTER TABLE `roles_rights`
ADD CONSTRAINT `roles_rights_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
ADD CONSTRAINT `roles_rights_ibfk_2` FOREIGN KEY (`right_label`) REFERENCES `rights` (`label`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`),
ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`id`) REFERENCES `contact` (`id`);

--
-- Constraints for table `users_roles`
--
ALTER TABLE `users_roles`
ADD CONSTRAINT `users_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
ADD CONSTRAINT `users_roles_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

-- Add index
CREATE UNIQUE INDEX users_login_idx ON users (login);
CREATE INDEX users_status_country_idx ON users (status, country_id);


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;