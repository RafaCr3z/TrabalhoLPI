<?php
session_start();
include '../basedados/basedados.h';

// Verifica se o utilizador está autenticado e se é um cliente (nível 3)
// Se não for, redireciona para a página de erro
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}

// Obtém o ID do cliente a partir da sessão
$id_cliente = $_SESSION["id_utilizador"];
// Define o ID da carteira da FelixBus (sistema)
$id_carteira_felixbus = 1;

// Consulta o saldo atual do cliente na base de dados
$sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
$result_saldo = mysqli_query($conn, $sql_saldo);
$row_saldo = mysqli_fetch_assoc($result_saldo);

// Inicializa variáveis para mensagens de feedback
$mensagem = '';
$tipo_mensagem = '';

// Verifica se existem mensagens passadas por URL (após redirecionamentos)
if (isset($_GET['msg']) && isset($_GET['tipo'])) {
    $mensagem = $_GET['msg'];
    $tipo_mensagem = $_GET['tipo'];
}

// Verifica se a coluna 'disponivel' existe na tabela 'horarios'
// Se não existir, adiciona-a (para compatibilidade com versões anteriores)
$check_column = "SHOW COLUMNS FROM horarios LIKE 'disponivel'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    mysqli_query($conn, "ALTER TABLE horarios ADD COLUMN disponivel TINYINT(1) NOT NULL DEFAULT 1");
}

// Verifica se a coluna 'numero_lugar' existe na tabela 'bilhetes'
// Se não existir, adiciona-a (para compatibilidade com versões anteriores)
$check_column = "SHOW COLUMNS FROM bilhetes LIKE 'numero_lugar'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    mysqli_query($conn, "ALTER TABLE bilhetes ADD COLUMN numero_lugar INT");
}

// Processa o formulário de compra de bilhete quando submetido
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
    // Obtém e valida os dados do formulário
    $id_rota = intval($_POST['rota']);
    list($hora_viagem, $data_viagem) = explode('|', $_POST['horario']);
    $quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
    $lugares = isset($_POST['lugares']) ? $_POST['lugares'] : '';

    // Valida se a quantidade de bilhetes é pelo menos 1
    if ($quantidade < 1) {
        $msg = "A quantidade de bilhetes deve ser pelo menos 1.";
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }

    // Valida se foram selecionados lugares
    if (empty($lugares)) {
        $msg = "Por favor, selecione os lugares para os bilhetes.";
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }

    // Converte a string de lugares numa array
    $lugares_array = explode(',', $lugares);

    // Verifica se a quantidade de lugares selecionados corresponde à quantidade de bilhetes
    if (count($lugares_array) != $quantidade) {
        $msg = "A quantidade de lugares selecionados não corresponde à quantidade de bilhetes.";
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }

    // Converte a data do formato dd/mm/yyyy para yyyy-mm-dd (formato SQL)
    $data_formatada = date('Y-m-d', strtotime(str_replace('/', '-', $data_viagem)));

    // Verifica se a rota existe e obtém informações como preço, origem e destino
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

        // Verifica se os lugares selecionados já estão ocupados
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

        // Se encontrar lugares já ocupados, mostra mensagem de erro
        if (mysqli_num_rows($result_check_lugares) > 0) {
            $lugares_ocupados = [];
            while ($row = mysqli_fetch_assoc($result_check_lugares)) {
                $lugares_ocupados[] = $row['numero_lugar'];
            }
            $msg = "Os seguintes lugares já estão ocupados: " . implode(', ', $lugares_ocupados);
            header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
            exit();
        }

        // Calcula o preço total da compra
        $preco_total = $preco * $quantidade;

        // Verifica se o cliente tem saldo suficiente
        if ($row_saldo['saldo'] >= $preco_total) {
            // Inicia uma transação para garantir a integridade dos dados
            mysqli_begin_transaction($conn);

            try {
                // 1. Reduz o saldo do cliente
                $sql_reduzir = "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?";
                $stmt = mysqli_prepare($conn, $sql_reduzir);
                mysqli_stmt_bind_param($stmt, "di", $preco_total, $id_cliente);
                mysqli_stmt_execute($stmt);

                // 2. Aumenta o saldo da FelixBus
                $sql_aumentar = "UPDATE carteira_felixbus SET saldo = saldo + ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql_aumentar);
                mysqli_stmt_bind_param($stmt, "di", $preco_total, $id_carteira_felixbus);
                mysqli_stmt_execute($stmt);

                // 3. Regista a transação no histórico
                $descricao = "Compra de $quantidade bilhete(s): $origem para $destino (Lugares: $lugares)";
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao)
                                VALUES (?, ?, ?, 'compra', ?)";
                $stmt = mysqli_prepare($conn, $sql_transacao);
                mysqli_stmt_bind_param($stmt, "iids", $id_cliente, $id_carteira_felixbus, $preco_total, $descricao);
                mysqli_stmt_execute($stmt);

                // 4. Cria os bilhetes com os lugares selecionados
                foreach ($lugares_array as $lugar) {
                    $sql_bilhete = "INSERT INTO bilhetes (id_cliente, id_rota, data_viagem, hora_viagem, numero_lugar)
                                   VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql_bilhete);
                    mysqli_stmt_bind_param($stmt, "iissi", $id_cliente, $id_rota, $data_formatada, $hora_viagem, $lugar);
                    mysqli_stmt_execute($stmt);
                }

                // 5. Atualiza o número de lugares disponíveis na tabela horarios
                $sql_update_lugares = "UPDATE horarios SET lugares_disponiveis = lugares_disponiveis - ?
                                     WHERE id_rota = ? AND data_viagem = ? AND horario_partida = ?";
                $stmt = mysqli_prepare($conn, $sql_update_lugares);
                mysqli_stmt_bind_param($stmt, "iiss", $quantidade, $id_rota, $data_formatada, $hora_viagem);
                mysqli_stmt_execute($stmt);

                // Confirma todas as operações da transação
                mysqli_commit($conn);

                // Redireciona com mensagem de sucesso
                $msg = "$quantidade bilhete(s) comprado(s) com sucesso para os lugares: $lugares!";
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=success");
                exit();

            } catch (Exception $e) {
                // Em caso de erro, reverte todas as operações
                mysqli_rollback($conn);
                $msg = $e->getMessage();
                header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
                exit();
            }
        } else {
            // Mensagem de erro se o saldo for insuficiente
            $msg = "Saldo insuficiente para comprar $quantidade bilhete(s). Total: €" . number_format($preco_total, 2, ',', '.');
            header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
            exit();
        }
    } else {
        // Mensagem de erro se a rota não for encontrada
        $msg = "Rota não encontrada ou não disponível.";
        header("Location: bilhetes_cliente.php?msg=$msg&tipo=error");
        exit();
    }
}

