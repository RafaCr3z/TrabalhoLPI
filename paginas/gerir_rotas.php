<?php
session_start();
include '../basedados/basedados.h';

// Verificar se o usuário é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';

// Adicionar nova rota
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar_rota'])) {
    $origem = mysqli_real_escape_string($conn, $_POST['origem']);
    $destino = mysqli_real_escape_string($conn, $_POST['destino']);
    $preco = floatval($_POST['preco']);
    $capacidade = intval($_POST['capacidade']);

    if ($preco <= 0 || $capacidade <= 0) {
        $mensagem = "Preço e capacidade devem ser valores positivos.";
        $tipo_mensagem = "error";
    } else {
        $sql = "INSERT INTO rotas (origem, destino, preco, capacidade)
                VALUES ('$origem', '$destino', $preco, $capacidade)";

        if (mysqli_query($conn, $sql)) {
            $id_rota = mysqli_insert_id($conn);
            $mensagem = "Rota adicionada com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao adicionar rota: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
    }
}

// Adicionar horário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar_horario'])) {
    $id_rota = intval($_POST['id_rota']);
    $horario = $_POST['horario_partida'];

    $sql = "INSERT INTO horarios (id_rota, horario_partida) VALUES ($id_rota, '$horario')";

    if (mysqli_query($conn, $sql)) {
        $mensagem = "Horário adicionado com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao adicionar horário: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Ativar/Desativar rota
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['toggle']);

    $sql = "UPDATE rotas SET disponivel = $status WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $mensagem = "Status da rota atualizado com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao atualizar status: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Buscar todas as rotas
$sql_rotas = "SELECT r.*,
              (SELECT COUNT(*) FROM horarios WHERE id_rota = r.id) as total_horarios
              FROM rotas r
              ORDER BY r.origem, r.destino";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar todos os horários
$sql_horarios = "SELECT h.*, r.origem, r.destino
                FROM horarios h
                JOIN rotas r ON h.id_rota = r.id
                ORDER BY r.origem, r.destino, h.horario_partida";
$result_horarios = mysqli_query($conn, $sql_horarios);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_rotas.css">
    <title>FelixBus - Gestão de Rotas</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="pg_admin.php">Página Inicial</a></div>
            <div class="link"> <a href="gerir_alertas.php">Alertas</a></div>
            <div class="link"> <a href="gerir_utilizadores.php">Utilizadores</a></div>
            <div class="link"> <a href="auditoria_transacoes.php">Auditoria</a></div>
            <div class="link"> <a href="perfil_admin.php">Meu Perfil</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin" style="color: white !important; font-weight: 600;">Área de Administrador</div>
        </div>
    </nav>

    <section>
        <h1>Gestão de Rotas e Horários</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem == 'success' ? 'success' : 'danger'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-container">
                <h2>Adicionar Nova Rota</h2>
                <form method="post" action="gerir_rotas.php">
                    <div class="form-group">
                        <label for="origem">Origem:</label>
                        <input type="text" id="origem" name="origem" required>
                    </div>

                    <div class="form-group">
                        <label for="destino">Destino:</label>
                        <input type="text" id="destino" name="destino" required>
                    </div>

                    <div class="form-group">
                        <label for="preco">Preço (€):</label>
                        <input type="number" id="preco" name="preco" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="capacidade">Capacidade:</label>
                        <input type="number" id="capacidade" name="capacidade" min="1" required>
                    </div>

                    <button type="submit" name="adicionar_rota">Adicionar Rota</button>
                </form>

                <h2>Adicionar Horário</h2>
                <form method="post" action="gerir_rotas.php">
                    <div class="form-group">
                        <label for="id_rota">Rota:</label>
                        <select id="id_rota" name="id_rota" required>
                            <option value="">Selecione uma rota</option>
                            <?php
                            mysqli_data_seek($result_rotas, 0);
                            while ($rota = mysqli_fetch_assoc($result_rotas)):
                            ?>
                                <option value="<?php echo $rota['id']; ?>">
                                    <?php echo htmlspecialchars($rota['origem'] . ' → ' . $rota['destino']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="horario_partida">Horário de Partida:</label>
                        <input type="time" id="horario_partida" name="horario_partida" required>
                    </div>

                    <button type="submit" name="adicionar_horario">Adicionar Horário</button>
                </form>
            </div>

            <div class="tables-container">
                <h2>Rotas Disponíveis</h2>
                <table class="rotas-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Preço</th>
                            <th>Capacidade</th>
                            <th>Horários</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        mysqli_data_seek($result_rotas, 0);
                        while ($rota = mysqli_fetch_assoc($result_rotas)):
                        ?>
                            <tr>
                                <td><?php echo $rota['id']; ?></td>
                                <td><?php echo htmlspecialchars($rota['origem']); ?></td>
                                <td><?php echo htmlspecialchars($rota['destino']); ?></td>
                                <td>€<?php echo number_format($rota['preco'], 2, ',', '.'); ?></td>
                                <td><?php echo $rota['capacidade']; ?></td>
                                <td><?php echo $rota['total_horarios']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $rota['disponivel'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $rota['disponivel'] ? 'Ativa' : 'Inativa'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($rota['disponivel']): ?>
                                        <a href="?toggle=0&id=<?php echo $rota['id']; ?>" class="action-btn deactivate">Desativar</a>
                                    <?php else: ?>
                                        <a href="?toggle=1&id=<?php echo $rota['id']; ?>" class="action-btn activate">Ativar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <h2>Horários Cadastrados</h2>
                <table class="horarios-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rota</th>
                            <th>Horário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($horario = mysqli_fetch_assoc($result_horarios)): ?>
                            <tr>
                                <td><?php echo $horario['id']; ?></td>
                                <td><?php echo htmlspecialchars($horario['origem'] . ' → ' . $horario['destino']); ?></td>
                                <td><?php echo $horario['horario_partida']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</body>
</html>
