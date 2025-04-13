<?php
    session_start();
    include '../basedados/basedados.h';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="contactos.css">
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
            <p>Encontre-nos facilmente</p>
        </div>

        <div class="info-container">
            <!-- Localização -->
            <div class="info-section">
                <h2>Localização</h2>
                <div class="info-content">
                    <div class="address-info">
                        <h3>Sede Principal</h3>
                        <p>Av. do Empresário</p>
                        <p>Campus da Talagueira, Zona do Lazer</p>
                        <p>6000-767 Castelo Branco</p>
                        <p>Portugal</p>
                    </div>
                    <div class="map">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3068.7436261522587!2d-7.514499684529092!3d39.82301797943851!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd3d47c9c95b4d65%3A0x402e92d784f5baa7!2sAv.%20do%20Empres%C3%A1rio%2C%206000-767%20Castelo%20Branco!5e0!3m2!1spt-PT!2spt!4v1635789245684!5m2!1spt-PT!2spt" 
                            width="100%" 
                            height="300" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
            </div>

            <!-- Horários -->
            <div class="info-section">
                <h2>Horário de Funcionamento</h2>
                <div class="info-content">
                    <div class="schedule-info">
                        <div class="schedule-item">
                            <h3>Bilheteira Central</h3>
                            <table class="schedule-table">
                                <tr>
                                    <td>Segunda a Sexta-feira</td>
                                    <td>07h00 - 20h00</td>
                                </tr>
                                <tr>
                                    <td>Sábados</td>
                                    <td>08h00 - 19h00</td>
                                </tr>
                                <tr>
                                    <td>Domingos e Feriados</td>
                                    <td>09h00 - 18h00</td>
                                </tr>
                            </table>
                        </div>
                        <div class="schedule-item">
                            <h3>Apoio ao Cliente</h3>
                            <table class="schedule-table">
                                <tr>
                                    <td>Segunda a Sexta-feira</td>
                                    <td>08h00 - 19h00</td>
                                </tr>
                                <tr>
                                    <td>Sábados</td>
                                    <td>09h00 - 17h00</td>
                                </tr>
                                <tr>
                                    <td>Domingos e Feriados</td>
                                    <td>10h00 - 16h00</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contactos -->
            <div class="info-section">
                <h2>Contactos</h2>
                <div class="info-content">
                    <div class="contact-info">
                        <div class="contact-item">
                            <h3>Linha Geral</h3>
                            <p>Telefone: 272 339 300</p>
                            <p>Correio eletrónico: geral@felixbus.pt</p>
                        </div>
                        <div class="contact-item">
                            <h3>Apoio ao Cliente</h3>
                            <p>Telefone: 272 339 300</p>
                            <p>Correio eletrónico: apoio@felixbus.pt</p>
                        </div>
                        <div class="contact-item">
                            <h3>Linha de Urgência (24h)</h3>
                            <p>Telefone: 272 339 300</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>


