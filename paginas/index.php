<?php
    include '../basedados/basedados.h';
    session_start();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="index.css">
    <title>FelixBus - Viaje com Conforto</title>
</head>
<body>
    <header class="bg-primary text-white p-3">
        <h1>FelixBus - Viaje com Conforto</h1>
    </header>

    <div class="container mt-4">
        <!-- Alertas -->
        <div class="alert alert-warning">
            <h3>🚨 Alertas</h3>
            <?php
                $conn = conectar();
                $sql = "SELECT mensagem FROM alertas WHERE data_inicio <= NOW() AND data_fim >= NOW()";
                $result = mysqli_query($conn, $sql);
                while ($alerta = mysqli_fetch_assoc($result)) {
                    echo "<div class='mb-2'>⚠️ " . htmlspecialchars($alerta['mensagem']) . "</div>";
                }
                fecharConexao($conn);
            ?>
        </div>

        <!-- Dados da Empresa -->
        <div class="card mb-4">
            <div class="card-body">
                <h3>📌 Contactos</h3>
                <p>📍 Localização: Lisboa, Portugal</p>
                <p>📞 Telefone: +351 123 456 789</p>
                <p>🕒 Horário: 08h00 - 20h00 (Segunda a Sexta)</p>
            </div>
        </div>

        <!-- Rotas Disponíveis -->
        <div class="card">
            <div class="card-body">
                <h3>🚌 Rotas Disponíveis</h3>
                <?php
                    $conn = conectar();
                    $sql = "SELECT r.origem, r.destino, h.horario_partida, r.preco 
                            FROM rotas r
                            INNER JOIN horarios h ON r.id = h.id_rota
                            WHERE r.disponivel = 1";
                    $result = mysqli_query($conn, $sql);
                    while ($rota = mysqli_fetch_assoc($result)) {
                        echo "<div class='mb-3 p-2 border'>";
                        echo "<h5>" . htmlspecialchars($rota['origem']) . " → " . htmlspecialchars($rota['destino']) . "</h5>";
                        echo "<p>⏰ Horário: " . htmlspecialchars($rota['horario_partida']) . "</p>";
                        echo "<p>💰 Preço: " . number_format($rota['preco'], 2) . "€</p>";
                        echo "</div>";
                    }
                    fecharConexao($conn);
                ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white p-3 mt-4">
        <p>&copy; 2024 FelixBus - João Resina & Rafael Cruz</p>
    </footer>
</body>
</html>