// Buscar rotas disponíveis
$sql_rotas = "SELECT DISTINCT r.id as id_rota, r.origem, r.destino, r.preco, r.capacidade,
             (SELECT COUNT(*) FROM horarios h WHERE h.id_rota = r.id AND h.disponivel = 1) as total_horarios
             FROM rotas r
             WHERE r.disponivel = 1
             ORDER BY r.origem, r.destino";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar todos os horários disponíveis
$sql_horarios = "SELECT h.id, h.id_rota, h.data_viagem, h.horario_partida, h.lugares_disponiveis,
                h.lugares_disponiveis as capacidade_viagem, r.capacidade
                FROM horarios h
                JOIN rotas r ON h.id_rota = r.id
                WHERE h.disponivel = 1
                ORDER BY h.id_rota, h.data_viagem ASC, h.horario_partida ASC";
$result_horarios = mysqli_query($conn, $sql_horarios);

// Organizar horários por rota
$horarios_por_rota = [];
while ($horario = mysqli_fetch_assoc($result_horarios)) {
    $id_rota = $horario['id_rota'];
    if (!isset($horarios_por_rota[$id_rota])) {
        $horarios_por_rota[$id_rota] = [];
    }

    // Formatar a data e hora para exibição
    $data_formatada = date('d/m/Y', strtotime($horario['data_viagem']));
    $hora_formatada = date('H:i', strtotime($horario['horario_partida']));

    $horarios_por_rota[$id_rota][] = [
        'id' => $horario['id'],
        'data_viagem' => $data_formatada,
        'hora_formatada' => $hora_formatada,
        'horario_partida' => $horario['horario_partida'],
        'lugares_disponiveis' => $horario['lugares_disponiveis'],
        'capacidade' => $horario['capacidade_viagem'] // Usar a capacidade específica da viagem
    ];
}

