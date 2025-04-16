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
    $numero_lugar = isset($_POST['lugar']) ? intval($_POST['lugar']) : null;

    // Converter a data do formato dd/mm/yyyy para yyyy-mm-dd (formato do MySQL)
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data_viagem)));

    // Verificar se a rota existe e obter o preço
    $sql_rota = "SELECT r.preco, r.origem, r.destino, r.capacidade
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
        $capacidade = $rota['capacidade'];

        // Verificar se o lugar está disponível
        if ($numero_lugar !== null) {
            $sql_verificar_lugar = "SELECT id FROM bilhetes
                                  WHERE id_rota = ?
                                  AND data_viagem = ?
                                  AND hora_viagem = ?
                                  AND numero_lugar = ?";
            $stmt = mysqli_prepare($conn, $sql_verificar_lugar);
            mysqli_stmt_bind_param($stmt, "issi", $id_rota, $data_formatada, $hora_viagem, $numero_lugar);
            mysqli_stmt_execute($stmt);
            $result_lugar = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result_lugar) > 0) {
                $msg = urlencode("O lugar selecionado já está ocupado. Por favor, escolha outro lugar.");
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
                exit();
            }

            // Verificar se o número do lugar é válido
            if ($numero_lugar < 1 || $numero_lugar > $capacidade) {
                $msg = urlencode("Número de lugar inválido.");
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
                exit();
            }
        }

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
                $descricao = "Compra de bilhete: $origem para $destino" . ($numero_lugar ? " (Lugar: $numero_lugar)" : "");
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                VALUES (?, ?, ?, 'compra', ?)";
                $stmt = mysqli_prepare($conn, $sql_transacao);
                mysqli_stmt_bind_param($stmt, "iids", $id_cliente, $id_carteira_felixbus, $preco, $descricao);
                mysqli_stmt_execute($stmt);

                // 4. Criar o bilhete
                if ($numero_lugar !== null) {
                    $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem, numero_lugar)
                                   VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql_bilhete);
                    mysqli_stmt_bind_param($stmt, "iissi", $id_cliente, $id_rota, $data_formatada, $hora_viagem, $numero_lugar);
                } else {
                    $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem)
                                   VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql_bilhete);
                    mysqli_stmt_bind_param($stmt, "iiss", $id_cliente, $id_rota, $data_formatada, $hora_viagem);
                }
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);

                // Redirecionar para evitar reenvio do formulário ao atualizar a página
                $msg = urlencode("Bilhete comprado com sucesso!" . ($numero_lugar ? " Lugar reservado: $numero_lugar" : ""));
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
$sql_bilhetes = "SELECT b.id, r.origem, r.destino, b.data_viagem, b.hora_viagem, r.preco, b.data_compra, u.nome as nome_cliente, b.numero_lugar
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                JOIN utilizadores u ON b.id_cliente = u.id
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

                        <div class="form-group" id="lugarGroup" style="display: none;">
                            <label for="lugar">Escolha o Lugar:</label>
                            <select id="lugar" name="lugar" required>
                                <option value="">Selecione um horário primeiro</option>
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
                        <div class="table-responsive">
                            <table class="bilhetes-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Rota</th>
                                        <th>Cliente</th>
                                        <th>Data</th>
                                        <th>Hora</th>
                                        <th>Lugar</th>
                                        <th>Preço</th>
                                        <th>Data de Compra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                        <tr>
                                            <td><?php echo $bilhete['id']; ?></td>
                                            <td><?php echo htmlspecialchars($bilhete['origem'] . ' → ' . $bilhete['destino']); ?></td>
                                            <td><?php echo htmlspecialchars($bilhete['nome_cliente']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                            <td><?php echo $bilhete['hora_viagem']; ?></td>
                                            <td><?php echo $bilhete['numero_lugar'] ? $bilhete['numero_lugar'] : 'Não definido'; ?></td>
                                            <td class="preco">€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></td>
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
        const lugarSelect = document.getElementById('lugar');
        const lugarGroup = document.getElementById('lugarGroup');
        const btnComprar = document.getElementById('btnComprar');

        // Armazenar os dados dos horários para uso posterior
        let horariosData = [];

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

            if (!horarioValue) {
                lugarGroup.style.display = 'none';
                btnComprar.style.display = 'none';
                return;
            }

            // Se temos os dados do horário em cache
            if (selectedIndex !== undefined && horariosData[selectedIndex]) {
                const horario = horariosData[selectedIndex];
                carregarLugares(horario.lugares_disponiveis);
                btnComprar.style.display = 'block';
            } else {
                // Caso contrário, buscar os lugares disponíveis do servidor
                const [hora, data] = horarioValue.split('|');
                const rotaId = rotaSelect.value;

                lugarSelect.innerHTML = '<option value="">Carregando lugares...</option>';
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
                        carregarLugares(data.lugares_disponiveis);
                        btnComprar.style.display = 'block';
                    } else {
                        lugarSelect.innerHTML = '<option value="" disabled>Nenhum lugar disponível</option>';
                        btnComprar.style.display = 'none';
                    }
                })
                .catch(error => {
                    lugarSelect.innerHTML = `<option value="">Erro ao carregar lugares</option>`;
                    btnComprar.style.display = 'none';
                });
            }
        });

        // Função para carregar os lugares disponíveis no select
        function carregarLugares(lugares) {
            lugarSelect.innerHTML = '<option value="">Selecione um lugar</option>';

            if (!lugares || lugares.length === 0) {
                lugarSelect.innerHTML = '<option value="" disabled>Nenhum lugar disponível</option>';
                return;
            }

            // Ordenar lugares numericamente
            lugares.sort((a, b) => a - b);

            lugares.forEach(lugar => {
                const option = document.createElement('option');
                option.value = lugar;
                option.textContent = `Lugar ${lugar}`;
                lugarSelect.appendChild(option);
            });

            lugarGroup.style.display = 'block';
        }
    });
    </script>

    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>