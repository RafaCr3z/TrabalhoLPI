<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
// Verifica se o utilizador está autenticado e se é um cliente (nível 3).
// Se não for, redireciona para a página de erro.
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 3) {
    response.sendRedirect("erro.jsp");
    return;
}

// Obtém o ID do utilizador a partir da sessão
int id_utilizador = (Integer)session.getAttribute("id_utilizador");

// Inicializa ligação à base de dados e variáveis para os dados do perfil
Connection conn = null;
PreparedStatement pstmt = null;
ResultSet rs = null;
String nome = "";
String email = "";
String telemovel = "";
String morada = "";

try {
    conn = getConnection();
    
    // Consulta SQL para obter os dados do utilizador autenticado
    pstmt = conn.prepareStatement("SELECT nome, email, telemovel, morada FROM utilizadores WHERE id = ?");
    pstmt.setInt(1, id_utilizador);
    rs = pstmt.executeQuery();
    
    // Verifica se foram encontrados dados para o utilizador
    if (rs.next()) {
        nome = rs.getString("nome");
        email = rs.getString("email");
        telemovel = rs.getString("telemovel");
        morada = rs.getString("morada");
    } else {
        throw new Exception("Nenhum dado encontrado para o utilizador.");
    }
} catch (Exception e) {
    out.println("Erro: " + e.getMessage());
    return;
} finally {
    // Fecha recursos da base de dados
    if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
    if (pstmt != null) try { pstmt.close(); } catch (SQLException e) { /* ignorar */ }
    if (conn != null) try { conn.close(); } catch (SQLException e) { /* ignorar */ }
}
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="perfil_cliente.css">
    <title>FelixBus - O Meu Perfil</title>
</head>

<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="pg_cliente.jsp">Página Inicial</a></div>
            <div class="link"> <a href="carteira_cliente.jsp">Carteira</a></div>
            <div class="link"> <a href="bilhetes_cliente.jsp">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
    </nav>
    <section>
        <h1>O Meu Perfil</h1>

        <div class="profile-container">
            <!-- Informações do perfil do utilizador -->
            <div class="profile-info">
                <p><strong>Nome:</strong> <%= nome %></p>
                <p><strong>Email:</strong> <%= email %></p>
                <p><strong>Telemóvel:</strong> <%= telemovel %></p>
                <p><strong>Morada:</strong> <%= morada %></p>
            </div>

            <!-- Botão de Editar Perfil alinhado à direita -->
            <div class="btn-edit">
                <a href="editar_perfil.jsp"><button>Editar Perfil</button></a>
            </div>
        </div>
    </section>

    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>

</html>
