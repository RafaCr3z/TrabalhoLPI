<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

$msg = "";
$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');

    if (!empty($nome) && !empty($email) && !empty($password)) {
        // Verificar se email já existe
        $check = mysqli_prepare($conn, "SELECT id_utilizador FROM utilizadores WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $erro = "O email indicado já se encontra registado.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO utilizadores (nome, email, password, nif, telefone, id_perfil, ativo) VALUES (?, ?, ?, ?, ?, 2, 1)");
            mysqli_stmt_bind_param($stmt, "sssss", $nome, $email, $hash, $nif, $telefone);

            if (mysqli_stmt_execute($stmt)) {
                $novo_id = mysqli_insert_id($conn);
                // Criar carteira virtual inicial
                $carteira = mysqli_prepare($conn, "INSERT INTO carteiras (id_utilizador, saldo) VALUES (?, 0.00)");
                mysqli_stmt_bind_param($carteira, "i", $novo_id);
                mysqli_stmt_execute($carteira);

                header("Location: login.php?registado=1");
                exit;
            } else {
                $erro = "Erro ao registar utilizador na base de dados.";
            }
        }
    } else {
        $erro = "Preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Registar Conta</title>
    <link rel="stylesheet" href="../../jsp/paginas/registar.css">
</head>
<body>
    <div class="login-container">
        <h2>Criar Conta de Cliente</h2>
        <?php if (!empty($erro)): ?>
            <div class="alert-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form action="registar.php" method="POST">
            <div class="campo">
                <label>Nome Completo:</label>
                <input type="text" name="nome" required>
            </div>
            <div class="campo">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="campo">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <div class="campo">
                <label>NIF (Opcional):</label>
                <input type="text" name="nif" maxlength="9">
            </div>
            <div class="campo">
                <label>Telefone (Opcional):</label>
                <input type="text" name="telefone" maxlength="15">
            </div>
            <button type="submit" class="btn-primary">Criar Conta</button>
        </form>
        <p><a href="login.php">Já tem conta? Entrar</a></p>
    </div>
</body>
</html>
