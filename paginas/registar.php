<?php
    session_start();
    include '../basedados/basedados.h';

    if (isset($_SESSION["id_nivel"]) && $_SESSION["id_nivel"] > 0) {
        header("Location: erro.php");
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["user"]) && isset($_POST["pwd"]) && isset($_POST["nome"]) && isset($_POST["email"]) && isset($_POST["telemovel"]) && isset($_POST["morada"])) {
        $user = $_POST["user"];
        $pwd = $_POST["pwd"];
        $nome = $_POST["nome"];
        $email = $_POST["email"];
        $telemovel = $_POST["telemovel"];
        $morada = $_POST["morada"];

        // Gerar hash da senha
        $hashed_pwd = password_hash($pwd, PASSWORD_DEFAULT);

        // Verifica se o nome de utilizador já existe
        $check_username_sql = "SELECT * FROM utilizadores WHERE user = '$user'";
        $check_username_result = mysqli_query($conn, $check_username_sql);
        if (mysqli_num_rows($check_username_result) > 0) {
            echo "<script>alert('Nome de utilizador já existe. Tente outro.');</script>";
            mysqli_close($conn);
            exit();
        }

        // Verifica se o nome já existe
        $check_user_sql = "SELECT * FROM utilizadores WHERE nome = '$nome'";
        $check_user_result = mysqli_query($conn, $check_user_sql);
        if (mysqli_num_rows($check_user_result) > 0) {
            echo "<script>alert('Usuário já existe. Tente outro.');</script>";
            mysqli_close($conn);
            exit();
        }

        // Verifica se o e-mail já existe
        $check_email_sql = "SELECT * FROM utilizadores WHERE email = '$email'";
        $check_email_result = mysqli_query($conn, $check_email_sql);
        if (mysqli_num_rows($check_email_result) > 0) {
            echo "<script>alert('E-mail já existe. Tente outro.');</script>";
            mysqli_close($conn);
            exit();
        }

        // Verifica se o telemóvel já existe
        $check_telemovel_sql = "SELECT * FROM utilizadores WHERE telemovel = '$telemovel'";
        $check_telemovel_result = mysqli_query($conn, $check_telemovel_sql);
        if (mysqli_num_rows($check_telemovel_result) > 0) {
            echo "<script>alert('Telemóvel já existe. Tente outro.');</script>";
            mysqli_close($conn);
            exit();
        }

        $sql = "INSERT INTO utilizadores (user, pwd, nome, email, telemovel, morada, tipo_perfil)
                VALUES ('$user', '$hashed_pwd', '$nome', '$email', '$telemovel', '$morada', 3)";

        if (mysqli_query($conn, $sql)) {
            // Criar carteira para o novo cliente
            $id_cliente = mysqli_insert_id($conn);
            $sql_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
            mysqli_query($conn, $sql_carteira);

            // Redirecionar para a página inicial
            echo "<script>alert('Usuário registrado com sucesso!'); window.location.href = 'index.php';</script>";
            exit();
        } else {
            echo "<script>alert('Erro ao registrar usuário: " . mysqli_error($conn) . "');</script>";
        }

        mysqli_close($conn);
    }
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="registar.css">
    <title>Registar</title>
</head>
<body>
    <div class="register-container">
        <h2>Registar</h2>
        <form action="registar.php" method="post">

            <label for="user">Nome de Utilizador:</label>
            <input type="text" id="user" name="user" required>

            <label for="nome">Nome Completo:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="pwd">Senha:</label>
            <input type="password" id="pwd" name="pwd" required>

            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required email>

            <label for="telemovel">Telemóvel:</label>
            <input type="text" id="telemovel" name="telemovel" required maxlength="9" minlength="9">

            <label for="morada">Morada:</label>
            <input type="text" id="morada" name="morada" required>

            <button type="submit">Criar Conta</button>
        </form>
        <form action="index.php" method="get">
            <button type="submit" style="margin-top: 10px;">Voltar</button>
        </form>
    </div>
</body>
</html>