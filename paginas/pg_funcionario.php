<?php
session_start();
include '../basedados/basedados.h';

// Verificar se o usuário é funcionário
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 2) {
    header("Location: erro.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="pg_funcionario.css">
    <title>FelixBus - Área do Funcionário</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="gerir_carteiras.php">Gestão de Carteiras</a></div>
            <div class="link"> <a href="gerir_bilhetes_func.php">Gestão de Bilhetes</a></div>
            <div class="link"> <a href="perfil_funcionario.php">Meu Perfil</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área de Funcionário</div>
        </div>
    </nav>

    <section>
        <div class="admin-dashboard">
            <h1>Área do Funcionário</h1>

            <div class="dashboard-cards">
                <div class="card">
                    <h2>Gestão de Carteiras</h2>
                    <p>Gerencie o saldo das carteiras dos clientes.</p>
                    <a href="gerir_carteiras.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Gestão de Bilhetes</h2>
                    <p>Compre bilhetes para clientes e gerencie bilhetes existentes.</p>
                    <a href="gerir_bilhetes_func.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Meu Perfil</h2>
                    <p>Visualize e edite seus dados pessoais.</p>
                    <a href="perfil_funcionario.php" class="card-btn">Acessar</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Adicionar antes do fechamento do </body> -->
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>