-- Tabulka pro sledování časů celých objednávek
CREATE TABLE IF NOT EXISTS order_timing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    table_name VARCHAR(100),
    delivery_service VARCHAR(100),
    
    -- Časové značky
    created_at DATETIME NOT NULL,           -- Kdy byla objednávka vytvořena
    first_item_started DATETIME,            -- Kdy byla první položka označena jako "in-progress"
    all_items_completed DATETIME,           -- Kdy byly všechny položky dokončeny
    passed_at DATETIME,                     -- Kdy byla objednávka předána
    
    -- Vypočítané časy (v sekundách)
    preparation_time INT,                   -- Čas od created_at do all_items_completed
    total_time INT,                         -- Čas od created_at do passed_at
    waiting_time INT,                       -- Čas od created_at do first_item_started
    
    -- Počet položek
    total_items INT DEFAULT 0,
    
    -- Stav objednávky
    status ENUM('active', 'completed', 'warning', 'archived') DEFAULT 'active',
    warning_reason VARCHAR(255),            -- Důvod výstrahy (např. "Překročen čas 30 minut")
    
    -- Indexy
    INDEX idx_order_id (order_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_preparation_time (preparation_time),
    
    UNIQUE KEY unique_order (order_id)
);

-- Tabulka pro sledování časů jednotlivých položek
CREATE TABLE IF NOT EXISTS item_timing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    item_id INT NOT NULL,
    
    -- Informace o položce
    item_name VARCHAR(255) NOT NULL,
    product_id VARCHAR(50),
    quantity INT DEFAULT 1,
    note TEXT,
    
    -- Časové značky
    created_at DATETIME NOT NULL,           -- Kdy byla položka vytvořena
    started_at DATETIME,                    -- Kdy byla označena jako "in-progress"
    completed_at DATETIME,                  -- Kdy byla dokončena
    
    -- Vypočítané časy (v sekundách)
    preparation_time INT,                   -- Čas od started_at do completed_at
    waiting_time INT,                       -- Čas od created_at do started_at
    total_time INT,                         -- Čas od created_at do completed_at
    
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
);

-- Tabulka pro agregované statistiky položek (pro rychlejší dotazy)
CREATE TABLE IF NOT EXISTS item_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    product_id VARCHAR(50),
    
    -- Statistiky
    total_orders INT DEFAULT 0,             -- Celkový počet objednávek této položky
    avg_preparation_time DECIMAL(8,2),      -- Průměrný čas přípravy (sekundy)
    min_preparation_time INT,               -- Nejkratší čas přípravy
    max_preparation_time INT,               -- Nejdelší čas přípravy
    
    -- Poslední aktualizace
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexy
    INDEX idx_item_name (item_name),
    INDEX idx_product_id (product_id),
    INDEX idx_avg_time (avg_preparation_time),
    
    UNIQUE KEY unique_item_stats (item_name, product_id)
);

-- Tabulka pro denní statistiky (pro grafy a trendy)
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    
    -- Celkové statistiky dne
    total_orders INT DEFAULT 0,
    completed_orders INT DEFAULT 0,
    warning_orders INT DEFAULT 0,           -- Objednávky nad 30 minut
    
    -- Časové statistiky
    avg_preparation_time DECIMAL(8,2),      -- Průměrný čas přípravy
    avg_total_time DECIMAL(8,2),            -- Průměrný celkový čas
    max_preparation_time INT,               -- Nejdelší příprava dne
    
    -- Výkonnostní metriky
    orders_under_15min INT DEFAULT 0,       -- Objednávky do 15 minut
    orders_15_30min INT DEFAULT 0,          -- Objednávky 15-30 minut
    orders_over_30min INT DEFAULT 0,        -- Objednávky nad 30 minut
    
    -- Indexy
    INDEX idx_date (date),
    
    UNIQUE KEY unique_date (date)
);

-- Tabulka pro uživatele statistik (zaheslování)
CREATE TABLE IF NOT EXISTS stats_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'viewer') DEFAULT 'viewer',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    
    UNIQUE KEY unique_username (username)
);

-- Vložení výchozího admin uživatele (heslo: admin123)
INSERT IGNORE INTO stats_users (username, password_hash, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
