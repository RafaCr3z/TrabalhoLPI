<?php
    session_start();
    include '../basedados/basedados.h';

<<<<<<< HEAD
    session_destroy();
    header("Location: index.php");
    exit();

?>
=======
    // Encerrar a sessão ao clicar no botão
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        session_destroy();
        header("Location: index.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .error-container {
            background-color: #ffffff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 300px;
        }

        h2 {
            color: #ff0000;
            margin-bottom: 20px;
        }

        p {
            margin-bottom: 20px;
            color: #555555;
        }

        button {
            padding: 10px 20px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h2>Erro</h2>
        <p>Ocorreu um erro. Clique no botão abaixo para voltar ao início.</p>
        <form method="post">
            <button type="submit">Voltar</button>
        </form>
    </div>
</body>
</html>
>>>>>>> 5a4d63a1c91c54f7b6584aaeb515c4c4a9021c08
