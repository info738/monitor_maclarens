<?php
// admin_excluded.php
session_start();
// if (!isset($_SESSION['logged_in'])) { exit('Nepřihlášen.'); }

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require 'db.php';

$productsFile = 'products.json';
$excludedFile = 'excluded_items.json';

// 0) Import již dříve vyloučených položek z excluded_items.json do DB,
// pokud je tabulka excluded_products prázdná.
$stmt = $db->query("SELECT COUNT(*) FROM excluded_products");
$count = $stmt->fetchColumn();
if ($count == 0 && file_exists($excludedFile)) {
    $json = file_get_contents($excludedFile);
    $excludedFromJson = json_decode($json, true);
    if (is_array($excludedFromJson)) {
        $stmtInsert = $db->prepare("INSERT INTO excluded_products (product_id) VALUES (:product_id)");
        foreach ($excludedFromJson as $pid) {
            $stmtInsert->execute([':product_id' => $pid]);
        }
    }
}

// 1) Načtení produktů z products.json (očekáváme pole produktů)
if (!file_exists($productsFile)) {
    die("products.json neexistuje. Spusťte fetch_products.php nebo jiný skript.");
}
$productsData = json_decode(file_get_contents($productsFile), true);
if (!is_array($productsData)) {
    die("Neplatný formát products.json (není pole).");
}

// 2) Načtení aktuálního seznamu vyloučených produktů z DB
$stmt = $db->query("SELECT product_id FROM excluded_products");
$excludedData = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 3) Zpracování formuláře – aktualizace seznamu vyloučených produktů
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Očekáváme, že formulář vrací pole 'excluded' obsahující produktová ID
    $newExcluded = $_POST['excluded'] ?? [];
    // Vymažeme celý stávající seznam
    $db->exec("DELETE FROM excluded_products");
    // Vložíme nová vyloučená ID
    $stmtInsert = $db->prepare("INSERT INTO excluded_products (product_id) VALUES (:product_id)");
    foreach ($newExcluded as $pid) {
        $stmtInsert->execute([':product_id' => $pid]);
    }
    $excludedData = $newExcluded;
}

// 4) Heuristika pro klíčová slova – např. pro nápoje
$beverageKeywords = [
    'pivo','beer','kofola','cola','cider','juice','tonic','vodka','rum','gin','mojito','mai-tai','tequila',
    'cuba libre','spritz','wine','vino','voda','rajec','sprite','f.h. prager','sierra nevada','desperados',
    // Další klíčová slova…
];

