<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_utilizador'];
$msg = "";
$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if (!empty($nome)) {
        $stmt = mysqli_prepare($conn, "UPDATE utilizadores SET nome = ?, nif = ?, telefone = ? WHERE id_utilizador = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $nome, $nif, $telefone, $id_user);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['nome'] = $nome;
            $msg = "Perfil atualizado com sucesso!";
        } else {
            $erro = "Erro ao guardar alterações.";
        }
    } else {
        $erro = "O nome é de preenchimento obrigatório.";
    }
}

$q = mysqli_prepare($conn, "SELECT nome, email, nif, telefone FROM utilizadores WHERE id_utilizador = ?");
mysqli_stmt_bind_param($q, "i", $id_user);
mysqli_stmt_execute($q);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Editar Perfil</title>
    <link rel="stylesheet" href="../../jsp/paginas/editar_perfil.css">
</head>
<body>
    <div class="container">
        <h2>Editar Dados de Perfil</h2>
        <?php if (!empty($msg)): ?>
            <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form action="editar_perfil.php" method="POST">
            <div class="campo">
                <label>Nome:</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($user['nome']) ?>" required>
            </div>
            <div class="campo">
                <label>Email (Não editável):</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>
            <div class="campo">
                <label>NIF:</label>
                <input type="text" name="nif" value="<?= htmlspecialchars($user['nif'] ?? '') ?>" maxlength="9">
            </div>
            <div class="campo">
                <label>Telefone:</label>
                <input type="text" name="telefone" value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" maxlength="15">
            </div>
            <button type="submit" class="btn-primary">Guardar Alterações</button>
            <a href="perfil_cliente.php" class="btn-voltar">Cancelar</a>
        </form>
    </div>
</body>
</html>
