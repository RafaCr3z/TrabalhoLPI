<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_utilizador'];

$stmt = mysqli_prepare($conn, "SELECT nome, email, nif, telefone, data_criacao FROM utilizadores WHERE id_utilizador = ?");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - O Meu Perfil</title>
    <link rel="stylesheet" href="../../jsp/paginas/perfil_cliente.css">
</head>
<body>
    <header class="navbar">
        <h1>O Meu Perfil de Cliente</h1>
        <nav>
            <a href="pg_cliente.php">Área de Cliente</a>
            <a href="carteira_cliente.php">Carteira</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <div class="card-perfil">
            <h2>Dados Pessoais</h2>
            <p><b>Nome:</b> <?= htmlspecialchars($user['nome']) ?></p>
            <p><b>Email:</b> <?= htmlspecialchars($user['email']) ?></p>
            <p><b>NIF:</b> <?= htmlspecialchars($user['nif'] ?: 'Não registado') ?></p>
            <p><b>Telefone:</b> <?= htmlspecialchars($user['telefone'] ?: 'Não registado') ?></p>
            <p><b>Membro desde:</b> <?= htmlspecialchars($user['data_criacao']) ?></p>
            <a href="editar_perfil.php" class="btn-primary">Editar Informações</a>
        </div>
    </main>
</body>
</html>
