<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Os Nossos Serviços</title>
    <link rel="stylesheet" href="../../jsp/paginas/servicos.css">
</head>
<body>
    <header class="navbar">
        <h1>Serviços FelixBus</h1>
        <nav>
            <a href="index.php">Início</a>
            <a href="contactos.php">Contactos</a>
            <?php if (isset($_SESSION['id_utilizador'])): ?>
                <a href="pg_cliente.php">Área de Cliente</a>
            <?php else: ?>
                <a href="login.php">Entrar</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <h2>O Que Oferecemos aos Nossos Passageiros</h2>
        <div class="grid-servicos">
            <div class="card-servico">
                <h3>🚍 Conforto a Bordo</h3>
                <p>Lugares espaçosos com tomadas USB, ar condicionado e Wi-Fi de alta velocidade gratuito em todas as rotas.</p>
            </div>
            <div class="card-servico">
                <h3>📱 Bilhética Digital</h3>
                <p>Emissão instantânea de bilhetes digitais com QR Code e validação sem papel no momento do embarque.</p>
            </div>
            <div class="card-servico">
                <h3>💳 Carteira Integrada</h3>
                <p>Sistema de pagamentos com carteira virtual e auditoria transacional para compras rápidas e seguras.</p>
            </div>
            <div class="card-servico">
                <h3>🔔 Alertas em Tempo Real</h3>
                <p>Notificações instantâneas sobre alterações de horários, atrasos ou avisos operacionais de rota.</p>
            </div>
        </div>
    </main>
</body>
</html>
