<?php
session_start();
include '../basedados/basedados.h';

// Verificar permissões
if (!isset($_SESSION["id_nivel"]) || ($_SESSION["id_nivel"] != 1 && $_SESSION["id_nivel"] != 2)) {
    header("Location: erro.php");
    exit();
}

// Obter ID da carteira FelixBus
$sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
$result_felixbus = mysqli_query($conn, $sql_felixbus);
$id_carteira_felixbus = mysqli_fetch_assoc($result_felixbus)['id'];

$mensagem = '';
$tipo_mensagem = '';

// Processar compra de bilhete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    $id_cliente = $_POST['id_cliente'];
    $id_rota = $_POST['rota'];
    list($hora_viagem, $data_viagem) = explode('|', $_POST['horario']);
    $quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
    $lugares = isset($_POST['lugares']) ? $_POST['lugares'] : '';
    
    // Converter data para formato SQL
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data_viagem)));
    
    // Verificar cliente
    $sql_check_cliente = "SELECT u.id, u.nome, u.tipo_perfil FROM utilizadores u WHERE u.id = ? AND u.tipo_perfil = 3";
    $stmt = mysqli_prepare($conn, $sql_check_cliente);
    mysqli_stmt_bind_param($stmt, "i", $id_cliente);
    mysqli_stmt_execute($stmt);
    $result_check_cliente = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result_check_cliente) > 0) {
        $cliente = mysqli_fetch_assoc($result_check_cliente);
        
        // Verificar rota
        $sql_check_rota = "SELECT r.id, r.origem, r.destino, r.preco, r.capacidade FROM rotas r WHERE r.id = ? AND r.disponivel = 1";
        $stmt = mysqli_prepare($conn, $sql_check_rota);
        mysqli_stmt_bind_param($stmt, "i", $id_rota);
        mysqli_stmt_execute($stmt);
        $result_check_rota = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result_check_rota) > 0) {
            $rota = mysqli_fetch_assoc($result_check_rota);
            $origem = $rota['origem'];
            $destino = $rota['destino'];
            $preco = $rota['preco'];
            
            // Verificar saldo
            $sql_check_saldo = "SELECT c.saldo FROM carteiras c WHERE c.id_cliente = ?";
            $stmt = mysqli_prepare($conn, $sql_check_saldo);
            mysqli_stmt_bind_param($stmt, "i", $id_cliente);
            mysqli_stmt_execute($stmt);
            $result_check_saldo = mysqli_stmt_get_result($stmt);
            $saldo = mysqli_fetch_assoc($result_check_saldo)['saldo'];
            
            // Verificar lugares
            $lugares_array = explode(',', $lugares);
            if (count($lugares_array) != $quantidade) {
                $mensagem = "A quantidade de lugares selecionados não corresponde à quantidade de bilhetes.";
                $tipo_mensagem = "danger";
            } else {
                // Verificar disponibilidade
                $sql_check_lugares = "SELECT b.numero_lugar FROM bilhetes b 
                                    WHERE b.id_rota = ? AND b.data_viagem = ? AND b.hora_viagem = ? 
                                    AND b.numero_lugar IN (" . implode(',', array_fill(0, count($lugares_array), '?')) . ")";
                
                $stmt = mysqli_prepare($conn, $sql_check_lugares);
                $params = array_merge([$id_rota, $data_formatada, $hora_viagem], $lugares_array);
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                $result_check_lugares = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result_check_lugares) > 0) {
                    $lugares_ocupados = [];
                    while ($row = mysqli_fetch_assoc($result_check_lugares)) {
                        $lugares_ocupados[] = $row['numero_lugar'];
                    }
                    $mensagem = "Os seguintes lugares já estão ocupados: " . implode(', ', $lugares_ocupados);
                    $tipo_mensagem = "danger";
                } else {
                    $preco_total = $preco * $quantidade;
                    
                    if ($saldo < $preco_total) {
                        $mensagem = "Saldo insuficiente. O cliente precisa de €" . number_format($preco_total, 2, ',', '.') . 
                                   " para comprar $quantidade bilhete(s), mas tem apenas €" . number_format($saldo, 2, ',', '.') . ".";
                        $tipo_mensagem = "danger";
                    } else {
                        mysqli_begin_transaction($conn);
                        
                        try {
                            // 1. Atualizar saldo do cliente
                            $sql_update_saldo = "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?";
                            $stmt = mysqli_prepare($conn, $sql_update_saldo);
                            mysqli_stmt_bind_param($stmt, "di", $preco_total, $id_cliente);
                            mysqli_stmt_execute($stmt);
                            
                            // 2. Atualizar saldo da FelixBus
                            $sql_update_felixbus = "UPDATE carteira_felixbus SET saldo = saldo + ? WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $sql_update_felixbus);
                            mysqli_stmt_bind_param($stmt, "di", $preco_total, $id_carteira_felixbus);
                            mysqli_stmt_execute($stmt);
                            
                            // 3. Registrar transação
                            $descricao = "Compra de $quantidade bilhete(s): $origem para $destino (Cliente: {$cliente['nome']})";
                            $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                            VALUES (?, ?, ?, 'compra', ?)";
                            $stmt = mysqli_prepare($conn, $sql_transacao);
                            mysqli_stmt_bind_param($stmt, "iids", $id_cliente, $id_carteira_felixbus, $preco_total, $descricao);
                            mysqli_stmt_execute($stmt);
                            
                            // 4. Criar bilhetes
                            foreach ($lugares_array as $lugar) {
                                $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem, data_compra, numero_lugar)
                                              VALUES (?, ?, ?, ?, NOW(), ?)";
                                $stmt = mysqli_prepare($conn, $sql_bilhete);
                                mysqli_stmt_bind_param($stmt, "iissi", $id_cliente, $id_rota, $data_formatada, $hora_viagem, $lugar);
                                mysqli_stmt_execute($stmt);
                            }
                            
                            // 5. Atualizar lugares disponíveis
                            $sql_update_lugares = "UPDATE horarios SET lugares_disponiveis = lugares_disponiveis - ?
                                                 WHERE id_rota = ? AND data_viagem = ? AND horario_partida = ?";
                            $stmt = mysqli_prepare($conn, $sql_update_lugares);
                            mysqli_stmt_bind_param($stmt, "iiss", $quantidade, $id_rota, $data_formatada, $hora_viagem);
                            mysqli_stmt_execute($stmt);
                            
                            mysqli_commit($conn);
                            
                            $mensagem = "$quantidade bilhete(s) comprado(s) com sucesso para o cliente {$cliente['nome']}! Origem: $origem, Destino: $destino, Data: " .
                                       date('d/m/Y', strtotime($data_formatada)) . ", Hora: $hora_viagem, Lugares: $lugares, Preço total: €" . number_format($preco_total, 2, ',', '.');
                            $tipo_mensagem = "success";
                            
                        } catch (Exception $e) {
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

// Processar eliminação de bilhete
if (isset($_GET['eliminar_bilhete']) && !empty($_GET['eliminar_bilhete'])) {
    $id_bilhete = intval($_GET['eliminar_bilhete']);
    
    $sql_verificar = "SELECT b.id, b.id_rota, b.data_viagem, b.hora_viagem, b.numero_lugar
                     FROM bilhetes b WHERE b.id = ?";
    $stmt = mysqli_prepare($conn, $sql_verificar);
    mysqli_stmt_bind_param($stmt, "i", $id_bilhete);
    mysqli_stmt_execute($stmt);
    $result_verificar = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result_verificar) > 0) {
        $bilhete = mysqli_fetch_assoc($result_verificar);
        
        mysqli_begin_transaction($conn);
        
        try {
            // Atualizar lugares disponíveis
            $sql_update_lugares = "UPDATE horarios SET lugares_disponiveis = lugares_disponiveis + 1
                                 WHERE id_rota = ? AND data_viagem = ? AND horario_partida = ?";
            $stmt = mysqli_prepare($conn, $sql_update_lugares);
            mysqli_stmt_bind_param($stmt, "iss", $bilhete['id_rota'], $bilhete['data_viagem'], $bilhete['hora_viagem']);
            mysqli_stmt_execute($stmt);
            
            // Eliminar bilhete
            $sql_eliminar = "DELETE FROM bilhetes WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql_eliminar);
            mysqli_stmt_bind_param($stmt, "i", $id_bilhete);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($conn);
            
            $mensagem = "Bilhete com ID $id_bilhete foi eliminado com sucesso e o lugar foi libertado!";
            $tipo_mensagem = "success";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $mensagem = "Erro ao eliminar bilhete: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}

// Buscar clientes
$sql_clientes = "SELECT u.id, u.nome, u.email, c.saldo
                FROM utilizadores u
                LEFT JOIN carteiras c ON u.id = c.id_cliente
                WHERE u.tipo_perfil = 3
                ORDER BY u.nome";
$result_clientes = mysqli_query($conn, $sql_clientes);

// Buscar rotas disponíveis
$sql_rotas = "SELECT r.id, r.origem, r.destino, r.preco, r.capacidade
             FROM rotas r
             WHERE r.disponivel = 1
             ORDER BY r.origem, r.destino";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar horários disponíveis
$horarios_por_rota = [];
$sql_horarios = "SELECT h.id_rota, h.data_viagem, h.horario_partida, h.lugares_disponiveis, r.capacidade
                FROM horarios h
                JOIN rotas r ON h.id_rota = r.id
                WHERE h.disponivel = 1 AND h.lugares_disponiveis > 0
                ORDER BY h.data_viagem, h.horario_partida";
$result_horarios = mysqli_query($conn, $sql_horarios);

while ($horario = mysqli_fetch_assoc($result_horarios)) {
    if (!isset($horarios_por_rota[$horario['id_rota']])) {
        $horarios_por_rota[$horario['id_rota']] = [];
    }
    $horarios_por_rota[$horario['id_rota']][] = $horario;
}

// Buscar bilhetes recentes ordenados pela data de compra mais recente
$sql_bilhetes = "SELECT
                    r.origem,
                    r.destino,
                    b.data_viagem,
                    b.hora_viagem,
                    r.preco,
                    b.data_compra,
                    u.nome as nome_cliente,
                    GROUP_CONCAT(b.id) as ids,
                    GROUP_CONCAT(b.numero_lugar) as lugares,
                    COUNT(*) as quantidade
                FROM bilhetes b
                JOIN rotas r ON b.id_rota = r.id
                JOIN utilizadores u ON b.id_cliente = u.id
                GROUP BY r.origem, r.destino, b.data_viagem, b.hora_viagem, r.preco, b.data_compra, u.nome
                ORDER BY b.data_compra DESC, b.id DESC
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
            <div class="alert alert-<?php echo htmlspecialchars($tipo_mensagem, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
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
                                <option value="<?php echo htmlspecialchars($cliente['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8') . ') - Saldo: €' . number_format($cliente['saldo'] ?? 0, 2, ',', '.'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rota">Rota:</label>
                        <select id="rota" name="rota" required>
                            <option value="">Selecione uma rota</option>
                            <?php while ($rota = mysqli_fetch_assoc($result_rotas)): ?>
                                <option value="<?php echo $rota['id']; ?>" data-preco="<?php echo $rota['preco']; ?>" data-capacidade="<?php echo $rota['capacidade']; ?>">
                                    <?php echo $rota['origem'] . ' → ' . $rota['destino'] . ' (€' . number_format($rota['preco'], 2, ',', '.') . ')'; ?>
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
                                // Inicializar contador para mostrar IDs sequenciais na tabela
                                $contador_bilhetes = 1;
                                // Percorrer todos os bilhetes retornados pela consulta
                                while ($bilhete = mysqli_fetch_assoc($result_bilhetes)): ?>
                                    <tr>
                                        <td><?php echo $contador_bilhetes++; ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['nome_cliente'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['origem'], ENT_QUOTES, 'UTF-8') . ' → ' . htmlspecialchars($bilhete['destino'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['hora_viagem'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($bilhete['quantidade'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $bilhete['lugares'] ? htmlspecialchars($bilhete['lugares'], ENT_QUOTES, 'UTF-8') : 'Não especificado'; ?></td>
                                        <td>€<?php echo number_format($bilhete['preco'], 2, ',', '.'); ?></td>
                                        <td>€<?php echo number_format($bilhete['preco'] * $bilhete['quantidade'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($bilhete['data_compra'])); ?></td>
                                        <td>
                                            <?php
                                            // Extrair os IDs dos bilhetes do grupo
                                            $ids = explode(',', $bilhete['ids']);
                                            $contador = 1;
                                            // Para cada bilhete no grupo, mostrar um botão de eliminação
                                            foreach ($ids as $id):
                                                // Usar IDs sequenciais (1, 2, 3, etc.) para melhor apresentação
                                                $id_sequencial = "ID " . $contador;
                                                $contador++;
                                            ?>
                                                <a href="gerir_bilhetes.php?eliminar_bilhete=<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" class="btn-eliminar" onclick="return confirm('Tem certeza que deseja eliminar o bilhete <?php echo htmlspecialchars($id_sequencial, ENT_QUOTES, 'UTF-8'); ?>?');">Eliminar <?php echo htmlspecialchars($id_sequencial, ENT_QUOTES, 'UTF-8'); ?></a>
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
// Dados dos horários já estão carregados do PHP no HTML
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

        // Coletar viagens disponíveis para esta rota
        const viagensDisponiveis = [];

        todasOpcoes.forEach(opcao => {
            if (opcao.dataset.rota) {
                // Se a opção tem um data-rota, verificar se corresponde à rota selecionada
                if (opcao.dataset.rota === rotaId) {
                    opcao.style.display = '';
                    temHorarios = true;

                    // Adicionar às viagens disponíveis
                    viagensDisponiveis.push({
                        data: opcao.dataset.data,
                        hora: opcao.dataset.hora,
                        lugares: opcao.dataset.lugares,
                        capacidade: opcao.dataset.capacidade
                    });
                } else {
                    opcao.style.display = 'none';
                }
            }
        });

        // Remover duplicatas (pode acontecer em alguns casos)
        if (viagensDisponiveis.length > 0) {
            const viagensUnicas = [];
            const viagensMap = new Map();

            viagensDisponiveis.forEach(viagem => {
                const chave = `${viagem.data}-${viagem.hora}`;
                if (!viagensMap.has(chave)) {
                    viagensMap.set(chave, true);
                    viagensUnicas.push(viagem);
                }
            });

            // Ordenar viagens por data e horário
            viagensUnicas.sort((a, b) => {
                const dataA = a.data.split('/').reverse().join('-') + ' ' + a.hora;
                const dataB = b.data.split('/').reverse().join('-') + ' ' + b.hora;
                return new Date(dataA) - new Date(dataB);
            });
        }

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

    // Função para simular lugares ocupados
    function buscarLugaresOcupados() {
        // Simular lugares ocupados aleatoriamente
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
    horarioSelect.addEventListener('change', function() {
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
            const capacidadeMaxima = parseInt(selectedOption.dataset.capacidade);
            maxQuantidade.textContent = `${lugares} de ${capacidadeMaxima}`;
            quantidadeInput.max = lugares;
            quantidadeInput.value = Math.min(1, lugares);

            // Mostrar o container de quantidade
            quantidadeContainer.style.display = 'block';

            // Buscar lugares ocupados (simulado)
            lugaresOcupados = buscarLugaresOcupados();

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
    // Obter referências aos elementos do formulário
    const quantidadeInput = document.getElementById('quantidade');
    const lugaresEscolhidos = document.getElementById('lugaresEscolhidos');

    // Se os elementos não existirem, permite o envio do formulário
    if (!quantidadeInput || !lugaresEscolhidos) {
        return true;
    }

    // Obter a quantidade de bilhetes e os lugares selecionados
    const quantidade = parseInt(quantidadeInput.value);
    const lugares = lugaresEscolhidos.value.split(',').filter(lugar => lugar.trim() !== '');

    // Verificar se a quantidade de lugares selecionados corresponde à quantidade de bilhetes
    if (lugares.length !== quantidade) {
        // Se nenhum lugar foi selecionado
        if (lugares.length === 0) {
            alert('Por favor, selecione os lugares para os bilhetes.');
        } 
        // Se foram selecionados menos lugares que a quantidade de bilhetes
        else if (lugares.length < quantidade) {
            alert(`Você selecionou apenas ${lugares.length} lugar(es), mas está comprando ${quantidade} bilhete(s). Por favor, selecione todos os lugares.`);
        } 
        // Se foram selecionados mais lugares que a quantidade de bilhetes
        else {
            alert(`Você selecionou ${lugares.length} lugar(es), mas está comprando apenas ${quantidade} bilhete(s). Por favor, ajuste a quantidade ou desmarque alguns lugares.`);
        }
        return false; // Impede o envio do formulário
    }

    return true; // Permite o envio do formulário se tudo estiver correto
}
</script>
