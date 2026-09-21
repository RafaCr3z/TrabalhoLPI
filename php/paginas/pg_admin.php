<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador']) || $_SESSION['id_perfil'] != 4) {
    header("Location: login.php");
    exit;
}

// KPI Statistics
$tot_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM utilizadores"))['total'];
$tot_rotas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM rotas"))['total'];
$tot_bilhetes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bilhetes"))['total'];
$tot_receita = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(preco_pago), 0) as total FROM bilhetes WHERE estado != 'CANCELADO'"))['total'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Painel de Administração</title>
    <link rel="stylesheet" href="../../jsp/paginas/pg_admin.css">
</head>
<body>
    <header class="navbar">
        <h1>Painel de Administração - FelixBus</h1>
        <nav>
            <a href="pg_admin.php">Dashboard</a>
            <a href="gerir_utilizadores.php">Gerir Utilizadores</a>
            <a href="gerir_rotas.php">Gerir Rotas e Horários</a>
            <a href="gerir_alertas.php">Emitir Alertas</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <h2>Indicadores Globais de Operação</h2>
        <div class="grid-kpis">
            <div class="card-kpi">
                <h3>Utilizadores Registados</h3>
                <p class="kpi-num"><?= $tot_users ?></p>
            </div>
            <div class="card-kpi">
                <h3>Rotas Ativas</h3>
                <p class="kpi-num"><?= $tot_rotas ?></p>
            </div>
            <div class="card-kpi">
                <h3>Bilhetes Emitidos</h3>
                <p class="kpi-num"><?= $tot_bilhetes ?></p>
            </div>
            <div class="card-kpi">
                <h3>Receita Global</h3>
                <p class="kpi-num"><?= number_format($tot_receita, 2) ?> €</p>
            </div>
        </div>

        <section class="menu-rapido">
            <h3>Operações de Gestão</h3>
            <div class="botoes-gestao">
                <a href="gerir_utilizadores.php" class="btn-acao">👥 Administrar Contas e Perfis</a>
                <a href="gerir_rotas.php" class="btn-acao">🗺️ Criar e Atualizar Rotas</a>
                <a href="gerir_alertas.php" class="btn-acao">📢 Publicar Avisos Operacionais</a>
            </div>
        </section>
    </main>
</body>
</html>
