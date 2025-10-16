<?php
// kitchen.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'db.php';
require_once 'timing_functions.php';
date_default_timezone_set('Europe/Prague');

// Pokud je AJAX požadavek, zpracujeme ho HNED na začátku
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    // Funkce pro získání dat objednávek
    function getOrdersDataAjax($db) {
        // KONTROLA A AUTOMATICKÉ ODSTRANĚNÍ VYLOUČENÝCH POLOŽEK Z KUCHYNĚ
        $sqlCheckExcluded = "UPDATE order_items
                             SET kitchen_status = 'passed',
                                 last_updated = NOW()
                             WHERE kitchen_status IN ('new','in-progress','reordered','completed')
                               AND product_id IS NOT NULL
                               AND product_id IN (SELECT product_id FROM excluded_products)";
        $stmt = $db->prepare($sqlCheckExcluded);
        $stmt->execute();

        // Dotaz pro aktivní položky
        $sqlActive = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                      FROM order_items oi
                      JOIN orders o ON oi.order_id = o.id
                      WHERE oi.kitchen_status IN ('new','in-progress','reordered')
                        AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                      ORDER BY o.created ASC";
        $stmt = $db->query($sqlActive);
        $activeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dotaz pro dokončené položky
        $sqlCompleted = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                         FROM order_items oi
                         JOIN orders o ON oi.order_id = o.id
                         WHERE oi.kitchen_status = 'completed'
                           AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                           AND oi.last_updated >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                         ORDER BY o.created ASC";
        $stmt = $db->query($sqlCompleted);
        $completedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [$activeItems, $completedItems];
    }

    list($activeItems, $completedItems) = getOrdersDataAjax($db);

    // Seskupíme položky podle objednávek
    $activeOrders = [];
    foreach ($activeItems as $item) {
        $orderId = $item['order_id'];
        $activeOrders[$orderId][] = $item;
    }

    $completedOrders = [];
    foreach ($completedItems as $item) {
        $orderId = $item['order_id'];
        $completedOrders[$orderId][] = $item;
    }

    echo json_encode([
        'active' => $activeOrders,
        'completed' => $completedOrders,
        'timestamp' => time()
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Kuchyňský monitor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Minimalistický design */
* {
  box-sizing: border-box;
}
body {
  margin: 0;
  padding: 16px;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: #f8fafc;
  color: #1e293b;
  font-size: 14px;
  line-height: 1.5;
}
.top-menu {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  background: #fff;
  padding: 12px 16px;
  border-radius: 12px;
  align-items: center;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border: 1px solid #e2e8f0;
}
.top-menu a {
  text-decoration: none;
  padding: 6px 12px;
  background: #f1f5f9;
  border-radius: 6px;
  color: #475569;
  border: 1px solid #e2e8f0;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}
.top-menu a:hover {
  background: #e2e8f0;
  color: #334155;
}
.status-indicator {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 8px;
}
.status-indicator.online {
  background: #10b981;
}
.status-indicator.offline {
  background: #ef4444;
}
h2 {
  font-weight: 600;
  margin: 0 0 16px 0;
  font-size: 18px;
  color: #0f172a;
}
.orders-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  align-items: start;
}
.order-box {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  transition: all 0.3s ease;
}
.order-box.completed {
  opacity: 0.6;
  background: #f8fafc;
}
.order-box.fade-out {
  opacity: 0;
  transform: translateX(-100%) scale(0.8);
  filter: blur(2px);
}
.order-box.removing {
  background: #fef2f2 !important;
  border-color: #fca5a5 !important;
  box-shadow: 0 0 20px rgba(239, 68, 68, 0.3) !important;
}
.order-box.removing::before {
  content: '⏳ Odstraňuji...';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(239, 68, 68, 0.9);
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  z-index: 10;
  animation: pulse 1s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}
.order-header {
  padding: 12px 16px;
  background: #0f172a;
  color: #fff;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.order-header h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}
.order-time {
  font-size: 12px;
  opacity: 0.8;
}
.order-body {
  padding: 16px;
}
.order-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  font-size: 12px;
  color: #64748b;
}
.table-info {
  background: #0f172a;
  color: #fff;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}
.delivery-service {
  display: flex;
  align-items: center;
}
.delivery-logo {
  height: 16px;
  margin-left: 8px;
}
.order-items {
  list-style: none;
  padding: 0;
  margin: 0 0 12px 0;
}
.order-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 8px;
  border-left: 3px solid #e2e8f0;
}
.order-item.status-new {
  border-left-color: #ef4444;
  background: #fef2f2;
}
.order-item.status-in-progress {
  border-left-color: #f59e0b;
  background: #fffbeb;
}
.order-item.status-reordered {
  border-left-color: #8b5cf6;
  background: #f3f4f6;
}
.order-item.status-completed {
  border-left-color: #10b981;
  background: #f0fdf4;
}
.item-title {
  font-size: 14px;
  font-weight: 600;
  margin: 0 0 4px 0;
  color: #0f172a;
}
.item-note {
  font-size: 12px;
  color: #dc2626;
  font-weight: 500;
  margin: 4px 0;
}
.subitem-line {
  font-size: 12px;
  color: #475569;
  margin: 4px 0 0 16px;
  padding: 4px 8px;
  background: #fff;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}
.order-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}
.btn {
  padding: 8px 16px;
  font-size: 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-weight: 500;
  flex: 1;
  text-align: center;
}
.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
.btn-preparing {
  background: #f59e0b;
  color: #fff;
}
.btn-preparing:hover {
  background: #d97706;
}
.btn-complete {
  background: #10b981;
  color: #fff;
}
.btn-complete:hover {
  background: #059669;
}
.btn-secondary {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}
.btn-secondary:hover {
  background: #e2e8f0;
}
.delivery-note {
  margin-top: 12px;
  padding: 8px;
  background: #f1f5f9;
  border-radius: 6px;
  font-size: 12px;
  color: #475569;
  border-left: 3px solid #3b82f6;
}
.section-divider {
  margin: 32px 0;
  border: 0;
  border-top: 1px solid #e2e8f0;
}
.auto-refresh-info {
  position: fixed;
  top: 16px;
  right: 16px;
  background: #fff;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  color: #64748b;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  border: 1px solid #e2e8f0;
}
.completion-info {
  background: #f0fdf4;
  color: #166534;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-align: center;
  border: 1px solid #bbf7d0;
}
</style>
</head>
<body>
<div class="auto-refresh-info">
  <span class="status-indicator online" id="statusIndicator"></span>
  <span id="refreshStatus">Aktualizace za <span id="countdown">30</span>s</span>
</div>

<div class="top-menu">
  <a href="index.php">Dashboard</a>
  <a href="bar.php">Bar</a>
  <a href="admin_excluded.php">Vyloučené položky</a>
  <a href="admin_warnings.php">Výstražné objednávky</a>
  <a href="statistics.php">Statistiky</a>
</div>
<?php
// Funkce pro získání dat objednávek
function getOrdersData($db) {
    // KONTROLA A AUTOMATICKÉ ODSTRANĚNÍ VYLOUČENÝCH POLOŽEK Z KUCHYNĚ
    // Najdeme všechny aktivní položky které jsou nyní vyloučené a automaticky je označíme jako "passed"
    $sqlCheckExcluded = "UPDATE order_items
                         SET kitchen_status = 'passed',
                             last_updated = NOW()
                         WHERE kitchen_status IN ('new','in-progress','reordered','completed')
                           AND product_id IS NOT NULL
                           AND product_id IN (SELECT product_id FROM excluded_products)";
    $stmt = $db->prepare($sqlCheckExcluded);
    $result = $stmt->execute();

    // Logování pro debug (volitelné)
    if ($result) {
        $affectedRows = $stmt->rowCount();
        if ($affectedRows > 0) {
            error_log("Kitchen cleanup: Automaticky odstraněno $affectedRows vyloučených položek z kuchyně");
        }
    }

    // KONTROLA VÝSTRAŽNÝCH OBJEDNÁVEK (nad 30 minut)
    $warningCount = checkWarningOrders($db);

    // INICIALIZACE TIMING PRO NOVÉ OBJEDNÁVKY (pouze dnešní)
    // Najdeme objednávky které ještě nemají timing záznam a jsou z posledních 24 hodin
    $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $sqlNewOrders = "SELECT DISTINCT o.id, o.table_name, o.delivery_service
                     FROM orders o
                     JOIN order_items oi ON o.id = oi.order_id
                     WHERE oi.kitchen_status IN ('new','in-progress','reordered','completed')
                       AND o.created >= ?
                       AND o.id NOT IN (SELECT order_id FROM order_timing)
                       AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))";
    $stmt = $db->prepare($sqlNewOrders);
    $stmt->execute([$yesterday]);
    $newOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($newOrders as $order) {
        initializeOrderTiming($db, $order['id'], $order['table_name'], $order['delivery_service']);
    }

    // Dotaz pro aktivní položky – vylepšené filtrování vyloučených položek a výstražných objednávek
    $sqlActive = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                  FROM order_items oi
                  JOIN orders o ON oi.order_id = o.id
                  LEFT JOIN order_timing ot ON o.id = ot.order_id
                  WHERE oi.kitchen_status IN ('new','in-progress','reordered')
                    AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                    AND (ot.status IS NULL OR ot.status != 'warning')
                  ORDER BY o.created ASC";
    $stmt = $db->query($sqlActive);
    $activeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dotaz pro dokončené položky (také aplikujeme filtr) - pouze posledních 30 minut a POUZE completed (ne passed)
    $sqlCompleted = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                     FROM order_items oi
                     JOIN orders o ON oi.order_id = o.id
                     WHERE oi.kitchen_status = 'completed'
                       AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                       AND oi.last_updated >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                     ORDER BY o.created ASC";
    $stmt = $db->query($sqlCompleted);
    $completedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pro notifikaci – získáme ID položek, které mají shown = 0 (a nejsou vyloučené)
    $sqlNewNotification = "SELECT id FROM order_items
                           WHERE kitchen_status IN ('new','in-progress','reordered')
                             AND shown = 0
                             AND (product_id IS NULL OR product_id NOT IN (SELECT product_id FROM excluded_products))";
    $stmt = $db->query($sqlNewNotification);
    $newOrderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Aktualizace položek, které jsme již "notifikovali"
    if (!empty($newOrderIds)) {
        $in = str_repeat('?,', count($newOrderIds) - 1) . '?';
        $sqlUpdate = "UPDATE order_items SET shown = 1 WHERE id IN ($in)";
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute($newOrderIds);
    }

    return [$activeItems, $completedItems];
}



