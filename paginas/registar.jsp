<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ page import="java.security.MessageDigest" %>
<%@ page import="java.math.BigInteger" %>
<%@ page import="java.security.NoSuchAlgorithmException" %>
<%@ include file="../basedados/basedados.jsp" %>

<%!
// Método para gerar hash da senha usando SHA-256
public static String hashPassword(String password) {
    try {
        MessageDigest md = MessageDigest.getInstance("SHA-256");
        byte[] messageDigest = md.digest(password.getBytes());
        BigInteger no = new BigInteger(1, messageDigest);
        String hashtext = no.toString(16);
        while (hashtext.length() < 32) {
            hashtext = "0" + hashtext;
        }
        return hashtext;
    } catch (NoSuchAlgorithmException e) {
        throw new RuntimeException(e);
    }
}
%>

<%
// Verificar se o utilizador já está autenticado
if (session.getAttribute("id_nivel") != null && (Integer)session.getAttribute("id_nivel") > 0) {
    response.sendRedirect("erro.jsp");
    return;
}

// Inicializar variáveis
String mensagemErro = "";
boolean registoSucesso = false;

// Processar o formulário quando enviado
if ("POST".equals(request.getMethod())) {
    String user = request.getParameter("user");
    String pwd = request.getParameter("pwd");
    String nome = request.getParameter("nome");
    String email = request.getParameter("email");
    String telemovel = request.getParameter("telemovel");
    String morada = request.getParameter("morada");
    
    // Validar se todos os campos foram preenchidos
    if (user != null && !user.trim().isEmpty() && 
        pwd != null && !pwd.trim().isEmpty() && 
        nome != null && !nome.trim().isEmpty() && 
        email != null && !email.trim().isEmpty() && 
        telemovel != null && !telemovel.trim().isEmpty() && 
        morada != null && !morada.trim().isEmpty()) {
        
        Connection conn = null;
        PreparedStatement pstmt = null;
        ResultSet rs = null;
        
        try {
            conn = getConnection();
            
            // Verificar se o nome de utilizador já existe
            pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE user = ?");
            pstmt.setString(1, user);
            rs = pstmt.executeQuery();
            if (rs.next()) {
                mensagemErro = "O nome de utilizador já existe. Por favor, escolha outro.";
                return;
            }
            
            // Verificar se o nome já existe
            pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE nome = ?");
            pstmt.setString(1, nome);
            rs = pstmt.executeQuery();
            if (rs.next()) {
                mensagemErro = "Este nome já se encontra registado. Por favor, escolha outro.";
                return;
            }
            
            // Verificar se o e-mail já existe
            pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE email = ?");
            pstmt.setString(1, email);
            rs = pstmt.executeQuery();
            if (rs.next()) {
                mensagemErro = "Este endereço de e-mail já se encontra registado. Por favor, utilize outro.";
                return;
            }
            
            // Verificar se o telemóvel já existe
            pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE telemovel = ?");
            pstmt.setString(1, telemovel);
            rs = pstmt.executeQuery();
            if (rs.next()) {
                mensagemErro = "Este número de telemóvel já se encontra registado. Por favor, utilize outro.";
                return;
            }
            
            // Gerar hash da palavra-passe
            String hashed_pwd = hashPassword(pwd);
            
            // Inserir novo utilizador
            pstmt = conn.prepareStatement("INSERT INTO utilizadores (user, pwd, nome, email, telemovel, morada, tipo_perfil, ativo) VALUES (?, ?, ?, ?, ?, ?, 3, 0)", Statement.RETURN_GENERATED_KEYS);
            pstmt.setString(1, user);
            pstmt.setString(2, hashed_pwd);
            pstmt.setString(3, nome);
            pstmt.setString(4, email);
            pstmt.setString(5, telemovel);
            pstmt.setString(6, morada);
            
            int affectedRows = pstmt.executeUpdate();
            
            if (affectedRows > 0) {
                // Obter o ID do novo cliente
                rs = pstmt.getGeneratedKeys();
                if (rs.next()) {
                    int id_cliente = rs.getInt(1);
                    
                    // Criar carteira para o novo cliente
                    pstmt = conn.prepareStatement("INSERT INTO carteiras (id_cliente, saldo) VALUES (?, 0.00)");
                    pstmt.setInt(1, id_cliente);
                    pstmt.executeUpdate();
                    
                    registoSucesso = true;
                }
            }
        } catch (Exception e) {
            mensagemErro = "Erro ao registar utilizador: " + e.getMessage();
            e.printStackTrace();
        } finally {
            if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
            if (pstmt != null) try { pstmt.close(); } catch (SQLException e) { /* ignorar */ }
            if (conn != null) try { conn.close(); } catch (SQLException e) { /* ignorar */ }
        }
    } else {
        mensagemErro = "Por favor, preencha todos os campos.";
    }
}
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="registar.css">
    <title>Registar</title>
    <script>
        <% if (registoSucesso) { %>
            alert('Conta criada com sucesso! Aguarde a validação por parte do administrador para poder iniciar sessão.');
            window.location.href = 'index.jsp';
        <% } %>
        
        <% if (!mensagemErro.isEmpty()) { %>
            alert('<%= mensagemErro %>');
        <% } %>
    </script>
</head>
<body>
    <div class="register-container">
        <h2>Registar</h2>
        <form action="registar.jsp" method="post">
            <label for="user">Nome de Utilizador:</label>
            <input type="text" id="user" name="user" required>

            <label for="nome">Nome Completo:</label>
            <input type="text" id="nome" name="nome" required>

            <label for="pwd">Palavra-passe:</label>
            <input type="password" id="pwd" name="pwd" required>

            <label for="email">Endereço de E-mail:</label>
            <input type="email" id="email" name="email" required>

            <label for="telemovel">Telemóvel:</label>
            <input type="text" id="telemovel" name="telemovel" required maxlength="9" minlength="9">

            <label for="morada">Morada:</label>
            <input type="text" id="morada" name="morada" required>

            <button type="submit">Criar Conta</button>
        </form>
        <form action="index.jsp" method="get">
            <button type="submit" style="margin-top: 10px;">Voltar</button>
        </form>
    </div>
</body>
</html>
