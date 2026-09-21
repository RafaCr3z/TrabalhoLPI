<?php
session_start();
$enviado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enviado = true;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>FelixBus - Contactos</title>
    <link rel="stylesheet" href="../../jsp/paginas/contactos.css">
</head>
<body>
    <header class="navbar">
        <h1>Contactos e Apoio ao Cliente</h1>
        <nav>
            <a href="index.php">Início</a>
            <a href="servicos.php">Serviços</a>
        </nav>
    </header>

    <main class="container">
        <h2>Fale Connosco</h2>
        <?php if ($enviado): ?>
            <div class="alert success">A sua mensagem foi registada com sucesso! Responderemos brevemente.</div>
        <?php endif; ?>

        <form action="contactos.php" method="POST">
            <div class="campo">
                <label>Nome:</label>
                <input type="text" name="nome" required>
            </div>
            <div class="campo">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="campo">
                <label>Assunto:</label>
                <input type="text" name="assunto" required>
            </div>
            <div class="campo">
                <label>Mensagem:</label>
                <textarea name="mensagem" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn-primary">Enviar Mensagem</button>
        </form>
    </main>
</body>
</html>
