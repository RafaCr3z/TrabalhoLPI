<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

// Obter rotas disponíveis para pesquisa
$query_rotas = "SELECT * FROM rotas ORDER BY origem ASC";
$result_rotas = mysqli_query($conn, $query_rotas);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FelixBus - Viagens Rodoviárias</title>
    <link rel="stylesheet" href="../../jsp/paginas/estilo.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>🚌 FelixBus</h1>
            <nav>
                <a href="index.php">Início</a>
                <a href="servicos.php">Serviços</a>
                <a href="contactos.php">Contactos</a>
                <?php if (isset($_SESSION['id_utilizador'])): ?>
                    <?php if ($_SESSION['id_perfil'] == 4): ?>
                        <a href="pg_admin.php">Painel Admin</a>
                    <?php else: ?>
                        <a href="pg_cliente.php">Área de Cliente</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-logout">Sair</a>
                <?php else: ?>
                    <a href="login.php">Entrar</a>
                    <a href="registar.php">Registar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <h2>Reserve a sua próxima viagem com a FelixBus</h2>
            <p>Conforto, pontualidade e os melhores preços em transporte rodoviário.</p>
        </section>

        <section class="pesquisa-viagens">
            <h3>Pesquisar Viagens Disponíveis</h3>
            <form action="index.php" method="GET" class="form-pesquisa">
                <div class="campo">
                    <label for="origem">Origem:</label>
                    <select name="origem" id="origem">
                        <option value="">Todas as origens</option>
                        <?php while ($r = mysqli_fetch_assoc($result_rotas)): ?>
                            <option value="<?= htmlspecialchars($r['origem']) ?>"><?= htmlspecialchars($r['origem']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="data">Data:</label>
                    <input type="date" name="data" id="data" value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" class="btn-primary">Pesquisar</button>
            </form>
        </section>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> FelixBus — Projeto LPI (João Resina & Rafael Cruz - IPCB)</p>
    </footer>
</body>
</html>
