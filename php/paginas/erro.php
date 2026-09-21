<?php
session_start();
$codigo = $_GET['code'] ?? '404';
$mensagem = $_GET['msg'] ?? 'A página ou recurso solicitado não foi encontrado.';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Erro <?= htmlspecialchars($codigo) ?></title>
    <link rel="stylesheet" href="../../jsp/paginas/erro.css">
</head>
<body>
    <div class="container-erro">
        <h1>Erro <?= htmlspecialchars($codigo) ?></h1>
        <p><?= htmlspecialchars($mensagem) ?></p>
        <a href="index.php" class="btn-primary">Voltar à Página Inicial</a>
    </div>
</body>
</html>
