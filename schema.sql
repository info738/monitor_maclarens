-- schema.sql

-- Tabulka objednávek
CREATE TABLE orders (
    id VARCHAR(50) PRIMARY KEY,           -- ID objednávky z Dotykacky
    created DATETIME,
    note TEXT,
    table_name VARCHAR(100),
    delivery_service VARCHAR(50),
    delivery_note TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabulka položek objednávek
CREATE TABLE order_items (
    id VARCHAR(50) PRIMARY KEY,           -- ID položky
    order_id VARCHAR(50),
    name VARCHAR(255),
    quantity INT,
    kitchen_status ENUM('new','in-progress','completed','passed','reordered') DEFAULT 'new',
    note TEXT,
    shown TINYINT(1) DEFAULT 0,           -- příznak, zda již byla položka "notifikována" (zobrazená)
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Nepovinná tabulka pro podpoložky (např. přizpůsobení)
CREATE TABLE order_item_subitems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id VARCHAR(50),
    name VARCHAR(255),
    quantity INT,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id)
);

-- Přidání sloupce product_id do tabulky order_items (pokud již tabulka existuje)
ALTER TABLE order_items
    ADD COLUMN product_id VARCHAR(50) DEFAULT NULL;

-- Vytvoření tabulky pro vyloučené produkty
CREATE TABLE IF NOT EXISTS excluded_products (
    product_id VARCHAR(50) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
