<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_utilizador'];
$msg = "";
$erro = "";

// Obter saldo da carteira
$q_cart = mysqli_prepare($conn, "SELECT id_carteira, saldo FROM carteiras WHERE id_utilizador = ?");
mysqli_stmt_bind_param($q_cart, "i", $id_user);
mysqli_stmt_execute($q_cart);
$carteira = mysqli_fetch_assoc(mysqli_stmt_get_result($q_cart));
$id_carteira = $carteira['id_carteira'] ?? 0;
$saldo = $carteira['saldo'] ?? 0.00;

// Processar compra de bilhete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comprar_bilhete'])) {
    $id_horario = intval($_POST['id_horario'] ?? 0);
    $numero_lugar = intval($_POST['numero_lugar'] ?? 0);

    if ($id_horario > 0 && $numero_lugar > 0) {
        // Obter detalhes do horário e rota
        $q_h = mysqli_prepare($conn, "
            SELECT h.lugares_disponiveis, r.preco_base, r.origem, r.destino
            FROM horarios h
            JOIN rotas r ON h.id_rota = r.id_rota
            WHERE h.id_horario = ? AND h.estado_viagem = 'AGENDADA'
        ");
        mysqli_stmt_bind_param($q_h, "i", $id_horario);
        mysqli_stmt_execute($q_h);
        $horario = mysqli_fetch_assoc(mysqli_stmt_get_result($q_h));

        if ($horario) {
            $preco = $horario['preco_base'];

            if ($saldo >= $preco && $horario['lugares_disponiveis'] > 0) {
                mysqli_begin_transaction($conn);
                try {
                    // 1. Debitar saldo
                    $novo_saldo = $saldo - $preco;
                    $u_cart = mysqli_prepare($conn, "UPDATE carteiras SET saldo = ? WHERE id_carteira = ?");
                    mysqli_stmt_bind_param($u_cart, "di", $novo_saldo, $id_carteira);
                    mysqli_stmt_execute($u_cart);

                    // 2. Registar transação
                    $t = mysqli_prepare($conn, "INSERT INTO transacoes (id_carteira, tipo, valor, descricao) VALUES (?, 'COMPRA', ?, ?)");
                    $desc = "Compra de Bilhete: " . $horario['origem'] . " -> " . $horario['destino'];
                    mysqli_stmt_bind_param($t, "ids", $id_carteira, $preco, $desc);
                    mysqli_stmt_execute($t);

                    // 3. Emitir bilhete com código único
                    $codigo_bilhete = "TKT-" . strtoupper(bin2hex(random_bytes(4)));
                    $ins_b = mysqli_prepare($conn, "INSERT INTO bilhetes (codigo_bilhete, id_utilizador, id_horario, numero_lugar, preco_pago, estado) VALUES (?, ?, ?, ?, ?, 'EMITIDO')");
                    mysqli_stmt_bind_param($ins_b, "siiid", $codigo_bilhete, $id_user, $id_horario, $numero_lugar, $preco);
                    mysqli_stmt_execute($ins_b);

                    // 4. Decrementar lugares disponíveis
                    $u_h = mysqli_prepare($conn, "UPDATE horarios SET lugares_disponiveis = lugares_disponiveis - 1 WHERE id_horario = ?");
                    mysqli_stmt_bind_param($u_h, "i", $id_horario);
                    mysqli_stmt_execute($u_h);

                    mysqli_commit($conn);
                    $msg = "Bilhete emitido com sucesso! Código: " . $codigo_bilhete;
                    $saldo = $novo_saldo;
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $erro = "Falha ao processar a compra do bilhete.";
                }
            } else {
                $erro = "Saldo insuficiente na carteira (" . number_format($saldo, 2) . " €) para pagar " . number_format($preco, 2) . " €.";
            }
        } else {
            $erro = "Horário indisponível para reserva.";
        }
    }
}

// Obter viagens disponíveis
$viagens = mysqli_query($conn, "
    SELECT h.id_horario, h.data_partida, h.hora_partida, h.lugares_disponiveis, r.origem, r.destino, r.preco_base, a.matricula
    FROM horarios h
    JOIN rotas r ON h.id_rota = r.id_rota
    JOIN autocarros a ON h.id_autocarro = a.id_autocarro
    WHERE h.estado_viagem = 'AGENDADA' AND h.lugares_disponiveis > 0
    ORDER BY h.data_partida ASC, h.hora_partida ASC
");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Compra de Bilhetes</title>
    <link rel="stylesheet" href="../../jsp/paginas/bilhetes_cliente.css">
</head>
<body>
    <header class="navbar">
        <h1>Reserva de Bilhetes</h1>
        <nav>
            <a href="pg_cliente.php">Área de Cliente</a>
            <a href="carteira_cliente.php">Carteira (<?= number_format($saldo, 2) ?> €)</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <?php if (!empty($msg)): ?>
            <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <h2>Viagens e Horários Disponíveis</h2>
        <div class="grid-viagens">
            <?php while ($v = mysqli_fetch_assoc($viagens)): ?>
                <div class="card-viagem">
                    <h3><?= htmlspecialchars($v['origem']) ?> ➔ <?= htmlspecialchars($v['destino']) ?></h3>
                    <p><b>Data:</b> <?= htmlspecialchars($v['data_partida']) ?> às <?= htmlspecialchars($v['hora_partida']) ?></p>
                    <p><b>Autocarro:</b> <?= htmlspecialchars($v['matricula']) ?></p>
                    <p><b>Lugares Livres:</b> <?= $v['lugares_disponiveis'] ?></p>
                    <p class="preco"><?= number_format($v['preco_base'], 2) ?> €</p>

                    <form action="bilhetes_cliente.php" method="POST">
                        <input type="hidden" name="id_horario" value="<?= $v['id_horario'] ?>">
                        <label>Escolher Lugar (1 a 50):</label>
                        <input type="number" name="numero_lugar" min="1" max="50" value="<?= rand(1, 50) ?>" required>
                        <button type="submit" name="comprar_bilhete" class="btn-primary">Comprar com Saldo</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    </main>
</body>
</html>
