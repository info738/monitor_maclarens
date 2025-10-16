<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Vyčištění výstražných objednávek</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Vyčištění výstražných objednávek</h1>
        
        <?php
        require_once 'db.php';
        
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            try {
                if ($action === 'check') {
                    // Kontrola stavu
                    $stmt = $db->query("SELECT 
                                          COUNT(*) as total_warnings,
                                          COUNT(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as old_warnings,
                                          MIN(created_at) as oldest_warning,
                                          MAX(created_at) as newest_warning
                                       FROM order_timing 
                                       WHERE status = 'warning'");
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo '<div class="info"><strong>Aktuální stav:</strong></div>';
                    echo '<div class="info">Celkem výstražných objednávek: ' . $stats['total_warnings'] . '</div>';
                    echo '<div class="info">Starších než 24 hodin: ' . $stats['old_warnings'] . '</div>';
                    
                    if ($stats['oldest_warning']) {
                        echo '<div class="info">Nejstarší výstraha: ' . $stats['oldest_warning'] . '</div>';
                        echo '<div class="info">Nejnovější výstraha: ' . $stats['newest_warning'] . '</div>';
                    }
                    
                    if ($stats['old_warnings'] > 0) {
                        echo '<div class="warning">Doporučuji vyčistit staré výstrahy.</div>';
                    }
                    
                } elseif ($action === 'cleanup_old') {
                    // Vyčištění starých výstrah (starších než 24 hodin)
                    $stmt = $db->prepare("UPDATE order_timing 
                                         SET status = 'archived',
                                             warning_reason = CONCAT(warning_reason, ' - Automaticky archivováno')
                                         WHERE status = 'warning' 
                                         AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
                    $stmt->execute();
                    $cleaned = $stmt->rowCount();
                    
                    echo '<div class="success">✓ Archivováno ' . $cleaned . ' starých výstražných objednávek</div>';
                    
                } elseif ($action === 'cleanup_all') {
                    // Vyčištění všech výstrah
                    $stmt = $db->prepare("UPDATE order_timing 
                                         SET status = 'archived',
                                             warning_reason = CONCAT(warning_reason, ' - Manuálně archivováno')
                                         WHERE status = 'warning'");
                    $stmt->execute();
                    $cleaned = $stmt->rowCount();
                    
                    echo '<div class="success">✓ Archivováno ' . $cleaned . ' výstražných objednávek</div>';
                    
                } elseif ($action === 'reset_timing') {
                    // Reset timing systému - smaže všechny timing záznamy
                    $tables = ['order_timing', 'item_timing', 'item_stats', 'daily_stats'];
                    $totalDeleted = 0;
                    
                    foreach ($tables as $table) {
                        $stmt = $db->query("DELETE FROM $table");
                        $deleted = $stmt->rowCount();
                        $totalDeleted += $deleted;
                        echo '<div class="success">✓ Smazáno ' . $deleted . ' záznamů z tabulky ' . $table . '</div>';
                    }
                    
                    echo '<div class="success"><strong>✓ Celkem smazáno ' . $totalDeleted . ' záznamů</strong></div>';
                    echo '<div class="info">Timing systém byl resetován. Nové objednávky se budou sledovat od začátku.</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">❌ Chyba: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        ?>
        
        <div class="info">
            <strong>Dostupné akce:</strong>
            <ul>
                <li><strong>Kontrola stavu</strong> - zobrazí aktuální počet výstražných objednávek</li>
                <li><strong>Vyčistit staré</strong> - archivuje výstrahy starší než 24 hodin</li>
                <li><strong>Vyčistit vše</strong> - archivuje všechny výstražné objednávky</li>
                <li><strong>Reset timing</strong> - smaže všechny timing data (použijte opatrně!)</li>
            </ul>
        </div>
        
        <form method="POST" style="margin: 20px 0;">
            <button type="submit" name="action" value="check" class="btn">📊 Kontrola stavu</button>
            <button type="submit" name="action" value="cleanup_old" class="btn">🧹 Vyčistit staré (24h+)</button>
            <button type="submit" name="action" value="cleanup_all" class="btn btn-danger" 
                    onclick="return confirm('Opravdu chcete archivovat všechny výstražné objednávky?')">
                🗑️ Vyčistit vše
            </button>
            <button type="submit" name="action" value="reset_timing" class="btn btn-danger" 
                    onclick="return confirm('POZOR: Toto smaže všechna timing data! Opravdu pokračovat?')">
                ⚠️ Reset timing systému
            </button>
        </form>
        
        <hr style="margin: 30px 0;">
        <div class="info">
            <strong>Navigace:</strong>
            <ul>
                <li><a href="kitchen.php">Kuchyňský monitor</a></li>
                <li><a href="admin_warnings.php">Výstražné objednávky</a></li>
                <li><a href="statistics.php">Statistiky</a></li>
                <li><a href="install_tables.php">Instalace tabulek</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
