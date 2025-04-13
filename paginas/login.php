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

        // Para depuração - verificar o formato da senha armazenada
        $pwd_stored = $row['pwd'];
        $pwd_length = strlen($pwd_stored);
        $is_md5 = (strlen($pwd_stored) === 32 && ctype_xdigit($pwd_stored));
        $is_bcrypt = (substr($pwd_stored, 0, 4) === '$2y$');

        // Verificar a senha de várias maneiras possíveis
        $senha_valida = false;

        // 1. Verificar se é MD5
        if ($is_md5) {
            $senha_valida = (md5($pass) === $pwd_stored);
        }
        // 2. Verificar se é bcrypt
        else if ($is_bcrypt) {
            $senha_valida = password_verify($pass, $pwd_stored);
        }
        // 3. Verificar se é texto simples
        else {
            $senha_valida = ($pass === $pwd_stored);
        }

        // Se a senha for válida e não estiver em formato bcrypt, atualizar para bcrypt
        if ($senha_valida && !$is_bcrypt) {
            $hashed_pwd = password_hash($pass, PASSWORD_DEFAULT);
            $id_usuario = $row['id'];
            $sql_update = "UPDATE utilizadores SET pwd = '$hashed_pwd' WHERE id = $id_usuario";
            mysqli_query($conn, $sql_update);
        }

        // Para depuração - registrar informações sobre a verificação da senha
        if (!$senha_valida) {
            // Apenas para depuração - não usar em produção
            $debug_info = "Tentativa de login para usuário: $nome\n";
            $debug_info .= "Formato da senha: " . ($is_md5 ? "MD5" : ($is_bcrypt ? "bcrypt" : "texto simples")) . "\n";
            $debug_info .= "Comprimento da senha armazenada: $pwd_length\n";

            // Escrever em um arquivo de log (apenas para depuração)
            file_put_contents('../login_debug.log', $debug_info, FILE_APPEND);
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
    <title>FelixBus - Login</title>
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
