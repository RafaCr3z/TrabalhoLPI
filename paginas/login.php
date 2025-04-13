<?php
    session_start();
    include '../basedados/basedados.h';

    if (isset($_SESSION["id_nivel"]) > 0) {
        header("Location: erro.php");
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["nome"]) && isset($_POST["pass"])) {
        $nome = $_POST["nome"];
        $pass = $_POST["pass"];

        // Buscar o usuário pelo nome de utilizador
        $sql = "SELECT * FROM `utilizadores` WHERE `user` = '$nome'";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("Erro na consulta: " . mysqli_error($conn));
        }

        if (mysqli_num_rows($result) == 0) {
            echo "<script>alert('Usuário não encontrado.'); window.location.href = 'login.php';</script>";
            exit();
        }

        // Verificar a senha
        $row = mysqli_fetch_array($result);

        // Verificar se a senha está em formato de hash MD5 ou não
        if (strlen($row['pwd']) === 32 && ctype_xdigit($row['pwd'])) {
            // Senha já está em formato de hash MD5
            $senha_valida = (md5($pass) === $row['pwd']);
        } else {
            // Senha antiga sem hash - verificar diretamente e atualizar para hash MD5
            $senha_valida = ($pass === $row['pwd']);

            // Se a senha antiga for válida, atualizar para o formato com hash MD5
            if ($senha_valida) {
                $hashed_pwd = md5($pass);
                $id_usuario = $row['id'];
                $sql_update = "UPDATE utilizadores SET pwd = '$hashed_pwd' WHERE id = $id_usuario";
                mysqli_query($conn, $sql_update);
            }
        }

        if ($senha_valida) {
            // Login bem-sucedido
            $id_nivel = $row['tipo_perfil'];
            $id_utilizador = $row['id'];

            $_SESSION["nome"] = $nome;
            $_SESSION["id_nivel"] = $id_nivel;
            $_SESSION["id_utilizador"] = $id_utilizador;

            if ($id_nivel == 1) {
                header("Location: pg_admin.php");
            } else if ($id_nivel == 2) {
                header("Location: pg_funcionario.php");
            } else if ($id_nivel == 3) {
                header("Location: pg_cliente.php");
            } else {
                mysqli_close($conn);
                header("Location: login.php");
                exit();
            }
        } else {
            echo "<script>alert('Senha incorreta.'); window.location.href = 'login.php';</script>";
            exit();
        }
    }

    mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
    <title>Login</title>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="login.php" method="post">
            <label for="nome">Nome de Utilizador:</label>
            <input type="text" id="nome" name="nome" required>
            <br>
            <label for="pass">Senha:</label>
            <input type="password" id="pass" name="pass" required>
            <br>
            <button type="submit">Entrar</button>

        </form>
        <form action="index.php" method="get">
            <button type="submit" style="margin-top: 10px;">Voltar</button>
        </form>
    </div>
</body>

</html>
