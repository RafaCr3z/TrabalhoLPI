<?php
// filepath: c:\xampp\htdocs\TrabalhoLPI\paginas\gerir_alertas.php
session_start();
include '../basedados/basedados.h';

// Verifica se o usuário é administrador
if (!isset($_SESSION['tipo_perfil']) || $_SESSION['tipo_perfil'] != 1) {
    header("Location: erro.php");
    exit;
}

// Conexão com o banco de dados
$conn = mysqli_connect("localhost", "root", "", "FelixBus");

// Adicionar alerta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    $mensagem = mysqli_real_escape_string($conn, $_POST['mensagem']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];

    $sql = "INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES ('$mensagem', '$data_inicio', '$data_fim')";
    mysqli_query($conn, $sql);
}

// Excluir alerta
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);
    $sql = "DELETE FROM alertas WHERE id = $id";
    mysqli_query($conn, $sql);
}

// Buscar alertas existentes
$result = mysqli_query($conn, "SELECT * FROM alertas ORDER BY data_inicio DESC");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerir Alertas</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
    <h1>Gerir Alertas</h1>
    <form method="post">
        <label for="mensagem">Mensagem:</label>
        <textarea id="mensagem" name="mensagem" required></textarea>

        <label for="data_inicio">Data de Início:</label>
        <input type="datetime-local" id="data_inicio" name="data_inicio" required>

        <label for="data_fim">Data de Fim:</label>
        <input type="datetime-local" id="data_fim" name="data_fim" required>

        <button type="submit" name="adicionar">Adicionar Alerta</button>
    </form>

    <h2>Alertas Atuais</h2>
    <table>
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
            <?php while ($alerta = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $alerta['id']; ?></td>
                    <td><?php echo htmlspecialchars($alerta['mensagem']); ?></td>
                    <td><?php echo $alerta['data_inicio']; ?></td>
                    <td><?php echo $alerta['data_fim']; ?></td>
                    <td>
                        <a href="?excluir=<?php echo $alerta['id']; ?>">Excluir</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>