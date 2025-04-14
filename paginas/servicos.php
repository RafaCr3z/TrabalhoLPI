<?php
    session_start();
    include '../basedados/basedados.h';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="servicos.css">
    <title>FelixBus - Serviços</title>
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
            <div class="btn"><a href="registar.php"><button class="register-btn">Registar</button></a></div>
        </div>
    </nav>

    <section class="main-section">
        <div class="hero-content">
            <h1>Os Nossos Serviços</h1>
        </div>

        <div class="services-container">
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <h3>Viagens Regulares</h3>
                <p>Carreiras diárias que ligam as principais cidades de Portugal com conforto e pontualidade.</p>
                <ul>
                    <li>Horários flexíveis</li>
                    <li>Rotas estratégicas</li>
                    <li>Preços competitivos</li>
                </ul>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h3>Carteira Digital</h3>
                <p>Sistema de pagamento digital para maior comodidade nas suas viagens.</p>
                <ul>
                    <li>Carregamentos online</li>
                    <li>Gestão de saldo</li>
                    <li>Pagamentos seguros</li>
                </ul>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3>Bilhetes Online</h3>
                <p>Compre os seus bilhetes de forma rápida e segura através da nossa plataforma.</p>
                <ul>
                    <li>Reservas antecipadas</li>
                    <li>Bilhetes digitais</li>
                    <li>Cancelamento flexível</li>
                </ul>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Viagens em Grupo</h3>
                <p>Soluções especiais para grupos e excursões.</p>
                <ul>
                    <li>Preços especiais</li>
                    <li>Reserva de autocarros</li>
                    <li>Atendimento personalizado</li>
                </ul>
            </div>
        </div>

        <div class="features-section">
            <h2>Porquê escolher a FelixBus?</h2>
            <div class="features-grid">
                <div class="feature">
                    <h4>Conforto</h4>
                    <p>Autocarros modernos com bancos reclináveis e ar condicionado.</p>
                </div>
                <div class="feature">
                    <h4>Segurança</h4>
                    <p>Frota com manutenção regular e motoristas experientes.</p>
                </div>
                <div class="feature">
                    <h4>Pontualidade</h4>
                    <p>Compromisso com horários e itinerários estabelecidos.</p>
                </div>
                <div class="feature">
                    <h4>Cobertura</h4>
                    <p>Vasta rede de rotas em todo o território português.</p>
                </div>
            </div>
        </div>
    </section>
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>


