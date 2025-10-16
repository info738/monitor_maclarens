<?php
// create_tables.php – kompletní vytvoření všech tabulek
require 'db.php';

$sqls = [
    // Tabulka objednávek
    "CREATE TABLE IF NOT EXISTS orders (
        id VARCHAR(50) PRIMARY KEY,
        created DATETIME,
        note TEXT,
        table_name VARCHAR(100),
        delivery_service VARCHAR(50),
        delivery_note TEXT,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

    // Tabulka položek objednávek s přidaným product_id
    "CREATE TABLE IF NOT EXISTS order_items (
        id VARCHAR(50) PRIMARY KEY,
        order_id VARCHAR(50),
        product_id VARCHAR(50) DEFAULT NULL,
        name VARCHAR(255),
        quantity INT,
        kitchen_status ENUM('new','in-progress','completed','passed','reordered') DEFAULT 'new',
        note TEXT,
        shown TINYINT(1) DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

    // Nepovinná tabulka pro podpoložky
    "CREATE TABLE IF NOT EXISTS order_item_subitems (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_item_id VARCHAR(50),
        name VARCHAR(255),
        quantity INT,
        FOREIGN KEY (order_item_id) REFERENCES order_items(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

    // Tabulka pro vyloučené produkty
    "CREATE TABLE IF NOT EXISTS excluded_products (
        product_id VARCHAR(50) PRIMARY KEY
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
];

foreach ($sqls as $sql) {
    $stmt = $db->prepare($sql);
    if ($stmt->execute()) {
        echo "Tabulka byla vytvořena nebo již existuje.<br>";
    } else {
        $errorInfo = $stmt->errorInfo();
        echo "Chyba při vytváření tabulky: " . $errorInfo[2] . "<br>";
    }
}
?>
