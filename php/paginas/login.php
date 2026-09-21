<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        $stmt = mysqli_prepare($conn, "SELECT id_utilizador, nome, password, id_perfil, ativo FROM utilizadores WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($res)) {
            if ($user['ativo'] == 1 && (password_verify($password, $user['password']) || $password === $user['password'])) {
                $_SESSION['id_utilizador'] = $user['id_utilizador'];
                $_SESSION['nome'] = $user['nome'];
                $_SESSION['id_perfil'] = $user['id_perfil'];

                if ($user['id_perfil'] == 4) {
                    header("Location: pg_admin.php");
                } else {
                    header("Location: pg_cliente.php");
                }
                exit;
            } else {
                $erro = "Credenciais inválidas ou conta inativa.";
            }
        } else {
            $erro = "Utilizador não encontrado.";
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
    <title>FelixBus - Entrar</title>
    <link rel="stylesheet" href="../../jsp/paginas/login.css">
</head>
<body>
    <div class="login-container">
        <h2>Autenticação FelixBus</h2>
        <?php if (!empty($erro)): ?>
            <div class="alert-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="campo">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="campo">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary">Entrar</button>
        </form>
        <p><a href="registar.php">Criar nova conta</a> | <a href="index.php">Voltar ao Início</a></p>
    </div>
</body>
</html>
