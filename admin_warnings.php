<?php
require_once 'db.php';
require_once 'timing_functions.php';

// Kontrola výstražných objednávek
checkWarningOrders($db);

// Zpracování akcí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $orderId = $_POST['order_id'] ?? '';
    
    if ($action === 'resolve' && $orderId) {
        // Označit objednávku jako vyřešenou
        $stmt = $db->prepare("UPDATE order_timing SET status = 'archived', warning_reason = CONCAT(warning_reason, ' - Vyřešeno administrátorem') WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        // Označit všechny položky jako passed
        $stmt = $db->prepare("UPDATE order_items SET kitchen_status = 'passed' WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        header('Location: admin_warnings.php?resolved=1');
        exit;
    }
    
    if ($action === 'return' && $orderId) {
        // Vrátit objednávku zpět do kuchyně
        $stmt = $db->prepare("UPDATE order_timing SET status = 'active', warning_reason = NULL WHERE order_id = ?");
        $stmt->execute([$orderId]);
        
        header('Location: admin_warnings.php?returned=1');
        exit;
    }
}

// Získání výstražných objednávek
$stmt = $db->query("SELECT ot.*, 
                           TIMESTAMPDIFF(MINUTE, ot.created_at, NOW()) as minutes_elapsed,
                           COUNT(oi.id) as item_count
                    FROM order_timing ot
                    LEFT JOIN order_items oi ON ot.order_id = oi.order_id AND oi.kitchen_status != 'passed'
                    WHERE ot.status = 'warning'
                    GROUP BY ot.order_id
                    ORDER BY ot.created_at ASC");
$warningOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Získání položek pro každou objednávku
$orderItems = [];
foreach ($warningOrders as $order) {
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? AND kitchen_status != 'passed' ORDER BY id ASC");
    $stmt->execute([$order['order_id']]);
    $orderItems[$order['order_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Administrace výstražných objednávek</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 16px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc; color: #1e293b; font-size: 14px; line-height: 1.5;
        }
        .top-menu {
            display: flex; gap: 8px; margin-bottom: 24px;
            background: #fff; padding: 12px 16px; border-radius: 12px;
            align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        .top-menu a {
            text-decoration: none; padding: 6px 12px; background: #f1f5f9;
            border-radius: 6px; color: #475569; border: 1px solid #e2e8f0;
            font-size: 13px; font-weight: 500; transition: all 0.2s;
        }
        .top-menu a:hover { background: #e2e8f0; color: #334155; }
        h1 { margin: 0 0 24px 0; font-weight: 600; font-size: 24px; color: #0f172a; }
        .alert {
            padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
            border: 1px solid; font-weight: 500;
        }
        .alert-success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .alert-info { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .alert-warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .stats-section {
            background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .stat-card {
            background: #f8fafc; padding: 16px; border-radius: 8px;
            text-align: center; border: 1px solid #e2e8f0;
        }
        .stat-number { font-size: 24px; font-weight: 600; color: #dc2626; }
        .stat-label { font-size: 12px; color: #64748b; margin-top: 4px; }
        .orders-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 16px;
        }
        .order-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #fca5a5;
            border-left: 4px solid #dc2626;
        }
        .order-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px;
        }
        .order-id { font-weight: 600; font-size: 16px; color: #0f172a; }
        .time-badge {
            background: #dc2626; color: #fff; padding: 4px 12px;
            border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .order-info {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
            margin-bottom: 16px; font-size: 13px; color: #64748b;
        }
        .order-items {
            background: #f8fafc; padding: 12px; border-radius: 8px;
            margin-bottom: 16px; border: 1px solid #e2e8f0;
        }
        .item { margin-bottom: 8px; }
        .item:last-child { margin-bottom: 0; }
        .item-name { font-weight: 500; color: #374151; }
        .item-note { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .order-actions {
            display: flex; gap: 8px; justify-content: flex-end;
        }
        .btn {
            padding: 8px 16px; border: none; border-radius: 6px;
            font-size: 13px; font-weight: 500; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-resolve {
            background: #dc2626; color: #fff;
        }
        .btn-resolve:hover { background: #b91c1c; }
        .btn-return {
            background: #059669; color: #fff;
        }
        .btn-return:hover { background: #047857; }
        .empty-state {
            text-align: center; padding: 60px 20px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .empty-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-title { font-size: 18px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .empty-text { color: #6b7280; }
    </style>
</head>
<body>

<div class="top-menu">
    <a href="index.php">Dashboard</a>
    <a href="kitchen.php">Kuchyň</a>
    <a href="bar.php">Bar</a>
    <a href="admin_excluded.php">Vyloučené položky</a>
    <a href="statistics.php">Statistiky</a>
</div>

<h1>⚠️ Výstražné objednávky (nad 30 minut)</h1>

<?php if (isset($_GET['resolved'])): ?>
<div class="alert alert-success">✓ Objednávka byla označena jako vyřešená</div>
<?php endif; ?>

<?php if (isset($_GET['returned'])): ?>
<div class="alert alert-info">↩️ Objednávka byla vrácena zpět do kuchyně</div>
<?php endif; ?>

<div class="stats-section">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($warningOrders); ?></div>
            <div class="stat-label">Výstražné objednávky</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php 
                $totalMinutes = 0;
                foreach ($warningOrders as $order) {
                    $totalMinutes += $order['minutes_elapsed'];
                }
                echo count($warningOrders) > 0 ? round($totalMinutes / count($warningOrders)) : 0;
                ?>
            </div>
            <div class="stat-label">Průměrný čas (min)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">
                <?php 
                $maxMinutes = 0;
                foreach ($warningOrders as $order) {
                    if ($order['minutes_elapsed'] > $maxMinutes) {
                        $maxMinutes = $order['minutes_elapsed'];
                    }
                }
                echo $maxMinutes;
                ?>
            </div>
            <div class="stat-label">Nejdelší čekání (min)</div>
        </div>
    </div>
</div>

<?php if (empty($warningOrders)): ?>
<div class="empty-state">
    <div class="empty-icon">✅</div>
    <div class="empty-title">Žádné výstražné objednávky</div>
    <div class="empty-text">Všechny objednávky jsou zpracovány v rozumném čase</div>
</div>
<?php else: ?>
<div class="orders-grid">
    <?php foreach ($warningOrders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <div class="order-id">Objednávka #<?php echo htmlspecialchars($order['order_id']); ?></div>
            <div class="time-badge"><?php echo $order['minutes_elapsed']; ?> min</div>
        </div>
        
        <div class="order-info">
            <div><strong>Stůl:</strong> <?php echo htmlspecialchars($order['table_name'] ?: 'N/A'); ?></div>
            <div><strong>Položek:</strong> <?php echo $order['item_count']; ?></div>
            <div><strong>Vytvořeno:</strong> <?php echo date('H:i', strtotime($order['created_at'])); ?></div>
            <div><strong>Důvod:</strong> <?php echo htmlspecialchars($order['warning_reason']); ?></div>
        </div>
        
        <div class="order-items">
            <?php foreach ($orderItems[$order['order_id']] as $item): ?>
            <div class="item">
                <div class="item-name"><?php echo $item['quantity']; ?>× <?php echo htmlspecialchars($item['name']); ?></div>
                <?php if ($item['note']): ?>
                <div class="item-note"><?php echo htmlspecialchars($item['note']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="order-actions">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="return">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                <button type="submit" class="btn btn-return" onclick="return confirm('Vrátit objednávku zpět do kuchyně?')">
                    ↩️ Vrátit do kuchyně
                </button>
            </form>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="resolve">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                <button type="submit" class="btn btn-resolve" onclick="return confirm('Označit objednávku jako vyřešenou? Tato akce je nevratná.')">
                    ✓ Vyřešit
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Auto-refresh každých 30 sekund
setInterval(() => {
    window.location.reload();
}, 30000);
</script>

</body>
</html>
