<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador']) || $_SESSION['id_perfil'] != 4) {
    header("Location: login.php");
    exit;
}

$msg = "";

// Ação de alternar estado (Ativo/Inativo)
if (isset($_GET['toggle_status'])) {
    $target_id = intval($_GET['toggle_status']);
    $stmt = mysqli_prepare($conn, "UPDATE utilizadores SET ativo = IF(ativo=1, 0, 1) WHERE id_utilizador = ? AND id_utilizador != ?");
    mysqli_stmt_bind_param($stmt, "ii", $target_id, $_SESSION['id_utilizador']);
    mysqli_stmt_execute($stmt);
    $msg = "Estado do utilizador atualizado com sucesso.";
}

// Listar todos os utilizadores com os seus perfis
$query_users = "
    SELECT u.id_utilizador, u.nome, u.email, u.nif, u.telefone, u.ativo, u.data_criacao, p.designacao as perfil
    FROM utilizadores u
    JOIN perfis p ON u.id_perfil = p.id_perfil
    ORDER BY u.id_utilizador ASC
";
$users = mysqli_query($conn, $query_users);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Gestão de Utilizadores</title>
    <link rel="stylesheet" href="../../jsp/paginas/gerir_utilizadores.css">
</head>
<body>
    <header class="navbar">
        <h1>Gestão de Utilizadores e Contas</h1>
        <nav>
            <a href="pg_admin.php">Painel Admin</a>
            <a href="gerir_rotas.php">Rotas</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <?php if (!empty($msg)): ?>
            <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Perfil</th>
                    <th>Estado</th>
                    <th>Data de Registo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = mysqli_fetch_assoc($users)): ?>
                    <tr>
                        <td><?= $u['id_utilizador'] ?></td>
                        <td><?= htmlspecialchars($u['nome']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge-perfil"><?= htmlspecialchars($u['perfil']) ?></span></td>
                        <td>
                            <span class="badge-status <?= $u['ativo'] ? 'ativo' : 'inativo' ?>">
                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['data_criacao']) ?></td>
                        <td>
                            <?php if ($u['id_utilizador'] != $_SESSION['id_utilizador']): ?>
                                <a href="gerir_utilizadores.php?toggle_status=<?= $u['id_utilizador'] ?>" class="btn-toggle">
                                    <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
                                </a>
                            <?php else: ?>
                                <em>Conta Atual</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