// Normální načtení stránky
list($activeItems, $completedItems) = getOrdersData($db);
?>
<h2>Kuchyň - Aktivní</h2>
<div class="orders-container" id="activeOrdersContainer">
<?php
// Funkce pro vykreslení objednávky
function renderOrder($orderId, $items, $db, $isCompleted = false) {
    $orderInfo = $items[0];
    $orderClass = $isCompleted ? 'order-box completed' : 'order-box';

    // Kontrola, zda jsou všechny položky dokončené
    $allCompleted = true;
    $allInProgress = true;
    foreach ($items as $item) {
        if ($item['kitchen_status'] !== 'completed') {
            $allCompleted = false;
        }
        if ($item['kitchen_status'] !== 'in-progress') {
            $allInProgress = false;
        }
    }

    echo "<div class='$orderClass' data-order-id='$orderId'>";

    // Header s časem
    $createdTime = new DateTime($orderInfo['created']);
    $timeAgo = $createdTime->format('H:i');

    echo "<div class='order-header'>";
    echo "<h3>Objednávka č. " . htmlspecialchars($orderId) . "</h3>";
    echo "<span class='order-time'>$timeAgo</span>";
    echo "</div>";

    echo "<div class='order-body'>";

    // Meta informace
    echo "<div class='order-meta'>";
    if (!empty($orderInfo['table_name'])) {
        echo "<span class='table-info'>Stůl: " . htmlspecialchars($orderInfo['table_name']) . "</span>";
    }
    if (!empty($orderInfo['delivery_service'])) {
        $service = strtolower($orderInfo['delivery_service']);
        echo "<div class='delivery-service'>";
        echo "<span>".ucfirst($service)."</span>";
        echo "<img class='delivery-logo' src='logos/$service.png' alt='$service logo'>";
        echo "</div>";
    }
    echo "</div>";

    // Sjednocené položky objednávky
    $unifiedItems = [];
    foreach ($items as $item) {
        $key = $item['name'] . '|' . ($item['note'] ?? '') . '|' . $item['kitchen_status'];
        if (isset($unifiedItems[$key])) {
            $unifiedItems[$key]['quantity'] += $item['quantity'];
            // Přidáme ID pro možnost aktualizace
            $unifiedItems[$key]['ids'][] = $item['id'];
        } else {
            $unifiedItems[$key] = $item;
            $unifiedItems[$key]['ids'] = [$item['id']];
        }
    }

    echo "<ul class='order-items'>";
    foreach ($unifiedItems as $it) {
        $cssClass = 'status-' . $it['kitchen_status'];
        $itemIds = implode(',', $it['ids']);
        echo "<li class='order-item $cssClass' data-itemid='".htmlspecialchars($itemIds)."'>";
        echo "<div class='item-title'>".htmlspecialchars($it['quantity'])." × ".htmlspecialchars($it['name'])."</div>";

        if (!empty($it['note'])) {
            echo "<div class='item-note'>".htmlspecialchars($it['note'])."</div>";
        }

        // Načteme podpoložky pro první ID (reprezentativní)
        $sqlSub = "SELECT * FROM order_item_subitems WHERE order_item_id = ?";
        $stmtSub = $db->prepare($sqlSub);
        $stmtSub->execute([$it['ids'][0]]);
        $subitems = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
        if ($subitems) {
            foreach ($subitems as $sub) {
                echo "<div class='subitem-line'>+ ".htmlspecialchars($sub['quantity'])." × ".htmlspecialchars($sub['name'])."</div>";
            }
        }
        echo "</li>";
    }
    echo "</ul>";

    // Tlačítka pro celou objednávku
    if (!$isCompleted) {
        echo "<div class='order-actions'>";
        if ($allCompleted) {
            // Všechny položky jsou dokončené - zobrazíme info že je hotovo
            echo "<div class='completion-info'>✅ Objednávka dokončena - bude automaticky předána do baru</div>";
        } elseif ($allInProgress) {
            // Všechny položky jsou v přípravě - tlačítko pro dokončení
            echo "<button class='btn btn-complete' onclick='completeOrder(\"$orderId\")'>Dokončit vše</button>";
        } else {
            // Smíšené stavy - tlačítko pro přípravě
            echo "<button class='btn btn-preparing' onclick='prepareOrder(\"$orderId\")'>Připravit vše</button>";
        }
        echo "</div>";
    } else {
        // Dokončené objednávky - tlačítko pro vrácení
        echo "<div class='order-actions'>";
        echo "<button class='btn btn-secondary' onclick='returnOrder(\"$orderId\")'>Vrátit do přípravy</button>";
        echo "</div>";
    }

    // Poznámka k objednávce
    if (!empty($orderInfo['delivery_note'])) {
        echo "<div class='delivery-note'>".htmlspecialchars($orderInfo['delivery_note'])."</div>";
    }

    echo "</div>";
    echo "</div>";
}

