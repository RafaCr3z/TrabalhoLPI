<?php
    session_start();
    include '../basedados/basedados.h';

    if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
        header("Location: erro.php");
        exit();
    }

    $id_utilizador = $_SESSION["id_utilizador"];


    $sql = "SELECT nome, email, telemovel, morada FROM utilizadores WHERE id = $id_utilizador";
    $resultado = mysqli_query($conn, $sql);

    if (!$resultado) {
        die("Erro na consulta: " . mysqli_error($conn));
    }   

    if (mysqli_num_rows($resultado) > 0) {
        $dados = mysqli_fetch_assoc($resultado);
    } else {
        die("Nenhum dado encontrado para o utilizador.");
    }

    if (!$dados) {
        die("Nenhum dado encontrado para o utilizador.");
    }


    mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="perfil.css">
    <title>FelixBus - Meu Perfil</title>
</head>

<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="cliente.php">Página Inicial</a></div>
            <div class="link"> <a href="carteira.php">Carteira</a></div>
            <div class="link"> <a href="bilhetes.php">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
        </div>
    </nav>
    <section>
        <h2>Meu Perfil</h2>
        <p><strong>Nome:</strong> <?php echo htmlspecialchars($dados['nome']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($dados['email']); ?></p>
        <p><strong>Telemóvel:</strong> <?php echo htmlspecialchars($dados['telemovel']); ?></p>
        <p><strong>Morada:</strong> <?php echo nl2br(htmlspecialchars($dados['morada'])); ?></p>
        
        <!-- Botão de Editar Perfil alinhado à direita -->
        <div class="btn-edit">
            <a href="editarPerfil.php"><button>Editar Perfil</button></a>
        </div>
    </section>
</body>

</html>
