--
-- Структура базы данных
--

-- --------------------------------------------------------

--
-- Структура таблицы `a_log`
--

CREATE TABLE `a_log` (
  `id` int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bot_id` int DEFAULT NULL,
  `type` varchar(10) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `a_log`
  ADD KEY `created` (`created`),
  ADD KEY `type` (`type`),
  ADD KEY `bot_id` (`bot_id`);

--
-- Структура таблицы `a_websites`
--

CREATE TABLE `a_websites` (
  `id` int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `url` varchar(250) NOT NULL,
  `active` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `a_websites`
  ADD KEY `updated` (`updated`);

--
-- Структура таблицы `bot_chats`
--

CREATE TABLE `bot_chats` (
  `bot_id` int NOT NULL,
  `chat_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `bot_chats`
  ADD UNIQUE KEY `bot_id_chat_id_type` (`bot_id`,`chat_id`,`type`) USING BTREE,
  ADD KEY `bot_id` (`bot_id`);

--
-- Структура таблицы `bot_options`
--

CREATE TABLE `bot_options` (
  `id` int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `bot_id` int NOT NULL,
  `key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `bot_options`
  ADD UNIQUE KEY `bot_id_key` (`bot_id`,`key`) USING BTREE,
  ADD KEY `bot_id` (`bot_id`);

--
-- Структура таблицы `bot_updates`
--

CREATE TABLE `bot_updates` (
  `bot_id` int NOT NULL AUTO_INCREMENT,
  `update_id` bigint NOT NULL,
  `cmd` varchar(30) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `value` json NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `bot_updates`
  ADD UNIQUE KEY `bot_id` (`bot_id`,`update_id`),
  ADD KEY `created` (`created`),
  ADD KEY `cmd` (`cmd`);

--
-- Структура таблицы `bot_log`
--

CREATE TABLE `bot_log` (
  `id` int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bot_id` int DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `request` json DEFAULT NULL,
  `response` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `bot_log`
  ADD KEY `created` (`created`),
  ADD KEY `action` (`action`),
  ADD KEY `bot_id` (`bot_id`);

--
-- Структура таблицы `postings`
--

CREATE TABLE `postings` (
  `id` int NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bot_id` int NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `posting_number` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `data` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `postings`
  ADD UNIQUE KEY `bot_id_2` (`bot_id`,`type`,`posting_number`,`status`),
  ADD KEY `created` (`created`),
  ADD KEY `type` (`type`),
  ADD KEY `bot_id` (`bot_id`);

--
-- Структура таблицы `posting_status`
--

CREATE TABLE `posting_status` (
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `bot_id` int NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `posting_number` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `sup_status` varchar(50) NOT NULL DEFAULT '',
  `message_id` json,
  `order_text` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `posting_status`
  ADD UNIQUE KEY `bot_id_2` (`bot_id`,`type`,`posting_number`),
  ADD KEY `status` (`status`),
  ADD KEY `created` (`created`),
  ADD KEY `updated` (`updated`),
  ADD KEY `type` (`type`),
  ADD KEY `bot_id` (`bot_id`);
