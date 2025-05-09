<?php
session_start();
include '../basedados/basedados.h';

// Verifica se o utilizador tem permissões de administrador
// Se não for administrador, redireciona para a página de erro
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Estabelece ligação à base de dados MySQL
$conn = mysqli_connect("localhost", "root", "", "FelixBus");

// Inicializa variáveis para mensagens de feedback e controlo de estado
$mensagem_feedback = '';
$tipo_mensagem = '';
$alerta_para_editar = null;

// Verifica se foi solicitada a edição de um alerta através do URL
if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    // Converte o ID para inteiro para evitar injeção SQL
    $id_editar = intval($_GET['editar']);
    
    // Utiliza prepared statement para buscar o alerta a ser editado
    $stmt_editar = mysqli_prepare($conn, "SELECT * FROM alertas WHERE id = ?");
    mysqli_stmt_bind_param($stmt_editar, "i", $id_editar);
    mysqli_stmt_execute($stmt_editar);
    $result_editar = mysqli_stmt_get_result($stmt_editar);

    // Se encontrar o alerta, guarda os dados para preencher o formulário
    if (mysqli_num_rows($result_editar) > 0) {
        $alerta_para_editar = mysqli_fetch_assoc($result_editar);
    }
    // Liberta recursos da consulta
    mysqli_stmt_close($stmt_editar);
}

// Processa o formulário para adicionar um novo alerta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    // Valida as datas inseridas pelo utilizador
    if (!strtotime($_POST['data_inicio']) || !strtotime($_POST['data_fim'])) {
        $mensagem_feedback = "Data inválida!";
        $tipo_mensagem = "error";
    } else if (strtotime($_POST['data_inicio']) > strtotime($_POST['data_fim'])) {
        // Verifica se a data de início é anterior à data de fim
        $mensagem_feedback = "A data de início deve ser anterior à data de fim!";
        $tipo_mensagem = "error";
    } else {
        // Utiliza prepared statement para inserir o novo alerta na base de dados
        $stmt = mysqli_prepare($conn, "INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $_POST['mensagem'], $_POST['data_inicio'], $_POST['data_fim']);
        
        // Executa a inserção e verifica se foi bem-sucedida
        if(mysqli_stmt_execute($stmt)) {
            $mensagem_feedback = "Alerta adicionado com sucesso!";
            $tipo_mensagem = "success";
        } else {
            // Em caso de erro, mostra a mensagem de erro
            $mensagem_feedback = "Erro ao adicionar alerta: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
        // Liberta recursos da consulta
        mysqli_stmt_close($stmt);
    }
}

// Processa o formulário para atualizar um alerta existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    // Converte o ID para inteiro para evitar injeção SQL
    $id_alerta = intval($_POST['id_alerta']);
    
    // Valida as datas inseridas pelo utilizador
    if (!strtotime($_POST['data_inicio']) || !strtotime($_POST['data_fim'])) {
        $mensagem_feedback = "Data inválida!";
        $tipo_mensagem = "error";
    } else if (strtotime($_POST['data_inicio']) > strtotime($_POST['data_fim'])) {
        // Verifica se a data de início é anterior à data de fim
        $mensagem_feedback = "A data de início deve ser anterior à data de fim!";
        $tipo_mensagem = "error";
    } else {
        // Utiliza prepared statement para atualizar o alerta na base de dados
        $stmt = mysqli_prepare($conn, "UPDATE alertas SET mensagem = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $_POST['mensagem'], $_POST['data_inicio'], $_POST['data_fim'], $id_alerta);
        
        // Executa a atualização e verifica se foi bem-sucedida
        if (mysqli_stmt_execute($stmt)) {
            $mensagem_feedback = "Alerta com ID $id_alerta foi editado com sucesso!";
            $tipo_mensagem = "success";
            mysqli_stmt_close($stmt);
            // Redireciona para limpar o formulário de edição e mostrar mensagem de sucesso
            header("Location: gerir_alertas.php?msg=updated&id=$id_alerta");
            exit();
        } else {
            // Em caso de erro, mostra a mensagem de erro
            $mensagem_feedback = "Erro ao atualizar alerta ID $id_alerta: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
        // Liberta recursos da consulta
        mysqli_stmt_close($stmt);
    }
}

// Processa o pedido para excluir um alerta
if (isset($_GET['excluir'])) {
    // Converte o ID para inteiro para evitar injeção SQL
    $id = intval($_GET['excluir']);
    
    // Utiliza prepared statement para excluir o alerta da base de dados
    $stmt = mysqli_prepare($conn, "DELETE FROM alertas WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    // Executa a exclusão e verifica se foi bem-sucedida
    if(mysqli_stmt_execute($stmt)) {
        $mensagem_feedback = "Alerta com ID $id foi excluído com sucesso!";
        $tipo_mensagem = "success";
    } else {
        // Em caso de erro, mostra a mensagem de erro
        $mensagem_feedback = "Erro ao excluir alerta ID $id: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
    // Liberta recursos da consulta
    mysqli_stmt_close($stmt);
}

// Define mensagem se vier de um redirecionamento após atualização
if (isset($_GET['msg'])) {
    // Obtém o ID do alerta atualizado, se disponível
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Verifica se a mensagem é de atualização bem-sucedida
    if ($_GET['msg'] == 'updated') {
        $mensagem_feedback = "Alerta com ID $id foi editado com sucesso!";
        $tipo_mensagem = "success";
    }
}

// Busca todos os alertas existentes para exibir na tabela
$sql = "SELECT * FROM alertas";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Verifica se a consulta foi bem-sucedida
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
                    <!-- Preenche o campo com o valor existente se estiver a editar -->
                    <textarea id="mensagem" name="mensagem" required><?php echo $alerta_para_editar ? htmlspecialchars($alerta_para_editar['mensagem'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                </div>

                <!-- Campo para a data de início -->
                <div class="form-group">
                    <label for="data_inicio">Data de Início:</label>
                    <!-- Preenche o campo com o valor existente se estiver a editar -->
                    <input type="datetime-local" id="data_inicio" name="data_inicio" value="<?php echo $alerta_para_editar ? date('Y-m-d\TH:i', strtotime($alerta_para_editar['data_inicio'])) : ''; ?>" required>
                </div>

                <!-- Campo para a data de fim -->
                <div class="form-group">
                    <label for="data_fim">Data de Fim:</label>
                    <!-- Preenche o campo com o valor existente se estiver a editar -->
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

