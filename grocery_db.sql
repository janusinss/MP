-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2025 at 01:52 PM
-- Server version: 11.6.2-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `grocery_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_percent` int(11) NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_percent`, `expiry_date`, `status`) VALUES
(1, 'WELCOME20', 20, '2030-12-31', 'Active'),
(2, 'FRESH50', 50, '2030-12-31', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `address`, `total_amount`, `status`, `order_date`, `created_at`) VALUES
(1, 1, 'Janus Dominic', 'Maasin Zone 1-B, Zamboanga City', 0.50, 'Cancelled', '2025-11-20 12:13:57', '2025-11-19 12:40:04'),
(3, 1, 'Janus Dominic', 'Maasin', 1.70, 'Cancelled', '2025-11-21 07:16:12', '2025-11-16 12:40:04'),
(4, 1, 'Janus Dominic', 'AD', 3.50, 'Cancelled', '2025-11-21 07:32:59', '2025-11-21 12:40:04'),
(5, 1, 'Janus Dominic', 'THe BAby', 12.70, 'Cancelled', '2025-11-21 08:16:32', '2025-11-17 12:40:04'),
(6, 1, 'Janus Dominic', 'The Zamboanga', 12.70, 'Cancelled', '2025-11-21 08:49:23', '2025-11-20 12:40:04'),
(7, 1, 'Janus Dominic', '123', 1.36, 'Cancelled', '2025-11-21 09:38:27', '2025-11-21 12:40:04'),
(8, 1, 'addadad', 'adad', 0.50, 'Cancelled', '2025-11-21 10:12:25', '2025-11-15 12:40:04'),
(9, 1, 'Janus Dominic', 'Maasin', 5.00, 'Cancelled', '2025-11-21 11:25:37', '2025-11-19 12:40:04'),
(10, 1, 'Janus Dominic', 'Maasin', 0.50, 'Cancelled', '2025-11-21 11:27:32', '2025-11-15 12:40:04'),
(11, 1, 'Janus Dominic', 'Maasin', 0.50, 'Cancelled', '2025-11-21 11:30:38', '2025-11-17 12:40:04'),
(12, NULL, 'adad', 'adad', 1.80, 'Pending', '2025-11-21 12:03:24', '2025-11-18 12:40:04'),
(13, 2, 'Janus Dominic', 'Maasin', 12.30, 'Pending', '2025-11-21 12:39:24', '2025-11-18 12:40:04'),
(14, 2, 'Janus Dominic', 'Maasin', 22.80, 'Pending', '2025-11-21 12:40:38', '2025-11-21 12:40:38');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`) VALUES
(1, 1, 1, 1),
(3, 3, 1, 1),
(4, 3, 2, 1),
(5, 4, 3, 1),
(6, 5, 1, 1),
(7, 5, 2, 1),
(8, 5, 3, 1),
(9, 5, 4, 1),
(10, 5, 5, 1),
(11, NULL, 1, 1),
(12, NULL, 2, 1),
(13, NULL, 3, 1),
(14, NULL, 4, 1),
(15, NULL, 5, 1),
(16, 7, 1, 1),
(17, 7, 2, 1),
(18, 8, 1, 1),
(19, 9, 1, 10),
(20, 10, 1, 1),
(21, 11, 1, 1),
(22, 12, 100, 1),
(23, 13, 97, 1),
(24, 13, 98, 1),
(25, 13, 99, 1),
(26, 13, 100, 1),
(27, 14, 89, 1),
(28, 14, 90, 1),
(29, 14, 91, 1),
(30, 14, 98, 1),
(31, 14, 99, 1),
(32, 14, 100, 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'default.jpg',
  `stock_qty` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image`, `stock_qty`, `image_url`, `category`) VALUES
(1, 'Red Apple', 0.50, '69201ed03f11a.jpg', 491, NULL, 'Fruits'),
(2, 'Banana Bundle', 1.20, '69201ed5c07d2.jpg', 49, NULL, 'Fruits'),
(3, 'Whole Milk', 3.50, '69201edb24b83.jpg', 4, NULL, 'Dairy'),
(4, 'Sourdough Bread', 4.00, '69201ee1a7f8d.jpg', 14, NULL, 'Bakery'),
(5, 'Green Grapes', 3.50, '69201ee7876f3.jpg', 49, NULL, 'Fruits'),
(6, 'Green Apple', 0.55, 'p1.jpg', 100, NULL, 'Fruits'),
(7, 'Red Grapes', 3.50, 'p2.jpg', 50, NULL, 'Fruits'),
(8, 'Watermelon', 5.00, 'p3.jpg', 20, NULL, 'Fruits'),
(9, 'Pineapple', 3.20, 'p4.jpg', 30, NULL, 'Fruits'),
(10, 'Strawberries', 4.50, 'p5.jpg', 40, NULL, 'Fruits'),
(11, 'Blueberries', 4.99, 'p6.jpg', 45, NULL, 'Fruits'),
(12, 'Lemon', 0.40, 'p7.jpg', 150, NULL, 'Fruits'),
(13, 'Lime', 0.45, 'p8.jpg', 120, NULL, 'Fruits'),
(14, 'Peach', 1.10, 'p9.jpg', 60, NULL, 'Fruits'),
(15, 'Pear', 0.90, 'p10.jpg', 70, NULL, 'Fruits'),
(16, 'Cherry Pack', 6.00, 'p11.jpg', 25, NULL, 'Fruits'),
(17, 'Mango', 1.50, 'p12.jpg', 55, NULL, 'Fruits'),
(18, 'Avocado', 1.80, 'p13.jpg', 40, NULL, 'Vegetables'),
(19, 'Carrot Bag', 2.00, 'p14.jpg', 80, NULL, 'Vegetables'),
(20, 'Broccoli', 1.75, 'p15.jpg', 35, NULL, 'Vegetables'),
(21, 'Spinach', 2.50, 'p16.jpg', 40, NULL, 'Vegetables'),
(22, 'Cucumber', 0.80, 'p17.jpg', 90, NULL, 'Vegetables'),
(23, 'Tomato', 0.60, 'p18.jpg', 110, NULL, 'Vegetables'),
(24, 'Potato Bag', 4.50, 'p19.jpg', 60, NULL, 'Vegetables'),
(25, 'Onion Bag', 3.00, 'p20.jpg', 75, NULL, 'Vegetables'),
(26, 'Garlic', 0.50, 'p21.jpg', 200, NULL, 'Vegetables'),
(27, 'Bell Pepper (Red)', 1.20, 'p22.jpg', 50, NULL, 'Vegetables'),
(28, 'Bell Pepper (Green)', 1.00, 'p23.jpg', 55, NULL, 'Vegetables'),
(29, 'Lettuce', 1.50, 'p24.jpg', 40, NULL, 'Vegetables'),
(30, 'Cheddar Cheese', 5.50, 'p25.jpg', 30, NULL, 'Dairy'),
(31, 'Swiss Cheese', 6.00, 'p26.jpg', 25, NULL, 'Dairy'),
(32, 'Yogurt (Plain)', 1.00, 'p27.jpg', 100, NULL, 'Dairy'),
(33, 'Yogurt (Strawberry)', 1.00, 'p28.jpg', 100, NULL, 'Dairy'),
(34, 'Butter', 4.00, 'p29.jpg', 60, NULL, 'Dairy'),
(35, 'Cream Cheese', 2.50, 'p30.jpg', 45, NULL, 'Dairy'),
(36, 'Almond Milk', 3.80, 'p31.jpg', 40, NULL, 'Dairy'),
(37, 'Soy Milk', 3.60, 'p32.jpg', 40, NULL, 'Dairy'),
(38, 'Chocolate Milk', 2.50, 'p33.jpg', 50, NULL, 'Dairy'),
(39, 'Heavy Cream', 3.00, 'p34.jpg', 20, NULL, 'Dairy'),
(40, 'Sour Cream', 1.80, 'p35.jpg', 35, NULL, 'Dairy'),
(41, 'Whole Wheat Bread', 3.50, 'p36.jpg', 30, NULL, 'Bakery'),
(42, 'Bagels (6 Pack)', 4.00, 'p37.jpg', 25, NULL, 'Bakery'),
(43, 'Croissants (4 Pack)', 5.50, 'p38.jpg', 20, NULL, 'Bakery'),
(44, 'Donuts (Dozen)', 8.00, 'p39.jpg', 15, NULL, 'Bakery'),
(45, 'Muffins (Blueberry)', 4.50, 'p40.jpg', 20, NULL, 'Bakery'),
(46, 'Baguette', 2.00, 'p41.jpg', 40, NULL, 'Bakery'),
(47, 'Tortillas', 2.50, 'p42.jpg', 60, NULL, 'Bakery'),
(48, 'Hamburger Buns', 3.00, 'p43.jpg', 50, NULL, 'Bakery'),
(49, 'Hotdog Buns', 3.00, 'p44.jpg', 50, NULL, 'Bakery'),
(50, 'Chocolate Cake', 12.00, 'p45.jpg', 5, NULL, 'Bakery'),
(51, 'Chicken Breast', 8.50, 'p46.jpg', 30, NULL, 'Meat'),
(52, 'Ground Beef', 7.00, 'p47.jpg', 40, NULL, 'Meat'),
(53, 'Steak', 15.00, 'p48.jpg', 15, NULL, 'Meat'),
(54, 'Pork Chops', 9.00, 'p49.jpg', 25, NULL, 'Meat'),
(55, 'Bacon', 6.50, 'p50.jpg', 50, NULL, 'Meat'),
(56, 'Sausages', 5.50, 'p51.jpg', 45, NULL, 'Meat'),
(57, 'Salmon Fillet', 12.00, 'p52.jpg', 20, NULL, 'Meat'),
(58, 'Shrimp', 14.00, 'p53.jpg', 25, NULL, 'Meat'),
(59, 'Tuna Can', 1.50, 'p54.jpg', 150, NULL, 'Meat'),
(60, 'Turkey Slices', 5.00, 'p55.jpg', 40, NULL, 'Meat'),
(61, 'Potato Chips', 3.00, 'p56.jpg', 80, NULL, 'Snacks'),
(62, 'Pretzels', 2.50, 'p57.jpg', 70, NULL, 'Snacks'),
(63, 'Popcorn', 2.00, 'p58.jpg', 60, NULL, 'Snacks'),
(64, 'Chocolate Bar', 1.20, 'p59.jpg', 200, NULL, 'Snacks'),
(65, 'Gummy Bears', 1.50, 'p60.jpg', 100, NULL, 'Snacks'),
(66, 'Cookies', 3.50, 'p61.jpg', 50, NULL, 'Snacks'),
(67, 'Crackers', 2.80, 'p62.jpg', 60, NULL, 'Snacks'),
(68, 'Trail Mix', 4.50, 'p63.jpg', 40, NULL, 'Snacks'),
(69, 'Granola Bars', 3.80, 'p64.jpg', 55, NULL, 'Snacks'),
(70, 'Beef Jerky', 6.00, 'p65.jpg', 30, NULL, 'Snacks'),
(71, 'Soda (Cola)', 1.50, 'p66.jpg', 100, NULL, 'Beverages'),
(72, 'Soda (Lemon)', 1.50, 'p67.jpg', 100, NULL, 'Beverages'),
(73, 'Water Bottle', 1.00, 'p68.jpg', 200, NULL, 'Beverages'),
(74, 'Sparkling Water', 1.20, 'p69.jpg', 150, NULL, 'Beverages'),
(75, 'Iced Tea', 2.00, 'p70.jpg', 80, NULL, 'Beverages'),
(76, 'Lemonade', 2.50, 'p71.jpg', 60, NULL, 'Beverages'),
(77, 'Energy Drink', 3.00, 'p72.jpg', 50, NULL, 'Beverages'),
(78, 'Coffee Beans', 12.00, 'p73.jpg', 30, NULL, 'Beverages'),
(79, 'Green Tea Box', 4.00, 'p74.jpg', 40, NULL, 'Beverages'),
(80, 'Apple Juice', 3.50, 'p75.jpg', 50, NULL, 'Beverages'),
(81, 'Pasta', 1.50, 'p76.jpg', 100, NULL, 'Pantry'),
(82, 'Rice (5lb)', 6.00, 'p77.jpg', 40, NULL, 'Pantry'),
(83, 'Tomato Sauce', 2.00, 'p78.jpg', 80, NULL, 'Pantry'),
(84, 'Olive Oil', 8.00, 'p79.jpg', 30, NULL, 'Pantry'),
(85, 'Vegetable Oil', 4.00, 'p80.jpg', 40, NULL, 'Pantry'),
(86, 'Flour', 3.00, 'p81.jpg', 50, NULL, 'Pantry'),
(87, 'Sugar', 2.50, 'p82.jpg', 60, NULL, 'Pantry'),
(88, 'Salt', 1.00, 'p83.jpg', 100, NULL, 'Pantry'),
(89, 'Pepper', 3.00, 'p84.jpg', 79, NULL, 'Pantry'),
(90, 'Honey', 6.50, 'p85.jpg', 34, NULL, 'Pantry'),
(91, 'Peanut Butter', 4.00, 'p86.jpg', 49, NULL, 'Pantry'),
(92, 'Jam (Strawberry)', 3.50, 'p87.jpg', 45, NULL, 'Pantry'),
(93, 'Ketchup', 2.50, 'p88.jpg', 70, NULL, 'Pantry'),
(94, 'Mustard', 2.00, 'p89.jpg', 75, NULL, 'Pantry'),
(95, 'Mayonnaise', 3.50, 'p90.jpg', 50, NULL, 'Pantry'),
(96, 'Cereal', 4.50, 'p91.jpg', 60, NULL, 'Pantry'),
(97, 'Oatmeal', 3.00, 'p92.jpg', 54, NULL, 'Pantry'),
(98, 'Pancake Mix', 3.50, 'p93.jpg', 38, NULL, 'Pantry'),
(99, 'Syrup', 4.00, 'p94.jpg', 43, NULL, 'Pantry'),
(100, 'Soup Can', 1.80, 'p95.jpg', 97, NULL, 'Pantry'),
(101, 'The Apple', 11.00, '69205ee998415.jpg', 2, NULL, 'Fruits');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `address`, `created_at`) VALUES
(2, 'Janus Dominic', 'janusdominic0@gmail.com', '$2y$10$BHJby08kX2GjWffDCng9DeClUPACH.F7IKcWr0m1oQHKVNubFhWBK', 'Maasin', '2025-11-21 12:39:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=257;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
