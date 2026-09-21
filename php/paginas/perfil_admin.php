<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador']) || $_SESSION['id_perfil'] != 4) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['id_utilizador'];
$stmt = mysqli_prepare($conn, "SELECT nome, email, data_criacao FROM utilizadores WHERE id_utilizador = ?");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Perfil Administrador</title>
    <link rel="stylesheet" href="../../jsp/paginas/perfil_admin.css">
</head>
<body>
    <header class="navbar">
        <h1>Perfil de Administrador</h1>
        <nav>
            <a href="pg_admin.php">Painel Admin</a>
            <a href="gerir_utilizadores.php">Utilizadores</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <div class="card-perfil">
            <h2>Conta de Administrador</h2>
            <p><b>Nome:</b> <?= htmlspecialchars($admin['nome']) ?></p>
            <p><b>Email:</b> <?= htmlspecialchars($admin['email']) ?></p>
            <p><b>Nível de Privilégios:</b> <span class="badge-admin">Super Administrador (RBAC Nível 4)</span></p>
            <p><b>Membro desde:</b> <?= htmlspecialchars($admin['data_criacao']) ?></p>
        </div>
    </main>
</body>
</html>
