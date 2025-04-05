<?php
session_start();
include '../basedados/basedados.h';

if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}

$id_cliente = $_SESSION["id_utilizador"];
$operacao_realizada = false; // Variável de controlo

// Exibe o saldo atual
$sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
$result_saldo = mysqli_query($conn, $sql_saldo);
$row_saldo = mysqli_fetch_assoc($result_saldo);

// Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor = $_POST["valor"];
    $operacao = $_POST["operacao"];

    if ($valor <= 0) {
        echo "<script>alert('Valor inválido.');</script>";
    } else {
        if ($operacao == "adicionar") {
            $sql_atualiza = "UPDATE carteiras SET saldo = saldo + $valor WHERE id_cliente = $id_cliente";
        } else if ($operacao == "retirar" && $row_saldo['saldo'] >= $valor) {
            $sql_atualiza = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
        } else {
            echo "<script>alert('Saldo insuficiente.');</script>";
            header("Location: carteira.php");
            exit();
        }

        if (mysqli_query($conn, $sql_atualiza)) {
            $operacao_realizada = true; 
            echo "<script>alert('Operação realizada com sucesso.');</script>";
            echo "<script>window.location.href = 'carteira.php?success=1';</script>"; // 
            exit();
        } else {
            echo "<script>alert('Erro ao realizar a operação: " . mysqli_error($conn) . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="carteira.css">
    <title>FelixBus</title>
    <link rel="stylesheet" href="index.css">
    <title>FelixBus</title>
</head>
<body>

    <?php if (!$operacao_realizada): ?> 
        <nav>
            <div class="logo">
                <h1>Felix<span>Bus</span></h1>
            </div>
            <div class="links">
                <div class="link"> <a href="perfil.php">Perfil</a></div>
                <div class="link"> <a href="cliente.php">Página Inicial</a></div>
                <div class="link"> <a href="bilhetes.php">Bilhetes</a></div>
            </div>
            <div class="buttons">
                <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            </div>
        </nav>
    <?php endif; ?>

    <section>
        <div class="carteira-container">
            <h2>Carteira</h2>
            <p>Saldo atual:
                <?php
                if ($row_saldo) {
                    echo "€" . number_format($row_saldo['saldo'], 2, ',', '.');
                } else {
                    echo "Erro ao obter saldo.";
                }
                ?>
            </p>
            <form action="carteira.php" method="post">
                <label for="valor">Valor:</label>
                <input type="number" id="valor" name="valor" step="0.01" required>
                <label for="operacao">Operação:</label>
                <select id="operacao" name="operacao" required>
                    <option value="adicionar">Adicionar</option>
                    <option value="retirar">Retirar</option>
                </select>
                <button type="submit">Confirmar</button>
            </form>
        </div>
    </section>
</body>
</html>
