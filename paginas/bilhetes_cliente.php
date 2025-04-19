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

// Verificar se a coluna 'numero_lugar' existe na tabela 'bilhetes'
$check_column = "SHOW COLUMNS FROM bilhetes LIKE 'numero_lugar'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    // A coluna não existe, vamos criá-la
    $add_column = "ALTER TABLE bilhetes ADD COLUMN numero_lugar INT";
    mysqli_query($conn, $add_column);
}

// Código para buscar horários disponíveis
if (isset($_GET['get_horarios']) && isset($_GET['rota_id'])) {
    $rota_id = intval($_GET['rota_id']);

    // Verificar se a rota existe
    $check_rota = "SELECT id, origem, destino, capacidade FROM rotas WHERE id = $rota_id AND disponivel = 1";
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

            // Buscar lugares já ocupados para este horário
            $data_formatada = date('Y-m-d', strtotime($row['data_viagem']));
            $hora_formatada = date('H:i:s', strtotime($row['horario_partida']));

            $sql_lugares_ocupados = "SELECT numero_lugar FROM bilhetes
                                    WHERE id_rota = $rota_id
                                    AND data_viagem = '$data_formatada'
                                    AND hora_viagem = '$hora_formatada'
                                    AND numero_lugar IS NOT NULL";
            $result_lugares = mysqli_query($conn, $sql_lugares_ocupados);

            $lugares_ocupados = [];
            while ($lugar = mysqli_fetch_assoc($result_lugares)) {
                $lugares_ocupados[] = $lugar['numero_lugar'];
            }

            // Calcular lugares disponíveis
            $lugares_disponiveis = [];
            for ($i = 1; $i <= $rota_info['capacidade']; $i++) {
                if (!in_array($i, $lugares_ocupados)) {
                    $lugares_disponiveis[] = $i;
                }
            }

            $horarios[] = [
                'id' => $row['id'],
                'data_viagem' => date('d/m/Y', strtotime($row['data_viagem'])),
                'hora_formatada' => date('H:i', strtotime($row['horario_partida'])),
                'origem' => $rota_info['origem'],
                'destino' => $rota_info['destino'],
                'lugares_disponiveis' => $lugares_disponiveis,
                'total_lugares' => $rota_info['capacidade']
            ];
        }
    }

    // Garantir que a resposta seja JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo json_encode(['horarios' => $horarios]);
    exit();
}

// Código para buscar lugares disponíveis para um horário específico
if (isset($_GET['get_lugares']) && isset($_GET['rota_id']) && isset($_GET['data']) && isset($_GET['hora'])) {
    $rota_id = intval($_GET['rota_id']);
    $data = $_GET['data'];
    $hora = $_GET['hora'];

    // Converter a data do formato dd/mm/yyyy para yyyy-mm-dd (formato do MySQL)
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data)));

    // Verificar se a rota existe
    $check_rota = "SELECT id, capacidade FROM rotas WHERE id = $rota_id AND disponivel = 1";
    $rota_result = mysqli_query($conn, $check_rota);
    $rota_info = mysqli_fetch_assoc($rota_result);

    if (!$rota_info) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rota não disponível']);
        exit();
    }

    // Buscar lugares já ocupados
    $sql_lugares_ocupados = "SELECT numero_lugar FROM bilhetes
                            WHERE id_rota = $rota_id
                            AND data_viagem = '$data_formatada'
                            AND hora_viagem = '$hora'
                            AND numero_lugar IS NOT NULL";
    $result_lugares = mysqli_query($conn, $sql_lugares_ocupados);

    $lugares_ocupados = [];
    while ($lugar = mysqli_fetch_assoc($result_lugares)) {
        $lugares_ocupados[] = $lugar['numero_lugar'];
    }

    // Calcular lugares disponíveis
    $lugares_disponiveis = [];
    for ($i = 1; $i <= $rota_info['capacidade']; $i++) {
        if (!in_array($i, $lugares_ocupados)) {
            $lugares_disponiveis[] = $i;
        }
    }

    // Garantir que a resposta seja JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo json_encode(['lugares_disponiveis' => $lugares_disponiveis, 'total_lugares' => $rota_info['capacidade']]);
    exit();
}

