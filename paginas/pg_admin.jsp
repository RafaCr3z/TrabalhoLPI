<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
    // Verificar se o utilizador é administrador
    if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
        response.sendRedirect("erro.jsp");
        return;
    }
    
    // Obter conexão com o banco de dados
    Connection conn = null;
    try {
        conn = getConnection();
    } catch (Exception e) {
        e.printStackTrace();
    }
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="pg_admin.css">
    <title>FelixBus - Área de Administração</title>
</head>
<body>
    <!--NAVBAR -->
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Administrador</div>
        </div>
    </nav>

    <!--SECTION -->
    <section>
        <div class="admin-dashboard">
            <h1>Painel do Administração</h1>

            <div class="dashboard-cards">
                <div class="card">
                    <h2>Alertas</h2>
                    <p>Gerencie os alertas e promoções exibidos no site.</p>
                    <a href="gerir_alertas.jsp" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Rotas</h2>
                    <p>Faça a gestão das rotas, horários e preços das viagens.</p>
                    <a href="gerir_rotas.jsp" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Utilizadores</h2>
                    <p>Faça a gestão dos utilizadores do sistema.</p>
                    <a href="gerir_utilizadores.jsp" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>O Meu Perfil</h2>
                    <p>Visualize e edite os seus dados pessoais.</p>
                    <a href="perfil_admin.jsp" class="card-btn">Aceder</a>
                </div>

                <div class="card">
                    <h2>Estatísticas</h2>
                    <p>Visualize estatísticas de vendas e utilização do sistema.</p>
                    <a href="#" class="card-btn disabled">Brevemente</a>
                </div>
            </div>

            <div class="resumo-financeiro">
                <h2>Resumo Financeiro</h2>
                <div class="resumo-cards">
                    <div class="resumo-card">
                        <h3>Saldo FelixBus</h3>
                        <p class="valor">€<%
                            String saldoFormatado = "0,00";
                            try {
                                Statement stmt = conn.createStatement();
                                ResultSet rs = stmt.executeQuery("SELECT saldo FROM carteira_felixbus LIMIT 1");
                                if (rs.next()) {
                                    double saldo = rs.getDouble("saldo");
                                    saldoFormatado = String.format("%,.2f", saldo).replace('.', ',');
                                }
                                rs.close();
                                stmt.close();
                            } catch (Exception e) {
                                e.printStackTrace();
                            }
                            out.print(saldoFormatado);
                        %></p>
                    </div>

                    <div class="resumo-card">
                        <h3>Total de Transações</h3>
                        <p class="valor"><%
                            int totalTransacoes = 0;
                            try {
                                Statement stmt = conn.createStatement();
                                ResultSet rs = stmt.executeQuery("SELECT COUNT(*) as total FROM transacoes");
                                if (rs.next()) {
                                    totalTransacoes = rs.getInt("total");
                                }
                                rs.close();
                                stmt.close();
                            } catch (Exception e) {
                                e.printStackTrace();
                            }
                            out.print(totalTransacoes);
                        %></p>
                    </div>

                    <div class="resumo-card">
                        <h3>Bilhetes Vendidos</h3>
                        <p class="valor"><%
                            int totalBilhetes = 0;
                            try {
                                Statement stmt = conn.createStatement();
                                ResultSet rs = stmt.executeQuery("SELECT COUNT(*) as total FROM bilhetes");
                                if (rs.next()) {
                                    totalBilhetes = rs.getInt("total");
                                }
                                rs.close();
                                stmt.close();
                            } catch (Exception e) {
                                e.printStackTrace();
                            }
                            out.print(totalBilhetes);
                        %></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!--FOOTER -->
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

<%
    // Fechar conexão com o banco de dados
    if (conn != null) {
        try {
            conn.close();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }
%>
</body>
</html>


