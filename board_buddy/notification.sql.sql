-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('customer', 'admin') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_important TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample customer notifications
INSERT INTO notifications (user_type, title, message, is_important) VALUES
('customer', 'Payment Reminder', 'Your next rental payment is due on September 15, 2025.', 0),
('customer', 'Room Maintenance', 'Scheduled maintenance will occur on September 12, 2025 from 10AM to 1PM.', 1),
('customer', 'New House Listing', 'A new house is available near your university.', 0);

-- Insert sample admin notifications
INSERT INTO notifications (user_type, title, message, is_important) VALUES
('admin', 'New Tenant Request', 'A new customer has applied for Room #B12.', 0),
('admin', 'System Update', 'The Board Buddy platform will undergo maintenance on September 13, 2025.', 0),
('admin', 'Payment Received', 'Tenant John Doe has successfully paid for September.', 1);
