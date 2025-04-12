<?php
session_start();
include '../basedados/basedados.h';
include '../includes/autenticacao.php';

// Verificar se o usuário é funcionário ou administrador
verificarAcesso([1, 2]);

// Obter ID da carteira FelixBus
$sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
$result_felixbus = mysqli_query($conn, $sql_felixbus);
$row_felixbus = mysqli_fetch_assoc($result_felixbus);
$id_carteira_felixbus = $row_felixbus['id'];

// Variáveis para mensagens de alerta
$mensagem = '';
$tipo_mensagem = '';

// Processar compra de bilhete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    $id_cliente = $_POST['id_cliente'];
    $rota_horario = explode('|', $_POST['rota_horario']);
    $id_rota = $rota_horario[0];
    $hora_viagem = $rota_horario[1];
    $data_viagem = $_POST['data_viagem'];

    // Verificar se o cliente existe e é realmente um cliente
    $sql_check_cliente = "SELECT u.id, u.nome, u.tipo_perfil FROM utilizadores u WHERE u.id = $id_cliente AND u.tipo_perfil = 3";
    $result_check_cliente = mysqli_query($conn, $sql_check_cliente);

    if (mysqli_num_rows($result_check_cliente) == 0) {
        $mensagem = "Cliente não encontrado ou ID inválido.";
        $tipo_mensagem = "danger";
    } else {
        $cliente = mysqli_fetch_assoc($result_check_cliente);

        // Verificar se a rota existe e obter o preço
        $sql_rota = "SELECT r.preco, r.origem, r.destino
                    FROM rotas r
                    WHERE r.id = $id_rota";
        $result_rota = mysqli_query($conn, $sql_rota);

        if (mysqli_num_rows($result_rota) == 0) {
            $mensagem = "Rota não encontrada.";
            $tipo_mensagem = "danger";
        } else {
            $rota = mysqli_fetch_assoc($result_rota);
            $preco = $rota['preco'];
            $origem = $rota['origem'];
            $destino = $rota['destino'];

            // Verificar se o cliente tem carteira e saldo suficiente
            $sql_carteira = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
            $result_carteira = mysqli_query($conn, $sql_carteira);

            if (mysqli_num_rows($result_carteira) == 0) {
                // Criar carteira para o cliente se não existir
                $sql_criar_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
                mysqli_query($conn, $sql_criar_carteira);
                $saldo = 0.00;
            } else {
                $row_carteira = mysqli_fetch_assoc($result_carteira);
                $saldo = $row_carteira['saldo'];
            }

            if ($saldo < $preco) {
                $mensagem = "Saldo insuficiente. O cliente precisa de €" . number_format($preco, 2, ',', '.') .
                           " para comprar este bilhete, mas tem apenas €" . number_format($saldo, 2, ',', '.') . ".";
                $tipo_mensagem = "danger";
            } else {
                // Iniciar transação para garantir integridade dos dados
                mysqli_begin_transaction($conn);

                try {
                    // 1. Atualizar saldo do cliente
                    $sql_update_cliente = "UPDATE carteiras SET saldo = saldo - $preco WHERE id_cliente = $id_cliente";
                    if (!mysqli_query($conn, $sql_update_cliente)) {
                        throw new Exception("Erro ao atualizar saldo do cliente: " . mysqli_error($conn));
                    }

                    // 2. Atualizar saldo da FelixBus
                    $sql_update_felixbus = "UPDATE carteira_felixbus SET saldo = saldo + $preco WHERE id = $id_carteira_felixbus";
                    if (!mysqli_query($conn, $sql_update_felixbus)) {
                        throw new Exception("Erro ao atualizar saldo da FelixBus: " . mysqli_error($conn));
                    }

                    // 3. Registrar a transação
                    $descricao = "Compra de bilhete: $origem para $destino (Cliente: {$cliente['nome']})";
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

                    $mensagem = "Bilhete comprado com sucesso para o cliente {$cliente['nome']}! Origem: $origem, Destino: $destino, Data: " .
                               date('d/m/Y', strtotime($data_viagem)) . ", Hora: $hora_viagem, Preço: €" . number_format($preco, 2, ',', '.');
                    $tipo_mensagem = "success";

                } catch (Exception $e) {
                    // Rollback em caso de erro
                    mysqli_rollback($conn);
                    $mensagem = $e->getMessage();
                    $tipo_mensagem = "danger";
                }
            }
        }
    }
}

