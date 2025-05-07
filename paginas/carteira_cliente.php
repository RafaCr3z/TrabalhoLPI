<?php
session_start();
include '../basedados/basedados.h';

if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}

$id_cliente = $_SESSION["id_utilizador"];

// Obter ID da carteira FelixBus
$sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
$result_felixbus = mysqli_query($conn, $sql_felixbus);
$row_felixbus = mysqli_fetch_assoc($result_felixbus);
$id_carteira_felixbus = $row_felixbus['id'];

// Exibe o saldo atual
$sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
$result_saldo = mysqli_query($conn, $sql_saldo);
$row_saldo = mysqli_fetch_assoc($result_saldo);

// Se o cliente não tiver carteira, criar uma
if (!$row_saldo) {
    $sql_criar_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
    mysqli_query($conn, $sql_criar_carteira);
    $row_saldo = ['saldo' => 0.00];
}

// Verifica se o formulário foi enviado
// Variáveis para mensagens de alerta
$mensagem = '';
$tipo_mensagem = '';

// Verificar se há mensagens na sessão
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];

    // Limpar as mensagens da sessão após exibi-las
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor = $_POST["valor"];
    $operacao = $_POST["operacao"];

    if ($valor <= 0) {
        $_SESSION['mensagem'] = "Valor inválido. Por favor, introduza um valor superior a zero.";
        $_SESSION['tipo_mensagem'] = "danger";

        // Redirecionar para evitar reenvio do formulário ao atualizar a página
        header("Location: carteira_cliente.php");
        exit();
    } else {
        // Iniciar transação para garantir integridade dos dados
        mysqli_begin_transaction($conn);

        try {
            if ($operacao == "adicionar") {
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo + $valor WHERE id_cliente = $id_cliente";
                $tipo_transacao = "depósito";
                $descricao = "Depósito de €$valor na carteira";
            } else if ($operacao == "retirar" && $row_saldo['saldo'] >= $valor) {
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
                $tipo_transacao = "levantamento";
                $descricao = "Levantamento de €$valor da carteira";
            } else {
                $_SESSION['mensagem'] = "Saldo insuficiente para realizar esta operação.";
                $_SESSION['tipo_mensagem'] = "danger";

                // Redirecionar para evitar reenvio do formulário ao atualizar a página
                header("Location: carteira_cliente.php");
                exit();
            }

            // Atualizar saldo (apenas se tiver uma operação válida)
            if ($sql_atualiza && mysqli_query($conn, $sql_atualiza)) {
                // Registrar transação
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                  VALUES ($id_cliente, $id_carteira_felixbus, $valor, '$tipo_transacao', '$descricao')";

                if (mysqli_query($conn, $sql_transacao)) {
                    mysqli_commit($conn);
                    // Atualizar o saldo exibido
                    $result_saldo = mysqli_query($conn, $sql_saldo);
                    $row_saldo = mysqli_fetch_assoc($result_saldo);
                    // Operação realizada com sucesso
                    $_SESSION['mensagem'] = "Operação realizada com sucesso!";
                    $_SESSION['tipo_mensagem'] = "success";

                    // Redirecionar para evitar reenvio do formulário ao atualizar a página
                    header("Location: carteira_cliente.php");
                    exit();
                } else {
                    throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
                }
            } else {
                mysqli_rollback($conn);
                $_SESSION['mensagem'] = "Erro ao atualizar saldo: " . mysqli_error($conn);
                $_SESSION['tipo_mensagem'] = "danger";

                // Redirecionar para evitar reenvio do formulário ao atualizar a página
                header("Location: carteira_cliente.php");
                exit();
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['mensagem'] = $e->getMessage();
            $_SESSION['tipo_mensagem'] = "danger";

            // Redirecionar para evitar reenvio do formulário ao atualizar a página
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

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="carteira-container">
            <h2>Saldo e Operações</h2>
            <p>Saldo atual:
                <?php
                if ($row_saldo) {
                    echo "€" . number_format($row_saldo['saldo'], 2, ',', '.');
                } else {
                    echo "Erro ao obter saldo.";
                }
                ?>
            </p>
            <form action="carteira_cliente.php" method="post">
                <label for="valor">Valor:</label>
                <input type="number" id="valor" name="valor" step="0.01" required>
                <label for="operacao">Operação:</label>
                <select id="operacao" name="operacao" required>
                    <option value="adicionar">Depositar</option>
                    <option value="retirar">Levantar</option>
                </select>
                <button type="submit">Confirmar</button>
            </form>
        </div>

        <div class="historico-container">
            <h2>Histórico de Transações</h2>
            <p class="scroll-hint">Deslize para ver mais transações</p>
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
                    // Buscar histórico de transações (aumentado o limite para mostrar mais itens na rolagem)
                    $sql_historico = "SELECT * FROM transacoes WHERE id_cliente = $id_cliente ORDER BY data_transacao DESC LIMIT 50";
                    $result_historico = mysqli_query($conn, $sql_historico);

                    if (mysqli_num_rows($result_historico) > 0) {
                        while ($transacao = mysqli_fetch_assoc($result_historico)) {
                            $classe_valor = '';
                            if ($transacao['tipo'] == 'depósito') {
                                $classe_valor = 'deposito';
                                $valor_formatado = '+€' . number_format($transacao['valor'], 2, ',', '.');
                            } else {
                                $classe_valor = 'retirada';
                                $valor_formatado = '-€' . number_format($transacao['valor'], 2, ',', '.');
                            }

                            echo "<tr>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($transacao['data_transacao'])) . "</td>";
                            echo "<td>" . ucfirst($transacao['tipo']) . "</td>";
                            echo "<td class='$classe_valor'>$valor_formatado</td>";
                            echo "<td>" . htmlspecialchars($transacao['descricao']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>Nenhuma transação encontrada.</td></tr>";
                    }
                    ?>
                </tbody>
                </table>
            </div>
        </div>
    </section>

     <!-- Adicionar antes do fechamento do </body> -->
     <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>
