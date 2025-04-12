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

// Processar operação na carteira
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['operacao_carteira'])) {
    $id_cliente = $_POST['id_cliente'];
    $valor = $_POST['valor'];
    $operacao = $_POST['operacao'];

    if ($valor <= 0) {
        $mensagem = "Valor inválido. Por favor, insira um valor maior que zero.";
        $tipo_mensagem = "danger";
    } else {
        // Verificar se o cliente existe e é realmente um cliente
        $sql_check_cliente = "SELECT u.id, u.nome, u.tipo_perfil FROM utilizadores u WHERE u.id = $id_cliente AND u.tipo_perfil = 3";
        $result_check_cliente = mysqli_query($conn, $sql_check_cliente);

        if (mysqli_num_rows($result_check_cliente) == 0) {
            $mensagem = "Cliente não encontrado ou ID inválido.";
            $tipo_mensagem = "danger";
        } else {
            $cliente = mysqli_fetch_assoc($result_check_cliente);

            // Verificar se o cliente tem carteira
            $sql_check_carteira = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
            $result_check_carteira = mysqli_query($conn, $sql_check_carteira);

            if (mysqli_num_rows($result_check_carteira) == 0) {
                // Criar carteira para o cliente se não existir
                $sql_criar_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
                mysqli_query($conn, $sql_criar_carteira);
                $saldo_atual = 0.00;
            } else {
                $row_carteira = mysqli_fetch_assoc($result_check_carteira);
                $saldo_atual = $row_carteira['saldo'];
            }

            // Iniciar transação para garantir integridade dos dados
            mysqli_begin_transaction($conn);

            try {
                if ($operacao == "adicionar") {
                    $sql_atualiza = "UPDATE carteiras SET saldo = saldo + $valor WHERE id_cliente = $id_cliente";
                    $tipo_transacao = "deposito";
                    $descricao = "Depósito de €$valor na carteira do cliente {$cliente['nome']} (ID: $id_cliente) por {$_SESSION['nome']}";
                } else if ($operacao == "retirar") {
                    if ($saldo_atual < $valor) {
                        throw new Exception("Saldo insuficiente para realizar esta operação.");
                    }
                    $sql_atualiza = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
                    $tipo_transacao = "retirada";
                    $descricao = "Retirada de €$valor da carteira do cliente {$cliente['nome']} (ID: $id_cliente) por {$_SESSION['nome']}";
                }

                if (!mysqli_query($conn, $sql_atualiza)) {
                    throw new Exception("Erro ao atualizar saldo: " . mysqli_error($conn));
                }

                // Registrar a transação
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                  VALUES ($id_cliente, $id_carteira_felixbus, $valor, '$tipo_transacao', '$descricao')";
                if (!mysqli_query($conn, $sql_transacao)) {
                    throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
                }

                // Commit da transação
                mysqli_commit($conn);

                $mensagem = "Operação realizada com sucesso! " . ucfirst($tipo_transacao) . " de €$valor " .
                           ($operacao == "adicionar" ? "adicionado à" : "retirado da") .
                           " carteira do cliente {$cliente['nome']}.";
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

// Buscar clientes para o dropdown
$sql_clientes = "SELECT u.id, u.nome, u.email, c.saldo
                FROM utilizadores u
                LEFT JOIN carteiras c ON u.id = c.id_cliente
                WHERE u.tipo_perfil = 3
                ORDER BY u.nome";
$result_clientes = mysqli_query($conn, $sql_clientes);

// Buscar histórico de transações
$sql_transacoes = "SELECT t.*, u.nome as nome_cliente, u.email as email_cliente
                  FROM transacoes t
                  JOIN utilizadores u ON t.id_cliente = u.id
                  WHERE t.tipo IN ('deposito', 'retirada')
                  ORDER BY t.data_transacao DESC
                  LIMIT 50";
$result_transacoes = mysqli_query($conn, $sql_transacoes);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_carteiras.css">
    <title>FelixBus - Gestão de Carteiras</title>
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
            <div class="link"> <a href="gerir_bilhetes_func.php">Gestão de Bilhetes</a></div>
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
        <h1>Gestão de Carteiras de Clientes</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-container">
                <h2>Operações na Carteira</h2>
                <form method="post" action="gerir_carteiras.php">
                    <div class="form-group">
                        <label for="id_cliente">Cliente:</label>
                        <select id="id_cliente" name="id_cliente" required>
                            <option value="">Selecione um cliente</option>
                            <?php while ($cliente = mysqli_fetch_assoc($result_clientes)): ?>
                                <option value="<?php echo $cliente['id']; ?>">
                                    <?php echo htmlspecialchars($cliente['nome']) . ' (' . htmlspecialchars($cliente['email']) . ') - Saldo: €' . number_format($cliente['saldo'] ?? 0, 2, ',', '.'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="valor">Valor (€):</label>
                        <input type="number" id="valor" name="valor" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="operacao">Operação:</label>
                        <select id="operacao" name="operacao" required>
                            <option value="adicionar">Adicionar Saldo</option>
                            <option value="retirar">Retirar Saldo</option>
                        </select>
                    </div>

                    <button type="submit" name="operacao_carteira">Confirmar Operação</button>
                </form>
            </div>

            <div class="historico-container">
                <h2>Histórico de Operações</h2>
                <?php if (mysqli_num_rows($result_transacoes) > 0): ?>
                    <div class="table-responsive">
                        <table class="historico-table">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th>Valor</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transacao = mysqli_fetch_assoc($result_transacoes)): ?>
                                    <?php
                                    $classe_valor = '';
                                    if ($transacao['tipo'] == 'deposito') {
                                        $classe_valor = 'deposito';
                                        $valor_formatado = '+€' . number_format($transacao['valor'], 2, ',', '.');
                                        $operacao = 'Depósito';
                                    } else {
                                        $classe_valor = 'retirada';
                                        $valor_formatado = '-€' . number_format($transacao['valor'], 2, ',', '.');
                                        $operacao = 'Retirada';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($transacao['data_transacao'])); ?></td>
                                        <td><?php echo htmlspecialchars($transacao['nome_cliente']); ?></td>
                                        <td><?php echo $operacao; ?></td>
                                        <td class="<?php echo $classe_valor; ?>"><?php echo $valor_formatado; ?></td>
                                        <td><?php echo htmlspecialchars($transacao['descricao']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="no-data">Nenhuma operação encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
