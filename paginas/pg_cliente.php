<?php
session_start();
include '../basedados/basedados.h';
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale = 1.0">
    <link rel="stylesheet" href="cliente_style.css">
    <title>FelixBus - Área do Cliente</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="perfil_cliente.php">Perfil</a></div>
            <div class="link"> <a href="carteira_cliente.php">Carteira</a></div>
            <div class="link"> <a href="bilhetes_cliente.php">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-cliente">Área de Cliente</div>
        </div>
    </nav>
    <section>
        <h1>Bem-vindo à Área do Cliente</h1>

        <div class="welcome-container">
            <p>Olá, <?php echo isset($_SESSION["nome"]) ? $_SESSION["nome"] : "Cliente"; ?>! Bem-vindo à sua área pessoal no FelixBus.</p>
            <p>Aqui você pode gerenciar sua carteira, comprar bilhetes e visualizar suas informações pessoais.</p>
        </div>

        <div class="options-container">
            <div class="option-card">
                <h3>Meu Perfil</h3>
                <p>Visualize e edite suas informações pessoais.</p>
                <a href="perfil_cliente.php">Acessar Perfil</a>
            </div>

            <div class="option-card">
                <h3>Minha Carteira</h3>
                <p>Gerencie seu saldo e visualize o histórico de transações.</p>
                <a href="carteira_cliente.php">Acessar Carteira</a>
            </div>

            <div class="option-card">
                <h3>Meus Bilhetes</h3>
                <p>Compre novos bilhetes e visualize os bilhetes adquiridos.</p>
                <a href="bilhetes_cliente.php">Acessar Bilhetes</a>
            </div>
        </div>
    </section>
</body>
</html>