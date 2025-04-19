<?php
session_start();
include '../basedados/basedados.h';

// Adicione isso no início do arquivo, logo após o session_start()
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se o usuário é funcionário ou administrador
if (!isset($_SESSION["id_nivel"]) || ($_SESSION["id_nivel"] != 1 && $_SESSION["id_nivel"] != 2)) {
    header("Location: erro.php");
    exit();
}

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
    $id_rota = $_POST['rota'];
    list($hora_viagem, $data_viagem) = explode('|', $_POST['horario']);
    $quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
    $lugares = isset($_POST['lugares']) ? $_POST['lugares'] : '';

    // Converter a data do formato dd/mm/yyyy para yyyy-mm-dd
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data_viagem)));

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

            // Verificar se há lugares suficientes disponíveis
            $sql_lugares = "SELECT lugares_disponiveis FROM horarios
                           WHERE id_rota = $id_rota
                           AND data_viagem = '$data_formatada'
                           AND horario_partida = '$hora_viagem'";
            $result_lugares = mysqli_query($conn, $sql_lugares);

            if (mysqli_num_rows($result_lugares) == 0) {
                $mensagem = "Horário não encontrado.";
                $tipo_mensagem = "danger";
            } else {
                $resultado_lugares = mysqli_fetch_assoc($result_lugares);
                $lugares_disponiveis = $resultado_lugares['lugares_disponiveis'];

                if ($lugares_disponiveis < $quantidade) {
                    $mensagem = "Não há lugares suficientes disponíveis. Disponíveis: $lugares_disponiveis, Solicitados: $quantidade.";
                    $tipo_mensagem = "danger";
                } else {
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

                    // Calcular o preço total com base na quantidade
                    $preco_total = $preco * $quantidade;

                    if ($saldo < $preco_total) {
                        $mensagem = "Saldo insuficiente. O cliente precisa de €" . number_format($preco_total, 2, ',', '.') .
                                   " para comprar $quantidade bilhete(s), mas tem apenas €" . number_format($saldo, 2, ',', '.') . ".";
                        $tipo_mensagem = "danger";
                    } else {
                        // Iniciar transação para garantir integridade dos dados
                        mysqli_begin_transaction($conn);

                        try {
                            // 1. Atualizar saldo do cliente
                            $sql_update_cliente = "UPDATE carteiras SET saldo = saldo - $preco_total WHERE id_cliente = $id_cliente";
                            if (!mysqli_query($conn, $sql_update_cliente)) {
                                throw new Exception("Erro ao atualizar saldo do cliente: " . mysqli_error($conn));
                            }

                            // 2. Atualizar saldo da FelixBus
                            $sql_update_felixbus = "UPDATE carteira_felixbus SET saldo = saldo + $preco_total WHERE id = $id_carteira_felixbus";
                            if (!mysqli_query($conn, $sql_update_felixbus)) {
                                throw new Exception("Erro ao atualizar saldo da FelixBus: " . mysqli_error($conn));
                            }

                            // 3. Registrar a transação
                            $descricao = "Compra de $quantidade bilhete(s): $origem para $destino (Cliente: {$cliente['nome']})";
                            $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                            VALUES ($id_cliente, $id_carteira_felixbus, $preco_total, 'compra', '$descricao')";
                            if (!mysqli_query($conn, $sql_transacao)) {
                                throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
                            }

                            // 4. Atualizar lugares disponíveis
                            $sql_update_lugares = "UPDATE horarios SET lugares_disponiveis = lugares_disponiveis - $quantidade
                                                  WHERE id_rota = $id_rota
                                                  AND data_viagem = '$data_formatada'
                                                  AND horario_partida = '$hora_viagem'";
                            if (!mysqli_query($conn, $sql_update_lugares)) {
                                throw new Exception("Erro ao atualizar lugares disponíveis: " . mysqli_error($conn));
                            }

                            // 5. Criar os bilhetes com os lugares selecionados
                            if (!empty($lugares)) {
                                $lugares_array = explode(',', $lugares);

                                // Verificar se a quantidade de lugares selecionados corresponde à quantidade de bilhetes
                                if (count($lugares_array) != $quantidade) {
                                    throw new Exception("A quantidade de lugares selecionados não corresponde à quantidade de bilhetes.");
                                }

                                // Verificar se os lugares já estão ocupados
                                $lugares_str = implode(',', $lugares_array);
                                $sql_check_lugares = "SELECT numero_lugar FROM bilhetes
                                                     WHERE id_rota = $id_rota
                                                     AND data_viagem = '$data_formatada'
                                                     AND hora_viagem = '$hora_viagem'
                                                     AND numero_lugar IN ($lugares_str)";
                                $result_check_lugares = mysqli_query($conn, $sql_check_lugares);

                                if (mysqli_num_rows($result_check_lugares) > 0) {
                                    $lugares_ocupados = [];
                                    while ($row = mysqli_fetch_assoc($result_check_lugares)) {
                                        $lugares_ocupados[] = $row['numero_lugar'];
                                    }
                                    throw new Exception("Os seguintes lugares já estão ocupados: " . implode(', ', $lugares_ocupados));
                                }

                                // Criar os bilhetes com os lugares selecionados
                                foreach ($lugares_array as $lugar) {
                                    $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem, numero_lugar)
                                                   VALUES ($id_cliente, $id_rota, '$data_formatada', '$hora_viagem', $lugar)";
                                    if (!mysqli_query($conn, $sql_bilhete)) {
                                        throw new Exception("Erro ao criar bilhete para o lugar $lugar: " . mysqli_error($conn));
                                    }
                                }
                            } else {
                                // Criar bilhetes sem lugares específicos (comportamento antigo)
                                for ($i = 0; $i < $quantidade; $i++) {
                                    $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem)
                                                   VALUES ($id_cliente, $id_rota, '$data_formatada', '$hora_viagem')";
                                    if (!mysqli_query($conn, $sql_bilhete)) {
                                        throw new Exception("Erro ao criar bilhete: " . mysqli_error($conn));
                                    }
                                }
                            }

                            // Commit da transação
                            mysqli_commit($conn);

                            $mensagem_lugares = !empty($_POST['lugares']) ? " Lugares: " . $_POST['lugares'] : "";
                            $mensagem = "$quantidade bilhete(s) comprado(s) com sucesso para o cliente {$cliente['nome']}! Origem: $origem, Destino: $destino, Data: " .
                                       date('d/m/Y', strtotime($data_formatada)) . ", Hora: $hora_viagem" . $mensagem_lugares . ", Preço total: €" . number_format($preco_total, 2, ',', '.');
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
$sql_rotas = "SELECT r.id as id_rota, r.origem, r.destino, r.preco
             FROM rotas r
             WHERE r.disponivel = 1
             ORDER BY r.origem, r.destino";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Processar eliminação de bilhete
if (isset($_GET['eliminar_bilhete']) && !empty($_GET['eliminar_bilhete'])) {
    $id_bilhete = intval($_GET['eliminar_bilhete']);

    // Verificar se o bilhete existe
    $sql_verificar = "SELECT b.id, b.id_rota, b.data_viagem, b.hora_viagem, b.numero_lugar
                     FROM bilhetes b
                     WHERE b.id = $id_bilhete";
    $result_verificar = mysqli_query($conn, $sql_verificar);

    if (mysqli_num_rows($result_verificar) > 0) {
        $bilhete = mysqli_fetch_assoc($result_verificar);
        $id_rota = $bilhete['id_rota'];
        $data_viagem = $bilhete['data_viagem'];
        $hora_viagem = $bilhete['hora_viagem'];

        // Iniciar transação
        mysqli_begin_transaction($conn);

        try {
            // 1. Atualizar lugares disponíveis na tabela horarios
            $sql_update_lugares = "UPDATE horarios
                                 SET lugares_disponiveis = lugares_disponiveis + 1
                                 WHERE id_rota = $id_rota
                                 AND data_viagem = '$data_viagem'
                                 AND horario_partida = '$hora_viagem'";

            if (!mysqli_query($conn, $sql_update_lugares)) {
                throw new Exception("Erro ao atualizar lugares disponíveis: " . mysqli_error($conn));
            }

            // 2. Eliminar o bilhete
            $sql_eliminar = "DELETE FROM bilhetes WHERE id = $id_bilhete";

            if (!mysqli_query($conn, $sql_eliminar)) {
                throw new Exception("Erro ao eliminar bilhete: " . mysqli_error($conn));
            }

            // Commit da transação
            mysqli_commit($conn);

            $mensagem = "Bilhete com ID $id_bilhete foi eliminado com sucesso e o lugar foi liberado!";
            $tipo_mensagem = "success";

        } catch (Exception $e) {
            // Rollback em caso de erro
            mysqli_rollback($conn);
            $mensagem = $e->getMessage();
            $tipo_mensagem = "danger";
        }
    } else {
        $mensagem = "Bilhete ID $id_bilhete não encontrado.";
        $tipo_mensagem = "danger";
    }
}

// Processar requisição AJAX para buscar lugares ocupados
if (isset($_POST['buscar_lugares_ocupados'])) {
    header('Content-Type: application/json');

    $rota_id = isset($_POST['rota_id']) ? intval($_POST['rota_id']) : 0;
    $data_viagem = isset($_POST['data_viagem']) ? $_POST['data_viagem'] : '';
    $hora_viagem = isset($_POST['hora_viagem']) ? $_POST['hora_viagem'] : '';

    if (!$rota_id || empty($data_viagem) || empty($hora_viagem)) {
        echo json_encode(['error' => 'Parâmetros inválidos']);
        exit();
    }

    // Buscar lugares ocupados
    $sql = "SELECT numero_lugar FROM bilhetes
            WHERE id_rota = $rota_id
            AND data_viagem = '$data_viagem'
            AND hora_viagem = '$hora_viagem'
            AND numero_lugar IS NOT NULL";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['error' => 'Erro na consulta: ' . mysqli_error($conn)]);
        exit();
    }

    $lugares_ocupados = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $lugares_ocupados[] = intval($row['numero_lugar']);
    }

    echo json_encode(['lugares_ocupados' => $lugares_ocupados]);
    exit();
}

