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

// Adicionar alerta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    $mensagem = mysqli_real_escape_string($conn, $_POST['mensagem']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];

    $sql = "INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES ('$mensagem', '$data_inicio', '$data_fim')";
    if(mysqli_query($conn, $sql)) {
        echo "<script>alert('Alerta adicionado com sucesso!');</script>";
    } else {
        echo "<script>alert('Erro ao adicionar alerta: " . mysqli_error($conn) . "');</script>";
    }
}

// Excluir alerta
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $sql = "DELETE FROM alertas WHERE id = $id";
    if(mysqli_query($conn, $sql)) {
        echo "<script>alert('Alerta excluído com sucesso!');</script>";
    } else {
        echo "<script>alert('Erro ao excluir alerta: " . mysqli_error($conn) . "');</script>";
    }
}

// Buscar alertas existentes
$sql = "SELECT * FROM alertas ORDER BY data_inicio DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo "<script>alert('Erro ao buscar alertas: " . mysqli_error($conn) . "');</script>";
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
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="pg_admin.php">Página Inicial</a></div>
            <div class="link"> <a href="gerir_rotas.php">Rotas</a></div>
            <div class="link"> <a href="gerir_utilizadores.php">Utilizadores</a></div>
            <div class="link"> <a href="auditoria_transacoes.php">Auditoria</a></div>
            <div class="link"> <a href="perfil_admin.php">Meu Perfil</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2>Adicionar Novo Alerta</h2>
            <form method="post" action="gerir_alertas.php">
                <div class="form-group">
                    <label for="mensagem">Mensagem:</label>
                    <textarea id="mensagem" name="mensagem" required></textarea>
                </div>

                <div class="form-group">
                    <label for="data_inicio">Data de Início:</label>
                    <input type="datetime-local" id="data_inicio" name="data_inicio" required>
                </div>

                <div class="form-group">
                    <label for="data_fim">Data de Fim:</label>
                    <input type="datetime-local" id="data_fim" name="data_fim" required>
                </div>

                <button type="submit" name="adicionar">Adicionar Alerta</button>
            </form>
        </div>

        <div class="table-container">
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
                            echo "<td>" . htmlspecialchars($alerta['mensagem']) . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($alerta['data_inicio'])) . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($alerta['data_fim'])) . "</td>";
                            echo "<td><a href='?excluir=" . $alerta['id'] . "' class='action-btn delete' onclick='return confirm(\"Tem certeza que deseja excluir este alerta?\")'>Excluir</a></td>";
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
</body>
</html>