// Seskupíme aktivní položky podle objednávky
$activeOrders = [];
foreach ($activeItems as $item) {
    $orderId = $item['order_id'];
    $activeOrders[$orderId][] = $item;
}

foreach ($activeOrders as $orderId => $items) {
    renderOrder($orderId, $items, $db, false);
}
?>
</div>

<hr class="section-divider">

<h2>Kuchyň - Dokončené</h2>
<div class="orders-container" id="completedOrdersContainer">
<?php
// Seskupíme dokončené položky podle objednávky
$completedOrders = [];
foreach ($completedItems as $item) {
    $orderId = $item['order_id'];
    $completedOrders[$orderId][] = $item;
}

foreach ($completedOrders as $orderId => $items) {
    renderOrder($orderId, $items, $db, true);
}
?>
</div>

<script>
let refreshInterval;
let countdownInterval;
let countdownSeconds = 10;

// Automatická aktualizace
function startAutoRefresh() {
    refreshInterval = setInterval(loadOrders, 10000); // 10 sekund
    startCountdown();
}

function startCountdown() {
    countdownSeconds = 10;
    const countdownElement = document.getElementById('countdown');
    const statusIndicator = document.getElementById('statusIndicator');

    countdownInterval = setInterval(() => {
        countdownSeconds--;
        countdownElement.textContent = countdownSeconds;

        if (countdownSeconds <= 0) {
            countdownSeconds = 10;
        }
    }, 1000);
}

