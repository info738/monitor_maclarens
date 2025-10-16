<?php
// bar.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'db.php';
date_default_timezone_set('Europe/Prague');

// Pokud je AJAX požadavek, zpracujeme ho HNED na začátku
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    // Funkce pro získání dokončených objednávek pro bar
    function getBarOrdersData($db) {
        // Dotaz pro dokončené položky - pouze posledních 2 hodin
        $sqlCompleted = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                         FROM order_items oi
                         JOIN orders o ON oi.order_id = o.id
                         WHERE oi.kitchen_status = 'completed'
                           AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                           AND oi.last_updated >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                         ORDER BY o.created ASC";
        $stmt = $db->query($sqlCompleted);
        $completedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $completedItems;
    }
    
    $completedItems = getBarOrdersData($db);
    
    // Seskupíme položky podle objednávek a sjednotíme duplicity
    $completedOrders = [];
    foreach ($completedItems as $item) {
        $orderId = $item['order_id'];
        if (!isset($completedOrders[$orderId])) {
            $completedOrders[$orderId] = [];
        }
        
        // Hledáme, zda už máme tuto položku
        $found = false;
        foreach ($completedOrders[$orderId] as &$existingItem) {
            if ($existingItem['name'] === $item['name'] && $existingItem['note'] === $item['note']) {
                // Sečteme množství
                $existingItem['quantity'] += $item['quantity'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $completedOrders[$orderId][] = $item;
        }
    }
    
    echo json_encode([
        'completed' => $completedOrders,
        'timestamp' => time()
    ]);
    exit;
}

// Funkce pro získání dokončených objednávek pro bar
function getBarOrdersData($db) {
    $sqlCompleted = "SELECT oi.*, o.created, o.table_name, o.delivery_service, o.delivery_note
                     FROM order_items oi
                     JOIN orders o ON oi.order_id = o.id
                     WHERE oi.kitchen_status = 'completed'
                       AND (oi.product_id IS NULL OR oi.product_id NOT IN (SELECT product_id FROM excluded_products))
                       AND oi.last_updated >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                     ORDER BY o.created ASC";
    $stmt = $db->query($sqlCompleted);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Normální načtení stránky
$completedItems = getBarOrdersData($db);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<title>Bar monitor</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Stejný styl jako kitchen.php ale s barvou pro bar */
* {
  box-sizing: border-box;
}
body {
  margin: 0; 
  padding: 16px; 
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
  background: #f0f9ff;
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
  background: #0ea5e9;
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
.order-box.fade-out {
  opacity: 0;
  transform: scale(0.95);
}
.order-header {
  padding: 12px 16px; 
  background: #0ea5e9; 
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
  background: #0ea5e9;
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
  background: #f0fdf4; 
  border: 1px solid #bbf7d0; 
  border-radius: 8px;
  padding: 12px; 
  margin-bottom: 8px;
  border-left: 3px solid #10b981;
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
.btn-pass { 
  background: #0ea5e9; 
  color: #fff; 
}
.btn-pass:hover { 
  background: #0284c7; 
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
</style>
</head>
<body>
<div class="auto-refresh-info">
  <span class="status-indicator online" id="statusIndicator"></span>
  <span id="refreshStatus">Aktualizace za <span id="countdown">30</span>s</span>
</div>

<div class="top-menu">
  <a href="index.php">Dashboard</a>
  <a href="kitchen.php">Kuchyň</a>
  <a href="admin_excluded.php">Vyloučené položky</a>
  <a href="admin_warnings.php">Výstražné objednávky</a>
  <a href="statistics.php">Statistiky</a>
</div>

<h2>Bar - Dokončené objednávky</h2>
<div class="orders-container" id="completedOrdersContainer">
<?php
// Funkce pro vykreslení objednávky pro bar
function renderBarOrder($orderId, $items, $db) {
    $orderInfo = $items[0];
    
    echo "<div class='order-box' data-order-id='$orderId'>";
    
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
        $key = $item['name'] . '|' . ($item['note'] ?? '');
        if (isset($unifiedItems[$key])) {
            $unifiedItems[$key]['quantity'] += $item['quantity'];
        } else {
            $unifiedItems[$key] = $item;
        }
    }
    
    echo "<ul class='order-items'>";
    foreach ($unifiedItems as $it) {
        echo "<li class='order-item' data-itemid='".htmlspecialchars($it['id'])."'>";
        echo "<div class='item-title'>".htmlspecialchars($it['quantity'])." × ".htmlspecialchars($it['name'])."</div>";
        
        if (!empty($it['note'])) {
            echo "<div class='item-note'>".htmlspecialchars($it['note'])."</div>";
        }
        
        // Načteme podpoložky
        $sqlSub = "SELECT * FROM order_item_subitems WHERE order_item_id = ?";
        $stmtSub = $db->prepare($sqlSub);
        $stmtSub->execute([$it['id']]);
        $subitems = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
        if ($subitems) {
            foreach ($subitems as $sub) {
                echo "<div class='subitem-line'>+ ".htmlspecialchars($sub['quantity'])." × ".htmlspecialchars($sub['name'])."</div>";
            }
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // Tlačítko pro předání
    echo "<div class='order-actions'>";
    echo "<button class='btn btn-pass' onclick='passOrder(\"$orderId\")'>Předat objednávku</button>";
    echo "</div>";
    
    // Poznámka k objednávce
    if (!empty($orderInfo['delivery_note'])) {
        echo "<div class='delivery-note'>".htmlspecialchars($orderInfo['delivery_note'])."</div>";
    }
    
    echo "</div>";
    echo "</div>";
}

// Seskupíme dokončené položky podle objednávky
$completedOrders = [];
foreach ($completedItems as $item) {
    $orderId = $item['order_id'];
    $completedOrders[$orderId][] = $item;
}

foreach ($completedOrders as $orderId => $items) {
    renderBarOrder($orderId, $items, $db);
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

    fetch('bar.php?ajax=1')
        .then(response => response.json())
        .then(data => {
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

// Aktualizace dokončených objednávek
function updateCompletedOrders(orders) {
    const container = document.getElementById('completedOrdersContainer');
    container.innerHTML = '';

    for (const [orderId, items] of Object.entries(orders)) {
        const orderElement = createBarOrderElement(orderId, items);
        container.appendChild(orderElement);
    }
}

// Vytvoření HTML elementu objednávky pro bar
function createBarOrderElement(orderId, items) {
    const orderDiv = document.createElement('div');
    orderDiv.className = 'order-box';
    orderDiv.setAttribute('data-order-id', orderId);

    const orderInfo = items[0];
    const createdTime = new Date(orderInfo.created);
    const timeAgo = createdTime.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });

    // Sjednocení položek
    const unifiedItems = {};
    items.forEach(item => {
        const key = item.name + '|' + (item.note || '');
        if (unifiedItems[key]) {
            unifiedItems[key].quantity += parseInt(item.quantity);
        } else {
            unifiedItems[key] = { ...item, quantity: parseInt(item.quantity) };
        }
    });

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

    // Sjednocené položky
    Object.values(unifiedItems).forEach(item => {
        html += `
            <li class="order-item" data-itemid="${item.id}">
                <div class="item-title">${item.quantity} × ${item.name}</div>`;

        if (item.note) {
            html += `<div class="item-note">${item.note}</div>`;
        }

        html += `</li>`;
    });

    html += `</ul>`;

    // Tlačítko pro předání
    html += `
        <div class="order-actions">
            <button class="btn btn-pass" onclick="passOrder('${orderId}')">Předat objednávku</button>
        </div>`;

    if (orderInfo.delivery_note) {
        html += `<div class="delivery-note">${orderInfo.delivery_note}</div>`;
    }

    html += `</div>`;

    orderDiv.innerHTML = html;
    return orderDiv;
}

// Předání objednávky
function passOrder(orderId) {
    const orderElement = document.querySelector(`[data-order-id="${orderId}"]`);

    // Okamžitá vizuální změna - objednávka zmizí
    orderElement.classList.add('fade-out');
    setTimeout(() => {
        if (orderElement.parentNode) {
            orderElement.parentNode.removeChild(orderElement);
        }
    }, 300);

    // AJAX na pozadí
    fetch('update_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderId: orderId, action: 'passCompleted' })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Chyba:', data.error);
            // V případě chyby obnovíme stránku
            setTimeout(() => loadOrders(), 1000);
        }
    })
    .catch(error => {
        console.error('Chyba:', error);
        setTimeout(() => loadOrders(), 1000);
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
