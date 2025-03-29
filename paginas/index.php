<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FelixBus - Página Inicial</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        header {
            background-color: #333;
            color: white;
            padding: 20px;
        }
        .container {
            margin-top: 50px;
        }
        a {
            display: inline-block;
            margin: 10px;
            padding: 10px 20px;
            background-color: #008CBA;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        a:hover {
            background-color: #005f75;
        }
    </style>
</head>
<body>
    <header>
        <h1>Bem-vindo ao FelixBus</h1>
    </header>
    <div class="container">
        <h2>O seu serviço de transporte confiável</h2>
        <p>Escolha uma opção abaixo:</p>
        <a href="login.php">Login</a>
        <a href="register.php">Registar</a>
    </div>
</body>
</html>