// Načtení objednávek přes AJAX
function loadOrders() {
    const statusIndicator = document.getElementById('statusIndicator');
    const refreshStatus = document.getElementById('refreshStatus');

    statusIndicator.className = 'status-indicator offline';
    refreshStatus.textContent = 'Aktualizuji...';

    fetch('kitchen.php?ajax=1')
        .then(response => response.json())
        .then(data => {
            updateActiveOrders(data.active);
            updateCompletedOrders(data.completed);

            statusIndicator.className = 'status-indicator online';
            refreshStatus.innerHTML = 'Aktualizace za <span id="countdown">10</span>s';

            // Restart countdown
            clearInterval(countdownInterval);
            startCountdown();
        })
        .catch(error => {
            console.error('Chyba při načítání:', error);
            statusIndicator.className = 'status-indicator offline';
            refreshStatus.textContent = 'Chyba připojení';
        });
}

// Aktualizace aktivních objednávek se zachováním scroll pozice
function updateActiveOrders(orders) {
    const container = document.getElementById('activeOrdersContainer');
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    // Zachováme existující objednávky pro porovnání
    const existingOrders = new Set();
    container.querySelectorAll('[data-order-id]').forEach(el => {
        existingOrders.add(el.dataset.orderId);
    });

    const newOrders = new Set(Object.keys(orders));

    // Odstraníme pouze objednávky které už neexistují
    container.querySelectorAll('[data-order-id]').forEach(el => {
        if (!newOrders.has(el.dataset.orderId)) {
            el.classList.add('fade-out');
            setTimeout(() => {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 300);
        }
    });

    // Přidáme nebo aktualizujeme objednávky
    for (const [orderId, items] of Object.entries(orders)) {
        const existingElement = container.querySelector(`[data-order-id="${orderId}"]`);
        if (existingElement) {
            // Aktualizujeme existující objednávku
            updateExistingOrder(existingElement, items, false);
        } else {
            // Přidáme novou objednávku
            container.appendChild(createOrderElement(orderId, items, false));
        }
    }

    // Obnovíme scroll pozici
    window.scrollTo(0, scrollTop);
}

// Aktualizace dokončených objednávek se zachováním scroll pozice
function updateCompletedOrders(orders) {
    const container = document.getElementById('completedOrdersContainer');
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

    // Zachováme existující objednávky
    const existingOrders = new Set();
    container.querySelectorAll('[data-order-id]').forEach(el => {
        existingOrders.add(el.dataset.orderId);
    });

    const newOrders = new Set(Object.keys(orders));

    // Odstraníme objednávky které už neexistují
    container.querySelectorAll('[data-order-id]').forEach(el => {
        if (!newOrders.has(el.dataset.orderId)) {
            el.classList.add('fade-out');
            setTimeout(() => {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 300);
        }
    });

    // Přidáme nebo aktualizujeme objednávky
    for (const [orderId, items] of Object.entries(orders)) {
        const existingElement = container.querySelector(`[data-order-id="${orderId}"]`);
        if (existingElement) {
            updateExistingOrder(existingElement, items, true);
        } else {
            const orderElement = createOrderElement(orderId, items, true);
            container.appendChild(orderElement);

            // Automatické mizení po minutě s efektním odchodem
            setTimeout(() => {
                orderElement.classList.add('removing');
                setTimeout(() => {
                    orderElement.classList.add('fade-out');
                    setTimeout(() => {
                        if (orderElement.parentNode) {
                            orderElement.parentNode.removeChild(orderElement);
                        }
                    }, 500);
                }, 2000);
            }, 60000);
        }
    }

    // Obnovíme scroll pozici
    window.scrollTo(0, scrollTop);
}

// Aktualizace existující objednávky bez přestavění
function updateExistingOrder(orderElement, items, isCompleted) {
    const orderId = orderElement.dataset.orderId;

    // Aktualizujeme tlačítka podle stavu
    const allCompleted = items.every(item => item.kitchen_status === 'completed');
    const allInProgress = items.every(item => item.kitchen_status === 'in-progress');

    const actionsDiv = orderElement.querySelector('.order-actions');
    if (!isCompleted && actionsDiv) {
        if (allCompleted) {
            // Automaticky předáme dokončenou objednávku po 5 sekundách
            actionsDiv.innerHTML = `<div class="completion-info">✅ Objednávka dokončena - automaticky se předá za <span id="countdown-${orderId}">5</span>s</div>`;

            let countdown = 5;
            const countdownElement = document.getElementById(`countdown-${orderId}`);
            const countdownInterval = setInterval(() => {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    autoPassOrder(orderId);
                }
            }, 1000);

        } else if (allInProgress) {
            actionsDiv.innerHTML = `<button class="btn btn-complete" onclick="completeOrder('${orderId}')">Dokončit vše</button>`;
        } else {
            actionsDiv.innerHTML = `<button class="btn btn-preparing" onclick="prepareOrder('${orderId}')">Připravit vše</button>`;
        }
    }

    // Aktualizujeme styly položek
    const unifiedItems = {};
    items.forEach(item => {
        const key = item.name + '|' + (item.note || '') + '|' + item.kitchen_status;
        if (unifiedItems[key]) {
            unifiedItems[key].quantity += parseInt(item.quantity);
        } else {
            unifiedItems[key] = { ...item, quantity: parseInt(item.quantity) };
        }
    });

    orderElement.querySelectorAll('.order-item').forEach((itemEl, index) => {
        const unifiedItemsArray = Object.values(unifiedItems);
        if (unifiedItemsArray[index]) {
            itemEl.className = `order-item status-${unifiedItemsArray[index].kitchen_status}`;
        }
    });
}

// Vytvoření HTML elementu objednávky
function createOrderElement(orderId, items, isCompleted) {
    const orderDiv = document.createElement('div');
    orderDiv.className = isCompleted ? 'order-box completed' : 'order-box';
    orderDiv.setAttribute('data-order-id', orderId);

    const orderInfo = items[0];
    const createdTime = new Date(orderInfo.created);
    const timeAgo = createdTime.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });

    // Kontrola stavů
    const allCompleted = items.every(item => item.kitchen_status === 'completed');
    const allInProgress = items.every(item => item.kitchen_status === 'in-progress');

    let html = `
        <div class="order-header">
            <h3>Objednávka č. ${orderId}</h3>
            <span class="order-time">${timeAgo}</span>
        </div>
        <div class="order-body">
            <div class="order-meta">`;

    if (orderInfo.table_name) {
        html += `<span class="table-info">Stůl: ${orderInfo.table_name}</span>`;
    }

    if (orderInfo.delivery_service) {
        const service = orderInfo.delivery_service.toLowerCase();
        html += `
            <div class="delivery-service">
                <span>${service.charAt(0).toUpperCase() + service.slice(1)}</span>
                <img class="delivery-logo" src="logos/${service}.png" alt="${service} logo">
            </div>`;
    }

    html += `</div><ul class="order-items">`;

    // Sjednocení položek
    const unifiedItems = {};
    items.forEach(item => {
        const key = item.name + '|' + (item.note || '') + '|' + item.kitchen_status;
        if (unifiedItems[key]) {
            unifiedItems[key].quantity += parseInt(item.quantity);
            unifiedItems[key].ids.push(item.id);
        } else {
            unifiedItems[key] = {
                ...item,
                quantity: parseInt(item.quantity),
                ids: [item.id]
            };
        }
    });

    // Sjednocené položky
    Object.values(unifiedItems).forEach(item => {
        const itemIds = item.ids.join(',');
        html += `
            <li class="order-item status-${item.kitchen_status}" data-itemid="${itemIds}">
                <div class="item-title">${item.quantity} × ${item.name}</div>`;

        if (item.note) {
            html += `<div class="item-note">${item.note}</div>`;
        }

        html += `</li>`;
    });

    html += `</ul>`;

    // Tlačítka
    if (!isCompleted) {
        html += `<div class="order-actions">`;
        if (allCompleted) {
            // Automatické předání s odpočtem
            html += `<div class="completion-info">✅ Objednávka dokončena - automaticky se předá za <span id="countdown-${orderId}">5</span>s</div>`;

            // Spustíme odpočet po vytvoření elementu
            setTimeout(() => {
                let countdown = 5;
                const countdownElement = document.getElementById(`countdown-${orderId}`);
                const countdownInterval = setInterval(() => {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        autoPassOrder(orderId);
                    }
                }, 1000);
            }, 100);

        } else if (allInProgress) {
            html += `<button class="btn btn-complete" onclick="completeOrder('${orderId}')">Dokončit vše</button>`;
        } else {
            html += `<button class="btn btn-preparing" onclick="prepareOrder('${orderId}')">Připravit vše</button>`;
        }
        html += `</div>`;
    } else {
        html += `
            <div class="order-actions">
                <button class="btn btn-secondary" onclick="returnOrder('${orderId}')">Vrátit do přípravy</button>
            </div>`;
    }

    if (orderInfo.delivery_note) {
        html += `<div class="delivery-note">${orderInfo.delivery_note}</div>`;
    }

    html += `</div>`;

    orderDiv.innerHTML = html;
    return orderDiv;
}

