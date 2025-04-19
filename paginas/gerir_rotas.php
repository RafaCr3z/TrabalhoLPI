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

// Iniciar ou retomar a sessão para armazenar mensagens
if (!isset($_SESSION['mensagem_rota'])) {
    $_SESSION['mensagem_rota'] = '';
    $_SESSION['tipo_mensagem_rota'] = '';
}

// Verificar se há mensagens da sessão
if (!empty($_SESSION['mensagem_rota'])) {
    $mensagem = $_SESSION['mensagem_rota'];
    $tipo_mensagem = $_SESSION['tipo_mensagem_rota'];

    // Limpar as mensagens da sessão após usá-las
    $_SESSION['mensagem_rota'] = '';
    $_SESSION['tipo_mensagem_rota'] = '';
}

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
    // Verificar se o token é válido
    if (isset($_SESSION['token_rota']) && isset($_POST['token_rota']) && $_SESSION['token_rota'] === $_POST['token_rota']) {
        $origem = mysqli_real_escape_string($conn, $_POST['origem']);
        $destino = mysqli_real_escape_string($conn, $_POST['destino']);
        $preco = floatval($_POST['preco']);
        $capacidade = intval($_POST['capacidade']);

        if ($preco <= 0 || $capacidade <= 0) {
            $_SESSION['mensagem_rota'] = "Preço e capacidade devem ser valores positivos.";
            $_SESSION['tipo_mensagem_rota'] = "error";
        } else {
            $sql = "INSERT INTO rotas (origem, destino, preco, capacidade, disponivel)
                    VALUES ('$origem', '$destino', $preco, $capacidade, 1)";

            if (mysqli_query($conn, $sql)) {
                $id_rota = mysqli_insert_id($conn);
                $_SESSION['mensagem_rota'] = "Rota com ID $id_rota adicionada com sucesso!";
                $_SESSION['tipo_mensagem_rota'] = "success";
            } else {
                $_SESSION['mensagem_rota'] = "Erro ao adicionar rota: " . mysqli_error($conn);
                $_SESSION['tipo_mensagem_rota'] = "error";
            }
        }
    }

    // Gerar um novo token para a próxima operação
    $_SESSION['token_rota'] = md5(uniqid(mt_rand(), true));

    // Redirecionar para evitar reenvio do formulário
    header("Location: gerir_rotas.php");
    exit();
}

// Adicionar horário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar_horario'])) {
    // Verificar se o token é válido
    if (isset($_SESSION['token_rota']) && isset($_POST['token_rota']) && $_SESSION['token_rota'] === $_POST['token_rota']) {
        $id_rota = intval($_POST['id_rota']);
        $horario = $_POST['horario_partida'];
        $data_viagem = $_POST['data_viagem'];

        // Buscar a capacidade da rota
        $sql_capacidade = "SELECT capacidade FROM rotas WHERE id = $id_rota";
        $result_capacidade = mysqli_query($conn, $sql_capacidade);
        $rota = mysqli_fetch_assoc($result_capacidade);

        $sql = "INSERT INTO horarios (id_rota, horario_partida, data_viagem, lugares_disponiveis, disponivel)
                VALUES ($id_rota, '$horario', '$data_viagem', {$rota['capacidade']}, 1)";

        if (mysqli_query($conn, $sql)) {
            $id_horario = mysqli_insert_id($conn);
            $_SESSION['mensagem_rota'] = "Horário com ID $id_horario adicionado com sucesso!";
            $_SESSION['tipo_mensagem_rota'] = "success";
        } else {
            $_SESSION['mensagem_rota'] = "Erro ao adicionar horário: " . mysqli_error($conn);
            $_SESSION['tipo_mensagem_rota'] = "error";
        }
    }

    // Gerar um novo token para a próxima operação
    $_SESSION['token_rota'] = md5(uniqid(mt_rand(), true));

    // Redirecionar para evitar reenvio do formulário
    header("Location: gerir_rotas.php");
    exit();
}

// Atualizar horário existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_horario'])) {
    $id_horario = intval($_POST['id_horario']);
    $id_rota = intval($_POST['id_rota']);
    $horario = $_POST['horario_partida'];
    $data_viagem = $_POST['data_viagem'];

    $sql = "UPDATE horarios SET id_rota = $id_rota, horario_partida = '$horario', data_viagem = '$data_viagem' WHERE id = $id_horario";

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