// Converter os horários para JSON para uso no JavaScript
$horarios_json = json_encode($horarios_por_rota);



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
                                        <?php echo $rota['origem'] . ' → ' . $rota['destino'] . ' - €' . number_format($rota['preco'], 2, ',', '.'); ?>
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
                                            <td><?php echo $bilhete['origem'] . ' → ' . $bilhete['destino']; ?></td>
                                            <td><?php echo $bilhete['nome_cliente']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($bilhete['data_viagem'])); ?></td>
                                            <td><?php echo $bilhete['hora_viagem']; ?></td>
                                            <td><?php echo $bilhete['quantidade']; ?></td>
                                            <td><?php
                                                $lugares = $bilhete['lugares'] ? implode(', ', array_filter(explode(',', $bilhete['lugares']))) : 'Não definido';
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
    // Dados dos horários carregados do PHP
    const horariosRotas = <?php echo $horarios_json; ?>;

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

            // Limpar o select de horários
            horarioSelect.innerHTML = '<option value="">Selecione uma data e horário</option>';
            lugarGroup.style.display = 'none';
            btnComprar.style.display = 'none';

            // Obter os horários para a rota selecionada do banco de dados
            horariosData = horariosRotas[rotaId] || [];

            // Ordenar viagens por data e horário
            if (horariosData.length > 0) {
                horariosData.sort((a, b) => {
                    const dataA = a.data_viagem.split('/').reverse().join('-') + ' ' + a.hora_formatada;
                    const dataB = b.data_viagem.split('/').reverse().join('-') + ' ' + b.hora_formatada;
                    return new Date(dataA) - new Date(dataB);
                });
            }

            // Adicionar opções ao select
            horariosData.forEach((horario, index) => {
                const option = document.createElement('option');
                option.value = `${horario.hora_formatada}|${horario.data_viagem}`;
                option.textContent = `${horario.data_viagem} às ${horario.hora_formatada} (Capacidade: ${horario.capacidade})`;
                option.dataset.index = index;
                horarioSelect.appendChild(option);
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

                // Obter o número de lugares disponíveis
                const lugaresDisponiveisTotal = parseInt(horario.lugares_disponiveis);

                // Obter a capacidade do ônibus da viagem selecionada
                capacidadeOnibus = parseInt(horario.capacidade);

                // Atualizar o máximo de bilhetes disponíveis
                quantidadeInput.max = lugaresDisponiveisTotal;
                quantidadeInput.value = Math.min(quantidadeInput.value, lugaresDisponiveisTotal);
                quantidadeDisponivel.textContent = `Lugares disponíveis: ${lugaresDisponiveisTotal} de ${capacidadeOnibus}`;

                // Calcular lugares ocupados
                lugaresOcupados = [];
                for (let i = 1; i <= capacidadeOnibus; i++) {
                    // Se o número do lugar é maior que o total de lugares disponíveis,
                    // significa que está ocupado
                    if (i > lugaresDisponiveisTotal) {
                        lugaresOcupados.push(i);
                    }
                }

                // Criar array de lugares disponíveis
                const lugaresDisponiveis = [];
                for (let i = 1; i <= lugaresDisponiveisTotal; i++) {
                    lugaresDisponiveis.push(i);
                }

                renderizarLugares(lugaresDisponiveis);
                atualizarCorDisponibilidade(lugaresDisponiveisTotal);
            } else {
                // Caso não encontre o horário no cache (não deve acontecer)
                lugaresSelector.innerHTML = '<div class="error">Erro ao carregar lugares. Por favor, selecione o horário novamente.</div>';
                lugarGroup.style.display = 'block';
                btnComprar.style.display = 'none';
            }
        });

        // Função para renderizar a grade de lugares
        function renderizarLugares(lugaresDisponiveis) {
            lugaresSelector.innerHTML = '';

            if (!lugaresDisponiveis || lugaresDisponiveis.length === 0) {
                lugaresSelector.innerHTML = '<div class="empty-state">Nenhum lugar disponível</div>';
                return;
            }

            // Mostrar o container de lugares
            lugarGroup.style.display = 'block';

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

        // Atualizar a cor do texto de acordo com a disponibilidade
        function atualizarCorDisponibilidade(totalLugaresDisponiveis) {
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
            const max = parseInt(quantidadeInput.max);
            const value = parseInt(quantidadeInput.value);

            if (value > max) {
                quantidadeInput.value = max;
                return false;
            } else if (value < 1) {
                quantidadeInput.value = 1;
                return false;
            }

            return true;
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
