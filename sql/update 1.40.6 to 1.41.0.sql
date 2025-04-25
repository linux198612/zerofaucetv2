CREATE TABLE `currencies` (
  `id` int(32) UNSIGNED NOT NULL AUTO_INCREMENT,
  `currency_name` varchar(75) NOT NULL,
  `code` varchar(75) NOT NULL,
  `price` decimal(20,6) NOT NULL,
  `wallet` varchar(20) NOT NULL,
  `minimum_withdrawal` decimal(30,2) DEFAULT 0.01,
  `timestamp` int(32) NOT NULL,
  `status` varchar(75) NOT NULL DEFAULT 'off',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `currencies` (`id`, `currency_name`, `code`, `price`, `wallet`, `minimum_withdrawal`, `timestamp`, `status`) VALUES
(1, 'litecoin', 'LTC', 82.270000, 'faucetpay', 0.10, 1743920909, 'on'),
(2, 'pepe', 'PEPE', 0.000007, 'faucetpay', 0.10, 1743920909, 'on'),
(3, 'solana', 'SOL', 120.310000, 'faucetpay', 0.10, 1743920909, 'on');


INSERT INTO `settings` (`id`, `name`, `value`) VALUES
(64, 'faucetpay_mode', 'off'),
(65, 'faucetpay_api_key', ''),
(66, 'withdrawlimithour', '1');

ALTER TABLE withdrawals 
ADD COLUMN zer_value DECIMAL(20,8) DEFAULT NULL AFTER amount;

ALTER TABLE withdrawals 
MODIFY COLUMN amount DECIMAL(20,8);

CREATE TABLE banned_username (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE wallet_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE deposits ADD COLUMN wallet_id INT DEFAULT NULL;
ALTER TABLE deposits DROP COLUMN private_key, DROP COLUMN withdrawn;