// Processar compra de bilhete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    $id_rota = intval($_POST['rota']);
    list($hora_viagem, $data_viagem) = explode('|', $_POST['horario']);
    $quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
    $lugares = isset($_POST['lugares']) ? $_POST['lugares'] : '';

    // Validar quantidade mínima
    if ($quantidade < 1) {
        $msg = urlencode("A quantidade de bilhetes deve ser pelo menos 1.");
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }

    // Validar seleção de lugares
    if (empty($lugares)) {
        $msg = urlencode("Por favor, selecione os lugares para os bilhetes.");
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }

    // Converter a string de lugares em um array
    $lugares_array = explode(',', $lugares);

    // Verificar se a quantidade de lugares selecionados corresponde à quantidade de bilhetes
    if (count($lugares_array) != $quantidade) {
        $msg = urlencode("A quantidade de lugares selecionados não corresponde à quantidade de bilhetes.");
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }

    // Converter a data do formato dd/mm/yyyy para yyyy-mm-dd
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data_viagem)));

    // Verificar se a rota existe e obter informações
    $sql_rota = "SELECT r.preco, r.origem, r.destino, r.capacidade
                 FROM rotas r
                 WHERE r.id = ? AND r.disponivel = 1";

    $stmt = mysqli_prepare($conn, $sql_rota);
    mysqli_stmt_bind_param($stmt, "i", $id_rota);
    mysqli_stmt_execute($stmt);
    $result_rota = mysqli_stmt_get_result($stmt);

    if ($row_rota = mysqli_fetch_assoc($result_rota)) {
        $preco = $row_rota['preco'];
        $origem = $row_rota['origem'];
        $destino = $row_rota['destino'];
        $capacidade = $row_rota['capacidade'];

        // Verificar se os lugares já estão ocupados
        $lugares_str = implode(',', $lugares_array);
        $sql_check_lugares = "SELECT numero_lugar FROM bilhetes
                             WHERE id_rota = ?
                             AND data_viagem = ?
                             AND hora_viagem = ?
                             AND numero_lugar IN ($lugares_str)";
        $stmt = mysqli_prepare($conn, $sql_check_lugares);
        mysqli_stmt_bind_param($stmt, "iss", $id_rota, $data_formatada, $hora_viagem);
        mysqli_stmt_execute($stmt);
        $result_check_lugares = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result_check_lugares) > 0) {
            $lugares_ocupados = [];
            while ($row = mysqli_fetch_assoc($result_check_lugares)) {
                $lugares_ocupados[] = $row['numero_lugar'];
            }
            $msg = urlencode("Os seguintes lugares já estão ocupados: " . implode(', ', $lugares_ocupados));
            header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
            exit();
        }

        // Verificar se o cliente tem saldo suficiente para todos os bilhetes
        $preco_total = $preco * $quantidade;

        if ($row_saldo['saldo'] >= $preco_total) {
            mysqli_begin_transaction($conn);

            try {
                // 1. Reduzir saldo do cliente
                $sql_reduzir = "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?";
                $stmt = mysqli_prepare($conn, $sql_reduzir);
                mysqli_stmt_bind_param($stmt, "di", $preco_total, $id_cliente);
                mysqli_stmt_execute($stmt);

                // 2. Aumentar saldo da FelixBus
                $sql_aumentar = "UPDATE carteira_felixbus SET saldo = saldo + ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql_aumentar);
                mysqli_stmt_bind_param($stmt, "di", $preco_total, $id_carteira_felixbus);
                mysqli_stmt_execute($stmt);

                // 3. Registar a transação
                $descricao = "Compra de $quantidade bilhete(s): $origem para $destino (Lugares: $lugares)";
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                VALUES (?, ?, ?, 'compra', ?)";
                $stmt = mysqli_prepare($conn, $sql_transacao);
                mysqli_stmt_bind_param($stmt, "iids", $id_cliente, $id_carteira_felixbus, $preco_total, $descricao);
                mysqli_stmt_execute($stmt);

                // 4. Criar os bilhetes com os lugares selecionados
                foreach ($lugares_array as $lugar) {
                    $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem, numero_lugar)
                                   VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql_bilhete);
                    mysqli_stmt_bind_param($stmt, "iissi", $id_cliente, $id_rota, $data_formatada, $hora_viagem, $lugar);
                    mysqli_stmt_execute($stmt);
                }

                // 5. Atualizar lugares disponíveis na tabela horarios
                $sql_update_lugares = "UPDATE horarios SET lugares_disponiveis = lugares_disponiveis - ?
                                     WHERE id_rota = ? AND data_viagem = ? AND horario_partida = ?";
                $stmt = mysqli_prepare($conn, $sql_update_lugares);
                mysqli_stmt_bind_param($stmt, "iiss", $quantidade, $id_rota, $data_formatada, $hora_viagem);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);

                $msg = urlencode("$quantidade bilhete(s) comprado(s) com sucesso para os lugares: $lugares!");
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=success");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $msg = urlencode($e->getMessage());
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
                exit();
            }
        } else {
            $msg = urlencode("Saldo insuficiente para comprar $quantidade bilhete(s). Total: €" . number_format($preco_total, 2, ',', '.'));
            header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
            exit();
        }
    } else {
        $msg = urlencode("Rota não encontrada ou não disponível.");
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
$sql_bilhetes = "SELECT
                    r.origem,
                    r.destino,
                    b.data_viagem,
                    b.hora_viagem,
                    r.preco,
                    MIN(b.data_compra) as data_compra,
                    u.nome as nome_cliente,
                    COUNT(*) as quantidade,
                    GROUP_CONCAT(b.id) as ids,
                    GROUP_CONCAT(b.numero_lugar) as lugares
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                JOIN utilizadores u ON b.id_cliente = u.id
                WHERE b.id_cliente = ?
                GROUP BY r.origem, r.destino, b.data_viagem, b.hora_viagem, r.preco, u.nome
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
    <title>FelixBus - Os Meus Bilhetes</title>
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

                        <div class="form-group">
                            <label for="quantidade">Quantidade de Bilhetes:</label>
                            <input type="number" id="quantidade" name="quantidade" min="1" value="1" required>
                            <small id="quantidadeDisponivel"></small>
                        </div>

                        <div class="form-group" id="lugarGroup" style="display: none;">
                            <label>Escolha os Lugares:</label>
                            <div id="lugaresSelector" class="lugares-grid">
                                <!-- Os lugares serão adicionados dinamicamente aqui -->
                            </div>
                            <input type="hidden" id="lugaresEscolhidos" name="lugares" value="">
                            <small id="lugaresInfo" class="lugar-info">Selecione os lugares no diagrama acima.</small>
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
                        <div class="table-responsive">
                            <table class="bilhetes-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Rota</th>
                                        <th>Cliente</th>
                                        <th>Data</th>
                                        <th>Hora</th>
                                        <th>Quantidade</th>
                                        <th>Lugares</th>
                                        <th>Preço Unit.</th>
                                        <th>Preço Total</th>
                                        <th>Data de Compra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $contador_bilhetes = 1;
                                    while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                        <tr>
                                            <td>
                                                <?php echo $contador_bilhetes++; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($bilhete['origem'] . ' → ' . $bilhete['destino']); ?></td>
                                            <td><?php echo htmlspecialchars($bilhete['nome_cliente']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                            <td><?php echo $bilhete['hora_viagem']; ?></td>
                                            <td><?php echo $bilhete['quantidade']; ?></td>
                                            <td><?php
                                                $lugares = $bilhete['lugares'] ?
                                                    implode(', ', array_filter(explode(',', $bilhete['lugares']))) :
                                                    'Não definido';
                                                echo $lugares;
                                            ?></td>
                                            <td class="preco">€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></td>
                                            <td class="preco">€<?php echo number_format($bilhete['preco'] * $bilhete['quantidade'], 2, ',', '.'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($bilhete['data_compra'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
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
        const lugaresSelector = document.getElementById('lugaresSelector');
        const lugaresEscolhidos = document.getElementById('lugaresEscolhidos');
        const lugarGroup = document.getElementById('lugarGroup');
        const lugaresInfo = document.getElementById('lugaresInfo');
        const btnComprar = document.getElementById('btnComprar');
        const quantidadeInput = document.getElementById('quantidade');
        const quantidadeDisponivel = document.getElementById('quantidadeDisponivel');

        // Armazenar os dados dos horários para uso posterior
        let horariosData = [];
        // Array para armazenar os lugares selecionados
        let lugaresSelecionados = [];
        // Array para armazenar os lugares ocupados
        let lugaresOcupados = [];
        // Capacidade total do ônibus
        let capacidadeOnibus = 0;

        rotaSelect.addEventListener('change', function() {
            const rotaId = this.value;

            if (!rotaId) {
                horarioSelect.innerHTML = '<option value="">Selecione uma rota primeiro</option>';
                lugarGroup.style.display = 'none';
                btnComprar.style.display = 'none';
                return;
            }

            horarioSelect.innerHTML = '<option value="">Carregando horários...</option>';
            lugarGroup.style.display = 'none';
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
                horariosData = data.horarios || [];

                if (!horariosData.length) {
                    horarioSelect.innerHTML = '<option value="" disabled>Nenhum horário disponível</option>';
                    lugarGroup.style.display = 'none';
                    btnComprar.style.display = 'none';
                    return;
                }

                // Ordenar horários por data e hora
                horariosData.sort((a, b) => {
                    const dataA = a.data_viagem.split('/').reverse().join('-') + ' ' + a.hora_formatada;
                    const dataB = b.data_viagem.split('/').reverse().join('-') + ' ' + b.hora_formatada;
                    return new Date(dataA) - new Date(dataB);
                });

                horariosData.forEach(horario => {
                    const option = document.createElement('option');
                    option.value = `${horario.hora_formatada}|${horario.data_viagem}`;
                    option.textContent = `${horario.data_viagem} às ${horario.hora_formatada}`;
                    option.dataset.index = horariosData.indexOf(horario);
                    horarioSelect.appendChild(option);
                });
            })
            .catch(error => {
                horarioSelect.innerHTML = `<option value="">Erro ao carregar horários</option>`;
                lugarGroup.style.display = 'none';
                btnComprar.style.display = 'none';
            });
        });

        // Quando o horário é selecionado, carregar os lugares disponíveis
        horarioSelect.addEventListener('change', function() {
            const selectedIndex = this.options[this.selectedIndex].dataset.index;
            const horarioValue = this.value;

            // Resetar lugares selecionados
            lugaresSelecionados = [];
            lugaresEscolhidos.value = '';

            if (!horarioValue) {
                lugarGroup.style.display = 'none';
                btnComprar.style.display = 'none';
                return;
            }

            // Se temos os dados do horário em cache
            if (selectedIndex !== undefined && horariosData[selectedIndex]) {
                const horario = horariosData[selectedIndex];
                capacidadeOnibus = horario.total_lugares;

                // Obter lugares ocupados (todos os lugares que não estão na lista de disponíveis)
                lugaresOcupados = [];
                for (let i = 1; i <= capacidadeOnibus; i++) {
                    if (!horario.lugares_disponiveis.includes(i)) {
                        lugaresOcupados.push(i);
                    }
                }

                renderizarLugares(horario.lugares_disponiveis);
                atualizarQuantidadeMaxima(horario.lugares_disponiveis.length);
            } else {
                // Caso contrário, buscar os lugares disponíveis do servidor
                const [hora, data] = horarioValue.split('|');
                const rotaId = rotaSelect.value;

                lugaresSelector.innerHTML = '<div class="loading">Carregando lugares...</div>';
                lugarGroup.style.display = 'block';
                btnComprar.style.display = 'none';

                const url = new URL(window.location.href);
                url.search = '';
                url.searchParams.append('get_lugares', '1');
                url.searchParams.append('rota_id', rotaId);
                url.searchParams.append('data', data);
                url.searchParams.append('hora', hora);
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
                    if (data.lugares_disponiveis && data.lugares_disponiveis.length > 0) {
                        capacidadeOnibus = data.total_lugares;

                        // Obter lugares ocupados (todos os lugares que não estão na lista de disponíveis)
                        lugaresOcupados = [];
                        for (let i = 1; i <= capacidadeOnibus; i++) {
                            if (!data.lugares_disponiveis.includes(i)) {
                                lugaresOcupados.push(i);
                            }
                        }

                        renderizarLugares(data.lugares_disponiveis);
                        atualizarQuantidadeMaxima(data.lugares_disponiveis.length);
                    } else {
                        lugaresSelector.innerHTML = '<div class="empty-state">Nenhum lugar disponível</div>';
                        btnComprar.style.display = 'none';
                    }
                })
                .catch(error => {
                    lugaresSelector.innerHTML = `<div class="error">Erro ao carregar lugares</div>`;
                    btnComprar.style.display = 'none';
                });
            }
        });

        // Função para renderizar a grade de lugares
        function renderizarLugares(lugaresDisponiveis) {
            lugaresSelector.innerHTML = '';

            if (!lugaresDisponiveis || lugaresDisponiveis.length === 0) {
                lugaresSelector.innerHTML = '<div class="empty-state">Nenhum lugar disponível</div>';
                return;
            }

            // Criar a grade de lugares
            for (let i = 1; i <= capacidadeOnibus; i++) {
                const lugarElement = document.createElement('div');
                lugarElement.classList.add('lugar');
                lugarElement.textContent = i;

                if (lugaresOcupados.includes(i)) {
                    lugarElement.classList.add('ocupado');
                    lugarElement.title = 'Lugar ocupado';
                } else if (lugaresSelecionados.includes(i)) {
                    lugarElement.classList.add('selecionado');
                    lugarElement.title = 'Lugar selecionado';
                } else {
                    lugarElement.classList.add('disponivel');
                    lugarElement.title = 'Lugar disponível';

                    lugarElement.addEventListener('click', function() {
                        const quantidadeDesejada = parseInt(quantidadeInput.value);
                        const index = lugaresSelecionados.indexOf(i);

                        // Se o lugar já está selecionado, permite desmarcar
                        if (index !== -1) {
                            lugaresSelecionados.splice(index, 1);
                            lugarElement.classList.remove('selecionado');
                            lugarElement.classList.add('disponivel');
                        }
                        // Se não está selecionado, verifica se pode selecionar mais lugares
                        else {
                            if (lugaresSelecionados.length >= quantidadeDesejada) {
                                // Não permitir selecionar mais lugares do que a quantidade de bilhetes
                                return;
                            }

                            lugaresSelecionados.push(i);
                            lugarElement.classList.add('selecionado');
                            lugarElement.classList.remove('disponivel');
                        }

                        // Atualizar o campo hidden com os lugares selecionados
                        lugaresEscolhidos.value = lugaresSelecionados.join(',');

                        // Atualizar informações sobre lugares selecionados
                        atualizarInfoLugares();

                        // Mostrar ou esconder o botão de compra
                        // Só mostra o botão se o número de lugares selecionados for EXATAMENTE igual à quantidade de bilhetes
                        btnComprar.style.display = lugaresSelecionados.length === quantidadeDesejada ? 'block' : 'none';
                    });
                }

                lugaresSelector.appendChild(lugarElement);
            }

            lugarGroup.style.display = 'block';
            atualizarInfoLugares();
        }

        // Função para atualizar informações sobre lugares selecionados
        function atualizarInfoLugares() {
            const quantidadeDesejada = parseInt(quantidadeInput.value);

            if (lugaresSelecionados.length === 0) {
                lugaresInfo.textContent = `Selecione ${quantidadeDesejada} lugar(es) no diagrama acima.`;
                lugaresInfo.style.color = '#6c757d';
            } else if (lugaresSelecionados.length < quantidadeDesejada) {
                const faltam = quantidadeDesejada - lugaresSelecionados.length;
                lugaresInfo.textContent = `Lugares selecionados: ${lugaresSelecionados.join(', ')}. Faltam selecionar ${faltam} lugar(es).`;
                lugaresInfo.style.color = 'orange';
            } else if (lugaresSelecionados.length === quantidadeDesejada) {
                lugaresInfo.textContent = `Lugares selecionados: ${lugaresSelecionados.join(', ')}`;
                lugaresInfo.style.color = 'green';
            } else {
                lugaresInfo.textContent = `Você selecionou ${lugaresSelecionados.length} lugares, mas só precisa de ${quantidadeDesejada}.`;
                lugaresInfo.style.color = 'red';
            }
        }

        // Função para atualizar quantidade máxima
        function atualizarQuantidadeMaxima(totalLugaresDisponiveis) {
            quantidadeInput.max = totalLugaresDisponiveis;
            quantidadeInput.value = Math.min(quantidadeInput.value, totalLugaresDisponiveis);
            quantidadeDisponivel.textContent = `Lugares disponíveis: ${totalLugaresDisponiveis}`;

            if (totalLugaresDisponiveis === 0) {
                btnComprar.style.display = 'none';
                quantidadeDisponivel.style.color = 'red';
            } else {
                quantidadeDisponivel.style.color = 'green';
                // Não mostrar o botão até que os lugares sejam selecionados
                btnComprar.style.display = 'none';
            }

            validarQuantidade();
        }

        // Função para validar a quantidade
        function validarQuantidade() {
            const selectedIndex = horarioSelect.options[horarioSelect.selectedIndex].dataset.index;
            if (selectedIndex !== undefined && horariosData[selectedIndex]) {
                const horario = horariosData[selectedIndex];
                const totalLugaresDisponiveis = horario.lugares_disponiveis.length;
                const quantidade = parseInt(quantidadeInput.value);

                if (quantidade > totalLugaresDisponiveis) {
                    quantidadeInput.value = totalLugaresDisponiveis;
                    quantidadeDisponivel.textContent = `Quantidade ajustada para o máximo disponível: ${totalLugaresDisponiveis}`;
                    quantidadeDisponivel.style.color = 'orange';
                    return false;
                } else if (quantidade < 1) {
                    quantidadeInput.value = 1;
                    quantidadeDisponivel.style.color = 'red';
                    return false;
                } else {
                    quantidadeDisponivel.textContent = `Lugares disponíveis: ${totalLugaresDisponiveis}`;
                    quantidadeDisponivel.style.color = 'green';
                    return true;
                }
            }
            return false;
        }

        // Quando a quantidade é alterada, atualizar a seleção de lugares
        quantidadeInput.addEventListener('input', function() {
            validarQuantidade();

            // Se já há lugares selecionados e a quantidade mudou
            if (lugaresSelecionados.length > 0) {
                const novaQuantidade = parseInt(this.value);

                if (lugaresSelecionados.length !== novaQuantidade) {
                    // Resetar lugares selecionados
                    lugaresSelecionados = [];
                    lugaresEscolhidos.value = '';

                    // Renderizar novamente a grade de lugares
                    const selectedIndex = horarioSelect.options[horarioSelect.selectedIndex].dataset.index;
                    if (selectedIndex !== undefined && horariosData[selectedIndex]) {
                        renderizarLugares(horariosData[selectedIndex].lugares_disponiveis);
                    }

                    // Esconder o botão de compra até que os lugares sejam selecionados
                    btnComprar.style.display = 'none';
                }
            }

            // Atualizar informações sobre lugares selecionados
            atualizarInfoLugares();
        });

        // Validar formulário antes de submeter
        const formCompra = document.getElementById('comprarBilheteForm');
        formCompra.addEventListener('submit', function(e) {
            const quantidade = parseInt(quantidadeInput.value);

            if (!validarQuantidade()) {
                e.preventDefault();
                alert('Por favor, selecione uma quantidade válida de bilhetes.');
                return;
            }

            // Verificar se o número de lugares selecionados corresponde à quantidade de bilhetes
            if (lugaresSelecionados.length !== quantidade) {
                e.preventDefault();
                alert(`Por favor, selecione exatamente ${quantidade} lugar(es).`);
                return;
            }

            // Verificar se os lugares foram selecionados
            if (lugaresSelecionados.length === 0) {
                e.preventDefault();
                alert('Por favor, selecione os lugares para os bilhetes.');
                return;
            }
        });
    });
    </script>

    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>