// Funkce pro změnu stavu celé objednávky
function prepareOrder(orderId) {
    const orderElement = document.querySelector(`[data-order-id="${orderId}"]`);
    const itemIds = [];
    // Rozbalíme všechna ID (mohou být oddělená čárkami)
    orderElement.querySelectorAll('[data-itemid]').forEach(el => {
        const ids = el.dataset.itemid.split(',');
        itemIds.push(...ids);
    });

    // Okamžitá vizuální změna
    updateOrderButtonInstantly(orderElement, 'preparing');

    // AJAX na pozadí s timing
    updateOrderStatus(itemIds, 'in-progress', true); // true = track timing
}

function completeOrder(orderId) {
    const orderElement = document.querySelector(`[data-order-id="${orderId}"]`);
    const itemIds = [];
    // Rozbalíme všechna ID (mohou být oddělená čárkami)
    orderElement.querySelectorAll('[data-itemid]').forEach(el => {
        const ids = el.dataset.itemid.split(',');
        itemIds.push(...ids);
    });

    // Okamžitá vizuální změna
    updateOrderButtonInstantly(orderElement, 'completed');

    // AJAX na pozadí s timing
    updateOrderStatus(itemIds, 'completed', true); // true = track timing
}

// Funkce passOrder už není potřeba v kitchen.php - objednávky se automaticky předávají

