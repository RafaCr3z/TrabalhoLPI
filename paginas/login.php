<?php
<<<<<<< HEAD
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
=======
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
>>>>>>> 5a4d63a1c91c54f7b6584aaeb515c4c4a9021c08

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

<<<<<<< HEAD
mysqli_close($conn);
?>
=======
        // Use o nome correto da coluna 'tipo_perfil'
        $id_nivel = $row['tipo_perfil'];
        $id_utilizador = $row['id'];
>>>>>>> 5a4d63a1c91c54f7b6584aaeb515c4c4a9021c08

<!DOCTYPE html>
<html lang="pt">

<<<<<<< HEAD
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-container {
            background-color: #ffffff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 300px;
        }

        h2 {
            margin-bottom: 20px;
            color: #333333;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 5px;
            text-align: left;
            color: #555555;
        }

        input {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            font-size: 14px;
        }

        button {
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .error {
            color: red;
            margin-top: 10px;
            font-size: 14px;
        }
    </style>
</head>

=======
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
>>>>>>> 5a4d63a1c91c54f7b6584aaeb515c4c4a9021c08
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
<<<<<<< HEAD

        </form>
        <form action="index.php" method="get">
            <button type="submit" style="margin-top: 10px;">Voltar</button>
        </form>



    </div>
</body>

=======
        </form>

    </div>
</body>
>>>>>>> 5a4d63a1c91c54f7b6584aaeb515c4c4a9021c08
</html>