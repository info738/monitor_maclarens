<?php
// Funkce pro sledování časů objednávek a položek

// Inicializace sledování nové objednávky
function initializeOrderTiming($db, $orderId, $tableName = null, $deliveryService = null) {
    try {
        // Získáme informace o objednávce
        $stmt = $db->prepare("SELECT created, COUNT(*) as item_count FROM orders o 
                             JOIN order_items oi ON o.id = oi.order_id 
                             WHERE o.id = ? AND oi.kitchen_status != 'passed'
                             GROUP BY o.id");
        $stmt->execute([$orderId]);
        $orderInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$orderInfo) return false;
        
        // Vložíme nebo aktualizujeme záznam v order_timing
        $stmt = $db->prepare("INSERT INTO order_timing 
                             (order_id, table_name, delivery_service, created_at, total_items, status) 
                             VALUES (?, ?, ?, ?, ?, 'active')
                             ON DUPLICATE KEY UPDATE 
                             total_items = VALUES(total_items)");
        
        $stmt->execute([
            $orderId, 
            $tableName, 
            $deliveryService, 
            $orderInfo['created'], 
            $orderInfo['item_count']
        ]);
        
        // Inicializujeme timing pro jednotlivé položky
        $stmt = $db->prepare("SELECT id, name, product_id, quantity, note, created 
                             FROM order_items 
                             WHERE order_id = ? AND kitchen_status != 'passed'");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $db->prepare("INSERT IGNORE INTO item_timing 
                                 (order_id, item_id, item_name, product_id, quantity, note, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $orderId,
                $item['id'],
                $item['name'],
                $item['product_id'],
                $item['quantity'],
                $item['note'],
                $item['created']
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Chyba při inicializaci order timing: " . $e->getMessage());
        return false;
    }
}

// Aktualizace při zahájení přípravy položky
function updateItemStarted($db, $itemId) {
    try {
        $now = date('Y-m-d H:i:s');
        
        // Aktualizujeme started_at pro položku
        $stmt = $db->prepare("UPDATE item_timing 
                             SET started_at = ?, 
                                 waiting_time = TIMESTAMPDIFF(SECOND, created_at, ?)
                             WHERE item_id = ? AND started_at IS NULL");
        $stmt->execute([$now, $now, $itemId]);
        
        // Zkontrolujeme, jestli je to první položka v objednávce
        $stmt = $db->prepare("SELECT order_id FROM item_timing WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $orderId = $stmt->fetchColumn();
        
        if ($orderId) {
            // Aktualizujeme first_item_started pokud ještě není nastaveno
            $stmt = $db->prepare("UPDATE order_timing 
                                 SET first_item_started = ?,
                                     waiting_time = TIMESTAMPDIFF(SECOND, created_at, ?)
                                 WHERE order_id = ? AND first_item_started IS NULL");
            $stmt->execute([$now, $now, $orderId]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Chyba při aktualizaci item started: " . $e->getMessage());
        return false;
    }
}

// Aktualizace při dokončení položky
function updateItemCompleted($db, $itemId) {
    try {
        $now = date('Y-m-d H:i:s');
        
        // Aktualizujeme completed_at pro položku
        $stmt = $db->prepare("UPDATE item_timing 
                             SET completed_at = ?,
                                 preparation_time = TIMESTAMPDIFF(SECOND, started_at, ?),
                                 total_time = TIMESTAMPDIFF(SECOND, created_at, ?)
                             WHERE item_id = ?");
        $stmt->execute([$now, $now, $now, $itemId]);
        
        // Získáme order_id
        $stmt = $db->prepare("SELECT order_id FROM item_timing WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $orderId = $stmt->fetchColumn();
        
        if ($orderId) {
            // Zkontrolujeme, jestli jsou všechny položky dokončené
            $stmt = $db->prepare("SELECT COUNT(*) as total,
                                         SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed
                                 FROM item_timing 
                                 WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($counts['total'] == $counts['completed']) {
                // Všechny položky jsou dokončené
                $stmt = $db->prepare("UPDATE order_timing 
                                     SET all_items_completed = ?,
                                         preparation_time = TIMESTAMPDIFF(SECOND, created_at, ?)
                                     WHERE order_id = ?");
                $stmt->execute([$now, $now, $orderId]);
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Chyba při aktualizaci item completed: " . $e->getMessage());
        return false;
    }
}

// Aktualizace při předání objednávky
function updateOrderPassed($db, $orderId) {
    try {
        $now = date('Y-m-d H:i:s');
        
        // Aktualizujeme passed_at a total_time
        $stmt = $db->prepare("UPDATE order_timing 
                             SET passed_at = ?,
                                 total_time = TIMESTAMPDIFF(SECOND, created_at, ?),
                                 status = 'completed'
                             WHERE order_id = ?");
        $stmt->execute([$now, $now, $orderId]);
        
        // Označíme všechny položky jako passed
        $stmt = $db->prepare("UPDATE item_timing 
                             SET final_status = 'passed'
                             WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        // Aktualizujeme statistiky položek
        updateItemStats($db, $orderId);
        
        // Aktualizujeme denní statistiky
        updateDailyStats($db);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Chyba při aktualizaci order passed: " . $e->getMessage());
        return false;
    }
}

// Kontrola a označení výstražných objednávek (nad 30 minut)
function checkWarningOrders($db) {
    try {
        $thirtyMinutesAgo = date('Y-m-d H:i:s', strtotime('-30 minutes'));

        // Najdeme pouze objednávky které mají aktivní položky v kuchyni a jsou starší než 30 minut
        $stmt = $db->prepare("UPDATE order_timing ot
                             SET status = 'warning',
                                 warning_reason = 'Překročen čas 30 minut'
                             WHERE ot.status = 'active'
                             AND ot.created_at < ?
                             AND EXISTS (
                                 SELECT 1 FROM order_items oi
                                 WHERE oi.order_id = ot.order_id
                                 AND oi.kitchen_status IN ('new', 'in-progress', 'reordered', 'completed')
                             )");
        $stmt->execute([$thirtyMinutesAgo]);

        $warningCount = $stmt->rowCount();

        if ($warningCount > 0) {
            error_log("Označeno $warningCount objednávek jako výstražné (nad 30 minut)");
        }

        return $warningCount;

    } catch (Exception $e) {
        error_log("Chyba při kontrole výstražných objednávek: " . $e->getMessage());
        return 0;
    }
}

// Aktualizace statistik položek
function updateItemStats($db, $orderId) {
    try {
        // Získáme dokončené položky z této objednávky
        $stmt = $db->prepare("SELECT item_name, product_id, preparation_time 
                             FROM item_timing 
                             WHERE order_id = ? AND preparation_time IS NOT NULL");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            // Aktualizujeme nebo vytvoříme statistiku pro tuto položku
            $stmt = $db->prepare("INSERT INTO item_stats 
                                 (item_name, product_id, total_orders, avg_preparation_time, 
                                  min_preparation_time, max_preparation_time)
                                 VALUES (?, ?, 1, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE
                                 total_orders = total_orders + 1,
                                 avg_preparation_time = (avg_preparation_time * (total_orders - 1) + ?) / total_orders,
                                 min_preparation_time = LEAST(min_preparation_time, ?),
                                 max_preparation_time = GREATEST(max_preparation_time, ?)");
            
            $prepTime = $item['preparation_time'];
            $stmt->execute([
                $item['item_name'],
                $item['product_id'],
                $prepTime,
                $prepTime,
                $prepTime,
                $prepTime,
                $prepTime,
                $prepTime
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Chyba při aktualizaci item stats: " . $e->getMessage());
        return false;
    }
}

// Aktualizace denních statistik
function updateDailyStats($db) {
    try {
        $today = date('Y-m-d');
        
        // Získáme statistiky pro dnešek
        $stmt = $db->prepare("SELECT 
                                COUNT(*) as total_orders,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                                SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning_orders,
                                AVG(preparation_time) as avg_preparation_time,
                                AVG(total_time) as avg_total_time,
                                MAX(preparation_time) as max_preparation_time,
                                SUM(CASE WHEN total_time <= 900 THEN 1 ELSE 0 END) as orders_under_15min,
                                SUM(CASE WHEN total_time > 900 AND total_time <= 1800 THEN 1 ELSE 0 END) as orders_15_30min,
                                SUM(CASE WHEN total_time > 1800 THEN 1 ELSE 0 END) as orders_over_30min
                             FROM order_timing 
                             WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Aktualizujeme denní statistiky
        $stmt = $db->prepare("INSERT INTO daily_stats 
                             (date, total_orders, completed_orders, warning_orders, 
                              avg_preparation_time, avg_total_time, max_preparation_time,
                              orders_under_15min, orders_15_30min, orders_over_30min)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                             total_orders = VALUES(total_orders),
                             completed_orders = VALUES(completed_orders),
                             warning_orders = VALUES(warning_orders),
                             avg_preparation_time = VALUES(avg_preparation_time),
                             avg_total_time = VALUES(avg_total_time),
                             max_preparation_time = VALUES(max_preparation_time),
                             orders_under_15min = VALUES(orders_under_15min),
                             orders_15_30min = VALUES(orders_15_30min),
                             orders_over_30min = VALUES(orders_over_30min)");
        
        $stmt->execute([
            $today,
            $stats['total_orders'],
            $stats['completed_orders'],
            $stats['warning_orders'],
            $stats['avg_preparation_time'],
            $stats['avg_total_time'],
            $stats['max_preparation_time'],
            $stats['orders_under_15min'],
            $stats['orders_15_30min'],
            $stats['orders_over_30min']
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Chyba při aktualizaci daily stats: " . $e->getMessage());
        return false;
    }
}
?>