// Buscar todos os horários disponíveis
$sql_todos_horarios = "SELECT h.id, h.id_rota, h.data_viagem, h.horario_partida, h.disponivel, h.lugares_disponiveis,
                r.origem, r.destino, r.capacidade
                FROM horarios h
                JOIN rotas r ON h.id_rota = r.id
                WHERE h.disponivel = 1
                ORDER BY h.id_rota, h.data_viagem ASC, h.horario_partida ASC";
$result_todos_horarios = mysqli_query($conn, $sql_todos_horarios);

// Organizar horários por rota
$horarios_por_rota = [];
while ($horario = mysqli_fetch_assoc($result_todos_horarios)) {
    $id_rota = $horario['id_rota'];
    if (!isset($horarios_por_rota[$id_rota])) {
        $horarios_por_rota[$id_rota] = [];
    }
    $horarios_por_rota[$id_rota][] = $horario;
}

// Buscar bilhetes recentes (agrupados)
$sql_bilhetes = "SELECT
                    r.origem,
                    r.destino,
                    b.data_viagem,
                    b.hora_viagem,
                    r.preco,
                    b.data_compra,
                    u.nome as nome_cliente,
                    COUNT(*) as quantidade,
                    GROUP_CONCAT(b.id) as ids,
                    GROUP_CONCAT(b.numero_lugar) as lugares
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                JOIN utilizadores u ON b.id_cliente = u.id
                GROUP BY r.origem, r.destino, b.data_viagem, b.hora_viagem, r.preco, b.data_compra, u.nome
                ORDER BY b.data_compra DESC
                LIMIT 50";
