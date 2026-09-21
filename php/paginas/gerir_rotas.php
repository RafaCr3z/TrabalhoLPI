<?php
session_start();
require_once __DIR__ . '/../basedados/ligabd.php';

if (!isset($_SESSION['id_utilizador']) || $_SESSION['id_perfil'] != 4) {
    header("Location: login.php");
    exit;
}

$msg = "";
$erro = "";

// Adicionar nova rota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_rota'])) {
    $origem = trim($_POST['origem'] ?? '');
    $destino = trim($_POST['destino'] ?? '');
    $duracao = intval($_POST['duracao'] ?? 0);
    $preco = floatval($_POST['preco'] ?? 0.0);

    if (!empty($origem) && !empty($destino) && $duracao > 0 && $preco > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO rotas (origem, destino, duracao_estimada_min, preco_base) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssid", $origem, $destino, $duracao, $preco);
        if (mysqli_stmt_execute($stmt)) {
            $msg = "Nova rota adicionada com sucesso!";
        } else {
            $erro = "Erro ao inserir rota.";
        }
    } else {
        $erro = "Preencha todos os campos corretamente.";
    }
}

// Listar rotas existentes
$rotas = mysqli_query($conn, "SELECT * FROM rotas ORDER BY origem ASC, destino ASC");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Gestão de Rotas</title>
    <link rel="stylesheet" href="../../jsp/paginas/gerir_rotas.css">
</head>
<body>
    <header class="navbar">
        <h1>Gestão de Rotas e Tarifas</h1>
        <nav>
            <a href="pg_admin.php">Painel Admin</a>
            <a href="gerir_utilizadores.php">Utilizadores</a>
            <a href="logout.php">Sair</a>
        </nav>
    </header>

    <main class="container">
        <?php if (!empty($msg)): ?>
            <div class="alert success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <section class="form-secao">
            <h3>Adicionar Nova Rota</h3>
            <form action="gerir_rotas.php" method="POST" class="form-inline">
                <input type="text" name="origem" placeholder="Cidade Origem (ex.: Lisboa)" required>
                <input type="text" name="destino" placeholder="Cidade Destino (ex.: Castelo Branco)" required>
                <input type="number" name="duracao" placeholder="Duração (min)" min="15" required>
                <input type="number" name="preco" placeholder="Preço Base (€)" min="1" step="0.50" required>
                <button type="submit" name="adicionar_rota" class="btn-primary">Criar Rota</button>
            </form>
        </section>

        <section class="tabela-secao">
            <h3>Rotas Cadastradas</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>Duração Estimada</th>
                        <th>Preço Base</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = mysqli_fetch_assoc($rotas)): ?>
                        <tr>
                            <td><?= $r['id_rota'] ?></td>
                            <td><b><?= htmlspecialchars($r['origem']) ?></b></td>
                            <td><b><?= htmlspecialchars($r['destino']) ?></b></td>
                            <td><?= $r['duracao_estimada_min'] ?> min</td>
                            <td><?= number_format($r['preco_base'], 2) ?> €</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
