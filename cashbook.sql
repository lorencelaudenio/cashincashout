CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(150),
    plan ENUM('free','pro') DEFAULT 'free',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT,
    fullname VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('owner','staff') DEFAULT 'owner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT,
    user_id INT,
    type ENUM('cash_in','cash_out'),
    amount DECIMAL(12,2),
    fee DECIMAL(12,2) DEFAULT 0,
    customer_name VARCHAR(100),
    notes TEXT,
    status ENUM('claimed','unclaimed') DEFAULT 'claimed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);