$result_bilhetes = mysqli_query($conn, $sql_bilhetes);


?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_bilhetes.css">
    <title>FelixBus - Gestão de Bilhetes</title>
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
        <h1>Gestão de Bilhetes</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-container">
                <h2>Comprar Bilhete para Cliente</h2>
                <form method="post" action="gerir_bilhetes.php" id="comprarBilheteForm" onsubmit="return validarFormulario()">
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

                    <div class="form-group" id="horarioContainer" style="display: none;">
                        <label for="horario">Data e Horário:</label>
                        <select id="horario" name="horario" required>
                            <option value="">Selecione uma data e horário</option>
                            <?php foreach ($horarios_por_rota as $id_rota => $horarios): ?>
                                <?php foreach ($horarios as $horario): ?>
                                    <?php
                                        $data_formatada = date('d/m/Y', strtotime($horario['data_viagem']));
                                        $hora_formatada = date('H:i', strtotime($horario['horario_partida']));
                                        $horario_partida = date('H:i:s', strtotime($horario['horario_partida']));
                                    ?>
                                    <option value="<?php echo $horario_partida . '|' . $data_formatada; ?>"
                                            data-rota="<?php echo $horario['id_rota']; ?>"
                                            data-data="<?php echo $data_formatada; ?>"
                                            data-hora="<?php echo $hora_formatada; ?>"
                                            data-lugares="<?php echo $horario['lugares_disponiveis']; ?>"
                                            data-capacidade="<?php echo $horario['capacidade']; ?>"
                                            style="display: none;">
                                        <?php echo $data_formatada . ' às ' . $hora_formatada . ' (' . $horario['lugares_disponiveis'] . ' lugares)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="quantidadeContainer" style="display: none;">
                        <label for="quantidade">Quantidade:</label>
                        <input type="number" id="quantidade" name="quantidade" min="1" value="1" required>
                        <small id="quantidadeHelp" style="display: block; margin-top: 5px;">Máximo: <span id="maxQuantidade">0</span> bilhetes disponíveis</small>
                    </div>

                    <div class="form-group" id="lugaresContainer" style="display: none;">
                        <label for="lugares">Selecione os lugares:</label>
                        <div id="lugaresSelector" class="lugares-grid">
                            <!-- Os lugares serão adicionados dinamicamente aqui -->
                        </div>
                        <input type="hidden" id="lugaresEscolhidos" name="lugares" value="">
                        <small id="lugaresInfo" class="lugar-info">Selecione os lugares no diagrama acima.</small>
                    </div>



                    <button type="submit" name="comprar" id="btnComprar" style="display: none;">Comprar Bilhete</button>
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
                                    <th>Quantidade</th>
                                    <th>Lugares</th>
                                    <th>Preço Unit.</th>
                                    <th>Preço Total</th>
                                    <th>Data Compra</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $contador_bilhetes = 1;
                                while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                    <tr>
                                        <td><?php echo $contador_bilhetes++; ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['nome_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['origem'] . ' → ' . $bilhete['destino']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                        <td><?php echo $bilhete['hora_viagem']; ?></td>
                                        <td><?php echo $bilhete['quantidade']; ?></td>
                                        <td><?php echo $bilhete['lugares'] ? htmlspecialchars($bilhete['lugares']) : 'Não especificado'; ?></td>
                                        <td>€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></td>
                                        <td>€<?php echo number_format($bilhete['preco'] * $bilhete['quantidade'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($bilhete['data_compra'])); ?></td>
                                        <td>
                                            <?php
                                            // Extrair os IDs dos bilhetes
                                            $ids = explode(',', $bilhete['ids']);
                                            $contador = 1;
                                            foreach ($ids as $id):
                                                // Usar IDs sequenciais (1, 2, 3, etc.)
                                                $id_sequencial = "ID " . $contador;
                                                $contador++;
                                            ?>
                                                <a href="gerir_bilhetes.php?eliminar_bilhete=<?php echo $id; ?>" class="btn-eliminar" onclick="return confirm('Tem certeza que deseja eliminar o bilhete <?php echo $id_sequencial; ?>?');">Eliminar <?php echo $id_sequencial; ?></a>
                                            <?php endforeach; ?>
                                        </td>
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

     <!-- FOOTER -->
     <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rotaSelect = document.getElementById('rota');
    const horarioContainer = document.getElementById('horarioContainer');
    const horarioSelect = document.getElementById('horario');
    const btnComprar = document.getElementById('btnComprar');

    console.log('Script iniciado');

    // Função para mostrar/esconder horários com base na rota selecionada
    function atualizarHorarios() {
        const rotaId = rotaSelect.value;
        console.log('Rota selecionada:', rotaId);

        // Esconder o botão de compra
        btnComprar.style.display = 'none';

        if (!rotaId) {
            horarioContainer.style.display = 'none';
            return;
        }

        // Mostrar o container de horários
        horarioContainer.style.display = 'block';

        // Esconder todas as opções de horários
        const todasOpcoes = horarioSelect.querySelectorAll('option');
        let temHorarios = false;

        todasOpcoes.forEach(opcao => {
            if (opcao.dataset.rota) {
                // Se a opção tem um data-rota, verificar se corresponde à rota selecionada
                if (opcao.dataset.rota === rotaId) {
                    opcao.style.display = '';
                    temHorarios = true;
                } else {
                    opcao.style.display = 'none';
                }
            }
        });

        // Atualizar a opção padrão
        const opcaoPadrao = horarioSelect.querySelector('option[value=""]');
        if (opcaoPadrao) {
            opcaoPadrao.textContent = temHorarios ? 'Selecione uma data e horário' : 'Nenhum horário disponível';
        }

        // Resetar a seleção
        horarioSelect.value = '';

        // Esconder outros containers
        quantidadeContainer.style.display = 'none';
        lugaresContainer.style.display = 'none';
    }

    // Quando a rota é selecionada
    rotaSelect.addEventListener('change', atualizarHorarios);

    // Referências aos novos elementos
    const quantidadeContainer = document.getElementById('quantidadeContainer');
    const quantidadeInput = document.getElementById('quantidade');
    const maxQuantidade = document.getElementById('maxQuantidade');
    const lugaresContainer = document.getElementById('lugaresContainer');
    const lugaresSelector = document.getElementById('lugaresSelector');
    const lugaresEscolhidos = document.getElementById('lugaresEscolhidos');

    // Array para armazenar os lugares selecionados
    let lugaresSelecionados = [];
    let capacidadeOnibus = 0;
    let lugaresOcupados = [];
    const lugaresInfo = document.getElementById('lugaresInfo');

    // Função para buscar lugares ocupados
    async function buscarLugaresOcupados(rotaId, dataViagem, horaViagem) {
        try {
            // Converter a data do formato dd/mm/yyyy para yyyy-mm-dd para a consulta SQL
            const partes = dataViagem.split('/');
            const dataFormatada = `${partes[2]}-${partes[1]}-${partes[0]}`;

            // Buscar lugares ocupados via PHP
            const formData = new FormData();
            formData.append('buscar_lugares_ocupados', '1');
            formData.append('rota_id', rotaId);
            formData.append('data_viagem', dataFormatada);
            formData.append('hora_viagem', horaViagem);

            const response = await fetch('gerir_bilhetes.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data.lugares_ocupados || [];
        } catch (error) {
            console.error('Erro ao buscar lugares ocupados:', error);
            // Fallback para simulação em caso de erro
            const ocupados = [];
            const numOcupados = Math.floor(Math.random() * 10); // Entre 0 e 9 lugares ocupados

            for (let i = 0; i < numOcupados; i++) {
                const lugar = Math.floor(Math.random() * capacidadeOnibus) + 1;
                if (!ocupados.includes(lugar)) {
                    ocupados.push(lugar);
                }
            }

            return ocupados;
        }
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

    // Função para renderizar a grade de lugares
    function renderizarLugares() {
        lugaresSelector.innerHTML = '';

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

                    // Atualizar informações sobre lugares selecionados (sem mostrar alertas)
                });
            }

            lugaresSelector.appendChild(lugarElement);
        }
    }

    // Quando o horário é selecionado
    horarioSelect.addEventListener('change', async function() {
        const selectedOption = this.options[this.selectedIndex];
        const horarioValue = this.value;

        // Resetar lugares selecionados
        lugaresSelecionados = [];
        lugaresEscolhidos.value = '';

        if (horarioValue) {
            // Obter o número de lugares disponíveis
            const lugares = parseInt(selectedOption.dataset.lugares);

            // Obter a capacidade do ônibus da rota selecionada
            capacidadeOnibus = parseInt(selectedOption.dataset.capacidade);

            // Atualizar o máximo de bilhetes disponíveis
            maxQuantidade.textContent = lugares;
            quantidadeInput.max = lugares;
            quantidadeInput.value = Math.min(1, lugares);

            // Mostrar o container de quantidade
            quantidadeContainer.style.display = 'block';

            // Buscar lugares ocupados
            const rotaId = rotaSelect.value;
            const dataViagem = selectedOption.dataset.data;
            const horaViagem = selectedOption.dataset.hora;
            lugaresOcupados = await buscarLugaresOcupados(rotaId, dataViagem, horaViagem);

            // Renderizar a grade de lugares
            renderizarLugares();

            // Mostrar o container de lugares
            lugaresContainer.style.display = 'block';

            // Atualizar informações sobre lugares selecionados
            atualizarInfoLugares();

            // Esconder o botão de compra até que os lugares sejam selecionados
            btnComprar.style.display = 'none';


        } else {
            // Esconder o botão de compra e as informações
            btnComprar.style.display = 'none';
            quantidadeContainer.style.display = 'none';
            lugaresContainer.style.display = 'none';
        }
    });

    // Validar a quantidade quando for alterada
    quantidadeInput.addEventListener('change', function() {
        const max = parseInt(this.max);
        const value = parseInt(this.value);

        if (value > max) {
            this.value = max;
        } else if (value < 1) {
            this.value = 1;
        }

        // Se já há lugares selecionados e a quantidade mudou
        if (lugaresSelecionados.length > 0) {
            const novaQuantidade = parseInt(this.value);

            if (lugaresSelecionados.length !== novaQuantidade) {
                // Resetar lugares selecionados
                lugaresSelecionados = [];
                lugaresEscolhidos.value = '';

                // Renderizar novamente a grade de lugares
                renderizarLugares();

                // Esconder o botão de compra até que os lugares sejam selecionados
                btnComprar.style.display = 'none';
            }
        }

        // Atualizar informações sobre lugares selecionados
        atualizarInfoLugares();
    });

    // Inicializar
    atualizarHorarios();
});

// Função para validar o formulário antes de enviar
function validarFormulario() {
    const quantidadeInput = document.getElementById('quantidade');
    const lugaresEscolhidos = document.getElementById('lugaresEscolhidos');

    if (!quantidadeInput || !lugaresEscolhidos) {
        return true; // Se os elementos não existirem, permite o envio
    }

    const quantidade = parseInt(quantidadeInput.value);
    const lugares = lugaresEscolhidos.value.split(',').filter(lugar => lugar.trim() !== '');

    // Verificar se a quantidade de lugares selecionados corresponde à quantidade de bilhetes
    if (lugares.length !== quantidade) {
        if (lugares.length === 0) {
            alert('Por favor, selecione os lugares para os bilhetes.');
        } else if (lugares.length < quantidade) {
            alert(`Você selecionou apenas ${lugares.length} lugar(es), mas está comprando ${quantidade} bilhete(s). Por favor, selecione todos os lugares.`);
        } else {
            alert(`Você selecionou ${lugares.length} lugar(es), mas está comprando apenas ${quantidade} bilhete(s). Por favor, ajuste a quantidade ou desmarque alguns lugares.`);
        }
        return false; // Impede o envio do formulário
    }

    return true; // Permite o envio do formulário
}
</script>
