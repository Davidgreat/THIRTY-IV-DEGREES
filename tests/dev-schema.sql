-- Development fixture reconstructed from the source queries. Never apply to production.
CREATE TABLE product_reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,user_id INT,stars INT,body TEXT,approved TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE meal_option_groups(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,name VARCHAR(90),required TINYINT DEFAULT 0,max_select INT DEFAULT 1,sort_order INT DEFAULT 0);
CREATE TABLE meal_option_choices(id INT AUTO_INCREMENT PRIMARY KEY,group_id INT,name VARCHAR(90),price_delta DECIMAL(12,2) DEFAULT 0,sort_order INT DEFAULT 0);
ALTER TABLE order_items ADD customizations TEXT;
CREATE TABLE product_inventory(product_id INT PRIMARY KEY,track_stock TINYINT DEFAULT 0,stock_qty INT DEFAULT 0,low_stock_threshold INT DEFAULT 5);
CREATE TABLE inventory_movements(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,order_id INT,delta INT,reason VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE stock_reservations(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,order_id INT,quantity INT,expires_at DATETIME);
CREATE TABLE booking_blackouts(id INT AUTO_INCREMENT PRIMARY KEY,day DATE,kind VARCHAR(20),reason VARCHAR(500));
CREATE TABLE order_gifts(order_id INT PRIMARY KEY,recipient_name VARCHAR(150),recipient_phone VARCHAR(60),recipient_address TEXT,gift_message TEXT,anonymous_sender TINYINT DEFAULT 0);
CREATE TABLE order_schedules(order_id INT PRIMARY KEY,requested_for DATETIME);
CREATE TABLE order_status_events(id INT AUTO_INCREMENT PRIMARY KEY,order_id INT,status VARCHAR(30),note TEXT,actor_id INT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE site_banners(id INT AUTO_INCREMENT PRIMARY KEY,placement VARCHAR(30),headline VARCHAR(150),subtitle VARCHAR(250),image_url VARCHAR(1000),target_url VARCHAR(500),button_text VARCHAR(45),enabled TINYINT DEFAULT 1,sort_order INT DEFAULT 0);
CREATE TABLE payment_webhook_events(event_hash VARCHAR(64) PRIMARY KEY,event_type VARCHAR(80),payload_excerpt TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE refund_requests(id INT AUTO_INCREMENT PRIMARY KEY,payment_id INT,reason VARCHAR(500),requested_by INT,status VARCHAR(30),amount DECIMAL(12,2),gateway_refund_id VARCHAR(100),gateway_status VARCHAR(50),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE staff_permissions(user_id INT,permission VARCHAR(60),allowed TINYINT DEFAULT 0,PRIMARY KEY(user_id,permission));
CREATE TABLE email_queue(id INT AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(190) UNIQUE,recipient VARCHAR(190),subject VARCHAR(190),body TEXT,status VARCHAR(20),available_at DATETIME,attempts INT DEFAULT 0,locked_at DATETIME,last_error TEXT,sent_at DATETIME,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
INSERT INTO settings VALUES('app_version','5.8.3');
