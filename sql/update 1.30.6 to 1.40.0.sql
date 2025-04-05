-- A régi users tábla átnevezése oldusers-re
RENAME TABLE users TO oldusers;

-- Új users tábla létrehozása
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

-- Új deposits tábla létrehozása
CREATE TABLE deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address VARCHAR(255) NOT NULL,
    private_key VARCHAR(255) NOT NULL,
    amount DECIMAL(18, 8) DEFAULT 0,
    status ENUM('Pending', 'Completed', 'Rejected') DEFAULT 'Pending',
    withdrawn ENUM('No', 'Yes') DEFAULT 'No',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Új ptc_packages tábla létrehozása
CREATE TABLE ptc_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration_seconds INT NOT NULL,
    zer_cost DECIMAL(10,8) NOT NULL,
    reward DECIMAL(10,8) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Adatok beszúrása a ptc_packages táblába
INSERT INTO ptc_packages (name, duration_seconds, zer_cost, reward) VALUES
('5 seconds', 5, 0.002, 0.001),
('10 seconds', 10, 0.004, 0.002),
('30 seconds', 30, 0.012, 0.0060),
('50 seconds', 50, 0.020, 0.010);

-- Új user_ads tábla létrehozása
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

-- Új ptc_history tábla létrehozása
CREATE TABLE ptc_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ad_id INT NOT NULL,
    reward DECIMAL(10, 8) NOT NULL,
    viewed_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (ad_id) REFERENCES user_ads(id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Új adatok beszúrása a settings táblába
INSERT INTO settings (id, name, value) VALUES
(56, 'credit_value', '0.001'),
(57, 'ptc_status', 'on'),
(58, 'deposit_status', 'on'),
(59, 'smtp_server', ''),
(60, 'smtp_port', '587'),
(61, 'smtp_user', ''),
(62, 'smtp_pass', ''),
(63, 'smtp_ssl', 'on');

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- A ptc_status sor módosítása zeradsptc-re
UPDATE settings SET name = 'zeradsptc_status' WHERE id = 4;