// 5) Rozdělení produktů – nápoje vs. ostatní
$beverageList = [];
$othersList   = [];
foreach ($productsData as $p) {
    $pname = $p['name'] ?? '';
    $pid   = $p['id'] ?? '';
    if (!$pname || !$pid) continue;
    $maybeBev = false;
    foreach ($beverageKeywords as $kw) {
        if (stripos($pname, $kw) !== false) {
            $maybeBev = true;
            break;
        }
    }
    if ($maybeBev) {
        $beverageList[] = $p;
    } else {
        $othersList[] = $p;
    }
}
usort($beverageList, function($a,$b){ return strcmp($a['name']??'', $b['name']??''); });
usort($othersList, function($a,$b){ return strcmp($a['name']??'', $b['name']??''); });
$finalList = array_merge($beverageList, $othersList);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Administrace vyloučených položek</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
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
    h1 {
      margin: 0 0 24px 0;
      font-weight: 600;
      font-size: 24px;
      color: #0f172a;
    }
    .controls-section {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
    }
    .search-controls {
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    .search-input {
      flex: 1;
      min-width: 300px;
      padding: 10px 16px;
      font-size: 14px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      background: #fff;
      transition: all 0.2s;
    }
    .search-input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .filter-buttons {
      display: flex;
      gap: 8px;
    }
    .filter-btn {
      padding: 8px 16px;
      font-size: 13px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      background: #fff;
      color: #374151;
      cursor: pointer;
      transition: all 0.2s;
      font-weight: 500;
    }
    .filter-btn:hover {
      background: #f3f4f6;
    }
    .filter-btn.active {
      background: #3b82f6;
      color: #fff;
      border-color: #3b82f6;
    }
    .stats-row {
      display: flex;
      gap: 16px;
      font-size: 13px;
      color: #64748b;
    }
    .stat-item {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .stat-badge {
      background: #f1f5f9;
      color: #475569;
      padding: 2px 8px;
      border-radius: 12px;
      font-weight: 500;
    }
    .grid-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 12px;
    }
    .product-box {
      background: #fff;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid #e2e8f0;
      position: relative;
    }
    .product-box:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .product-box.excluded {
      background: #fef2f2;
      border-color: #fca5a5;
    }
    .product-box.excluded::before {
      content: '✓';
      position: absolute;
      top: 8px;
      right: 8px;
      width: 20px;
      height: 20px;
      background: #ef4444;
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
    }
    .product-name {
      font-size: 14px;
      font-weight: 600;
      margin: 0 0 8px 0;
      color: #0f172a;
      line-height: 1.4;
    }
    .product-id {
      font-size: 11px;
      color: #94a3b8;
      margin-bottom: 8px;
      font-family: 'Monaco', 'Menlo', monospace;
    }
    .product-tags {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
    }
    .tag {
      display: inline-block;
      padding: 2px 8px;
      font-size: 11px;
      border-radius: 12px;
      font-weight: 500;
    }
    .tag-beverage {
      background: #dbeafe;
      color: #1e40af;
    }
    .tag-food {
      background: #dcfce7;
      color: #166534;
    }
    .tag-other {
      background: #f3f4f6;
      color: #374151;
    }
    .actions-section {
      position: sticky;
      bottom: 0;
      background: #fff;
      padding: 16px 20px;
      border-radius: 12px;
      margin-top: 24px;
      box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn-save {
      padding: 12px 24px;
      font-size: 14px;
      border: none;
      border-radius: 8px;
      background: #3b82f6;
      color: #fff;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
    }
    .btn-save:hover {
      background: #2563eb;
      transform: translateY(-1px);
    }
    .btn-save:disabled {
      background: #9ca3af;
      cursor: not-allowed;
      transform: none;
    }
    .selection-info {
      font-size: 13px;
      color: #64748b;
    }
    .checkbox-hidden {
      display: none;
    }
    .hidden {
      display: none !important;
    }
    .success-message {
      background: #f0fdf4;
      color: #166534;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 16px;
      border: 1px solid #bbf7d0;
    }
    .loading {
      opacity: 0.6;
      pointer-events: none;
    }
  </style>
</head>
<body>

<div class="top-menu">
  <a href="index.php">Dashboard</a>
  <a href="kitchen.php">Kuchyň</a>
  <a href="bar.php">Bar</a>
  <a href="fetch_products.php">Stáhnout aktuální položky</a>
</div>

<h1>Administrace vyloučených položek</h1>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<div class="success-message">
  ✓ Uloženo! (<?php echo count($excludedData); ?> vyloučených položek)
</div>
<?php endif; ?>

<form method="POST" id="excludedForm">
  <div class="controls-section">
    <div class="search-controls">
      <input type="text"
             id="searchInput"
             class="search-input"
             placeholder="Vyhledat položku podle názvu nebo ID..."
             autocomplete="off">

      <div class="filter-buttons">
        <button type="button" class="filter-btn active" data-filter="all">Vše</button>
        <button type="button" class="filter-btn" data-filter="beverages">Nápoje</button>
        <button type="button" class="filter-btn" data-filter="food">Jídlo</button>
        <button type="button" class="filter-btn" data-filter="excluded">Vyloučené</button>
        <button type="button" class="filter-btn" data-filter="included">Zahrnuté</button>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-item">
        <span>Celkem položek:</span>
        <span class="stat-badge" id="totalCount"><?php echo count($finalList); ?></span>
      </div>
      <div class="stat-item">
        <span>Zobrazeno:</span>
        <span class="stat-badge" id="visibleCount"><?php echo count($finalList); ?></span>
      </div>
      <div class="stat-item">
        <span>Vyloučeno:</span>
        <span class="stat-badge" id="excludedCount"><?php echo count($excludedData); ?></span>
      </div>
    </div>
  </div>

  <div class="grid-container" id="productsContainer">
    <?php
    foreach ($finalList as $prod) {
        $pname = $prod['name'] ?? '';
        $pid   = $prod['id'] ?? '';
        if (!$pname || !$pid) continue;

        $isExcluded = in_array($pid, $excludedData, true);
        $maybeBeverage = in_array($prod, $beverageList, true);

        // Určení kategorie
        $category = 'other';
        if ($maybeBeverage) {
            $category = 'beverage';
        } else {
            // Jednoduchá heuristika pro jídlo
            $foodKeywords = ['burger', 'pizza', 'salát', 'soup', 'polévka', 'steak', 'chicken', 'kuře', 'fish', 'ryba'];
            foreach ($foodKeywords as $kw) {
                if (stripos($pname, $kw) !== false) {
                    $category = 'food';
                    break;
                }
            }
        }

        // Unikátní ID pro HTML prvek
        $htmlId = 'chk_'.md5($pid);
        $boxClass = 'product-box'.($isExcluded ? ' excluded' : '');

        echo "<label class='$boxClass'
                     for='".htmlspecialchars($htmlId)."'
                     data-boxid='".htmlspecialchars($htmlId)."'
                     data-name='".strtolower($pname)."'
                     data-id='".strtolower($pid)."'
                     data-category='$category'
                     data-excluded='".($isExcluded ? 'true' : 'false')."'>";

            echo "<div class='product-name'>".htmlspecialchars($pname)."</div>";
            echo "<div class='product-id'>".htmlspecialchars($pid)."</div>";

            echo "<div class='product-tags'>";
            if ($category === 'beverage') {
                echo "<span class='tag tag-beverage'>Nápoj</span>";
            } elseif ($category === 'food') {
                echo "<span class='tag tag-food'>Jídlo</span>";
            } else {
                echo "<span class='tag tag-other'>Ostatní</span>";
            }
            echo "</div>";

            $checkedAttr = $isExcluded ? 'checked' : '';
            echo "<input type='checkbox' class='checkbox-hidden' id='".htmlspecialchars($htmlId)."' name='excluded[]' value='".htmlspecialchars($pid)."' $checkedAttr>";
        echo "</label>";
    }
    ?>
  </div>

  <div class="actions-section">
    <div class="selection-info">
      <span id="selectionCount">0</span> položek vybráno k vyloučení
    </div>
    <button type="submit" class="btn-save" id="saveBtn">
      Uložit změny
    </button>
  </div>
</form>

<script>
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', function(){
  initializeEventListeners();
  updateStats();
});

