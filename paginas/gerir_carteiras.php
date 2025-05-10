<?php
session_start();
include '../basedados/basedados.h';

// Verificar se o utilizador é funcionário ou administrador
if (!isset($_SESSION["id_nivel"]) || ($_SESSION["id_nivel"] != 1 && $_SESSION["id_nivel"] != 2)) {
    header("Location: erro.php");
    exit();
}

// Obter ID da carteira FelixBus
$sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
$result_felixbus = mysqli_query($conn, $sql_felixbus);
$id_carteira_felixbus = mysqli_fetch_assoc($result_felixbus)['id'];

// Inicializar variáveis para mensagens
$mensagem = '';
$tipo_mensagem = '';

// Verificar se há mensagens da sessão
if (!empty($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];
    $_SESSION['mensagem'] = '';
    $_SESSION['tipo_mensagem'] = '';
}

// Processar operação na carteira
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['operacao_carteira'])) {
    if (isset($_SESSION['token']) && isset($_POST['token']) && $_SESSION['token'] === $_POST['token']) {
        // Validação e sanitização de dados
        $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
        $operacao = filter_input(INPUT_POST, 'operacao', FILTER_SANITIZE_STRING);

        if (!$id_cliente || !$valor || $valor <= 0) {
            $_SESSION['mensagem'] = "Valor inválido. Por favor, insira um valor maior que zero.";
            $_SESSION['tipo_mensagem'] = "danger";
        } else {
            // Verificar se o cliente existe usando prepared statement
            $stmt_check_cliente = mysqli_prepare($conn, "SELECT u.id, u.nome, u.tipo_perfil FROM utilizadores u WHERE u.id = ? AND u.tipo_perfil = 3");
            mysqli_stmt_bind_param($stmt_check_cliente, "i", $id_cliente);
            mysqli_stmt_execute($stmt_check_cliente);
            $result_check_cliente = mysqli_stmt_get_result($stmt_check_cliente);

            if (mysqli_num_rows($result_check_cliente) > 0) {
                $cliente = mysqli_fetch_assoc($result_check_cliente);
                
                // Verificar carteira usando prepared statement
                $stmt_check_carteira = mysqli_prepare($conn, "SELECT saldo FROM carteiras WHERE id_cliente = ?");
                mysqli_stmt_bind_param($stmt_check_carteira, "i", $id_cliente);
                mysqli_stmt_execute($stmt_check_carteira);
                $result_check_carteira = mysqli_stmt_get_result($stmt_check_carteira);
                
                if (mysqli_num_rows($result_check_carteira) == 0) {
                    $stmt_insert_carteira = mysqli_prepare($conn, "INSERT INTO carteiras (id_cliente, saldo) VALUES (?, 0.00)");
                    mysqli_stmt_bind_param($stmt_insert_carteira, "i", $id_cliente);
                    mysqli_stmt_execute($stmt_insert_carteira);
                    $saldo_atual = 0.00;
                } else {
                    $saldo_atual = mysqli_fetch_assoc($result_check_carteira)['saldo'];
                }

                mysqli_begin_transaction($conn);

                try {
                    if ($operacao == "depositar") {
                        $stmt_atualiza = mysqli_prepare($conn, "UPDATE carteiras SET saldo = saldo + ? WHERE id_cliente = ?");
                        mysqli_stmt_bind_param($stmt_atualiza, "di", $valor, $id_cliente);
                        $tipo_transacao = "depósito";
                        $descricao = "Depósito de €" . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . " na carteira do cliente " . htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') . " (ID: $id_cliente) por " . htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8');
                    } else if ($operacao == "levantar") {
                        if ($saldo_atual < $valor) {
                            throw new Exception("Saldo insuficiente para realizar esta operação.");
                        }
                        $stmt_atualiza = mysqli_prepare($conn, "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?");
                        mysqli_stmt_bind_param($stmt_atualiza, "di", $valor, $id_cliente);
                        $tipo_transacao = "levantamento";
                        $descricao = "Levantamento de €" . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . " da carteira do cliente " . htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') . " (ID: $id_cliente) por " . htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8');
                    }

                    if (!mysqli_stmt_execute($stmt_atualiza)) {
                        throw new Exception("Erro ao atualizar saldo: " . mysqli_error($conn));
                    }

                    // Registar a transação usando prepared statement
                    $stmt_transacao = mysqli_prepare($conn, "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_transacao, "iidss", $id_cliente, $id_carteira_felixbus, $valor, $tipo_transacao, $descricao);
                    
                    if (!mysqli_stmt_execute($stmt_transacao)) {
                        throw new Exception("Erro ao registar transação: " . mysqli_error($conn));
                    }

                    mysqli_commit($conn);
                    $_SESSION['mensagem'] = "Operação realizada com sucesso!";
                    $_SESSION['tipo_mensagem'] = "success";

                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $_SESSION['mensagem'] = $e->getMessage();
                    $_SESSION['tipo_mensagem'] = "danger";
                }
            } else {
                $_SESSION['mensagem'] = "Cliente não encontrado ou ID inválido.";
                $_SESSION['tipo_mensagem'] = "danger";
            }
        }
    }

    $_SESSION['token'] = uniqid(mt_rand(), true);
    header("Location: gerir_carteiras.php");
    exit();
}

