<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.security.MessageDigest, java.math.BigInteger" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
/*
    Verifica se o utilizador tem sessão iniciada e se é administrador (nível 1).
    Se não for, redireciona para a página de erro.
*/
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
    response.sendRedirect("erro.jsp");
    return;
}

// Obtém o ID do utilizador a partir da sessão
int id_utilizador = (Integer)session.getAttribute("id_utilizador");
String mensagem = "";
String tipo_mensagem = "";

// Inicializa ligação à base de dados e estrutura para guardar dados do perfil
Connection conn = null;
PreparedStatement pstmt = null;
ResultSet rs = null;
Map<String, String> dados = new HashMap<>();

try {
    conn = getConnection();
    
    // Busca os dados atuais do administrador
    pstmt = conn.prepareStatement("SELECT nome, email, telemovel, morada FROM utilizadores WHERE id = ?");
    pstmt.setInt(1, id_utilizador);
    rs = pstmt.executeQuery();
    
    if (rs.next()) {
        dados.put("nome", rs.getString("nome"));
        dados.put("email", rs.getString("email"));
        dados.put("telemovel", rs.getString("telemovel"));
        dados.put("morada", rs.getString("morada"));
    } else {
        throw new Exception("Erro ao buscar dados do utilizador.");
    }

    // Processa o formulário de atualização de dados pessoais
    if ("POST".equals(request.getMethod()) && request.getParameter("atualizar") != null) {
        String email = request.getParameter("email");
        String telemovel = request.getParameter("telemovel");
        String morada = request.getParameter("morada");

        // Verifica se o novo email já existe para outro utilizador
        pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE email = ? AND id != ?");
        pstmt.setString(1, email);
        pstmt.setInt(2, id_utilizador);
        rs = pstmt.executeQuery();
        
        if (rs.next()) {
            mensagem = "Este email já está a ser utilizado por outro utilizador.";
            tipo_mensagem = "error";
        } else {
            pstmt = conn.prepareStatement("UPDATE utilizadores SET email = ?, telemovel = ?, morada = ? WHERE id = ?");
            pstmt.setString(1, email);
            pstmt.setString(2, telemovel);
            pstmt.setString(3, morada);
            pstmt.setInt(4, id_utilizador);
            
            if (pstmt.executeUpdate() > 0) {
                mensagem = "Dados atualizados com sucesso!";
                tipo_mensagem = "success";
                dados.put("email", email);
                dados.put("telemovel", telemovel);
                dados.put("morada", morada);
            } else {
                mensagem = "Erro ao atualizar dados.";
                tipo_mensagem = "error";
            }
        }
    }

    // Processa o formulário de alteração de palavra-passe
    if ("POST".equals(request.getMethod()) && request.getParameter("alterar_senha") != null) {
        String senha_atual = request.getParameter("senha_atual");
        String nova_senha = request.getParameter("nova_senha");
        String confirmar_senha = request.getParameter("confirmar_senha");

        if (!nova_senha.equals(confirmar_senha)) {
            mensagem = "A nova palavra-passe e a confirmação não coincidem.";
            tipo_mensagem = "error";
        } else {
            // Verifica a palavra-passe atual
            pstmt = conn.prepareStatement("SELECT pwd FROM utilizadores WHERE id = ?");
            pstmt.setInt(1, id_utilizador);
            rs = pstmt.executeQuery();
            
            if (rs.next()) {
                String pwd_armazenada = rs.getString("pwd");
                boolean senha_valida = false;
                
                if (pwd_armazenada.startsWith("$2y$")) {
                    // Implementar verificação de senha bcrypt se necessário
                    senha_valida = false; // Placeholder - JSP não suporta nativamente bcrypt
                } else {
                    // Para SHA-256, compara diretamente (atenção: idealmente deve comparar hashes)
                    senha_valida = senha_atual.equals(pwd_armazenada);
                }

                if (senha_valida) {
                    // Gera o hash SHA-256 da nova palavra-passe
                    MessageDigest md = MessageDigest.getInstance("SHA-256");
                    byte[] messageDigest = md.digest(nova_senha.getBytes());
                    BigInteger no = new BigInteger(1, messageDigest);
                    String hashed_pwd = no.toString(16);
                    while (hashed_pwd.length() < 32) {
                        hashed_pwd = "0" + hashed_pwd;
                    }
                    
                    pstmt = conn.prepareStatement("UPDATE utilizadores SET pwd = ? WHERE id = ?");
                    pstmt.setString(1, hashed_pwd);
                    pstmt.setInt(2, id_utilizador);
                    
                    if (pstmt.executeUpdate() > 0) {
                        mensagem = "Palavra-passe alterada com sucesso!";
                        tipo_mensagem = "success";
                    } else {
                        mensagem = "Erro ao alterar palavra-passe.";
                        tipo_mensagem = "error";
                    }
                } else {
                    mensagem = "Palavra-passe atual incorreta.";
                    tipo_mensagem = "error";
                }
            }
        }
    }
} catch (Exception e) {
    mensagem = "Erro: " + e.getMessage();
    tipo_mensagem = "error";
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
    <link rel="stylesheet" href="perfil_admin.css">
    <link rel="stylesheet" href="common.css">
    <title>FelixBus - O Meu Perfil</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links" style="display: flex; justify-content: center; width: 50%;">
            <div class="link"> <a href="pg_admin.jsp" style="font-size: 1.2rem; font-weight: 500;">Voltar para Página Inicial</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Administrador</div>
        </div>
    </nav>

    <section>
        <h1>O Meu Perfil</h1>

        <% if (!mensagem.isEmpty()) { %>
            <div class="alert alert-<%= tipo_mensagem.equals("success") ? "success" : "danger" %>">
                <%= mensagem %>
            </div>
        <% } %>

        <div class="container">
            <div class="form-container">
                <h2>Dados Pessoais</h2>
                <form method="post" action="perfil_admin.jsp">
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" value="<%= dados.get("nome") %>" readonly>
                        <small>O nome não pode ser alterado.</small>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<%= dados.get("email") %>" required>
                    </div>
                    <div class="form-group">
                        <label for="telemovel">Telemóvel:</label>
                        <input type="text" id="telemovel" name="telemovel" value="<%= dados.get("telemovel") %>" required>
                    </div>
                    <div class="form-group">
                        <label for="morada">Morada:</label>
                        <textarea id="morada" name="morada" required><%= dados.get("morada") %></textarea>
                    </div>
                    <button type="submit" name="atualizar" value="1">Atualizar Dados</button>
                </form>
            </div>

            <div class="form-container">
                <h2>Alterar Palavra-passe</h2>
                <form method="post" action="perfil_admin.jsp">
                    <div class="form-group">
                        <label for="senha_atual">Palavra-passe Atual:</label>
                        <input type="password" id="senha_atual" name="senha_atual" required>
                    </div>
                    <div class="form-group">
                        <label for="nova_senha">Nova Palavra-passe:</label>
                        <input type="password" id="nova_senha" name="nova_senha" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Nova Palavra-passe:</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                    <button type="submit" name="alterar_senha" value="1">Alterar Palavra-passe</button>
                </form>
            </div>
        </div>
    </section>

    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>