// Buscar clientes para o dropdown
$sql_clientes = "SELECT u.id, u.nome, u.email, c.saldo
                FROM utilizadores u
                LEFT JOIN carteiras c ON u.id = c.id_cliente
                WHERE u.tipo_perfil = 3
                ORDER BY u.nome";
$result_clientes = mysqli_query($conn, $sql_clientes);

// Buscar rotas disponíveis
$sql_rotas = "SELECT r.id as id_rota, r.origem, r.destino, r.preco, h.id as id_horario, h.horario_partida
             FROM rotas r
             JOIN horarios h ON r.id = h.id_rota
             WHERE r.disponivel = 1
             ORDER BY r.origem, r.destino, h.horario_partida";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar bilhetes recentes
$sql_bilhetes = "SELECT b.id, b.data_compra, b.data_viagem, b.hora_viagem,
                       r.origem, r.destino, r.preco,
                       u.nome as nome_cliente, u.email as email_cliente
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                JOIN utilizadores u ON b.id_cliente = u.id
                ORDER BY b.data_compra DESC
                LIMIT 50";
$result_bilhetes = mysqli_query($conn, $sql_bilhetes);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_bilhetes_func.css">
    <title>FelixBus - Gestão de Bilhetes</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <?php if ($_SESSION["id_nivel"] == 1): ?>
                <div class="link"> <a href="pg_admin.php">Página Inicial</a></div>
            <?php else: ?>
                <div class="link"> <a href="pg_funcionario.php">Página Inicial</a></div>
            <?php endif; ?>
            <div class="link"> <a href="gerir_carteiras.php">Gestão de Carteiras</a></div>
            <?php if ($_SESSION["id_nivel"] == 1): ?>
                <div class="link"> <a href="perfil_admin.php">Meu Perfil</a></div>
            <?php else: ?>
                <div class="link"> <a href="perfil_funcionario.php">Meu Perfil</a></div>
            <?php endif; ?>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área de <?php echo $_SESSION["id_nivel"] == 1 ? 'Admin' : 'Funcionário'; ?></div>
        </div>
    </nav>

    <section>
        <h1>Gestão de Bilhetes</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-container">
                <h2>Comprar Bilhete para Cliente</h2>
                <form method="post" action="gerir_bilhetes_func.php">
                    <div class="form-group">
                        <label for="id_cliente">Cliente:</label>
                        <select id="id_cliente" name="id_cliente" required>
                            <option value="">Selecione um cliente</option>
                            <?php
                            // Reset the pointer to the beginning
                            mysqli_data_seek($result_clientes, 0);
                            while ($cliente = mysqli_fetch_assoc($result_clientes)):
                            ?>
                                <option value="<?php echo $cliente['id']; ?>">
                                    <?php echo htmlspecialchars($cliente['nome']) . ' (' . htmlspecialchars($cliente['email']) . ') - Saldo: €' . number_format($cliente['saldo'] ?? 0, 2, ',', '.'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rota_horario">Rota e Horário:</label>
                        <select id="rota_horario" name="rota_horario" required>
                            <option value="">Selecione uma rota</option>
                            <?php while ($rota = mysqli_fetch_assoc($result_rotas)): ?>
                                <option value="<?php echo $rota['id_rota'] . '|' . $rota['horario_partida']; ?>">
                                    <?php echo htmlspecialchars($rota['origem'] . ' → ' . $rota['destino'] . ' (' . $rota['horario_partida'] . ') - €' . number_format($rota['preco'], 2, ',', '.')); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="data_viagem">Data da Viagem:</label>
                        <input type="date" id="data_viagem" name="data_viagem" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <button type="submit" name="comprar">Comprar Bilhete</button>
                </form>
            </div>

            <div class="bilhetes-container">
                <h2>Bilhetes Recentes</h2>
                <?php if (mysqli_num_rows($result_bilhetes) > 0): ?>
                    <div class="table-responsive">
                        <table class="bilhetes-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Rota</th>
                                    <th>Data Viagem</th>
                                    <th>Hora</th>
                                    <th>Preço</th>
                                    <th>Data Compra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                    <tr>
                                        <td><?php echo substr($bilhete['id'], 0, 8); ?>...</td>
                                        <td><?php echo htmlspecialchars($bilhete['nome_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['origem'] . ' → ' . $bilhete['destino']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                        <td><?php echo $bilhete['hora_viagem']; ?></td>
                                        <td>€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($bilhete['data_compra'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-data">Nenhum bilhete encontrado.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
