<?php
    session_start();
    include '../basedados/basedados.h';
    if (isset($_SESSION["id_nivel"]) > 0) {
        header("Location: erro.php");
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
<<<<<<< HEAD
        <a href="index.php" class="logo">
            <h1>Felix<span>Bus</span></h1>
        </a>
        <div class= "links">
            <div class = "link"> <a href="index.php">HOME</a></div>
            <div class = "link"> <a href="servicos.php">SERVIÇOS</a></div>
            <div class = "link"> <a href="contactos.php">CONTACTOS</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="login.php"><button>Login</button></a></div>
            <div class="btn"><a href="registar.php"><button>Registar</button></a></div>
        </div>
        </nav>
<section>
=======
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="">HOME</a></div>
            <div class="link"> <a href="">SERVIÇOS</a></div>
            <div class="link"> <a href="">CONTACTOS</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="login.php"><button>Login</button></div>
            <div class="btn"> <a href="registar.php"><button>Sign up</button></div>
        </div>
    </nav>
    <section>
>>>>>>> 3a0a63a66d89e49aa57a9794bb882dcfd3fd04f4
    </section>
</body>
</head>

</html>
