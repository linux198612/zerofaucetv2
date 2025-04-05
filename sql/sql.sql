CREATE TABLE `admin_users` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `admin_users` (`id`, `username`, `password_hash`) VALUES
(1, 'admin', '$2y$10$W9hvVqLady2ivV791Nz9zOeqvjASvUTYxlcA9kW25EROz1RgjVsai');


CREATE TABLE IF NOT EXISTS `settings` (
`id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `value` varchar(400) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=latin1;

INSERT INTO `settings` (`id`, `name`, `value`) VALUES
(1, 'faucet_name', 'CoolFaucet V2'),
(2, 'maintenance', 'on'),
(3, 'zerads_id', ''),
(4, 'zeradsptc_status', 'on'),
(5, 'timer', '60'),
(6, 'daily_limit', '200'),
(7, 'reward', '0.0005'),
(8, 'zerochain_api', ''),
(9, 'zerochain_privatekey', ''),
(11, 'claim_enabled', 'on'),
(14, 'vpn_shield', 'no'),
(15, 'referral_percent', '20'),
(16, 'reverse_proxy', 'no'),
(17, 'bonus_reward_coin', '0.01'),
(18, 'bonus_reward_xp', '10'),
(19, 'bonus_faucet_require', '1'),
(22, 'iphub_api_key', ''),
(23, 'min_withdrawal_gateway', '100000'),
(26, 'hcaptcha_pub_key', ''),
(27, 'hcaptcha_sec_key', ''),
(29, 'currency_value', '0.0136'),
(30, 'reward_last_check', '1741678646'),
(31, 'level_system', 'on'),
(33, 'bonusmaxlevel', '5000'),
(34, 'bonuslevelxp', '100'),
(35, 'bonuslevelvalue', '0.1'),
(36, 'xpreward', '1'),
(38, 'offerwalls_status', 'on'),
(39, 'bitcotasks_api_key', ''),
(40, 'bitcotasks_secret_key', ''),
(41, 'bitcotasks_status', 'on'),
(42, 'coingecko_status', 'on'),
(43, 'shortlink_status', 'on'),
(44, 'achievements_status', 'on'),
(45, 'dailybonus_status', 'on'),
(46, 'manual_withdraw', 'off'),
(47, 'autofaucet_reward', '0.00005'),
(10, 'website_url', 'https://coolfaucet.hu/'),
(48, 'autofaucet_interval', '30'),
(49, 'rewardEnergy', '1'),
(50, 'autofocus', 'off'),
(51, 'autofaucet_status', 'on'),
(52, 'bitcotasks_bearer_token', ''),
(53, 'energyshop_status', 'on'),
(54, 'bitcotasks_ptc_status', 'on'),
(55, 'bitcotasks_shortlink_status', 'on'),
(56, 'credit_value', '0.001'),
(57, 'ptc_status', 'on'),
(58, 'deposit_status', 'on'),
(59, 'smtp_server', ''),
(60, 'smtp_port', '587'),
(61, 'smtp_user', ''),
(62, 'smtp_pass', ''),
(63, 'smtp_ssl', 'on');


CREATE TABLE IF NOT EXISTS `white_list` (
  `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `transactions` (
`id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userid` int(32) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,8) NOT NULL,
  `timestamp` int(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    address VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(50) DEFAULT NULL,
    balance decimal(20,8) NOT NULL,
    deposit DECIMAL(18, 8) DEFAULT 0,
    ads_credit INT DEFAULT 0,
    total_withdrawals decimal(20,8) DEFAULT 0,
    last_activity int(32) NOT NULL DEFAULT UNIX_TIMESTAMP(),
    level int(32) DEFAULT 0,
    xp int(32) DEFAULT 0,
	  auto_token VARCHAR(64) DEFAULT NULL,
    joined int(32) NOT NULL,
    referred_by INT NOT NULL DEFAULT 0,
    referral_earnings DECIMAL(15,8) NOT NULL DEFAULT 0.00000000,
    energy int(32) DEFAULT 0,
    last_autofaucet DATETIME DEFAULT NULL,
    last_claim int(32) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Kifizetések tábla
CREATE TABLE withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount decimal(10,8) NOT NULL,
    txid varchar(400) NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Paid', 'Rejected') NOT NULL DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE withdrawals ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'ZER';

CREATE TABLE IF NOT EXISTS `offerwalls_history` (
`id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userid` int(32) NOT NULL,
  `offerwalls` varchar(50) NOT NULL,
  `offerwalls_name` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `timestamp` int(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `shortlinks_list`(
  `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `url` varchar(250) NOT NULL, 
  `timer` int(32) NOT NULL, 
  `reward` decimal(20,8) NOT NULL, 
  `limit_view` int(32) NOT NULL,
  PRIMARY KEY (`id`)
  )ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `shortlinks_viewed`(
 `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
 `userid` int(32) NOT NULL, 
 `slid` int(32) NOT NULL, 
 `ip_address` varchar(150) NOT NULL, 
 `timestamp` int(32) NOT NULL, 
 `timestamp_expiry` int(32) NOT NULL,
  PRIMARY KEY (`id`)
 ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `shortlinks_views`(
 `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
 `userid` int(32) NOT NULL, 
 `slid` int(32) NOT NULL, 
 `claim_key` varchar(10) NOT NULL, 
 `shortlink` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
 ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

 CREATE TABLE IF NOT EXISTS `achievements` (
  `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `condition` int(32) UNSIGNED NOT NULL,
  `reward` decimal(10,6) DEFAULT 0.000000,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `achievement_history` (
  `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `achievement_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(32) UNSIGNED NOT NULL,
  `claim_time` int(32) UNSIGNED,
  `amount` decimal(10,6) DEFAULT 0.000000,
  `claimed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `bonus_history` (
  `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(32) UNSIGNED NOT NULL,
  `bonus_id` int(32) UNSIGNED NOT NULL,
  `bonus_date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `banned_ip` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `reason` TEXT,
    `banned_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `banned_address` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `address` VARCHAR(255) NOT NULL,
    `reason` TEXT,
    `banned_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `energyshop_packages` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `energy_cost` INT(11) NOT NULL,
    `zero_amount` DECIMAL(10,8) NOT NULL
);

-- Adatok beszúrása a ptc_packages táblába
INSERT INTO ptc_packages (name, duration_seconds, zer_cost, reward) VALUES
('5 seconds', 5, 0.002, 0.001),
('10 seconds', 10, 0.004, 0.002),
('30 seconds', 30, 0.012, 0.0060),
('50 seconds', 50, 0.020, 0.010);

CREATE TABLE deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address VARCHAR(255) NOT NULL,
    private_key VARCHAR(255) NOT NULL,
    amount DECIMAL(18, 8) DEFAULT 0,
    status ENUM('Pending', 'Completed', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE ptc_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration_seconds INT NOT NULL,
    zer_cost DECIMAL(10,8) NOT NULL,
    reward DECIMAL(10,8) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO ptc_packages (name, duration_seconds, zer_cost, reward) VALUES
('5 seconds', 5, 0.001, 0.0005),
('10 seconds', 10, 0.002, 0.0010),
('30 seconds', 30, 0.006, 0.0030);

CREATE TABLE user_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    package_id INT NOT NULL,
    views_remaining INT NOT NULL DEFAULT 0,
    status ENUM('Pending', 'Active', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (package_id) REFERENCES ptc_packages(id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE ptc_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ad_id INT NOT NULL,
    reward DECIMAL(10, 8) NOT NULL,
    viewed_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (ad_id) REFERENCES user_ads(id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE deposits ADD COLUMN withdrawn ENUM('No', 'Yes') DEFAULT 'No';

ALTER TABLE user_ads ADD COLUMN ad_type ENUM('window', 'iframe') NOT NULL DEFAULT 'window';