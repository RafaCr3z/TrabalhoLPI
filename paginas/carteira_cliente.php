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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor = $_POST["valor"];
    $operacao = $_POST["operacao"];

    if ($valor <= 0) {
        $mensagem = "Valor inválido. Por favor, insira um valor maior que zero.";
        $tipo_mensagem = "danger";
    } else {
        // Iniciar transação para garantir integridade dos dados
        mysqli_begin_transaction($conn);

        try {
            if ($operacao == "adicionar") {
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo + $valor WHERE id_cliente = $id_cliente";
                $tipo_transacao = "deposito";
                $descricao = "Depósito de €$valor na carteira";
            } else if ($operacao == "retirar" && $row_saldo['saldo'] >= $valor) {
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
                $tipo_transacao = "retirada";
                $descricao = "Retirada de €$valor da carteira";
            } else {
                $mensagem = "Saldo insuficiente para realizar esta operação.";
                $tipo_mensagem = "danger";
                // Pular o resto do processamento
                $sql_atualiza = null;
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
                    $mensagem = "Operação realizada com sucesso!";
                    $tipo_mensagem = "success";
                } else {
                    throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
                }
            } else {
                mysqli_rollback($conn);
                $mensagem = "Erro ao atualizar saldo: " . mysqli_error($conn);
                $tipo_mensagem = "danger";
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $mensagem = $e->getMessage();
            $tipo_mensagem = "danger";
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
            <div class="btn-cliente">Área de Cliente</div>
        </div>
    </nav>

    <section>
        <h1>Minha Carteira</h1>

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
                    <option value="adicionar">Adicionar</option>
                    <option value="retirar">Retirar</option>
                </select>
                <button type="submit">Confirmar</button>
            </form>
        </div>

        <div class="historico-container">
            <h2>Histórico de Transações</h2>
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
                    // Buscar histórico de transações
                    $sql_historico = "SELECT * FROM transacoes WHERE id_cliente = $id_cliente ORDER BY data_transacao DESC LIMIT 10";
                    $result_historico = mysqli_query($conn, $sql_historico);

                    if (mysqli_num_rows($result_historico) > 0) {
                        while ($transacao = mysqli_fetch_assoc($result_historico)) {
                            $classe_valor = '';
                            if ($transacao['tipo'] == 'deposito') {
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
    </section>

     <!-- Adicionar antes do fechamento do </body> -->
     <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
    
</body>
</html>
