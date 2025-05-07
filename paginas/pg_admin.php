<?php
    session_start();
    include '../basedados/basedados.h';
    if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
        header("Location: erro.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="pg_admin.css">
    <title>FelixBus - Área de Administração</title>
</head>
<body>
    <!--NAVBAR -->
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Administrador</div>
        </div>
    </nav>

    <!--SECTION -->
    <section>
        <div class="admin-dashboard">
            <h1>Painel do Administração</h1>

            <div class="dashboard-cards">
                <div class="card">
                    <h2>Alertas</h2>
                    <p>Gerencie os alertas e promoções exibidos no site.</p>
                    <a href="gerir_alertas.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Rotas</h2>
                    <p>Faça a gestão das rotas, horários e preços das viagens.</p>
                    <a href="gerir_rotas.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Utilizadores</h2>
                    <p>Faça a gestão dos utilizadores do sistema.</p>
                    <a href="gerir_utilizadores.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Gestão de Carteiras</h2>
                    <p>Faça a gestão do saldo das carteiras dos clientes.</p>
                    <a href="gerir_carteiras.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Gestão de Bilhetes</h2>
                    <p>Compre bilhetes para clientes e faça a gestão dos bilhetes existentes.</p>
                    <a href="gerir_bilhetes.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Auditoria</h2>
                    <p>Visualize todas as transações financeiras do sistema.</p>
                    <a href="auditoria_transacoes.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>O Meu Perfil</h2>
                    <p>Visualize e edite os seus dados pessoais.</p>
                    <a href="perfil_admin.php" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Estatísticas</h2>
                    <p>Visualize estatísticas de vendas e utilização do sistema.</p>
                    <a href="#" class="card-btn disabled">Brevemente</a>
                </div>
            </div>

            <div class="resumo-financeiro">
                <h2>Resumo Financeiro</h2>
                <div class="resumo-cards">
                    <div class="resumo-card">
                        <h3>Saldo FelixBus</h3>
                        <p class="valor">€<?php
                            $sql_saldo = "SELECT saldo FROM carteira_felixbus LIMIT 1";
                            $result_saldo = mysqli_query($conn, $sql_saldo);
                            $saldo = mysqli_fetch_assoc($result_saldo);
                            echo number_format($saldo['saldo'], 2, ',', '.');
                        ?></p>
                    </div>

                    <div class="resumo-card">
                        <h3>Total de Transações</h3>
                        <p class="valor"><?php
                            $sql_count = "SELECT COUNT(*) as total FROM transacoes";
                            $result_count = mysqli_query($conn, $sql_count);
                            $count = mysqli_fetch_assoc($result_count);
                            echo $count['total'];
                        ?></p>
                    </div>

                    <div class="resumo-card">
                        <h3>Bilhetes Vendidos</h3>
                        <p class="valor"><?php
                            $sql_bilhetes = "SELECT COUNT(*) as total FROM bilhetes";
                            $result_bilhetes = mysqli_query($conn, $sql_bilhetes);
                            $bilhetes = mysqli_fetch_assoc($result_bilhetes);
                            echo $bilhetes['total'];
                        ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!--FOOTER -->
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>



