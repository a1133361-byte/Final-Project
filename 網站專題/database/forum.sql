-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-04-05 11:54:14
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `forum`
--

-- --------------------------------------------------------

--
-- 資料表結構 `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, '心情', '分享你的生活點滴'),
(2, '技術', '程式開發與電腦硬體討論'),
(3, '美食', '全台各地美食推薦'),
(4, '遊戲', '主機遊戲與手遊交流');

-- --------------------------------------------------------

--
-- 資料表結構 `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `content`, `created_at`, `parent_id`) VALUES
(1, 1, 1, '確實', '2026-04-03 10:54:27', NULL),
(2, 2, 3, '我推薦你玩Minecraft', '2026-04-03 16:24:11', NULL),
(3, 2, 1, '好啊!我去買來玩看看!', '2026-04-04 11:13:03', NULL),
(4, 4, 3, '你的作業系統是甚麼???', '2026-04-05 17:11:07', NULL),
(5, 4, 1, 'Windows，請問我該怎做?', '2026-04-05 17:20:34', NULL),
(6, 4, 3, '左下角有產品logo，你按下去之後會看到關機的按鈕', '2026-04-05 17:23:16', NULL),
(7, 4, 3, '左下角有產品logo，你按下去之後會看到關機的按鈕', '2026-04-05 17:25:21', NULL),
(8, 4, 3, 'test', '2026-04-05 17:26:53', NULL),
(9, 4, 3, 'aaa', '2026-04-05 17:27:36', NULL),
(10, 4, 3, '回覆功能好難做= =', '2026-04-05 17:32:15', NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `likes`
--

INSERT INTO `likes` (`id`, `user_id`, `post_id`, `created_at`) VALUES
(27, 3, 1, '2026-04-05 04:51:26'),
(31, 3, 3, '2026-04-05 08:55:01'),
(32, 1, 4, '2026-04-05 09:21:01');

-- --------------------------------------------------------

--
-- 資料表結構 `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `posts`
--

INSERT INTO `posts` (`id`, `title`, `content`, `user_id`, `category_id`, `created_at`) VALUES
(1, '八方雲集吃12顆鍋貼太多了嗎？', '如題，今天去買八方雲集。我女朋友吃8顆，我吃12顆。她就很認真的念我說12顆太多了！我覺得好無辜，我今天可是想吃15顆但忍下來了，沒想到還是被念...', 1, 3, '2026-04-03 10:47:12'),
(3, '#徵友 徵人打遊戲', '我是I人 之前也很忙沒時間打遊戲\r\n也徵人很多次了 但大家都不是活人 都在潛水…\r\n希望不管是I人還是E人都能踴躍一點加入\r\n我玩的是猛獸 迷媚 REPO  蓋瑞模組 致命公司\r\novercooked（幾代忘了）幻獸帕魯 高爾夫球\r\n還有想買的多人遊戲 可以一起討論～～\r\n遊玩時間 我目前都可以\r\n但之後可能都要晚上10.30以後\r\n歡迎私訊我 有加過我的潛水的先不用了 謝謝🥲', 3, 4, '2026-04-05 15:11:03'),
(4, '有人可以教我怎麼關機嗎?', '我第一次使用電腦，現在我不知道要按什麼才關機', 1, 2, '2026-04-05 17:10:22');

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` tinyint(1) NOT NULL DEFAULT 0,
  `email` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `profile_img` varchar(255) DEFAULT 'default_avatar.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `email`, `created_at`, `bio`, `profile_img`) VALUES
(1, 'hotdog', '$2y$10$HWl.ASd.sOwQqHECMoZI7uk4SI0THwWRF9O48r1G6jaeDnAPabHji', 0, 'a0903291833@gmail.com', '2026-04-02 20:36:06', '喜愛小動物和運動', 'avatar_1_69d218dec8c460.27607006.jpg'),
(3, 'cookieCat', '$2y$10$2hw6yTyFEv3dilv8UJRMS.XujyJ5y35GAuDcnUwjIh00rngA9nE8O', 0, 'a1133361@mail.nuk.edu.tw', '2026-04-03 16:23:23', '我絕對不會神機錯亂!!!我是會站上夜城頂端的男人!!!', 'avatar_3_69d21a720ba343.42845887.jpg');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- 資料表索引 `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_parent_comment` (`parent_id`);

--
-- 資料表索引 `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_post_like` (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`);

--
-- 資料表索引 `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_parent_comment` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
