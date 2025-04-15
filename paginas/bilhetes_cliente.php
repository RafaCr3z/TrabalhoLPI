<?php
session_start();
include '../basedados/basedados.h';

if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}

$id_cliente = $_SESSION["id_utilizador"];
$id_carteira_felixbus = 1; // ID da carteira da FelixBus

// Buscar saldo do cliente
$sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
$result_saldo = mysqli_query($conn, $sql_saldo);
$row_saldo = mysqli_fetch_assoc($result_saldo);

$mensagem = '';
$tipo_mensagem = '';

// Verificar se há mensagem na URL
if (isset($_GET['msg']) && isset($_GET['tipo'])) {
    $mensagem = urldecode($_GET['msg']);
    $tipo_mensagem = $_GET['tipo'];
}

// Verificar se a coluna 'disponivel' existe na tabela 'horarios'
$check_column = "SHOW COLUMNS FROM horarios LIKE 'disponivel'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    // A coluna não existe, vamos criá-la
    $add_column = "ALTER TABLE horarios ADD COLUMN disponivel TINYINT(1) NOT NULL DEFAULT 1";
    mysqli_query($conn, $add_column);
}

// Código para buscar horários disponíveis
if (isset($_GET['get_horarios']) && isset($_GET['rota_id'])) {
    $rota_id = intval($_GET['rota_id']);

    // Verificar se a rota existe
    $check_rota = "SELECT id, origem, destino FROM rotas WHERE id = $rota_id AND disponivel = 1";
    $rota_result = mysqli_query($conn, $check_rota);
    $rota_info = mysqli_fetch_assoc($rota_result);

    if (!$rota_info) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rota não disponível']);
        exit();
    }

    // Query para buscar horários da rota
    $query = "SELECT h.id, h.data_viagem, h.horario_partida, h.disponivel, h.lugares_disponiveis
             FROM horarios h
             WHERE h.id_rota = $rota_id
             ORDER BY h.data_viagem ASC, h.horario_partida ASC";
    $result = mysqli_query($conn, $query);

    $horarios = [];

    // Usar uma data de referência fixa para 2024
    $data_referencia = '2024-04-16';

    while ($row = mysqli_fetch_assoc($result)) {
        // Só adiciona aos horários disponíveis se atender todos os critérios
        if ($row['disponivel'] == 1 &&
            $row['lugares_disponiveis'] > 0 &&
            strtotime($row['data_viagem']) >= strtotime($data_referencia)) {

            $horarios[] = [
                'id' => $row['id'],
                'data_viagem' => date('d/m/Y', strtotime($row['data_viagem'])),
                'hora_formatada' => date('H:i', strtotime($row['horario_partida'])),
                'origem' => $rota_info['origem'],
                'destino' => $rota_info['destino']
            ];
        }
    }

    // Garantir que a resposta seja JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo json_encode(['horarios' => $horarios]);
    exit();
}

// Processar compra de bilhete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    $id_rota = intval($_POST['rota']);
    list($hora_viagem, $data_viagem) = explode('|', $_POST['horario']);

    // Converter a data do formato dd/mm/yyyy para yyyy-mm-dd (formato do MySQL)
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data_viagem)));

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
                mysqli_stmt_execute($stmt);

                // 2. Aumentar saldo da FelixBus
                $sql_aumentar = "UPDATE carteira_felixbus SET saldo = saldo + ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql_aumentar);
                mysqli_stmt_bind_param($stmt, "di", $preco, $id_carteira_felixbus);
                mysqli_stmt_execute($stmt);

                // 3. Registrar a transação
                $descricao = "Compra de bilhete: $origem para $destino";
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                VALUES (?, ?, ?, 'compra', ?)";
                $stmt = mysqli_prepare($conn, $sql_transacao);
                mysqli_stmt_bind_param($stmt, "iids", $id_cliente, $id_carteira_felixbus, $preco, $descricao);
                mysqli_stmt_execute($stmt);

                // 4. Criar o bilhete
                $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem)
                               VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql_bilhete);
                mysqli_stmt_bind_param($stmt, "iiss", $id_cliente, $id_rota, $data_formatada, $hora_viagem);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);

                // Redirecionar para evitar reenvio do formulário ao atualizar a página
                $msg = urlencode("Bilhete comprado com sucesso!");
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=success");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $msg = urlencode($e->getMessage());
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
                exit();
            }
        } else {
            $msg = urlencode("Saldo insuficiente para comprar este bilhete.");
            header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
            exit();
        }
    } else {
        $msg = urlencode("Rota não encontrada.");
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }
}

