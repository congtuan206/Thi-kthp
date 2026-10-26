CREATE TABLE IF NOT EXISTS suppliers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'cái',
  price DECIMAL(14,2) NOT NULL DEFAULT 0 CHECK (price >= 0),
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  min_quantity INT UNSIGNED NOT NULL DEFAULT 5,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_name (name),
  INDEX idx_products_stock (quantity, min_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('IN','OUT') NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movement_product FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_movements_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO suppliers(name,phone,email,address) VALUES
('Công ty TNHH Minh Phát','0208 123 456','minhphat@example.com','Thái Nguyên'),
('Nhà cung cấp An Khang','0208 987 654','ankhang@example.com','Hà Nội');

INSERT INTO products(sku,name,unit,price,quantity,min_quantity) VALUES
('SP-001','Giấy in A4','ram',78000,36,10),
('SP-002','Bút bi xanh','chiếc',5000,120,30),
('SP-003','Sổ ghi chép','quyển',18000,8,10),
('SP-004','Mực máy in','hộp',245000,14,4);

INSERT INTO stock_movements(product_id,movement_type,quantity,note) VALUES
(1,'IN',36,'Nhập kho ban đầu'),
(2,'IN',120,'Nhập kho ban đầu'),
(3,'IN',8,'Nhập kho ban đầu'),
(4,'IN',14,'Nhập kho ban đầu');

