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
    <title>FelixBus - Administração</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="gerir_alertas.php">Alertas</a></div>
            <div class="link"> <a href="gerir_rotas.php">Rotas</a></div>
            <div class="link"> <a href="gerir_utilizadores.php">Utilizadores</a></div>
            <div class="link"> <a href="auditoria_transacoes.php">Auditoria</a></div>
            <div class="link"> <a href="perfil_admin.php">Meu Perfil</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área de Administrador</div>
        </div>
    </nav>

    <section>
        <div class="admin-dashboard">
            <h1>Painel de Administração</h1>

            <div class="dashboard-cards">
                <div class="card">
                    <h2>Alertas</h2>
                    <p>Gerencie os alertas e promoções exibidos no site.</p>
                    <a href="gerir_alertas.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Rotas</h2>
                    <p>Gerencie as rotas, horários e preços das viagens.</p>
                    <a href="gerir_rotas.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Utilizadores</h2>
                    <p>Gerencie os utilizadores do sistema.</p>
                    <a href="gerir_utilizadores.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Auditoria</h2>
                    <p>Visualize todas as transações financeiras do sistema.</p>
                    <a href="auditoria_transacoes.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Meu Perfil</h2>
                    <p>Visualize e edite seus dados pessoais.</p>
                    <a href="perfil_admin.php" class="card-btn">Acessar</a>
                </div>

                <div class="card">
                    <h2>Estatísticas</h2>
                    <p>Visualize estatísticas de vendas e utilização do sistema.</p>
                    <a href="#" class="card-btn disabled">Em breve</a>
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
</body>
</html>

