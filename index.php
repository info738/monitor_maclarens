<?php
// index.php

session_start();

// Nastav si libovolné heslo
$correctPassword = "maclarensPUB";

// Zpracování přihlášení
if (isset($_POST['password'])) {
    if ($_POST['password'] === $correctPassword) {
        $_SESSION['logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Špatné heslo!";
    }
}

// Odhlášení
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Pokud není přihlášen, zobrazíme formulář
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // HTML login formulář
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <title>Přihlášení</title>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Roboto', sans-serif;
                background: #f5f5f5;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-box {
                background: #fff;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                width: 300px;
            }
            h2 {
                margin-top: 0;
                margin-bottom: 15px;
                text-align: center;
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: 700;
            }
            input[type="password"] {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 14px;
            }
            .error {
                color: red;
                margin-bottom: 10px;
                text-align: center;
            }
            button {
                background: #007bff;
                color: #fff;
                border: none;
                border-radius: 4px;
                padding: 10px 20px;
                font-size: 14px;
                cursor: pointer;
                width: 100%;
            }
            button:hover {
                background: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Přihlášení</h2>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="password">Zadejte heslo</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit">Přihlásit se</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Pokud je přihlášen, zobrazíme rozcestník
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Monitor - Rozcestník</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0; 
            padding: 20px; 
            font-family: 'Roboto', sans-serif;
            background: #f5f5f5;
        }
        h1 {
            margin-top: 0;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }
        .menu-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            max-width: 800px;
            margin: 0 auto;
            justify-content: center;
        }
        .menu-box {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            width: 220px;
            height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.2s;
        }
        .menu-box:hover {
            transform: translateY(-3px);
        }
        .menu-box h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        .btn-logout {
            display: block;
            text-align: right;
            margin-bottom: 20px;
        }
        .btn-logout a {
            color: #dc3545;
            text-decoration: none;
            font-weight: 700;
        }
        .btn-logout a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="btn-logout">
    <a href="?logout=1">Odhlásit se</a>
</div>

<h1>MacLaren's pub Monitor</h1>

<div class="menu-container">
    <!-- Kuchyně -->
    <a class="menu-box" href="kitchen.php">
        <h3>Kuchyň</h3>
    </a>

    <!-- Bar -->
    <a class="menu-box" href="bar.php">
        <h3>Bar</h3>
    </a>

    <!-- Administrace vyloučených -->
    <a class="menu-box" href="excluded_admin.php">
        <h3>Vyloučené položky</h3>
    </a>
	
	<!-- Administrace reportů -->
	<a class="menu-box" href="report_stats.php">
  <h3>Report - Denní statistika</h3>
</a>


    <!-- Aktualizace feedu (order.php) -->
    <a class="menu-box" href="order.php" target="_blank">
        <h3>Aktualizovat feed</h3>
    </a>
</div>

</body>
</html>
