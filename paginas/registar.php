<?php
session_start();
include '../basedados/basedados.h';

if (isset($_SESSION["id_nivel"]) > 0) {
    header("Location: erro.php");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pwd = $_POST["pwd"];
    $nome = $_POST["nome"];
    $email = $_POST["email"];
    $telemovel = $_POST["telemovel"];
    $morada = $_POST["morada"];

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

    $sql = "INSERT INTO utilizadores (pwd, nome, email, telemovel, morada, tipo_perfil) 
                VALUES ('$hashed_pwd', '$nome', '$email', '$telemovel', '$morada', 3)";

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Usuário registrado com sucesso!');</script>";
        header("Location: index.php");
        exit();
    } else {
        echo "Erro ao registrar usuário: " . mysqli_error($conn);
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

            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="pwd">Senha:</label>
            <input type="password" id="pwd" name="pwd" required>

            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required email>

            <label for="telemovel">Telemóvel:</label>
            <input type="text" id="telemovel" name="telemovel" required maxlength="9" minlength="9">

            <label for="morada">Morada:</label>
            <input type="text" id="morada" name="morada" required>

            <button type="submit">Registar</button>
        </form>
        <form action="index.php" method="get">
            <button type="submit" style="margin-top: 10px;">Voltar</button>
        </form>
    </div>
</body>
</html>