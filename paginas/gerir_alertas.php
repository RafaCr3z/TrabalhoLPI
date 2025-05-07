<?php
session_start();
include '../basedados/basedados.h';

// Verifica se o usuário é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Conexão com o banco de dados
$conn = mysqli_connect("localhost", "root", "", "FelixBus");

// Inicializar variáveis
$mensagem_feedback = '';
$tipo_mensagem = '';
$alerta_para_editar = null;

// Carregar alerta para edição
if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $sql_editar = "SELECT * FROM alertas WHERE id = $id_editar";
    $result_editar = mysqli_query($conn, $sql_editar);

    if (mysqli_num_rows($result_editar) > 0) {
        $alerta_para_editar = mysqli_fetch_assoc($result_editar);
    }
}

// Adicionar alerta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    $mensagem = mysqli_real_escape_string($conn, $_POST['mensagem']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];

    $sql = "INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES ('$mensagem', '$data_inicio', '$data_fim')";
    if(mysqli_query($conn, $sql)) {
        $mensagem_feedback = "Alerta adicionado com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem_feedback = "Erro ao adicionar alerta: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Atualizar alerta existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar'])) {
    $id_alerta = intval($_POST['id_alerta']);
    $mensagem = mysqli_real_escape_string($conn, $_POST['mensagem']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];

    $sql = "UPDATE alertas SET mensagem = '$mensagem', data_inicio = '$data_inicio', data_fim = '$data_fim' WHERE id = $id_alerta";

    if (mysqli_query($conn, $sql)) {
        $mensagem_feedback = "Alerta com ID $id_alerta foi editado com sucesso!";
        $tipo_mensagem = "success";
        // Redirecionar para limpar o formulário de edição
        header("Location: gerir_alertas.php?msg=updated&id=$id_alerta");
        exit();
    } else {
        $mensagem_feedback = "Erro ao atualizar alerta ID $id_alerta: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Excluir alerta
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $sql = "DELETE FROM alertas WHERE id = $id";
    if(mysqli_query($conn, $sql)) {
        $mensagem_feedback = "Alerta com ID $id foi excluído com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem_feedback = "Erro ao excluir alerta ID $id: " . mysqli_error($conn);
        $tipo_mensagem = "error";
    }
}

// Definir mensagem se vier de um redirecionamento
if (isset($_GET['msg'])) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($_GET['msg'] == 'updated') {
        $mensagem_feedback = "Alerta com ID $id foi editado com sucesso!";
        $tipo_mensagem = "success";
    }
}

// Buscar alertas existentes
$sql = "SELECT * FROM alertas ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

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
    <link rel="stylesheet" href="common.css">
    <title>FelixBus - Gestão de Alertas</title>
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
            <div class="btn-admin" style="color: white !important; font-weight: 600;">Área do Administrador</div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2><?php echo $alerta_para_editar ? 'Editar Alerta' : 'Adicionar Novo Alerta'; ?></h2>
            <form method="post" action="gerir_alertas.php">
                <?php if ($alerta_para_editar): ?>
                    <input type="hidden" name="id_alerta" value="<?php echo $alerta_para_editar['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="mensagem">Mensagem:</label>
                    <textarea id="mensagem" name="mensagem" required><?php echo $alerta_para_editar ? $alerta_para_editar['mensagem'] : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="data_inicio">Data de Início:</label>
                    <input type="datetime-local" id="data_inicio" name="data_inicio" value="<?php echo $alerta_para_editar ? date('Y-m-d\TH:i', strtotime($alerta_para_editar['data_inicio'])) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="data_fim">Data de Fim:</label>
                    <input type="datetime-local" id="data_fim" name="data_fim" value="<?php echo $alerta_para_editar ? date('Y-m-d\TH:i', strtotime($alerta_para_editar['data_fim'])) : ''; ?>" required>
                </div>

                <?php if ($alerta_para_editar): ?>
                    <button type="submit" name="atualizar" class="update-btn">Atualizar Alerta</button>
                    <a href="gerir_alertas.php" class="cancel-btn">Cancelar</a>
                <?php else: ?>
                    <button type="submit" name="adicionar">Adicionar Alerta</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
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
                    if (mysqli_num_rows($result) > 0) {
                        while ($alerta = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $alerta['id'] . "</td>";
                            echo "<td>" . $alerta['mensagem'] . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($alerta['data_inicio'])) . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($alerta['data_fim'])) . "</td>";
                            echo "<td>
                                <div class='action-buttons'>
                                    <a href='?editar=" . $alerta['id'] . "' class='action-btn edit'>Editar</a>
                                    <a href='?excluir=" . $alerta['id'] . "' class='action-btn delete' onclick='return confirm(\"Tem certeza que deseja excluir este alerta?\")'> Excluir</a>
                                </div>
                            </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='no-results'>Nenhum alerta encontrado.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Adicionar antes do fechamento do </body> -->
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>