// Excluir rota
if (isset($_GET['excluir_rota']) && !empty($_GET['excluir_rota'])) {
    $id_rota = intval($_GET['excluir_rota']);

    // Verificar se a rota existe
    $sql_verificar = "SELECT id FROM rotas WHERE id = $id_rota";
    $result_verificar = mysqli_query($conn, $sql_verificar);

    if (mysqli_num_rows($result_verificar) > 0) {
        // Verificar se há horários associados a esta rota
        $sql_horarios_rota = "SELECT COUNT(*) as total FROM horarios WHERE id_rota = $id_rota";
        $result_horarios_rota = mysqli_query($conn, $sql_horarios_rota);
        $row_horarios_rota = mysqli_fetch_assoc($result_horarios_rota);

        if ($row_horarios_rota['total'] > 0) {
            $mensagem = "Não é possível excluir a rota ID $id_rota pois existem horários associados a ela.";
            $tipo_mensagem = "error";
        } else {
            // Verificar se há bilhetes associados a esta rota
            $sql_bilhetes = "SELECT COUNT(*) as total FROM bilhetes WHERE id_rota = $id_rota";
            $result_bilhetes = mysqli_query($conn, $sql_bilhetes);
            $row_bilhetes = mysqli_fetch_assoc($result_bilhetes);

            if ($row_bilhetes['total'] > 0) {
                $mensagem = "Não é possível excluir a rota ID $id_rota pois existem bilhetes associados a ela.";
                $tipo_mensagem = "error";
            } else {
                // Desativar a rota em vez de excluir
                $sql_desativar = "UPDATE rotas SET disponivel = 0 WHERE id = $id_rota";

                if (mysqli_query($conn, $sql_desativar)) {
                    $mensagem = "Rota com ID $id_rota foi desativada com sucesso!";
                    $tipo_mensagem = "success";
                } else {
                    $mensagem = "Erro ao desativar rota ID $id_rota: " . mysqli_error($conn);
                    $tipo_mensagem = "error";
                }
            }
        }
    } else {
        $mensagem = "Rota ID $id_rota não encontrada.";
        $tipo_mensagem = "error";
    }
}

// Excluir horário
if (isset($_GET['excluir_horario']) && !empty($_GET['excluir_horario'])) {
    $id_horario = intval($_GET['excluir_horario']);

    // Verificar se o horário existe
    $sql_verificar = "SELECT id FROM horarios WHERE id = $id_horario";
    $result_verificar = mysqli_query($conn, $sql_verificar);

    if (mysqli_num_rows($result_verificar) > 0) {
        // Verificar se há bilhetes associados a este horário
        $sql_bilhetes = "SELECT COUNT(*) as total FROM bilhetes WHERE id_rota IN (SELECT id_rota FROM horarios WHERE id = $id_horario) AND hora_viagem = (SELECT horario_partida FROM horarios WHERE id = $id_horario)";
        $result_bilhetes = mysqli_query($conn, $sql_bilhetes);
        $row_bilhetes = mysqli_fetch_assoc($result_bilhetes);

        if ($row_bilhetes['total'] > 0) {
            $mensagem = "Não é possível excluir o horário ID $id_horario pois existem bilhetes associados a ele.";
            $tipo_mensagem = "error";
        } else {
            // Desativar o horário em vez de excluir
            $sql_desativar = "UPDATE horarios SET disponivel = 0 WHERE id = $id_horario";

            if (mysqli_query($conn, $sql_desativar)) {
                $mensagem = "Horário com ID $id_horario foi desativado com sucesso!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "Erro ao desativar horário ID $id_horario: " . mysqli_error($conn);
                $tipo_mensagem = "error";
            }
        }
    } else {
        $mensagem = "Horário ID $id_horario não encontrado.";
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
              WHERE r.disponivel = 1
              ORDER BY r.id ASC";
$result_rotas = mysqli_query($conn, $sql_rotas);

// Buscar todos os horários
$sql_horarios = "SELECT h.*, r.origem, r.destino, r.capacidade
                FROM horarios h
                JOIN rotas r ON h.id_rota = r.id
                WHERE r.disponivel = 1 AND h.disponivel = 1
                ORDER BY h.data_viagem DESC, h.horario_partida ASC";
$result_horarios = mysqli_query($conn, $sql_horarios);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_rotas.css">
    <link rel="stylesheet" href="common.css">
    <title>FelixBus - Gestão de Rotas</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links" style="display: flex; justify-content: center; width: 50%;">
            <div class="link"> <a href="pg_admin.php" style="font-size: 1.2rem; font-weight: 500;">Voltar para Página Inicial</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Administrador</div>
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
                    <?php
                    // Gerar um novo token se não existir
                    if (!isset($_SESSION['token_rota'])) {
                        $_SESSION['token_rota'] = md5(uniqid(mt_rand(), true));
                    }
                    ?>
                    <input type="hidden" name="token_rota" value="<?php echo $_SESSION['token_rota']; ?>">
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
                    <input type="hidden" name="token_rota" value="<?php echo $_SESSION['token_rota']; ?>">
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
                        <label for="data_viagem">Data da Viagem:</label>
                        <input type="date" id="data_viagem" name="data_viagem" value="<?php echo $horario_para_editar ? $horario_para_editar['data_viagem'] : date('Y-m-d'); ?>" required>
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
                                    <div class="action-buttons">
                                        <a href="?editar=<?php echo $rota['id']; ?>" class="action-btn edit">Editar</a>
                                        <a href="?excluir_rota=<?php echo $rota['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza que deseja desativar esta rota?');">Desativar</a>
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
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($horario = mysqli_fetch_assoc($result_horarios)): ?>
                            <tr>
                                <td><?php echo $horario['id']; ?></td>
                                <td><?php echo htmlspecialchars($horario['origem'] . ' → ' . $horario['destino']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($horario['data_viagem'])); ?></td>
                                <td><?php echo $horario['horario_partida']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?editar_horario=<?php echo $horario['id']; ?>" class="action-btn edit">Editar</a>
                                        <a href="?excluir_horario=<?php echo $horario['id']; ?>" class="action-btn delete" onclick="return confirm('Tem certeza que deseja desativar este horário?');">Desativar</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>


