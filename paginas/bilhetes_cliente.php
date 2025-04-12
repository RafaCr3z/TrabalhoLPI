<?php
    session_start();
    include '../basedados/basedados.h';

    if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
        header("Location: erro.php");
        exit();
    }

    $id_cliente = $_SESSION["id_utilizador"];
    $mensagem = "";
    $tipo_mensagem = "";

    // Obter ID da carteira FelixBus
    $sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
    $result_felixbus = mysqli_query($conn, $sql_felixbus);
    $row_felixbus = mysqli_fetch_assoc($result_felixbus);
    $id_carteira_felixbus = $row_felixbus['id'];

    // Obter saldo do cliente
    $sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
    $result_saldo = mysqli_query($conn, $sql_saldo);
    $row_saldo = mysqli_fetch_assoc($result_saldo);

    // Se o cliente não tiver carteira, criar uma
    if (!$row_saldo) {
        $sql_criar_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
        mysqli_query($conn, $sql_criar_carteira);
        $row_saldo = ['saldo' => 0.00];
    }

// Processar compra de bilhete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    $id_rota = $_POST['id_rota'];
    $data_viagem = $_POST['data_viagem'];
    $hora_viagem = $_POST['hora_viagem'];

    // Verificar se a rota existe e obter o preço
    $sql_rota = "SELECT r.preco, r.origem, r.destino
                FROM rotas r
                WHERE r.id = $id_rota";
    $result_rota = mysqli_query($conn, $sql_rota);

    if (mysqli_num_rows($result_rota) > 0) {
        $rota = mysqli_fetch_assoc($result_rota);
        $preco = $rota['preco'];
        $origem = $rota['origem'];
        $destino = $rota['destino'];

        // Verificar se o cliente tem saldo suficiente
        if ($row_saldo['saldo'] >= $preco) {
            // Iniciar transação para garantir integridade dos dados
            mysqli_begin_transaction($conn);

            try {
                // 1. Reduzir saldo do cliente
                $sql_reduzir = "UPDATE carteiras SET saldo = saldo - $preco WHERE id_cliente = $id_cliente";
                if (!mysqli_query($conn, $sql_reduzir)) {
                    throw new Exception("Erro ao atualizar saldo do cliente: " . mysqli_error($conn));
                }

                // 2. Aumentar saldo da FelixBus
                $sql_aumentar = "UPDATE carteira_felixbus SET saldo = saldo + $preco WHERE id = $id_carteira_felixbus";
                if (!mysqli_query($conn, $sql_aumentar)) {
                    throw new Exception("Erro ao atualizar saldo da FelixBus: " . mysqli_error($conn));
                }

                // 3. Registrar a transação
                $descricao = "Compra de bilhete: $origem para $destino";
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                VALUES ($id_cliente, $id_carteira_felixbus, $preco, 'compra', '$descricao')";
                if (!mysqli_query($conn, $sql_transacao)) {
                    throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
                }

                // 4. Criar o bilhete
                $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem)
                               VALUES ($id_cliente, $id_rota, '$data_viagem', '$hora_viagem')";
                if (!mysqli_query($conn, $sql_bilhete)) {
                    throw new Exception("Erro ao criar bilhete: " . mysqli_error($conn));
                }

                // Commit da transação
                mysqli_commit($conn);
                $mensagem = "Bilhete comprado com sucesso!";
                $tipo_mensagem = "success";

                // Atualizar saldo após a compra
                $result_saldo = mysqli_query($conn, $sql_saldo);
                $row_saldo = mysqli_fetch_assoc($result_saldo);

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $mensagem = $e->getMessage();
                $tipo_mensagem = "error";
            }
        } else {
            $mensagem = "Saldo insuficiente para comprar este bilhete.";
            $tipo_mensagem = "error";
        }
    } else {
        $mensagem = "Rota não encontrada.";
        $tipo_mensagem = "error";
    }
}

// Buscar rotas disponíveis
$sql_rotas = "SELECT r.id, r.origem, r.destino, r.preco, h.horario_partida
             FROM rotas r
             JOIN horarios h ON r.id = h.id_rota
             WHERE r.disponivel = 1
             ORDER BY r.origem, r.destino, h.horario_partida";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar bilhetes do cliente
$sql_bilhetes = "SELECT b.id, r.origem, r.destino, b.data_viagem, b.hora_viagem, r.preco, b.data_compra
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                WHERE b.id_cliente = $id_cliente
                ORDER BY b.data_viagem DESC, b.hora_viagem ASC";
$result_bilhetes = mysqli_query($conn, $sql_bilhetes);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bilhetes_cliente.css">
    <title>FelixBus - Meus Bilhetes</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="perfil_cliente.php">Perfil</a></div>
            <div class="link"> <a href="pg_cliente.php">Página Inicial</a></div>
            <div class="link"> <a href="carteira_cliente.php">Carteira</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
        </div>
    </nav>

    <section>
        <h1>Meus Bilhetes</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem == 'success' ? 'success' : 'danger'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="saldo-info">
            <p>Saldo disponível: <strong>€<?php echo number_format($row_saldo['saldo'], 2, ',', '.'); ?></strong></p>
        </div>

        <div class="container">
            <div class="comprar-bilhete">
                <h2>Comprar Bilhete</h2>
                <form method="post" action="bilhetes_cliente.php">
                    <div class="form-group">
                        <label for="id_rota">Rota:</label>
                        <select id="id_rota" name="id_rota" required>
                            <option value="">Selecione uma rota</option>
                            <?php while ($rota = mysqli_fetch_assoc($result_rotas)): ?>
                                <option value="<?php echo $rota['id']; ?>">
                                    <?php echo htmlspecialchars($rota['origem'] . ' → ' . $rota['destino'] . ' (' . $rota['horario_partida'] . ') - €' . number_format($rota['preco'], 2, ',', '.')); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="data_viagem">Data da Viagem:</label>
                        <input type="date" id="data_viagem" name="data_viagem" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="hora_viagem">Hora da Viagem:</label>
                        <input type="time" id="hora_viagem" name="hora_viagem" required>
                    </div>

                    <button type="submit" name="comprar">Comprar Bilhete</button>
                </form>
            </div>

            <div class="meus-bilhetes">
                <h2>Meus Bilhetes</h2>
                <?php if (mysqli_num_rows($result_bilhetes) > 0): ?>
                    <table class="bilhetes-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Origem</th>
                                <th>Destino</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Preço</th>
                                <th>Data da Compra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                <tr>
                                    <td><?php echo substr($bilhete['id'], 0, 8); ?>...</td>
                                    <td><?php echo htmlspecialchars($bilhete['origem']); ?></td>
                                    <td><?php echo htmlspecialchars($bilhete['destino']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                    <td><?php echo $bilhete['hora_viagem']; ?></td>
                                    <td>€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($bilhete['data_compra'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-bilhetes">Você ainda não possui bilhetes.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
