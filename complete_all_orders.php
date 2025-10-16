<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Dokončení všech objednávek</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.8; }
        .order-list { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 10px 0; max-height: 300px; overflow-y: auto; }
        .order-item { padding: 5px 0; border-bottom: 1px solid #dee2e6; }
        .order-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏁 Dokončení všech objednávek</h1>
        
        <?php
        require_once 'db.php';
        require_once 'timing_functions.php';
        
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            try {
                if ($action === 'check') {
                    // Kontrola aktuálních objednávek
                    $stmt = $db->query("SELECT DISTINCT o.id, o.table_name, o.delivery_service,
                                          COUNT(oi.id) as total_items,
                                          SUM(CASE WHEN oi.kitchen_status = 'completed' THEN 1 ELSE 0 END) as completed_items,
                                          SUM(CASE WHEN oi.kitchen_status IN ('new', 'in-progress', 'reordered') THEN 1 ELSE 0 END) as pending_items
                                       FROM orders o
                                       JOIN order_items oi ON o.id = oi.order_id
                                       WHERE oi.kitchen_status IN ('new', 'in-progress', 'reordered', 'completed')
                                         AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                                       GROUP BY o.id, o.table_name, o.delivery_service
                                       ORDER BY o.id DESC");
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo '<div class="info"><strong>Aktuální stav objednávek:</strong></div>';
                    echo '<div class="info">Celkem aktivních objednávek: ' . count($orders) . '</div>';
                    
                    if (count($orders) > 0) {
                        $totalPending = 0;
                        $totalCompleted = 0;
                        $readyToPass = 0;
                        
                        echo '<div class="order-list">';
                        foreach ($orders as $order) {
                            $totalPending += $order['pending_items'];
                            $totalCompleted += $order['completed_items'];
                            
                            $status = '';
                            if ($order['pending_items'] > 0) {
                                $status = '<span style="color: #dc3545;">Nedokončeno (' . $order['pending_items'] . ' položek)</span>';
                            } elseif ($order['completed_items'] > 0) {
                                $status = '<span style="color: #28a745;">Připraveno k předání</span>';
                                $readyToPass++;
                            }
                            
                            $source = $order['table_name'] ?: $order['delivery_service'] ?: 'Neznámý zdroj';
                            echo '<div class="order-item">';
                            echo '<strong>Objednávka #' . $order['id'] . '</strong> - ' . htmlspecialchars($source);
                            echo ' (' . $order['total_items'] . ' položek) - ' . $status;
                            echo '</div>';
                        }
                        echo '</div>';
                        
                        echo '<div class="info">Nedokončených položek celkem: ' . $totalPending . '</div>';
                        echo '<div class="info">Dokončených položek celkem: ' . $totalCompleted . '</div>';
                        echo '<div class="info">Objednávek připravených k předání: ' . $readyToPass . '</div>';
                        
                        if ($totalPending > 0) {
                            echo '<div class="warning">Některé objednávky mají nedokončené položky.</div>';
                        }
                    } else {
                        echo '<div class="success">✓ Žádné aktivní objednávky v kuchyni!</div>';
                    }
                    
                } elseif ($action === 'complete_all') {
                    // Dokončení všech nedokončených položek
                    $stmt = $db->prepare("UPDATE order_items 
                                         SET kitchen_status = 'completed'
                                         WHERE kitchen_status IN ('new', 'in-progress', 'reordered')
                                           AND (product_id IS NULL OR product_id NOT IN (SELECT product_id FROM excluded_products))");
                    $stmt->execute();
                    $completed = $stmt->rowCount();
                    
                    // Aktualizace timing pro dokončené položky
                    if ($completed > 0) {
                        $stmt = $db->query("SELECT id FROM order_items WHERE kitchen_status = 'completed'");
                        $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($items as $itemId) {
                            updateItemCompleted($db, $itemId);
                        }
                    }
                    
                    echo '<div class="success">✓ Dokončeno ' . $completed . ' položek</div>';
                    
                } elseif ($action === 'pass_all') {
                    // Předání všech dokončených objednávek
                    $stmt = $db->query("SELECT DISTINCT o.id
                                       FROM orders o
                                       JOIN order_items oi ON o.id = oi.order_id
                                       WHERE oi.kitchen_status = 'completed'
                                         AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                                       GROUP BY o.id
                                       HAVING COUNT(CASE WHEN oi.kitchen_status != 'completed' THEN 1 END) = 0");
                    $readyOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $passed = 0;
                    foreach ($readyOrders as $orderId) {
                        $stmt = $db->prepare("UPDATE order_items 
                                             SET kitchen_status = 'passed'
                                             WHERE order_id = ? AND kitchen_status = 'completed'");
                        $stmt->execute([$orderId]);
                        
                        if ($stmt->rowCount() > 0) {
                            updateOrderPassed($db, $orderId);
                            $passed++;
                        }
                    }
                    
                    echo '<div class="success">✓ Předáno ' . $passed . ' objednávek</div>';
                    
                } elseif ($action === 'complete_and_pass_all') {
                    // Dokončení a předání všech objednávek najednou
                    
                    // 1. Dokončit všechny nedokončené položky
                    $stmt = $db->prepare("UPDATE order_items 
                                         SET kitchen_status = 'completed'
                                         WHERE kitchen_status IN ('new', 'in-progress', 'reordered')
                                           AND (product_id IS NULL OR product_id NOT IN (SELECT product_id FROM excluded_products))");
                    $stmt->execute();
                    $completed = $stmt->rowCount();
                    
                    // 2. Aktualizace timing pro dokončené položky
                    if ($completed > 0) {
                        $stmt = $db->query("SELECT id FROM order_items 
                                          WHERE kitchen_status = 'completed'
                                            AND (product_id IS NULL OR product_id NOT IN (SELECT product_id FROM excluded_products))");
                        $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($items as $itemId) {
                            updateItemCompleted($db, $itemId);
                        }
                    }
                    
                    // 3. Předat všechny dokončené objednávky
                    $stmt = $db->query("SELECT DISTINCT o.id
                                       FROM orders o
                                       JOIN order_items oi ON o.id = oi.order_id
                                       WHERE oi.kitchen_status = 'completed'
                                         AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))");
                    $readyOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $passed = 0;
                    foreach ($readyOrders as $orderId) {
                        $stmt = $db->prepare("UPDATE order_items 
                                             SET kitchen_status = 'passed'
                                             WHERE order_id = ? AND kitchen_status = 'completed'");
                        $stmt->execute([$orderId]);
                        
                        if ($stmt->rowCount() > 0) {
                            updateOrderPassed($db, $orderId);
                            $passed++;
                        }
                    }
                    
                    echo '<div class="success">✓ Dokončeno ' . $completed . ' položek a předáno ' . $passed . ' objednávek</div>';
                    echo '<div class="info">Kuchyň je nyní prázdná!</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Chyba: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ POZOR:</strong> Tyto akce ovlivní všechny aktivní objednávky v kuchyni!
        </div>
        
        <div class="info">
            <strong>Dostupné akce:</strong>
            <ul>
                <li><strong>Kontrola stavu</strong> - zobrazí všechny aktivní objednávky</li>
                <li><strong>Dokončit vše</strong> - označí všechny nedokončené položky jako dokončené</li>
                <li><strong>Předat vše</strong> - předá všechny dokončené objednávky do baru</li>
                <li><strong>Dokončit a předat vše</strong> - provede obě akce najednou (vyčistí kuchyň)</li>
            </ul>
        </div>
        
        <form method="POST" style="margin: 20px 0;">
            <button type="submit" name="action" value="check" class="btn">📊 Kontrola stavu</button>
            <button type="submit" name="action" value="complete_all" class="btn btn-success" 
                    onclick="return confirm('Opravdu chcete dokončit všechny nedokončené položky?')">
                ✅ Dokončit vše
            </button>
            <button type="submit" name="action" value="pass_all" class="btn btn-success" 
                    onclick="return confirm('Opravdu chcete předat všechny dokončené objednávky?')">
                📤 Předat vše
            </button>
            <button type="submit" name="action" value="complete_and_pass_all" class="btn btn-danger" 
                    onclick="return confirm('POZOR: Toto dokončí a předá VŠECHNY objednávky! Kuchyň bude prázdná. Pokračovat?')">
                🧹 Dokončit a předat vše
            </button>
        </form>
        
        <hr style="margin: 30px 0;">
        <div class="info">
            <strong>Navigace:</strong>
            <ul>
                <li><a href="kitchen.php">Kuchyňský monitor</a></li>
                <li><a href="bar.php">Barový monitor</a></li>
                <li><a href="admin_warnings.php">Výstražné objednávky</a></li>
                <li><a href="statistics.php">Statistiky</a></li>
                <li><a href="cleanup_warnings.php">Vyčištění výstrah</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
