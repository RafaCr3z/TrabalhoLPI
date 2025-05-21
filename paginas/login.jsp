<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ page import="java.util.*" %>

<%
    // Verificar sessão
    if (session.getAttribute("id_nivel") != null && (Integer)session.getAttribute("id_nivel") > 0) {
        response.sendRedirect("erro.jsp");
        return;
    }
    
    // Configuração da conexão
    String dbhost = "localhost";
    String dbuser = "root";
    String dbpass = "";
    String dbname = "FelixBus";
    Connection conn = null;
    
    try {
        Class.forName("com.mysql.jdbc.Driver");
        conn = DriverManager.getConnection("jdbc:mysql://" + dbhost + "/" + dbname, dbuser, dbpass);
        
        // Verifica se o formulário foi enviado
        if ("POST".equals(request.getMethod()) && 
            request.getParameter("nome") != null && 
            request.getParameter("pass") != null) {
            
            String nome = request.getParameter("nome");
            String pass = request.getParameter("pass");
            
            // Buscar o usuário pelo nome de utilizador
            PreparedStatement stmt = conn.prepareStatement("SELECT * FROM `utilizadores` WHERE `user` = ?");
            stmt.setString(1, nome);
            ResultSet rs = stmt.executeQuery();
            
            if (!rs.next()) {
                out.println("<script>alert('Usuário não encontrado.'); window.location.href = 'login.jsp';</script>");
                return;
            }
            
            // Verificar a senha
            String pwd_stored = rs.getString("pwd");
            int pwd_length = pwd_stored.length();
            boolean is_md5 = (pwd_length == 32 && pwd_stored.matches("[0-9a-fA-F]+"));
            boolean is_bcrypt = pwd_stored.startsWith("$2y$");
            
            // Verificar a senha de várias maneiras possíveis
            boolean senha_valida = false;
            
            // 1. Verificar se é MD5
            if (is_md5) {
                java.security.MessageDigest md = java.security.MessageDigest.getInstance("MD5");
                byte[] digest = md.digest(pass.getBytes());
                StringBuilder sb = new StringBuilder();
                for (byte b : digest) {
                    sb.append(String.format("%02x", b & 0xff));
                }
                senha_valida = sb.toString().equals(pwd_stored);
            }
            // 2. Verificar se é bcrypt
            else if (is_bcrypt) {
                // Nota: JSP não tem função nativa para verificar bcrypt
                // Você precisará usar uma biblioteca como jBCrypt
                // senha_valida = BCrypt.checkpw(pass, pwd_stored);
                senha_valida = false; // Placeholder - implemente com jBCrypt
            }
            // 3. Verificar se é texto simples
            else {
                senha_valida = pass.equals(pwd_stored);
            }
            
            // Se a senha for válida e não estiver em formato bcrypt, atualizar para bcrypt
            if (senha_valida && !is_bcrypt) {
                // Nota: Você precisará usar uma biblioteca como jBCrypt
                // String hashed_pwd = BCrypt.hashpw(pass, BCrypt.gensalt());
                String hashed_pwd = pwd_stored; // Placeholder - implemente com jBCrypt
                int id_usuario = rs.getInt("id");
                PreparedStatement updateStmt = conn.prepareStatement("UPDATE utilizadores SET pwd = ? WHERE id = ?");
                updateStmt.setString(1, hashed_pwd);
                updateStmt.setInt(2, id_usuario);
                updateStmt.executeUpdate();
                updateStmt.close();
            }
            
            if (senha_valida) {
                // Verificar se a conta está ativa
                if (rs.getInt("ativo") == 0) {
                    out.println("<script>alert('A sua conta ainda não foi ativada. Aguarde a validação por parte do administrador.'); window.location.href = 'login.jsp';</script>");
                    return;
                }
                
                // Login bem-sucedido
                int id_nivel = rs.getInt("tipo_perfil");
                int id_utilizador = rs.getInt("id");
                
                session.setAttribute("nome", nome);
                session.setAttribute("id_nivel", id_nivel);
                session.setAttribute("id_utilizador", id_utilizador);
                
                if (id_nivel == 1) {
                    response.sendRedirect("pg_admin.jsp");
                } else if (id_nivel == 2) {
                    response.sendRedirect("pg_funcionario.jsp");
                } else if (id_nivel == 3) {
                    response.sendRedirect("pg_cliente.jsp");
                } else {
                    response.sendRedirect("login.jsp");
                }
                return;
            } else {
                out.println("<script>alert('Senha incorreta.'); window.location.href = 'login.jsp';</script>");
                return;
            }
            
            rs.close();
            stmt.close();
        }
    } catch (Exception e) {
        out.println("<!-- Erro: " + e.getMessage() + " -->");
    } finally {
        if (conn != null) {
            try { conn.close(); } catch (SQLException e) { /* ignorar */ }
        }
    }
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
    <title>FelixBus - Login</title>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="login.jsp" method="post">
            <label for="nome">Nome de Utilizador:</label>
            <input type="text" id="nome" name="nome" required>
            <br>
            <label for="pass">Senha:</label>
            <input type="password" id="pass" name="pass" required>
            <br>
            <button type="submit">Entrar</button>
        </form>
        <form action="index.jsp" method="get">
            <button type="submit" style="margin-top: 10px;">Voltar</button>
        </form>
    </div>
</body>
</html>