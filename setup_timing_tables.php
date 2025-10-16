<?php
require_once 'db.php';

try {
    echo "Vytváření tabulek pro sledování časů...\n";
    
    // Tabulka pro sledování časů celých objednávek
    $sql1 = "CREATE TABLE IF NOT EXISTS order_timing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) NOT NULL,
        table_name VARCHAR(100),
        delivery_service VARCHAR(100),
        
        -- Časové značky
        created_at DATETIME NOT NULL,
        first_item_started DATETIME,
        all_items_completed DATETIME,
        passed_at DATETIME,
        
        -- Vypočítané časy (v sekundách)
        preparation_time INT,
        total_time INT,
        waiting_time INT,
        
        -- Počet položek
        total_items INT DEFAULT 0,
        
        -- Stav objednávky
        status ENUM('active', 'completed', 'warning', 'archived') DEFAULT 'active',
        warning_reason VARCHAR(255),
        
        -- Indexy
        INDEX idx_order_id (order_id),
        INDEX idx_created_at (created_at),
        INDEX idx_status (status),
        INDEX idx_preparation_time (preparation_time),
        
        UNIQUE KEY unique_order (order_id)
    )";
    
    $db->exec($sql1);
    echo "✓ Tabulka order_timing vytvořena\n";
    
    // Tabulka pro sledování časů jednotlivých položek
    $sql2 = "CREATE TABLE IF NOT EXISTS item_timing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(50) NOT NULL,
        item_id INT NOT NULL,
        
        -- Informace o položce
        item_name VARCHAR(255) NOT NULL,
        product_id VARCHAR(50),
        quantity INT DEFAULT 1,
        note TEXT,
        
        -- Časové značky
        created_at DATETIME NOT NULL,
        started_at DATETIME,
        completed_at DATETIME,
        
        -- Vypočítané časy (v sekundách)
        preparation_time INT,
        waiting_time INT,
        total_time INT,
        
        -- Stav položky
        final_status ENUM('completed', 'passed', 'cancelled') DEFAULT 'completed',
        
        -- Indexy
        INDEX idx_order_id (order_id),
        INDEX idx_item_id (item_id),
        INDEX idx_item_name (item_name),
        INDEX idx_product_id (product_id),
        INDEX idx_created_at (created_at),
        INDEX idx_preparation_time (preparation_time),
        
        UNIQUE KEY unique_item (item_id)
    )";
    
    $db->exec($sql2);
    echo "✓ Tabulka item_timing vytvořena\n";
    
    // Tabulka pro agregované statistiky položek
    $sql3 = "CREATE TABLE IF NOT EXISTS item_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        product_id VARCHAR(50),
        
        -- Statistiky
        total_orders INT DEFAULT 0,
        avg_preparation_time DECIMAL(8,2),
        min_preparation_time INT,
        max_preparation_time INT,
        
        -- Poslední aktualizace
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Indexy
        INDEX idx_item_name (item_name),
        INDEX idx_product_id (product_id),
        INDEX idx_avg_time (avg_preparation_time),
        
        UNIQUE KEY unique_item_stats (item_name, product_id)
    )";
    
    $db->exec($sql3);
    echo "✓ Tabulka item_stats vytvořena\n";
    
    // Tabulka pro denní statistiky
    $sql4 = "CREATE TABLE IF NOT EXISTS daily_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        
        -- Celkové statistiky dne
        total_orders INT DEFAULT 0,
        completed_orders INT DEFAULT 0,
        warning_orders INT DEFAULT 0,
        
        -- Časové statistiky
        avg_preparation_time DECIMAL(8,2),
        avg_total_time DECIMAL(8,2),
        max_preparation_time INT,
        
        -- Výkonnostní metriky
        orders_under_15min INT DEFAULT 0,
        orders_15_30min INT DEFAULT 0,
        orders_over_30min INT DEFAULT 0,
        
        -- Indexy
        INDEX idx_date (date),
        
        UNIQUE KEY unique_date (date)
    )";
    
    $db->exec($sql4);
    echo "✓ Tabulka daily_stats vytvořena\n";
    
    // Tabulka pro uživatele statistik
    $sql5 = "CREATE TABLE IF NOT EXISTS stats_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'viewer') DEFAULT 'viewer',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        
        UNIQUE KEY unique_username (username)
    )";
    
    $db->exec($sql5);
    echo "✓ Tabulka stats_users vytvořena\n";
    
    // Vložení výchozího admin uživatele (heslo: admin123)
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO stats_users (username, password_hash, role) VALUES (?, ?, 'admin')");
    $stmt->execute(['admin', $adminPassword]);
    echo "✓ Výchozí admin uživatel vytvořen (username: admin, password: admin123)\n";
    
    echo "\n🎉 Všechny tabulky byly úspěšně vytvořeny!\n";
    
} catch (Exception $e) {
    echo "❌ Chyba při vytváření tabulek: " . $e->getMessage() . "\n";
}
?>
