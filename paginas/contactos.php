<?php
    session_start();
    include '../basedados/basedados.h';
    if (isset($_SESSION["id_nivel"]) && $_SESSION["id_nivel"] > 0){
        header("Location: erro.php");
    }
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="contactos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>FelixBus - Contactos</title>
</head>
<body>
    <nav>
        <a href="index.php" class="logo">
            <h1>Felix<span>Bus</span></h1>
        </a>
        <div class="links">
            <div class="link"><a href="index.php">HOME</a></div>
            <div class="link"><a href="servicos.php">SERVIÇOS</a></div>
            <div class="link"><a href="contactos.php">CONTACTOS</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="login.php"><button>Login</button></a></div>
            <div class="btn"><a href="registar.php"><button>Registar</button></a></div>
        </div>
    </nav>

    <section class="main-section">
        <div class="hero-content">
            <h1>Contactos</h1>
        </div>

        <div class="contactos-container">
            <div class="contact-card">
                <div class="icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3>Localização</h3>
                <div class="info-block">
                    <h4>Sede Principal</h4>
                    <p>Av. do Empresário</p>
                    <p>Campus da Talagueira, Zona do Lazer</p>
                    <p>6000-767 Castelo Branco</p>
                    <p>Portugal</p>
                </div>
            </div>

            <div class="contact-card">
                <div class="icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h3>Contactos</h3>
                <div class="info-block">
                    <p><strong>Telefone Geral:</strong> +351 272 339 300</p>
                    <p><strong>Email:</strong> info@felixbus.pt</p>
                </div>
            </div>

            <div class="contact-card">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Horários</h3>
                <div class="info-block">
                    <h4>Sede Principal</h4>
                    <p>Segunda a Sexta: 9h - 18h</p>
                </div>
            </div>
        </div>
    </section>
</body>
</html>



