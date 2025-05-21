<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
// Verificar se o utilizador é cliente
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 3) {
    response.sendRedirect("erro.jsp");
    return;
}

// Nome do cliente para exibição
String nomeCliente = (String)session.getAttribute("nome");
if (nomeCliente == null) {
    nomeCliente = "Cliente";
}
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="cliente_style.css">
    <title>FelixBus - Área do Cliente</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="perfil_cliente.jsp">Perfil</a></div>
            <div class="link"> <a href="carteira_cliente.jsp">Carteira</a></div>
            <div class="link"> <a href="bilhetes_cliente.jsp">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
        </div>
    </nav>
    <section>
        <h1>Bem-vindo à Área do Cliente</h1>

        <div class="welcome-container">
            <p>Olá, <%= nomeCliente %>! Bem-vindo à sua área pessoal no FelixBus.</p>
            <p>Aqui você pode gerenciar sua carteira, comprar bilhetes e visualizar suas informações pessoais.</p>
        </div>

        <div class="options-container">
            <div class="option-card">
                <h3>Meu Perfil</h3>
                <p>Visualize e edite suas informações pessoais.</p>
                <a href="perfil_cliente.jsp">Aceder</a>
            </div>

            <div class="option-card">
                <h3>A Minha Carteira</h3>
                <p>Faça a gestão do seu saldo e visualize o histórico de transações.</p>
                <a href="carteira_cliente.jsp">Aceder</a>
            </div>

            <div class="option-card">
                <h3>Os Meus Bilhetes</h3>
                <p>Compre novos bilhetes e visualize os bilhetes adquiridos.</p>
                <a href="bilhetes_cliente.jsp">Aceder</a>
            </div>
        </div>
    </section>

    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>
