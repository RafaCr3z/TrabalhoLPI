<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador']) || $_SESSION['id_perfil'] != 4) {
    header("Location: login.php");
    exit;
}

$msg = "";
$erro = "";

// Emitir novo alerta operacional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emitir_alerta'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    $tipo = trim($_POST['tipo_alerta'] ?? 'INFO');

    if (!empty($titulo) && !empty($mensagem)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO alertas (titulo, mensagem, tipo_alerta) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $titulo, $mensagem, $tipo);
        if (mysqli_stmt_execute($stmt)) {
            $msg = "Alerta operacional publicado com sucesso!";
        } else {
            $erro = "Erro ao guardar alerta na base de dados.";
        }
    } else {
        $erro = "Preencha o título e a mensagem do aviso.";
    }
}

// Listar alertas emitidos
$alertas = mysqli_query($conn, "SELECT * FROM alertas ORDER BY data_emissao DESC");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Gestão de Alertas</title>
    <link rel="stylesheet" href="../../jsp/paginas/gerir_alertas.css">
</head>
<body>
    <header class="navbar">
        <h1>Gestão de Alertas e Incidentes</h1>
        <nav>
            <a href="pg_admin.php">Painel Admin</a>
            <a href="gerir_rotas.php">Rotas</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <?php if (!empty($msg)): ?>
            <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <section class="form-secao">
            <h3>Publicar Novo Aviso Operacional</h3>
            <form action="gerir_alertas.php" method="POST">
                <div class="campo">
                    <label>Título:</label>
                    <input type="text" name="titulo" placeholder="ex.: Atraso Linha Lisboa-Castelo Branco" required>
                </div>
                <div class="campo">
                    <label>Tipo de Alerta:</label>
                    <select name="tipo_alerta">
                        <option value="INFO">Informação Geral</option>
                        <option value="AVISO">Aviso Operacional</option>
                        <option value="ATRASO">Atraso de Viagem</option>
                        <option value="CANCELAMENTO">Cancelamento</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Mensagem Detalhada:</label>
                    <textarea name="mensagem" rows="4" required></textarea>
                </div>
                <button type="submit" name="emitir_alerta" class="btn-primary">Publicar Aviso</button>
            </form>
        </section>

        <section class="tabela-secao">
            <h3>Alertas Ativos</h3>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Título</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = mysqli_fetch_assoc($alertas)): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['data_emissao']) ?></td>
                            <td><span class="badge-alerta <?= strtolower($a['tipo_alerta']) ?>"><?= htmlspecialchars($a['tipo_alerta']) ?></span></td>
                            <td><b><?= htmlspecialchars($a['titulo']) ?></b></td>
                            <td><?= htmlspecialchars($a['mensagem']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