function initializeEventListeners() {
  // Checkbox listenery
  const boxes = document.querySelectorAll('.product-box');
  boxes.forEach(box => {
    const htmlId = box.getAttribute('data-boxid');
    const checkbox = document.getElementById(htmlId);

    checkbox.addEventListener('change', function(){
      if (checkbox.checked) {
        box.classList.add('excluded');
        box.dataset.excluded = 'true';
      } else {
        box.classList.remove('excluded');
        box.dataset.excluded = 'false';
      }
      updateStats();
    });
  });

  // Search input
  const searchInput = document.getElementById('searchInput');
  searchInput.addEventListener('input', debounce(filterBoxes, 300));

  // Filter buttons
  const filterButtons = document.querySelectorAll('.filter-btn');
  filterButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      filterButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentFilter = this.dataset.filter;
      filterBoxes();
    });
  });

  // Form submission
  const form = document.getElementById('excludedForm');
  form.addEventListener('submit', function() {
    const saveBtn = document.getElementById('saveBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Ukládám...';
    document.body.classList.add('loading');
  });
}

function filterBoxes() {
  const searchValue = document.getElementById('searchInput').value.toLowerCase();
  const container = document.getElementById('productsContainer');
  const boxes = container.querySelectorAll('.product-box');
  let visibleCount = 0;

  boxes.forEach(box => {
    let visible = true;

    // Search filter
    if (searchValue) {
      const productName = box.dataset.name || '';
      const productId = box.dataset.id || '';
      if (productName.indexOf(searchValue) === -1 && productId.indexOf(searchValue) === -1) {
        visible = false;
      }
    }

    // Category filter
    if (visible && currentFilter !== 'all') {
      if (currentFilter === 'beverages' && box.dataset.category !== 'beverage') {
        visible = false;
      } else if (currentFilter === 'food' && box.dataset.category !== 'food') {
        visible = false;
      } else if (currentFilter === 'excluded' && box.dataset.excluded !== 'true') {
        visible = false;
      } else if (currentFilter === 'included' && box.dataset.excluded !== 'false') {
        visible = false;
      }
    }

    if (visible) {
      box.classList.remove('hidden');
      visibleCount++;
    } else {
      box.classList.add('hidden');
    }
  });

  document.getElementById('visibleCount').textContent = visibleCount;
}

function updateStats() {
  const boxes = document.querySelectorAll('.product-box');
  let excludedCount = 0;
  let selectedCount = 0;

  boxes.forEach(box => {
    const checkbox = document.getElementById(box.dataset.boxid);
    if (checkbox.checked) {
      excludedCount++;
      selectedCount++;
    }
  });

  document.getElementById('excludedCount').textContent = excludedCount;
  document.getElementById('selectionCount').textContent = selectedCount;
}

// Debounce function for search
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
  // Ctrl/Cmd + S to save
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    document.getElementById('excludedForm').submit();
  }

  // Escape to clear search
  if (e.key === 'Escape') {
    document.getElementById('searchInput').value = '';
    filterBoxes();
  }
});
</script>
</body>
</html>
