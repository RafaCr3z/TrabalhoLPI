<?php
<<<<<<< HEAD
session_start();
include '../basedados/basedados.h';
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

    if (isset($_POST["nome"]) && isset($_POST["pass"])) {

        $nome = $_POST["nome"];
        $pass = $_POST["pass"];

        $sql = "SELECT * FROM `utilizador` WHERE `nome` = '$nome' AND `password` = '$pass'";
        $result = mysqli_query($conexao, $sql);

        $row = mysqli_fetch_array($result);

        $id_nivel = $row['id_nivel'];
        $id_utilizador = $row['id_utilizador'];

        $_SESSION["nome"] = $nome;
        $_SESSION["id_nivel"] = $id_nivel;
        $_SESSION["id_utilizador"] = $id_utilizador;

        if (!$result || mysqli_num_rows($result) == 0) {
        
            $_SESSION['erro'] = 1;
            header("Location: login.php");
            exit();
        }else if ($id_nivel == 4) {
            $_SESSION['erro'] = 2;
            header("Location: login.php");
            exit();
        }

        header("Location: home.php");
        exit();
    }
?>