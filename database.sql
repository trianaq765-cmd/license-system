CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(100) DEFAULT 'MyTool',
    license_type ENUM('TRIAL','BASIC','PRO','ENTERPRISE','LIFETIME') DEFAULT 'BASIC',
    status ENUM('active','used','expired','revoked') DEFAULT 'active',
    max_activations INT DEFAULT 1,
    current_activations INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    customer_email VARCHAR(255) NULL,
    notes TEXT NULL
);

CREATE TABLE IF NOT EXISTS activations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    hardware_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);

CREATE INDEX idx_license_key ON licenses(license_key);
CREATE INDEX idx_hardware ON activations(hardware_id);
