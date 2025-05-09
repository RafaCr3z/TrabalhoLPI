<?php
session_start();
include '../basedados/basedados.h';

// Verifica se o utilizador é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Ligação à base de dados
$conn = mysqli_connect("localhost", "root", "", "FelixBus");

// Inicializar variáveis
$mensagem_feedback = '';
$tipo_mensagem = '';
$alerta_para_editar = null;

// Carregar alerta para edição se o parâmetro 'editar' estiver presente no URL
if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    
    // Usar prepared statement para buscar alerta
    $stmt_editar = mysqli_prepare($conn, "SELECT * FROM alertas WHERE id = ?");
    mysqli_stmt_bind_param($stmt_editar, "i", $id_editar);
    mysqli_stmt_execute($stmt_editar);
    $result_editar = mysqli_stmt_get_result($stmt_editar);

    if (mysqli_num_rows($result_editar) > 0) {
        $alerta_para_editar = mysqli_fetch_assoc($result_editar);
    }
    mysqli_stmt_close($stmt_editar);
}

// Processar formulário para adicionar novo alerta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    // Validate dates
    if (!strtotime($_POST['data_inicio']) || !strtotime($_POST['data_fim'])) {
        $mensagem_feedback = "Data inválida!";
        $tipo_mensagem = "error";
        // Redirect or handle error
    } else if (strtotime($_POST['data_inicio']) > strtotime($_POST['data_fim'])) {
        $mensagem_feedback = "A data de início deve ser anterior à data de fim!";
        $tipo_mensagem = "error";
    } else {
        // Usar prepared statement para inserir
        $stmt = mysqli_prepare($conn, "INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $_POST['mensagem'], $_POST['data_inicio'], $_POST['data_fim']);
        
        if(mysqli_stmt_execute($stmt)) {
            $mensagem_feedback = "Alerta adicionado com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem_feedback = "Erro ao adicionar alerta: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
        mysqli_stmt_close($stmt);
    }
}

// Processar formulário para atualizar alerta existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    $id_alerta = intval($_POST['id_alerta']);
    
    // Validate dates
    if (!strtotime($_POST['data_inicio']) || !strtotime($_POST['data_fim'])) {
        $mensagem_feedback = "Data inválida!";
        $tipo_mensagem = "error";
        // Redirect or handle error
    } else if (strtotime($_POST['data_inicio']) > strtotime($_POST['data_fim'])) {
        $mensagem_feedback = "A data de início deve ser anterior à data de fim!";
        $tipo_mensagem = "error";
    } else {
        // Usar prepared statement para atualizar
        $stmt = mysqli_prepare($conn, "UPDATE alertas SET mensagem = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $_POST['mensagem'], $_POST['data_inicio'], $_POST['data_fim'], $id_alerta);
        
        if (mysqli_stmt_execute($stmt)) {
            $mensagem_feedback = "Alerta com ID $id_alerta foi editado com sucesso!";
            $tipo_mensagem = "success";
            mysqli_stmt_close($stmt);
            // Redirecionar para limpar o formulário de edição
            header("Location: gerir_alertas.php?msg=updated&id=$id_alerta");
            exit();
        } else {
            $mensagem_feedback = "Erro ao atualizar alerta ID $id_alerta: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
        mysqli_stmt_close($stmt);
    }
}

// Processar pedido para excluir alerta
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    
    // Usar prepared statement para excluir
    $stmt = mysqli_prepare($conn, "DELETE FROM alertas WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if(mysqli_stmt_execute($stmt)) {
        $mensagem_feedback = "Alerta com ID $id foi excluído com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem_feedback = "Erro ao excluir alerta ID $id: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
    mysqli_stmt_close($stmt);
}

// Definir mensagem se vier de um redirecionamento após atualização
if (isset($_GET['msg'])) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($_GET['msg'] == 'updated') {
        $mensagem_feedback = "Alerta com ID $id foi editado com sucesso!";
        $tipo_mensagem = "success";
    }
}

// Buscar todos os alertas existentes para exibir na tabela
$sql = "SELECT * FROM alertas";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

if (!$result) {
    $mensagem_feedback = "Erro ao buscar alertas: " . mysqli_error($conn);
    $tipo_mensagem = "error";
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_alertas.css">
    <title>FelixBus - Gestão de Alertas</title>
</head>
<body>
    <!-- Barra de navegação -->
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"><a href="pg_admin.php">Voltar para Página Inicial</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Administrador</div>
        </div>
    </nav>

    <!-- Conteúdo principal -->
    <div class="container">
        <!-- Formulário para adicionar ou editar alertas -->
        <div class="form-container">
            <h2><?php echo $alerta_para_editar ? 'Editar Alerta' : 'Adicionar Novo Alerta'; ?></h2>
            <form method="post" action="gerir_alertas.php">
                <?php if ($alerta_para_editar): ?>
                    <!-- Campo oculto com ID do alerta a ser editado -->
                    <input type="hidden" name="id_alerta" value="<?php echo $alerta_para_editar['id']; ?>">
                <?php endif; ?>

                <!-- Campo para a mensagem do alerta -->
                <div class="form-group">
                    <label for="mensagem">Mensagem:</label>
                    <textarea id="mensagem" name="mensagem" required><?php echo $alerta_para_editar ? htmlspecialchars($alerta_para_editar['mensagem'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                </div>

                <!-- Campo para a data de início -->
                <div class="form-group">
                    <label for="data_inicio">Data de Início:</label>
                    <input type="datetime-local" id="data_inicio" name="data_inicio" value="<?php echo $alerta_para_editar ? date('Y-m-d\TH:i', strtotime($alerta_para_editar['data_inicio'])) : ''; ?>" required>
                </div>

                <!-- Campo para a data de fim -->
                <div class="form-group">
                    <label for="data_fim">Data de Fim:</label>
                    <input type="datetime-local" id="data_fim" name="data_fim" value="<?php echo $alerta_para_editar ? date('Y-m-d\TH:i', strtotime($alerta_para_editar['data_fim'])) : ''; ?>" required>
                </div>

                <!-- Botões de ação do formulário -->
                <?php if ($alerta_para_editar): ?>
                    <button type="submit" name="atualizar">Atualizar Alerta</button>
                    <a href="gerir_alertas.php" class="cancel-btn">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="adicionar">Adicionar Alerta</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabela de alertas existentes -->
        <div class="table-container">
            <!-- Exibir mensagens de feedback ao utilizador -->
            <?php if (!empty($mensagem_feedback)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem == 'success' ? 'success' : 'danger'; ?>">
                <?php echo $mensagem_feedback; ?>
            </div>
            <?php endif; ?>

            <h2>Alertas Adicionados</h2>
            <table class="alertas-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mensagem</th>
                        <th>Data de Início</th>
                        <th>Data de Fim</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Exibir todos os alertas na tabela
                    if (mysqli_num_rows($result) > 0) {
                        while ($alerta = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $alerta['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($alerta['mensagem'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($alerta['data_inicio'])) . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($alerta['data_fim'])) . "</td>";
                            echo "<td>
                                <div class='action-buttons'>
                                    <a href='?editar=" . $alerta['id'] . "' class='action-btn edit'>Editar</a>
                                    <a href='?excluir=" . $alerta['id'] . "' class='action-btn delete' onclick='return confirm(\"Tem certeza que deseja excluir este alerta?\")'>Excluir</a>
                                </div>
                            </td>";
                            echo "</tr>";
                        }
                    } else {
                        // Mensagem quando não há alertas
                        echo "<tr><td colspan='5' class='no-results'>Nenhum alerta encontrado.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rodapé da página -->
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>

