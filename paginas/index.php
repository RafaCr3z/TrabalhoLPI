<?php
    include '../basedados/basedados.h';
    session_start();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="index.css">
    <title>FelixBus - Viaje com Conforto</title>
</head>
<body>
    <header>
        <h1>FelixBus - Viaje com Conforto</h1>
    </header>
    <div class="container">
        <div class="hero">
            <h2>Explore novas rotas com segurança e comodidade!</h2>
            <p>O seu transporte confiável, sempre à sua disposição.</p>
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn">Registar</a>
        </div>
    </div>
    <footer>
        <p>&copy; 2025 <img src="estcb.png" style="max-height: 30px; filter: grayscale(100%);"> FelixBus Joo Resina & Rafael Cruz</p>
    </footer>
</body>
</html>
