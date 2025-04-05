<?php
    session_start();
    include '../basedados/basedados.h';

    if (isset($_SESSION["id_nivel"]) > 0) {
        header("Location: erro.php");
    }

    if (isset($_POST["nome"]) && isset($_POST["pass"])) {
        $nome = $_POST["nome"];
        echo $nome;
        $pass = $_POST["pass"];
        echo $pass;

        $sql = "SELECT * FROM `utilizadores` WHERE `nome` = '$nome' AND `pwd` = '$pass'";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("Erro na consulta: " . mysqli_error($conn));
        }

        if (mysqli_num_rows($result) == 0) {
            mysqli_close($conn);
            header("Location: login.php");
            echo "<script>alert('Usuário ou senha inválidos.');</script>";
            exit();
        }
        
    $row = mysqli_fetch_array($result);

    // Use o nome correto da coluna 'tipo_perfil'
    $id_nivel = $row['tipo_perfil'];
    $id_utilizador = $row['id'];

    $_SESSION["nome"] = $nome;
    $_SESSION["id_nivel"] = $id_nivel;
    $_SESSION["id_utilizador"] = $id_utilizador;

    if ($id_nivel == 1) {
        header("Location: admin.php");
    } else if ($id_nivel == 2) {
        header("Location: funcionario.php");
    } else if ($id_nivel == 3) {
        header("Location: cliente.php");
    } else {
        mysqli_close($conn);
        header("Location: login.php");
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
            <label for="nome">Usuário:</label>
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