function returnOrder(orderId) {
    const orderElement = document.querySelector(`[data-order-id="${orderId}"]`);
    const itemIds = [];
    // Rozbalíme všechna ID (mohou být oddělená čárkami)
    orderElement.querySelectorAll('[data-itemid]').forEach(el => {
        const ids = el.dataset.itemid.split(',');
        itemIds.push(...ids);
    });

    // Okamžitá vizuální změna
    updateOrderButtonInstantly(orderElement, 'preparing');

    // AJAX na pozadí
    updateOrderStatus(itemIds, 'in-progress');
}

// Okamžitá aktualizace tlačítka bez čekání na server
function updateOrderButtonInstantly(orderElement, newState) {
    const actionsDiv = orderElement.querySelector('.order-actions');
    const orderId = orderElement.dataset.orderId;

    if (newState === 'preparing') {
        actionsDiv.innerHTML = `<button class="btn btn-complete" onclick="completeOrder('${orderId}')">Dokončit vše</button>`;
        // Změna stylu položek na in-progress
        orderElement.querySelectorAll('.order-item').forEach(item => {
            item.className = 'order-item status-in-progress';
        });
    } else if (newState === 'completed') {
        // Automatické předání s odpočtem
        actionsDiv.innerHTML = `<div class="completion-info">✅ Objednávka dokončena - automaticky se předá za <span id="countdown-${orderId}">5</span>s</div>`;
        // Změna stylu položek na completed
        orderElement.querySelectorAll('.order-item').forEach(item => {
            item.className = 'order-item status-completed';
        });

        // Spustíme odpočet
        let countdown = 5;
        const countdownElement = document.getElementById(`countdown-${orderId}`);
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                autoPassOrder(orderId);
            }
        }, 1000);
    }
}

