<?php
session_start();
include '../basedados/basedados.h';
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale = 1.0">
<link rel="stylesheet" href="index.css">
<title>FelixBus</title>

<head>

<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="perfil.php">Perfil</a></div>
            <div class="link"> <a href="carteira.php">Carteira</a></div>
            <div class="link"> <a href="bilhetes.php">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></div>
        </div>
    </nav>
    <section>
    </section>
</body>
</head>

</html>