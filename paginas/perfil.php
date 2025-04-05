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
    <title>FelixBus - Meu Perfil</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        nav {
            background-color: #333;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav .logo h1 {
            margin: 0;
            font-size: 1.5em;
        }

        nav .logo span {
            color: #ff6347;
            
        }

        nav .links {
            display: flex;
            gap: 20px;
        }

        nav .link a {
            color: white;
            text-decoration: none;
            font-size: 1em;
        }

        nav .buttons .btn a {
            text-decoration: none;
        }

        nav .buttons button {
            background-color: #ff6347;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        nav .buttons button:hover {
            background-color: #e55347;
        }

        section {
            max-width: 800px;
            margin: 30px auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        section h2 {
            text-align: center;
            color: #333;
        }

        section p {
            font-size: 1.1em;
            color: #555;
            margin: 10px 0;
        }

        section p strong {
            color: #333;
        }

   
        .btn-edit {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-edit button {
            background-color: #e55347; 
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 1em;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

 
    </style>
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
