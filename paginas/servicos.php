<?php
    session_start();
    include '../basedados/basedados.h';

    // Se o usuário estiver logado como cliente, funcionário ou admin, redireciona para a página de erro
    if (isset($_SESSION["id_nivel"]) && $_SESSION["id_nivel"] > 0){
        header("Location: erro.php");
    }
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
            <div class="btn"><a href="registar.php"><button>Registar</button></a></div>
        </div>
    </nav>

    <section>
        <h1>Nossos Serviços</h1>

        <div class="servicos-container">
            <div class="servico-card">
                <div class="icon">
                    <i class="fas fa-bus"></i>
                </div>
                <h3>Transporte Regular</h3>
                <p>Oferecemos serviços de transporte regular entre as principais cidades de Portugal, com horários frequentes e preços acessíveis. Nossas rotas são cuidadosamente planejadas para garantir conforto e pontualidade.</p>
            </div>

            <div class="servico-card">
                <div class="icon">
                    <i class="fas fa-suitcase"></i>
                </div>
                <h3>Viagens Turísticas</h3>
                <p>Descubra os melhores destinos turísticos de Portugal com nossos pacotes especiais. Oferecemos rotas para praias, montanhas, cidades históricas e muito mais, com guias informativos sobre cada destino.</p>
            </div>

            <div class="servico-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Fretamento para Grupos</h3>
                <p>Precisa transportar um grupo? Oferecemos serviços de fretamento para eventos corporativos, excursões escolares, casamentos e outros eventos. Entre em contato para um orçamento personalizado.</p>
            </div>

            <div class="servico-card">
                <div class="icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3>Bilhetes Online</h3>
                <p>Compre seus bilhetes diretamente pelo nosso site ou aplicativo. Oferecemos um sistema de carteira digital para facilitar suas compras e garantir descontos exclusivos para clientes frequentes.</p>
            </div>

            <div class="servico-card">
                <div class="icon">
                    <i class="fas fa-wheelchair"></i>
                </div>
                <h3>Acessibilidade</h3>
                <p>Todos os nossos ônibus são equipados com recursos de acessibilidade para garantir uma viagem confortável para todos os passageiros, incluindo rampas de acesso e espaços reservados.</p>
            </div>

            <div class="servico-card">
                <div class="icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3>Suporte ao Cliente</h3>
                <p>Nossa equipe de atendimento está disponível para ajudar com informações sobre rotas, horários, preços e qualquer outra dúvida que você possa ter. Estamos comprometidos com a sua satisfação.</p>
            </div>
        </div>

        <div class="vantagens-container">
            <h2>Por que escolher a FelixBus?</h2>

            <div class="vantagens-lista">
                <div class="vantagem-item">
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="texto">
                        <h4>Pontualidade</h4>
                        <p>Nossos ônibus seguem rigorosamente os horários programados, garantindo que você chegue ao seu destino no tempo previsto.</p>
                    </div>
                </div>

                <div class="vantagem-item">
                    <div class="icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="texto">
                        <h4>Segurança</h4>
                        <p>Todos os nossos veículos passam por manutenção regular e são conduzidos por motoristas experientes e treinados.</p>
                    </div>
                </div>

                <div class="vantagem-item">
                    <div class="icon">
                        <i class="fas fa-couch"></i>
                    </div>
                    <div class="texto">
                        <h4>Conforto</h4>
                        <p>Assentos espaçosos, ar-condicionado e Wi-Fi gratuito para tornar sua viagem o mais confortável possível.</p>
                    </div>
                </div>

                <div class="vantagem-item">
                    <div class="icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="texto">
                        <h4>Preços Competitivos</h4>
                        <p>Oferecemos tarifas acessíveis e promoções frequentes para que você possa viajar sem comprometer seu orçamento.</p>
                    </div>
                </div>

                <div class="vantagem-item">
                    <div class="icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <div class="texto">
                        <h4>Sustentabilidade</h4>
                        <p>Nossa frota moderna é projetada para minimizar o impacto ambiental, com veículos de baixa emissão.</p>
                    </div>
                </div>

                <div class="vantagem-item">
                    <div class="icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="texto">
                        <h4>Ampla Cobertura</h4>
                        <p>Conectamos as principais cidades e regiões de Portugal, oferecendo uma rede abrangente de destinos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
