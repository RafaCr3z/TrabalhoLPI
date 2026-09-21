<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_utilizador'];

// Obter saldo da carteira
$q_carteira = mysqli_prepare($conn, "SELECT saldo FROM carteiras WHERE id_utilizador = ?");
mysqli_stmt_bind_param($q_carteira, "i", $id_user);
mysqli_stmt_execute($q_carteira);
$res_cart = mysqli_stmt_get_result($q_carteira);
$carteira = mysqli_fetch_assoc($res_cart);
$saldo = $carteira ? $carteira['saldo'] : 0.00;

// Obter bilhetes ativos
$q_bilhetes = mysqli_prepare($conn, "
    SELECT b.codigo_bilhete, b.numero_lugar, b.preco_pago, b.estado, h.data_partida, h.hora_partida, r.origem, r.destino
    FROM bilhetes b
    JOIN horarios h ON b.id_horario = h.id_horario
    JOIN rotas r ON h.id_rota = r.id_rota
    WHERE b.id_utilizador = ?
    ORDER BY h.data_partida DESC
");
mysqli_stmt_bind_param($q_bilhetes, "i", $id_user);
mysqli_stmt_execute($q_bilhetes);
$result_bilhetes = mysqli_stmt_get_result($q_bilhetes);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Área do Cliente</title>
    <link rel="stylesheet" href="../../jsp/paginas/cliente_style.css">
</head>
<body>
    <header class="navbar">
        <h1>Área de Cliente - Olá, <?= htmlspecialchars($_SESSION['nome']) ?></h1>
        <nav>
            <a href="index.php">Início</a>
            <a href="carteira_cliente.php">A Minha Carteira (<?= number_format($saldo, 2) ?> €)</a>
            <a href="bilhetes_cliente.php">Comprar Bilhete</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <section class="painel-resumo">
            <div class="card">
                <h3>Saldo Disponível</h3>
                <p class="valor"><?= number_format($saldo, 2) ?> €</p>
                <a href="carteira_cliente.php" class="btn">Carregar Saldo</a>
            </div>
        </section>

        <section class="meus-bilhetes">
            <h3>Os Meus Bilhetes de Viagem</h3>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Rota</th>
                        <th>Data / Hora</th>
                        <th>Lugar</th>
                        <th>Preço</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = mysqli_fetch_assoc($result_bilhetes)): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($b['codigo_bilhete']) ?></code></td>
                            <td><?= htmlspecialchars($b['origem']) ?> ➔ <?= htmlspecialchars($b['destino']) ?></td>
                            <td><?= htmlspecialchars($b['data_partida']) ?> <?= htmlspecialchars($b['hora_partida']) ?></td>
                            <td><?= htmlspecialchars($b['numero_lugar']) ?></td>
                            <td><?= number_format($b['preco_pago'], 2) ?> €</td>
                            <td><span class="badge"><?= htmlspecialchars($b['estado']) ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
