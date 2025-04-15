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

// Processar requisição AJAX para horários
if (isset($_GET['get_horarios']) && isset($_GET['rota_id'])) {
    $rota_id = intval($_GET['rota_id']);
    $data_atual = date('Y-m-d');
    
    $sql = "SELECT horario_partida 
            FROM horarios 
            WHERE id_rota = ? AND disponivel = 1 
            ORDER BY horario_partida";
            
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $rota_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $horarios = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['data_viagem'] = $data_atual;
        $horarios[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($horarios);
    exit();
}

// Processar compra de bilhete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    $id_rota = intval($_POST['rota']);
    $hora_viagem = $_POST['horario'];
    $data_viagem = date('Y-m-d'); // Usa a data atual

    // Verificar se a rota existe e obter o preço
    $sql_rota = "SELECT r.preco, r.origem, r.destino
                FROM rotas r
                WHERE r.id = ? AND r.disponivel = 1";
                
    $stmt = mysqli_prepare($conn, $sql_rota);
    mysqli_stmt_bind_param($stmt, "i", $id_rota);
    mysqli_stmt_execute($stmt);
    $result_rota = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result_rota) > 0) {
        $rota = mysqli_fetch_assoc($result_rota);
        $preco = $rota['preco'];
        $origem = $rota['origem'];
        $destino = $rota['destino'];

        if ($row_saldo['saldo'] >= $preco) {
            mysqli_begin_transaction($conn);

            try {
                // 1. Reduzir saldo do cliente
                $sql_reduzir = "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?";
                $stmt = mysqli_prepare($conn, $sql_reduzir);
                mysqli_stmt_bind_param($stmt, "di", $preco, $id_cliente);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Erro ao atualizar saldo do cliente");
                }

                // 2. Aumentar saldo da FelixBus
                $sql_aumentar = "UPDATE carteira_felixbus SET saldo = saldo + ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql_aumentar);
                mysqli_stmt_bind_param($stmt, "di", $preco, $id_carteira_felixbus);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Erro ao atualizar saldo da FelixBus");
                }

                // 3. Registrar a transação
                $descricao = "Compra de bilhete: $origem para $destino";
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                VALUES (?, ?, ?, 'compra', ?)";
                $stmt = mysqli_prepare($conn, $sql_transacao);
                mysqli_stmt_bind_param($stmt, "iids", $id_cliente, $id_carteira_felixbus, $preco, $descricao);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Erro ao registrar transação");
                }

                // 4. Criar o bilhete
                $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem)
                               VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql_bilhete);
                mysqli_stmt_bind_param($stmt, "iiss", $id_cliente, $id_rota, $data_viagem, $hora_viagem);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Erro ao criar bilhete");
                }

                mysqli_commit($conn);
                $mensagem = "Bilhete comprado com sucesso!";
                $tipo_mensagem = "success";

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
$sql_rotas = "SELECT DISTINCT r.id as id_rota, r.origem, r.destino, r.preco
             FROM rotas r
             JOIN horarios h ON r.id = h.id_rota
             WHERE r.disponivel = 1
             ORDER BY r.origem, r.destino";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar bilhetes do cliente
$sql_bilhetes = "SELECT b.id, r.origem, r.destino, b.data_viagem, b.hora_viagem, r.preco, b.data_compra
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                WHERE b.id_cliente = ?
                ORDER BY b.data_viagem DESC, b.hora_viagem ASC";
$stmt = mysqli_prepare($conn, $sql_bilhetes);
mysqli_stmt_bind_param($stmt, "i", $id_cliente);
mysqli_stmt_execute($stmt);
$result_bilhetes = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bilhetes_cliente.css">
    <title>FelixBus - Bilhetes</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Cliente</div>
        </div>
    </nav>

    <div class="container">
        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="saldo-info">
            <h3>Seu saldo atual: €<?php echo number_format($row_saldo['saldo'], 2, ',', '.'); ?></h3>
        </div>

        <div class="comprar-bilhete">
            <h2>Comprar Bilhete</h2>
            <form method="post" action="bilhetes_cliente.php" id="comprarBilheteForm">
                <div class="form-group">
                    <label for="rota">Rota:</label>
                    <select id="rota" name="rota" required>
                        <option value="">Selecione uma rota</option>
                        <?php while ($rota = mysqli_fetch_assoc($result_rotas)): ?>
                            <option value="<?php echo $rota['id_rota']; ?>">
                                <?php echo htmlspecialchars($rota['origem'] . ' → ' . $rota['destino'] . 
                                     ' - €' . number_format($rota['preco'], 2, ',', '.')); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group" id="horarioContainer" style="display: none;">
                    <label for="horario">Data e Horário:</label>
                    <select id="horario" name="horario" required>
                        <option value="">Selecione um horário</option>
                    </select>
                </div>

                <button type="submit" name="comprar" id="btnComprar" style="display: none;">
                    Comprar Bilhete
                </button>
            </form>
        </div>

        <div class="meus-bilhetes">
            <h2>Meus Bilhetes</h2>
            <div class="bilhetes-lista">
                <?php while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                    <div class="bilhete-item">
                        <div class="bilhete-info">
                            <h3><?php echo htmlspecialchars($bilhete['origem'] . ' → ' . $bilhete['destino']); ?></h3>
                            <p>Data: <?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></p>
                            <p>Hora: <?php echo $bilhete['hora_viagem']; ?></p>
                            <p>Preço: €<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></p>
                            <p>Comprado em: <?php echo date('d/m/Y', strtotime($bilhete['data_compra'])); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const rotaSelect = document.getElementById('rota');
        const horarioContainer = document.getElementById('horarioContainer');
        const horarioSelect = document.getElementById('horario');
        const btnComprar = document.getElementById('btnComprar');
        
        rotaSelect.addEventListener('change', function() {
            if (this.value) {
                fetch('bilhetes_cliente.php?get_horarios=1&rota_id=' + this.value)
                    .then(response => response.json())
                    .then(horarios => {
                        horarioSelect.innerHTML = '<option value="">Selecione um horário</option>';
                        horarios.forEach(horario => {
                            // Formatar a data para exibição (dd/mm/yyyy)
                            const data = new Date(horario.data_viagem);
                            const dataFormatada = data.toLocaleDateString('pt-PT');
                            
                            horarioSelect.innerHTML += `<option value="${horario.horario_partida}">
                                ${dataFormatada} - ${horario.horario_partida}
                            </option>`;
                        });
                        horarioContainer.style.display = 'block';
                        btnComprar.style.display = 'block';
                    });
            } else {
                horarioContainer.style.display = 'none';
                btnComprar.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>






