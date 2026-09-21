<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_utilizador'];
$msg = "";

// Processar carregamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['carregar'])) {
    $valor = floatval($_POST['valor'] ?? 0);
    if ($valor > 0) {
        mysqli_begin_transaction($conn);
        try {
            // Obter ID da carteira
            $q = mysqli_prepare($conn, "SELECT id_carteira, saldo FROM carteiras WHERE id_utilizador = ? FOR UPDATE");
            mysqli_stmt_bind_param($q, "i", $id_user);
            mysqli_stmt_execute($q);
            $cart = mysqli_fetch_assoc(mysqli_stmt_get_result($q));

            if ($cart) {
                $id_carteira = $cart['id_carteira'];
                $novo_saldo = $cart['saldo'] + $valor;

                // Atualizar saldo
                $u = mysqli_prepare($conn, "UPDATE carteiras SET saldo = ? WHERE id_carteira = ?");
                mysqli_stmt_bind_param($u, "di", $novo_saldo, $id_carteira);
                mysqli_stmt_execute($u);

                // Inserir transação de auditoria
                $t = mysqli_prepare($conn, "INSERT INTO transacoes (id_carteira, tipo, valor, descricao) VALUES (?, 'CARREGAMENTO', ?, 'Carregamento de Saldo via Multibanco/MBWay')");
                mysqli_stmt_bind_param($t, "ids", $id_carteira, $valor);
                mysqli_stmt_execute($t);

                mysqli_commit($conn);
                $msg = "Carregamento de " . number_format($valor, 2) . " € efetuado com sucesso!";
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $msg = "Erro ao processar transação.";
        }
    }
}

// Obter dados da carteira
$q_carteira = mysqli_prepare($conn, "SELECT id_carteira, saldo FROM carteiras WHERE id_utilizador = ?");
mysqli_stmt_bind_param($q_carteira, "i", $id_user);
mysqli_stmt_execute($q_carteira);
$carteira = mysqli_fetch_assoc(mysqli_stmt_get_result($q_carteira));
$id_carteira = $carteira['id_carteira'] ?? 0;
$saldo = $carteira['saldo'] ?? 0.00;

// Obter histórico de transações
$q_trans = mysqli_prepare($conn, "SELECT * FROM transacoes WHERE id_carteira = ? ORDER BY data_transacao DESC");
mysqli_stmt_bind_param($q_trans, "i", $id_carteira);
mysqli_stmt_execute($q_trans);
$result_trans = mysqli_stmt_get_result($q_trans);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Carteira Virtual</title>
    <link rel="stylesheet" href="../../jsp/paginas/carteira_cliente.css">
</head>
<body>
    <header class="navbar">
        <h1>A Minha Carteira Virtual</h1>
        <nav>
            <a href="pg_cliente.php">Área de Cliente</a>
            <a href="bilhetes_cliente.php">Comprar Bilhete</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <?php if (!empty($msg)): ?>
            <div class="alert"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="card-saldo">
            <h2>Saldo Atual</h2>
            <p class="montante"><?= number_format($saldo, 2) ?> €</p>
            
            <form action="carteira_cliente.php" method="POST" class="form-carregar">
                <label>Carregar Valor (€):</label>
                <input type="number" name="valor" min="5" max="500" step="0.50" value="10.00" required>
                <button type="submit" name="carregar" class="btn-primary">Confirmar Carregamento</button>
            </form>
        </div>

        <section class="historico-auditoria">
            <h3>Extrato de Transações e Movimentos</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($t = mysqli_fetch_assoc($result_trans)): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['data_transacao']) ?></td>
                            <td><span class="tag-<?= strtolower($t['tipo']) ?>"><?= htmlspecialchars($t['tipo']) ?></span></td>
                            <td><?= htmlspecialchars($t['descricao']) ?></td>
                            <td><b><?= number_format($t['valor'], 2) ?> €</b></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
