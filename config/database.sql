CREATE DATABASE IF NOT EXISTS smart_route_ai;
USE smart_route_ai;

-- Users table with role-based access
CREATE TABLE users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'rider') NOT NULL DEFAULT 'rider',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders table for delivery packages
CREATE TABLE orders (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    tracking_number VARCHAR(50) NOT NULL UNIQUE,
    recipient_name VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    address TEXT,
    status ENUM('pending', 'in_transit', 'delivered', 'failed') DEFAULT 'pending',
    assigned_rider_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_rider_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vehicles table
CREATE TABLE vehicles (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50),
    capacity INT,
    status ENUM('available', 'in_use', 'maintenance') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a default admin account (password: admin123)
INSERT INTO users (username, email, password, role) 
VALUES ('admin', 'admin@smartroute.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlWiXo.1rV2HlVnG7Y5Lq8Q9L5K8y2', 'admin');