// Buscar clientes para o dropdown usando prepared statement
$stmt_clientes = mysqli_prepare($conn, 
    "SELECT u.id, u.nome, c.saldo
     FROM utilizadores u
     LEFT JOIN carteiras c ON u.id = c.id_cliente
     WHERE u.tipo_perfil = 3
     ORDER BY u.nome");
mysqli_stmt_execute($stmt_clientes);
$result_clientes = mysqli_stmt_get_result($stmt_clientes);

// Buscar histórico de transações usando prepared statement
$stmt_transacoes = mysqli_prepare($conn, 
    "SELECT t.*, u.nome as nome_cliente
     FROM transacoes t
     JOIN utilizadores u ON t.id_cliente = u.id
     WHERE t.tipo IN ('depósito', 'levantamento')
     ORDER BY t.data_transacao DESC
     LIMIT 50");
mysqli_stmt_execute($stmt_transacoes);
$result_transacoes = mysqli_stmt_get_result($stmt_transacoes);

// Gerar token se não existir
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = uniqid(mt_rand(), true);
}
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
        <div class="links" style="display: flex; justify-content: center; width: 50%;">
            <?php if ($_SESSION["id_nivel"] == 1): ?>
                <div class="link"> <a href="pg_admin.php" style="font-size: 1.2rem; font-weight: 500;">Voltar para Página Inicial</a></div>
            <?php else: ?>
                <div class="link"> <a href="pg_funcionario.php" style="font-size: 1.2rem; font-weight: 500;">Voltar para Página Inicial</a></div>
            <?php endif; ?>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <?php if ($_SESSION["id_nivel"] == 1): ?>
                <div class="btn-admin">Área do Administrador</div>
            <?php else: ?>
                <div class="btn-admin">Área do Funcionário</div>
            <?php endif; ?>
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
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <div class="form-group">
                        <label for="id_cliente">Cliente:</label>
                        <select id="id_cliente" name="id_cliente" required>
                            <option value="">Selecione um cliente</option>
                            <?php while ($cliente = mysqli_fetch_assoc($result_clientes)): ?>
                                <option value="<?php echo $cliente['id']; ?>">
                                    <?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8'); ?> 
                                    (Saldo: €<?php echo number_format($cliente['saldo'] ?? 0, 2, ',', '.'); ?>)
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
                            <option value="depositar">Depositar</option>
                            <option value="levantar">Levantar</option>
                        </select>
                    </div>

                    <button type="submit" name="operacao_carteira">Confirmar Operação</button>
                </form>
            </div>

            <div class="historico-container">
                <h2>Histórico de Operações</h2>
                <?php if (mysqli_num_rows($result_transacoes) > 0): ?>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="historico-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Operação</th>
                                    <th>Valor</th>
                                    <th>Descrição</th>
                                    <th>Data/Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transacao = mysqli_fetch_assoc($result_transacoes)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transacao['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($transacao['nome_cliente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($transacao['tipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="<?php echo $transacao['tipo'] == 'depósito' ? 'deposito' : 'levantamento'; ?>">
                                            €<?php echo htmlspecialchars(number_format($transacao['valor'], 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($transacao['descricao'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($transacao['data_transacao'])); ?></td>
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

    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>
