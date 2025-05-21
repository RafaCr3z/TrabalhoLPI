<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
    // Verificar se o utilizador já está autenticado
    if (session.getAttribute("id_nivel") != null && (Integer)session.getAttribute("id_nivel") > 0) {
        response.sendRedirect("erro.jsp");
        return;
    }
    
    // Inicialização de variáveis para controle de erros
    String mensagemErro = "";
    boolean temErro = false;
    
    // Verifica se o formulário foi submetido
    if ("POST".equals(request.getMethod())) {
        
        // Captura os valores dos campos do formulário
        String nome = request.getParameter("nome");
        String pass = request.getParameter("pass");
        
        // Validação básica
        if (nome == null || nome.trim().isEmpty() || pass == null || pass.trim().isEmpty()) {
            mensagemErro = "Por favor, preencha todos os campos.";
            temErro = true;
        } else {
            // Declaração de variáveis para conexão com a base de dados
            Connection conn = null;
            Statement stmt = null;
            ResultSet rs = null;
            
            try {
                // Estabelece conexão com a base de dados
                conn = getConnection();
                
                if (conn == null) {
                    mensagemErro = "Erro de conexão com a base de dados.";
                    temErro = true;
                } else {
                    // Verificar se a tabela existe e mostrar informações para depuração
                    stmt = conn.createStatement();
                    
                    // Verificar se a base de dados FelixBus existe
                    rs = stmt.executeQuery("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'FelixBus'");
                    boolean dbExists = rs.next();
                    
                    if (!dbExists) {
                        mensagemErro = "Base de dados FelixBus não encontrada.";
                        temErro = true;
                    } else {
                        // Verificar se a tabela utilizadores existe
                        rs = stmt.executeQuery("SELECT COUNT(*) FROM utilizadores");
                        rs.next();
                        int numUsers = rs.getInt(1);
                        
                        // Buscar o utilizador específico
                        String sql = "SELECT * FROM utilizadores WHERE user = '" + nome + "'";
                        rs = stmt.executeQuery(sql);
                        
                        if (rs.next()) {
                            // Utilizador encontrado, verifica a senha
                            String pwd_stored = rs.getString("pwd");
                            
                            // Verifica se a senha está correta (comparação direta)
                            if (pass.equals(pwd_stored)) {
                                // Senha correta, verifica se a conta está ativa
                                int ativo = rs.getInt("ativo");
                                
                                if (ativo == 1) {
                                    // Conta ativa, login bem-sucedido
                                    int id_nivel = rs.getInt("tipo_perfil");
                                    int id_utilizador = rs.getInt("id");
                                    
                                    // Armazena informações na sessão
                                    session.setAttribute("nome", nome);
                                    session.setAttribute("id_nivel", id_nivel);
                                    session.setAttribute("id_utilizador", id_utilizador);
                                    
                                    // Redireciona conforme o nível de acesso
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
                                    mensagemErro = "A sua conta ainda não foi ativada.";
                                    temErro = true;
                                }
                            } else {
                                mensagemErro = "Senha incorreta. Senha fornecida: [" + pass + "], Senha armazenada: [" + pwd_stored + "]";
                                temErro = true;
                            }
                        } else {
                            mensagemErro = "Usuário '" + nome + "' não encontrado. Total de usuários na tabela: " + numUsers;
                            temErro = true;
                        }
                    }
                }
            } catch (Exception e) {
                mensagemErro = "Erro no sistema: " + e.getMessage();
                temErro = true;
                e.printStackTrace();
            } finally {
                // Fecha recursos
                if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
                if (stmt != null) try { stmt.close(); } catch (SQLException e) { /* ignorar */ }
                if (conn != null) try { conn.close(); } catch (SQLException e) { /* ignorar */ }
            }
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
        
        <% if (temErro) { %>
            <div class="error-message">
                <%= mensagemErro %>
            </div>
        <% } %>
        
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
