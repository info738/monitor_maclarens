<?php
session_start();
require_once 'db.php';

// Kontrola přihlášení
if (!isset($_SESSION['stats_user'])) {
    // Zpracování přihlášení
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            $stmt = $db->prepare("SELECT id, username, password_hash, role FROM stats_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['stats_user'] = $user;
                
                // Aktualizace posledního přihlášení
                $stmt = $db->prepare("UPDATE stats_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                header('Location: statistics.php');
                exit;
            } else {
                $loginError = 'Nesprávné přihlašovací údaje';
            }
        }
    }
    
    // Zobrazení přihlašovacího formuláře
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <title>Přihlášení - Statistiky</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0; padding: 0; min-height: 100vh;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex; align-items: center; justify-content: center;
            }
            .login-card {
                background: #fff; padding: 40px; border-radius: 16px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                width: 100%; max-width: 400px;
            }
            .login-header {
                text-align: center; margin-bottom: 32px;
            }
            .login-title {
                font-size: 24px; font-weight: 600; color: #1e293b; margin-bottom: 8px;
            }
            .login-subtitle {
                color: #64748b; font-size: 14px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-label {
                display: block; margin-bottom: 6px; font-weight: 500; color: #374151;
            }
            .form-input {
                width: 100%; padding: 12px 16px; border: 1px solid #d1d5db;
                border-radius: 8px; font-size: 14px; transition: all 0.2s;
            }
            .form-input:focus {
                outline: none; border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            .btn-login {
                width: 100%; padding: 12px; background: #3b82f6; color: #fff;
                border: none; border-radius: 8px; font-size: 14px; font-weight: 600;
                cursor: pointer; transition: all 0.2s;
            }
            .btn-login:hover { background: #2563eb; }
            .error-message {
                background: #fef2f2; color: #dc2626; padding: 12px; border-radius: 8px;
                margin-bottom: 20px; font-size: 14px; border: 1px solid #fecaca;
            }
            .demo-info {
                background: #f0f9ff; color: #0369a1; padding: 12px; border-radius: 8px;
                margin-top: 20px; font-size: 13px; border: 1px solid #bae6fd;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="login-header">
                <div class="login-title">📊 Statistiky</div>
                <div class="login-subtitle">Přihlaste se pro zobrazení statistik</div>
            </div>
            
            <?php if (isset($loginError)): ?>
            <div class="error-message"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Uživatelské jméno</label>
                    <input type="text" name="username" class="form-input" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label">Heslo</label>
                    <input type="password" name="password" class="form-input" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-login">Přihlásit se</button>
            </form>
            
            <div class="demo-info">
                <strong>Demo přístup:</strong><br>
                Uživatel: <code>admin</code><br>
                Heslo: <code>admin123</code>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Odhlášení
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: statistics.php');
    exit;
}

// Získání statistik
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$thisWeek = date('Y-m-d', strtotime('-7 days'));

// Dnešní statistiky
$stmt = $db->prepare("SELECT 
                        COUNT(*) as total_orders,
                        AVG(preparation_time) as avg_prep_time,
                        AVG(total_time) as avg_total_time,
                        MAX(preparation_time) as max_prep_time,
                        SUM(CASE WHEN total_time <= 900 THEN 1 ELSE 0 END) as under_15min,
                        SUM(CASE WHEN total_time > 900 AND total_time <= 1800 THEN 1 ELSE 0 END) as between_15_30min,
                        SUM(CASE WHEN total_time > 1800 THEN 1 ELSE 0 END) as over_30min
                     FROM order_timing 
                     WHERE DATE(created_at) = ? AND status IN ('completed', 'warning')");
$stmt->execute([$today]);
$todayStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Včerejší statistiky pro porovnání
$stmt->execute([$yesterday]);
$yesterdayStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Top 10 nejpomalejších položek
$stmt = $db->query("SELECT item_name, avg_preparation_time, total_orders 
                    FROM item_stats 
                    WHERE total_orders >= 3
                    ORDER BY avg_preparation_time DESC 
                    LIMIT 10");
$slowestItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 10 nejrychlejších položek
$stmt = $db->query("SELECT item_name, avg_preparation_time, total_orders 
                    FROM item_stats 
                    WHERE total_orders >= 3
                    ORDER BY avg_preparation_time ASC 
                    LIMIT 10");
$fastestItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Týdenní trend
$stmt = $db->prepare("SELECT date, avg_preparation_time, total_orders, warning_orders
                     FROM daily_stats 
                     WHERE date >= ? 
                     ORDER BY date ASC");
$stmt->execute([$thisWeek]);
$weeklyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktuální výstražné objednávky
$stmt = $db->query("SELECT COUNT(*) as warning_count FROM order_timing WHERE status = 'warning'");
$warningCount = $stmt->fetchColumn();

// Hodinové statistiky pro dnešek (pro detailní graf)
$stmt = $db->prepare("SELECT
                        HOUR(created_at) as hour,
                        COUNT(*) as orders_count,
                        AVG(preparation_time) as avg_prep_time
                     FROM order_timing
                     WHERE DATE(created_at) = ? AND status IN ('completed', 'warning')
                     GROUP BY HOUR(created_at)
                     ORDER BY hour");
$stmt->execute([$today]);
$hourlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vytvoříme pole pro všechny hodiny (0-23)
$hourlyData = array_fill(0, 24, ['orders_count' => 0, 'avg_prep_time' => 0]);
foreach ($hourlyStats as $stat) {
    $hourlyData[$stat['hour']] = $stat;
}

// Rozložení časů přípravy (histogram)
$stmt = $db->prepare("SELECT
                        CASE
                            WHEN preparation_time <= 300 THEN '0-5 min'
                            WHEN preparation_time <= 600 THEN '5-10 min'
                            WHEN preparation_time <= 900 THEN '10-15 min'
                            WHEN preparation_time <= 1200 THEN '15-20 min'
                            WHEN preparation_time <= 1800 THEN '20-30 min'
                            ELSE '30+ min'
                        END as time_range,
                        COUNT(*) as count
                     FROM order_timing
                     WHERE DATE(created_at) = ? AND preparation_time IS NOT NULL
                     GROUP BY time_range
                     ORDER BY MIN(preparation_time)");
$stmt->execute([$today]);
$timeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 nejaktivnějších stolů/služeb
$stmt = $db->prepare("SELECT
                        COALESCE(table_name, delivery_service, 'Neznámý') as source,
                        COUNT(*) as orders_count,
                        AVG(preparation_time) as avg_prep_time
                     FROM order_timing
                     WHERE DATE(created_at) >= ? AND status IN ('completed', 'warning')
                     GROUP BY source
                     ORDER BY orders_count DESC
                     LIMIT 5");
$stmt->execute([$thisWeek]);
$topSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Statistiky - Monitor kuchyně</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 16px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc; color: #1e293b; font-size: 14px; line-height: 1.5;
        }
        .top-menu {
            display: flex; gap: 8px; margin-bottom: 24px; justify-content: space-between;
            background: #fff; padding: 12px 16px; border-radius: 12px;
            align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        .menu-left { display: flex; gap: 8px; }
        .top-menu a {
            text-decoration: none; padding: 6px 12px; background: #f1f5f9;
            border-radius: 6px; color: #475569; border: 1px solid #e2e8f0;
            font-size: 13px; font-weight: 500; transition: all 0.2s;
        }
        .top-menu a:hover { background: #e2e8f0; color: #334155; }
        .user-info {
            display: flex; align-items: center; gap: 12px; font-size: 13px; color: #64748b;
        }
        .logout-btn {
            color: #dc2626; text-decoration: none; font-weight: 500;
        }
        h1 { margin: 0 0 24px 0; font-weight: 600; font-size: 24px; color: #0f172a; }
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: #fff; padding: 20px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
        }
        .stat-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 12px;
        }
        .stat-title { font-size: 13px; color: #64748b; font-weight: 500; }
        .stat-trend {
            font-size: 12px; padding: 2px 8px; border-radius: 12px;
            font-weight: 500;
        }
        .trend-up { background: #dcfce7; color: #166534; }
        .trend-down { background: #fef2f2; color: #dc2626; }
        .trend-same { background: #f1f5f9; color: #475569; }
        .stat-value { font-size: 28px; font-weight: 600; color: #0f172a; }
        .stat-unit { font-size: 14px; color: #64748b; margin-left: 4px; }
        .chart-section {
            background: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
        }
        .chart-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #0f172a; }
        .items-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 24px;
        }
        .items-list {
            background: #fff; padding: 20px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;
        }
        .items-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #0f172a; }
        .item-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f1f5f9;
        }
        .item-row:last-child { border-bottom: none; }
        .item-name { font-weight: 500; color: #374151; }
        .item-time { color: #64748b; font-size: 13px; }
        .warning-banner {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
            padding: 12px 16px; margin-bottom: 24px; color: #dc2626;
        }
    </style>
</head>
<body>

<div class="top-menu">
    <div class="menu-left">
        <a href="index.php">Dashboard</a>
        <a href="kitchen.php">Kuchyň</a>
        <a href="bar.php">Bar</a>
        <a href="admin_excluded.php">Vyloučené položky</a>
        <a href="admin_warnings.php">Výstražné objednávky</a>
    </div>
    <div class="user-info">
        Přihlášen jako: <strong><?php echo htmlspecialchars($_SESSION['stats_user']['username']); ?></strong>
        <a href="?logout=1" class="logout-btn">Odhlásit</a>
    </div>
</div>

<h1>📊 Statistiky výkonu kuchyně</h1>

<?php if ($warningCount > 0): ?>
<div class="warning-banner">
    ⚠️ <strong><?php echo $warningCount; ?></strong> objednávek čeká na kontrolu (nad 30 minut)
    <a href="admin_warnings.php" style="margin-left: 12px; color: #dc2626; font-weight: 600;">Zobrazit →</a>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Objednávky dnes</div>
            <?php 
            $todayCount = $todayStats['total_orders'] ?: 0;
            $yesterdayCount = $yesterdayStats['total_orders'] ?: 0;
            $diff = $todayCount - $yesterdayCount;
            if ($diff > 0) {
                echo '<div class="stat-trend trend-up">+' . $diff . '</div>';
            } elseif ($diff < 0) {
                echo '<div class="stat-trend trend-down">' . $diff . '</div>';
            } else {
                echo '<div class="stat-trend trend-same">±0</div>';
            }
            ?>
        </div>
        <div class="stat-value"><?php echo $todayCount; ?></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Průměrný čas přípravy</div>
            <?php 
            $todayAvg = $todayStats['avg_prep_time'] ?: 0;
            $yesterdayAvg = $yesterdayStats['avg_prep_time'] ?: 0;
            $diff = $todayAvg - $yesterdayAvg;
            if ($diff > 30) {
                echo '<div class="stat-trend trend-up">+' . round($diff/60, 1) . 'min</div>';
            } elseif ($diff < -30) {
                echo '<div class="stat-trend trend-down">' . round($diff/60, 1) . 'min</div>';
            } else {
                echo '<div class="stat-trend trend-same">±0</div>';
            }
            ?>
        </div>
        <div class="stat-value"><?php echo round($todayAvg/60, 1); ?><span class="stat-unit">min</span></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Objednávky do 15 min</div>
        </div>
        <div class="stat-value"><?php echo $todayStats['under_15min'] ?: 0; ?><span class="stat-unit">/ <?php echo $todayCount; ?></span></div>
    </div>
    
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-title">Objednávky nad 30 min</div>
        </div>
        <div class="stat-value" style="color: #dc2626;"><?php echo $todayStats['over_30min'] ?: 0; ?><span class="stat-unit">/ <?php echo $todayCount; ?></span></div>
    </div>
</div>

<div class="chart-section">
    <div class="chart-title">Týdenní trend výkonu</div>
    <canvas id="weeklyChart" width="400" height="200"></canvas>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <div class="chart-section">
        <div class="chart-title">Dnešní aktivita po hodinách</div>
        <canvas id="hourlyChart" width="400" height="300"></canvas>
    </div>

    <div class="chart-section">
        <div class="chart-title">Rozložení časů přípravy</div>
        <canvas id="distributionChart" width="400" height="300"></canvas>
    </div>
</div>

<div class="chart-section">
    <div class="chart-title">Top 5 nejaktivnějších zdrojů (týden)</div>
    <canvas id="sourcesChart" width="400" height="200"></canvas>
</div>

<div class="items-grid">
    <div class="items-list">
        <div class="items-title">🐌 Nejpomalejší položky</div>
        <?php foreach ($slowestItems as $item): ?>
        <div class="item-row">
            <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
            <div class="item-time"><?php echo round($item['avg_preparation_time']/60, 1); ?> min (<?php echo $item['total_orders']; ?>×)</div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="items-list">
        <div class="items-title">⚡ Nejrychlejší položky</div>
        <?php foreach ($fastestItems as $item): ?>
        <div class="item-row">
            <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
            <div class="item-time"><?php echo round($item['avg_preparation_time']/60, 1); ?> min (<?php echo $item['total_orders']; ?>×)</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Graf týdenního trendu
const ctx = document.getElementById('weeklyChart').getContext('2d');
const weeklyChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php echo '"' . implode('", "', array_map(function($d) { return date('j.n.', strtotime($d['date'])); }, $weeklyTrend)) . '"'; ?>],
        datasets: [{
            label: 'Průměrný čas přípravy (min)',
            data: [<?php echo implode(', ', array_map(function($d) { return round(($d['avg_preparation_time'] ?: 0)/60, 1); }, $weeklyTrend)); ?>],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4
        }, {
            label: 'Počet objednávek',
            data: [<?php echo implode(', ', array_map(function($d) { return $d['total_orders'] ?: 0; }, $weeklyTrend)); ?>],
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Čas (minuty)' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'Počet objednávek' },
                grid: { drawOnChartArea: false }
            }
        }
    }
});

// Graf hodinové aktivity
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyChart = new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo '"' . implode('", "', array_map(function($i) { return $i . ':00'; }, range(0, 23))) . '"'; ?>],
        datasets: [{
            label: 'Počet objednávek',
            data: [<?php echo implode(', ', array_map(function($d) { return $d['orders_count']; }, $hourlyData)); ?>],
            backgroundColor: 'rgba(59, 130, 246, 0.6)',
            borderColor: '#3b82f6',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Graf rozložení časů
const distributionCtx = document.getElementById('distributionChart').getContext('2d');
const distributionChart = new Chart(distributionCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo '"' . implode('", "', array_map(function($d) { return $d['time_range']; }, $timeDistribution)) . '"'; ?>],
        datasets: [{
            data: [<?php echo implode(', ', array_map(function($d) { return $d['count']; }, $timeDistribution)); ?>],
            backgroundColor: [
                '#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#dc2626'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Graf top zdrojů
const sourcesCtx = document.getElementById('sourcesChart').getContext('2d');
const sourcesChart = new Chart(sourcesCtx, {
    type: 'horizontalBar',
    data: {
        labels: [<?php echo '"' . implode('", "', array_map(function($d) { return htmlspecialchars($d['source']); }, $topSources)) . '"'; ?>],
        datasets: [{
            label: 'Počet objednávek',
            data: [<?php echo implode(', ', array_map(function($d) { return $d['orders_count']; }, $topSources)); ?>],
            backgroundColor: 'rgba(16, 185, 129, 0.6)',
            borderColor: '#10b981',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: { beginAtZero: true }
        }
    }
});

// Auto-refresh každých 5 minut
setInterval(() => {
    window.location.reload();
}, 300000);
</script>

</body>
</html>
