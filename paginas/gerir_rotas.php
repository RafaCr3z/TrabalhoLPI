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
$rota_para_editar = null;
$horario_para_editar = null;

// Carregar rota para edição
if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $sql_editar = "SELECT * FROM rotas WHERE id = $id_editar";
    $result_editar = mysqli_query($conn, $sql_editar);

    if (mysqli_num_rows($result_editar) > 0) {
        $rota_para_editar = mysqli_fetch_assoc($result_editar);
    }
}

// Carregar horário para edição
if (isset($_GET['editar_horario']) && !empty($_GET['editar_horario'])) {
    $id_horario_editar = intval($_GET['editar_horario']);
    $sql_horario_editar = "SELECT h.*, r.origem, r.destino FROM horarios h
                          JOIN rotas r ON h.id_rota = r.id
                          WHERE h.id = $id_horario_editar";
    $result_horario_editar = mysqli_query($conn, $sql_horario_editar);

    if (mysqli_num_rows($result_horario_editar) > 0) {
        $horario_para_editar = mysqli_fetch_assoc($result_horario_editar);
    }
}

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

// Atualizar horário existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_horario'])) {
    $id_horario = intval($_POST['id_horario']);
    $id_rota = intval($_POST['id_rota']);
    $horario = $_POST['horario_partida'];

    $sql = "UPDATE horarios SET id_rota = $id_rota, horario_partida = '$horario' WHERE id = $id_horario";

    if (mysqli_query($conn, $sql)) {
        $mensagem = "Horário com ID $id_horario foi editado com sucesso!";
        $tipo_mensagem = "success";
        // Redirecionar para limpar o formulário de edição
        header("Location: gerir_rotas.php?msg=horario_updated&id=$id_horario");
        exit();
    } else {
        $mensagem = "Erro ao atualizar horário ID $id_horario: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Atualizar rota existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_rota'])) {
    $id_rota = intval($_POST['id_rota']);
    $origem = mysqli_real_escape_string($conn, $_POST['origem']);
    $destino = mysqli_real_escape_string($conn, $_POST['destino']);
    $preco = floatval($_POST['preco']);
    $capacidade = intval($_POST['capacidade']);

    if ($preco <= 0 || $capacidade <= 0) {
        $mensagem = "Preço e capacidade devem ser valores positivos.";
        $tipo_mensagem = "error";
    } else {
        $sql = "UPDATE rotas SET origem = '$origem', destino = '$destino', preco = $preco, capacidade = $capacidade
               WHERE id = $id_rota";

        if (mysqli_query($conn, $sql)) {
            $mensagem = "Rota com ID $id_rota foi editada com sucesso!";
            $tipo_mensagem = "success";
            // Redirecionar para limpar o formulário de edição
            header("Location: gerir_rotas.php?msg=updated&id=$id_rota");
            exit();
        } else {
            $mensagem = "Erro ao atualizar rota ID $id_rota: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
    }
}

// Ativar/Desativar rota
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['toggle']);

    $sql = "UPDATE rotas SET disponivel = $status WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        if ($status == 1) {
            $mensagem = "Rota com ID $id foi ativada com sucesso!";
        } else {
            $mensagem = "Rota com ID $id foi desativada com sucesso!";
        }
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao atualizar status da rota ID $id: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Ativar/Desativar horário
if (isset($_GET['toggle_horario']) && isset($_GET['id_horario'])) {
    $id_horario = intval($_GET['id_horario']);
    $status = intval($_GET['toggle_horario']);

    // Verificar se a coluna 'disponivel' existe na tabela 'horarios'
    $check_column = "SHOW COLUMNS FROM horarios LIKE 'disponivel'";
    $column_result = mysqli_query($conn, $check_column);

    if (mysqli_num_rows($column_result) == 0) {
        // A coluna não existe, vamos criá-la
        $add_column = "ALTER TABLE horarios ADD COLUMN disponivel TINYINT(1) NOT NULL DEFAULT 1";
        mysqli_query($conn, $add_column);
    }

    $sql = "UPDATE horarios SET disponivel = $status WHERE id = $id_horario";

    if (mysqli_query($conn, $sql)) {
        if ($status == 1) {
            $mensagem = "Horário com ID $id_horario foi ativado com sucesso!";
        } else {
            $mensagem = "Horário com ID $id_horario foi desativado com sucesso!";
        }
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao atualizar status do horário ID $id_horario: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Definir mensagem se vier de um redirecionamento
if (isset($_GET['msg'])) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($_GET['msg'] == 'updated') {
        $mensagem = "Rota com ID $id foi editada com sucesso!";
        $tipo_mensagem = "success";
    } else if ($_GET['msg'] == 'horario_updated') {
        $mensagem = "Horário com ID $id foi editado com sucesso!";
        $tipo_mensagem = "success";
    }
}

// Buscar todas as rotas
$sql_rotas = "SELECT r.*,
              (SELECT COUNT(*) FROM horarios WHERE id_rota = r.id) as total_horarios
              FROM rotas r
              ORDER BY r.id ASC";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Verificar se a coluna 'disponivel' existe na tabela 'horarios'
$check_column = "SHOW COLUMNS FROM horarios LIKE 'disponivel'";
$column_result = mysqli_query($conn, $check_column);

if (mysqli_num_rows($column_result) == 0) {
    // A coluna não existe, vamos criá-la
    $add_column = "ALTER TABLE horarios ADD COLUMN disponivel TINYINT(1) NOT NULL DEFAULT 1";
    mysqli_query($conn, $add_column);
}

// Buscar todos os horários
$sql_horarios = "SELECT h.*, r.origem, r.destino
                FROM horarios h
                JOIN rotas r ON h.id_rota = r.id
                ORDER BY h.id ASC";
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
            <div class="btn-admin">Área de Administrador</div>
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
                <h2><?php echo $rota_para_editar ? 'Editar Rota' : 'Adicionar Nova Rota'; ?></h2>
                <form method="post" action="gerir_rotas.php">
                    <?php if ($rota_para_editar): ?>
                        <input type="hidden" name="id_rota" value="<?php echo $rota_para_editar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="origem">Origem:</label>
                        <input type="text" id="origem" name="origem" value="<?php echo $rota_para_editar ? htmlspecialchars($rota_para_editar['origem']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="destino">Destino:</label>
                        <input type="text" id="destino" name="destino" value="<?php echo $rota_para_editar ? htmlspecialchars($rota_para_editar['destino']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="preco">Preço (€):</label>
                        <input type="number" id="preco" name="preco" step="0.01" min="0.01" value="<?php echo $rota_para_editar ? $rota_para_editar['preco'] : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="capacidade">Capacidade:</label>
                        <input type="number" id="capacidade" name="capacidade" min="1" value="<?php echo $rota_para_editar ? $rota_para_editar['capacidade'] : ''; ?>" required>
                    </div>

                    <?php if ($rota_para_editar): ?>
                        <button type="submit" name="atualizar_rota" class="update-btn">Atualizar Rota</button>
                        <a href="gerir_rotas.php" class="cancel-btn">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" name="adicionar_rota">Adicionar Rota</button>
                    <?php endif; ?>
                </form>

                <h2><?php echo $horario_para_editar ? 'Editar Horário' : 'Adicionar Horário'; ?></h2>
                <form method="post" action="gerir_rotas.php">
                    <?php if ($horario_para_editar): ?>
                        <input type="hidden" name="id_horario" value="<?php echo $horario_para_editar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="id_rota">Rota:</label>
                        <select id="id_rota" name="id_rota" required>
                            <option value="">Selecione uma rota</option>
                            <?php
                            mysqli_data_seek($result_rotas, 0);
                            while ($rota = mysqli_fetch_assoc($result_rotas)):
                                $selected = ($horario_para_editar && $horario_para_editar['id_rota'] == $rota['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $rota['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($rota['origem'] . ' → ' . $rota['destino']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="horario_partida">Horário de Partida:</label>
                        <input type="time" id="horario_partida" name="horario_partida" value="<?php echo $horario_para_editar ? $horario_para_editar['horario_partida'] : ''; ?>" required>
                    </div>

                    <?php if ($horario_para_editar): ?>
                        <button type="submit" name="atualizar_horario" class="update-btn">Atualizar Horário</button>
                        <a href="gerir_rotas.php" class="cancel-btn">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" name="adicionar_horario">Adicionar Horário</button>
                    <?php endif; ?>
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
                                    <div class="action-buttons">
                                        <a href="?editar=<?php echo $rota['id']; ?>" class="action-btn edit">Editar</a>
                                        <?php if ($rota['disponivel']): ?>
                                            <a href="?toggle=0&id=<?php echo $rota['id']; ?>" class="action-btn deactivate">Desativar</a>
                                        <?php else: ?>
                                            <a href="?toggle=1&id=<?php echo $rota['id']; ?>" class="action-btn activate">Ativar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <h3>Horários</h3>
                <table class="horarios-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rota</th>
                            <th>Horário</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($horario = mysqli_fetch_assoc($result_horarios)): ?>
                            <tr>
                                <td><?php echo $horario['id']; ?></td>
                                <td><?php echo htmlspecialchars($horario['origem'] . ' → ' . $horario['destino']); ?></td>
                                <td><?php echo $horario['horario_partida']; ?></td>
                                <td>
                                    <?php if (isset($horario['disponivel']) && $horario['disponivel']): ?>
                                        <span class="status-badge active">Ativo</span>
                                    <?php else: ?>
                                        <span class="status-badge inactive">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?editar_horario=<?php echo $horario['id']; ?>" class="action-btn edit">Editar</a>
                                        <?php if (isset($horario['disponivel']) && $horario['disponivel']): ?>
                                            <a href="?toggle_horario=0&id_horario=<?php echo $horario['id']; ?>" class="action-btn deactivate">Desativar</a>
                                        <?php else: ?>
                                            <a href="?toggle_horario=1&id_horario=<?php echo $horario['id']; ?>" class="action-btn activate">Ativar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
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