// Automatické předání objednávky s efektním mizením
function autoPassOrder(orderId) {
    const orderElement = document.querySelector(`[data-order-id="${orderId}"]`);
    if (!orderElement) return;

    // Označíme objednávku jako odstraňovanou
    orderElement.classList.add('removing');

    // Po 2 sekundách spustíme fade-out efekt
    setTimeout(() => {
        orderElement.classList.add('fade-out');

        // Po dokončení animace odstraníme element
        setTimeout(() => {
            if (orderElement.parentNode) {
                orderElement.parentNode.removeChild(orderElement);
            }
        }, 500);
    }, 2000);

    // AJAX na pozadí s timing
    fetch('update_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderId: orderId, action: 'passCompleted', trackTiming: true })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Chyba při předávání objednávky:', data.error);
            // V případě chyby obnovíme stránku
            setTimeout(() => loadOrders(), 1000);
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        setTimeout(() => loadOrders(), 1000);
    });
}

// Aktualizace stavu položek
function updateOrderStatus(itemIds, newStatus, trackTiming = false) {
    fetch('update_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemIds: itemIds, newStatus: newStatus, trackTiming: trackTiming })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Chyba:', data.error);
            // V případě chyby obnovíme data za chvíli
            setTimeout(() => loadOrders(), 2000);
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        setTimeout(() => loadOrders(), 2000);
    });
}

// Spuštění při načtení stránky
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Zastavení při opuštění stránky
window.addEventListener('beforeunload', function() {
    clearInterval(refreshInterval);
    clearInterval(countdownInterval);
});
</script>
</body>
</html>
