<?php
session_start();
include '../basedados/basedados.h';

// Verifica se o utilizador está autenticado e se é um cliente (nível 3)
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    // Redireciona para a página de erro se não for um cliente
    header("Location: erro.php");
    exit();
}

// Obtém o ID do cliente a partir da sessão
$id_cliente = $_SESSION["id_utilizador"];

// Obtém o ID da carteira FelixBus (sistema)
$sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
$result_felixbus = mysqli_query($conn, $sql_felixbus);
$row_felixbus = mysqli_fetch_assoc($result_felixbus);
$id_carteira_felixbus = $row_felixbus['id'];

// Consulta o saldo atual do cliente na base de dados
$sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
$result_saldo = mysqli_query($conn, $sql_saldo);
$row_saldo = mysqli_fetch_assoc($result_saldo);

// Se o cliente não tiver carteira, cria uma com saldo zero
if (!$row_saldo) {
    $sql_criar_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
    mysqli_query($conn, $sql_criar_carteira);
    $row_saldo = ['saldo' => 0.00];
}

// Inicializa variáveis para mensagens de alerta
$mensagem = '';
$tipo_mensagem = '';

// Verifica se existem mensagens na sessão
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];

    // Limpa as mensagens da sessão após exibi-las
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Verifica se o formulário foi submetido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtém os valores do formulário
    $valor = $_POST["valor"];
    $operacao = $_POST["operacao"];

    // Verifica se o valor é válido (maior que zero)
    if ($valor <= 0) {
        $_SESSION['mensagem'] = "Valor inválido. Por favor, introduza um valor superior a zero.";
        $_SESSION['tipo_mensagem'] = "danger";

        // Redireciona para evitar reenvio do formulário ao atualizar a página
        header("Location: carteira_cliente.php");
        exit();
    } else {
        // Inicia uma transação para garantir a integridade dos dados
        mysqli_begin_transaction($conn);

        try {
            // Define a operação a realizar com base na escolha do utilizador
            if ($operacao == "adicionar") {
                // Adiciona valor à carteira
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo + $valor WHERE id_cliente = $id_cliente";
                $tipo_transacao = "depósito";
                $descricao = "Depósito de €$valor na carteira";
            } else if ($operacao == "retirar" && $row_saldo['saldo'] >= $valor) {
                // Retira valor da carteira se houver saldo suficiente
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
                $tipo_transacao = "levantamento";
                $descricao = "Levantamento de €$valor da carteira";
            } else {
                // Mensagem de erro se não houver saldo suficiente
                $_SESSION['mensagem'] = "Saldo insuficiente para realizar esta operação.";
                $_SESSION['tipo_mensagem'] = "danger";

                // Redireciona para evitar reenvio do formulário
                header("Location: carteira_cliente.php");
                exit();
            }

            // Executa a atualização do saldo
            if ($sql_atualiza && mysqli_query($conn, $sql_atualiza)) {
                // Regista a transação no histórico
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                  VALUES ($id_cliente, $id_carteira_felixbus, $valor, '$tipo_transacao', '$descricao')";

                if (mysqli_query($conn, $sql_transacao)) {
                    // Confirma a transação na base de dados
                    mysqli_commit($conn);
                    // Atualiza o saldo exibido
                    $result_saldo = mysqli_query($conn, $sql_saldo);
                    $row_saldo = mysqli_fetch_assoc($result_saldo);
                    // Define mensagem de sucesso
                    $_SESSION['mensagem'] = "Operação realizada com sucesso!";
                    $_SESSION['tipo_mensagem'] = "success";

                    // Redireciona para evitar reenvio do formulário
                    header("Location: carteira_cliente.php");
                    exit();
                } else {
                    // Lança exceção se houver erro ao registar a transação
                    throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
                }
            } else {
                // Cancela a transação e mostra mensagem de erro
                mysqli_rollback($conn);
                $_SESSION['mensagem'] = "Erro ao atualizar saldo: " . mysqli_error($conn);
                $_SESSION['tipo_mensagem'] = "danger";

                // Redireciona para evitar reenvio do formulário
                header("Location: carteira_cliente.php");
                exit();
            }
        } catch (Exception $e) {
            // Cancela a transação em caso de exceção
            mysqli_rollback($conn);
            $_SESSION['mensagem'] = $e->getMessage();
            $_SESSION['tipo_mensagem'] = "danger";

            // Redireciona para evitar reenvio do formulário
            header("Location: carteira_cliente.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="carteira_cliente.css">
    <title>FelixBus - Carteira</title>
</head>
<body>

    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="perfil_cliente.php">Perfil</a></div>
            <div class="link"> <a href="pg_cliente.php">Página Inicial</a></div>
            <div class="link"> <a href="bilhetes_cliente.php">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
        </div>
    </nav>

    <section>
        <h1>A Minha Carteira</h1>

        <!-- Exibe mensagens de alerta se existirem -->
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <!-- Informações de saldo -->
        <div class="saldo-info">
            <h3>Seu saldo atual:</h3>
            <span>€<?php echo number_format($row_saldo['saldo'], 2, ',', '.'); ?></span>
        </div>

        <!-- Layout de conteúdo -->
        <div class="content-wrapper">
            <!-- Contentor para operações na carteira -->
            <div class="carteira-container">
                <div class="card-header">
                    <h2>Operações na Carteira</h2>
                </div>
                <div class="card-body">
                    <!-- Formulário para operações na carteira -->
                    <form action="carteira_cliente.php" method="post">
                        <div class="form-group">
                            <label for="valor">Valor:</label>
                            <input type="number" id="valor" name="valor" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="operacao">Operação:</label>
                            <select id="operacao" name="operacao" required>
                                <option value="adicionar">Depositar</option>
                                <option value="retirar">Levantar</option>
                            </select>
                        </div>
                        <button type="submit">Confirmar</button>
                    </form>
                </div>
            </div>

            <!-- Contentor para o histórico de transações -->
            <div class="historico-container">
                <div class="card-header">
                    <h2>Histórico de Transações</h2>
                </div>
                <div class="card-body">
                    <!-- Tabela com rolagem para o histórico -->
                    <div class="historico-table-container">
                        <table class="historico-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            // Consulta para obter o histórico de transações do cliente
                            $sql_historico = "SELECT * FROM transacoes WHERE id_cliente = $id_cliente ORDER BY data_transacao DESC LIMIT 50";
                            $result_historico = mysqli_query($conn, $sql_historico);

                            // Verifica se existem transações
                            if (mysqli_num_rows($result_historico) > 0) {
                                // Percorre todas as transações encontradas
                                while ($transacao = mysqli_fetch_assoc($result_historico)) {
                                    // Define a classe CSS com base no tipo de transação
                                    $classe_valor = '';
                                    if ($transacao['tipo'] == 'depósito') {
                                        $classe_valor = 'deposito';
                                        $valor_formatado = '+€' . number_format($transacao['valor'], 2, ',', '.');
                                    } else {
                                        $classe_valor = 'retirada';
                                        $valor_formatado = '-€' . number_format($transacao['valor'], 2, ',', '.');
                                    }

                                    // Exibe cada linha da tabela com os dados da transação
                                    echo "<tr>";
                                    echo "<td>" . date('d/m/Y H:i', strtotime($transacao['data_transacao'])) . "</td>";
                                    echo "<td>" . ucfirst($transacao['tipo']) . "</td>";
                                    echo "<td class='$classe_valor'>$valor_formatado</td>";
                                    echo "<td>" . $transacao['descricao'] . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                // Mensagem quando não há transações
                                echo "<tr><td colspan='4' class='empty-state'>Nenhuma transação encontrada.</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>