// Buscar rotas disponíveis
$sql_rotas = "SELECT DISTINCT r.id as id_rota, r.origem, r.destino, r.preco,
             (SELECT COUNT(*) FROM horarios h WHERE h.id_rota = r.id) as total_horarios
             FROM rotas r
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
    <title>FelixBus - Meus Bilhetes</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
        </div>
    </nav>

    <div class="container">
        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem == 'success' ? 'success' : 'error'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="saldo-info">
            <h3>Seu saldo atual: <span>€<?php echo number_format($row_saldo['saldo'], 2, ',', '.'); ?></span></h3>
        </div>

        <div class="content-wrapper">
            <div class="comprar-bilhete">
                <div class="card-header">
                    <h2>Comprar Bilhete</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="bilhetes_cliente.php" id="comprarBilheteForm">
                        <div class="form-group">
                            <label for="rota">Rota:</label>
                            <select id="rota" name="rota" required>
                                <option value="">Selecione uma rota</option>
                                <?php while ($rota = mysqli_fetch_assoc($result_rotas)): ?>
                                    <option value="<?php echo $rota['id_rota']; ?>">
                                        <?php echo htmlspecialchars($rota['origem'] . ' → ' . $rota['destino'] . ' - €' . number_format($rota['preco'], 2, ',', '.')); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="horario">Data e Horário:</label>
                            <select id="horario" name="horario" required>
                                <option value="">Selecione uma rota primeiro</option>
                            </select>
                        </div>

                        <button type="submit" name="comprar" id="btnComprar" style="display: none;">
                            Comprar Bilhete
                        </button>
                    </form>
                </div>
            </div>

            <div class="meus-bilhetes">
                <div class="card-header">
                    <h2>Meus Bilhetes</h2>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_bilhetes) > 0): ?>
                        <div class="bilhetes-lista">
                            <?php while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                <div class="bilhete-item">
                                    <div class="bilhete-header">
                                        <h3><?php echo htmlspecialchars($bilhete['origem'] . ' → ' . $bilhete['destino']); ?></h3>
                                    </div>
                                    <div class="bilhete-info">
                                        <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></p>
                                        <p><strong>Hora:</strong> <?php echo $bilhete['hora_viagem']; ?></p>
                                        <p><strong>Preço:</strong> <span class="preco">€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></span></p>
                                        <div class="data-compra">Comprado em: <?php echo date('d/m/Y', strtotime($bilhete['data_compra'])); ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Ainda não possui nenhum bilhete. Compre o seu primeiro bilhete agora!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const rotaSelect = document.getElementById('rota');
        const horarioSelect = document.getElementById('horario');
        const btnComprar = document.getElementById('btnComprar');

        rotaSelect.addEventListener('change', function() {
            const rotaId = this.value;

            if (!rotaId) {
                horarioSelect.innerHTML = '<option value="">Selecione uma rota primeiro</option>';
                btnComprar.style.display = 'none';
                return;
            }

            horarioSelect.innerHTML = '<option value="">Carregando horários...</option>';
            btnComprar.style.display = 'none';

            // Construir URL para buscar horários
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.append('get_horarios', '1');
            url.searchParams.append('rota_id', rotaId);
            url.searchParams.append('_', new Date().getTime());

            fetch(url.toString(), {
                method: 'GET',
                headers: {'Accept': 'application/json'}
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                horarioSelect.innerHTML = '<option value="">Selecione uma data e horário</option>';

                if (!data.horarios || data.horarios.length === 0) {
                    horarioSelect.innerHTML = '<option value="" disabled>Nenhum horário disponível</option>';
                    btnComprar.style.display = 'none';
                    return;
                }

                // Ordenar horários por data e hora
                data.horarios.sort((a, b) => {
                    const dataA = a.data_viagem.split('/').reverse().join('-') + ' ' + a.hora_formatada;
                    const dataB = b.data_viagem.split('/').reverse().join('-') + ' ' + b.hora_formatada;
                    return new Date(dataA) - new Date(dataB);
                });

                data.horarios.forEach(horario => {
                    const option = document.createElement('option');
                    option.value = `${horario.hora_formatada}|${horario.data_viagem}`;
                    option.textContent = `${horario.data_viagem} às ${horario.hora_formatada}`;
                    horarioSelect.appendChild(option);
                });

                btnComprar.style.display = 'block';
            })
            .catch(error => {
                horarioSelect.innerHTML = `<option value="">Erro ao carregar horários</option>`;
                btnComprar.style.display = 'none';
            });
        });
    });
    </script>

    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>