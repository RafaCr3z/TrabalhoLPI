<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*" %>
<%@ include file="../basedados/basedados.jsp" %>

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

    <div class="container">
        <div class="columns">
            <!-- Coluna 1: Localização -->
            <div class="column">
                <h2>Localização</h2>
                <p>Av. do Empresário</p>
                <p>Campus da Talagueira, Zona do Lazer</p>
                <p>6000-767 Castelo Branco</p>
                <p>Portugal</p>
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3068.7436261522587!2d-7.514499684529092!3d39.82301797943851!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd3d47c9c95b4d65%3A0x402e92d784f5baa7!2sAv.%20do%20Empres%C3%A1rio%2C%206000-767%20Castelo%20Branco!5e0!3m2!1spt-PT!2spt!4v1635789245684!5m2!1spt-PT!2spt" 
                    width="100%" 
                    height="200" 
                    style="border:0;" 
                    allowfullscreen="">
                </iframe>
            </div>

            <!-- Coluna 2: Horário -->
            <div class="column">
                <h2>Horário</h2>
                <div class="horario-box">
                    <h3>Bilheteira Central</h3>
                    <p>Segunda a Sexta-feira: 07h00 - 20h00</p>
                    <p>Sábados: 08h00 - 19h00</p>
                    <p>Domingos e Feriados: 09h00 - 18h00</p>
                </div>
                
                <div class="horario-box">
                    <h3>Apoio ao Cliente</h3>
                    <p>Segunda a Sexta-feira: 08h00 - 19h00</p>
                    <p>Sábados: 09h00 - 17h00</p>
                    <p>Domingos e Feriados: 10h00 - 16h00</p>
                </div>
            </div>

            <!-- Coluna 3: Contactos -->
            <div class="column">
                <h2>Contactos</h2>
                <div class="contacto-box">
                    <h3>Linha Geral</h3>
                    <p>📞 272 339 300</p>
                    <p>✉️ geral@felixbus.pt</p>
                </div>

                <div class="contacto-box">
                    <h3>Apoio ao Cliente</h3>
                    <p>📞 272 339 300</p>
                    <p>✉️ apoio@felixbus.pt</p>
                </div>

                <div class="contacto-box">
                    <h3>Linha de Urgência (24h)</h3>
                    <p>📞 272 339 300</p>
                </div>
            </div>
        </div>
    </div>
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>


