<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*" %>
<%@ include file="../basedados/basedados.jsp" %>

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
        <a href="index.jsp" class="logo">
            <h1>Felix<span>Bus</span></h1>
        </a>
        <div class="links">
            <div class="link"><a href="index.jsp">HOME</a></div>
            <div class="link"><a href="servicos.jsp">SERVIÇOS</a></div>
            <div class="link"><a href="contactos.jsp">CONTACTOS</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="login.jsp"><button>Login</button></a></div>
            <div class="btn"><a href="registar.jsp"><button class="register-btn">Registar</button></a></div>
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
                <p>Viagens diárias que ligam as principais cidades de Portugal com todo o conforto e pontualidade.</p>
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
                <p>Adquira os seus bilhetes de forma rápida e segura através da nossa plataforma.</p>
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
                <p>Soluções especializadas para grupos e excursões.</p>
                <ul>
                    <li>Tarifas especiais</li>
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
                    <p>Autocarros modernos equipados com bancos reclináveis e ar condicionado.</p>
                </div>
                <div class="feature">
                    <h4>Segurança</h4>
                    <p>Frota com manutenção regular e motoristas profissionais experientes.</p>
                </div>
                <div class="feature">
                    <h4>Pontualidade</h4>
                    <p>Compromisso rigoroso com horários e percursos estabelecidos.</p>
                </div>
                <div class="feature">
                    <h4>Cobertura</h4>
                    <p>Extensa rede de percursos em todo o território português.</p>
                </div>
            </div>
        </div>
    </section>
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>




