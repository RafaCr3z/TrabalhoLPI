<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.security.*, java.math.BigInteger" %>
<%@ include file="../basedados/basedados.jsp" %>

<%!
// Função utilitária para gerar hash SHA-256 da palavra-passe
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

// Função para comparar a palavra-passe fornecida com o hash armazenado
public static boolean checkPassword(String password, String storedHash) {
    return storedHash.equals(hashPassword(password));
}
%>

<%
// Verifica se o utilizador é cliente (nível 3)
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 3) {
    response.sendRedirect("erro.jsp");
    return;
}

// Inicializa variáveis do utilizador
int id_utilizador = (Integer)session.getAttribute("id_utilizador");
String mensagem = "";
String tipo_mensagem = "";
String nome = "";
String email = "";
String telemovel = "";
String morada = "";

Connection conn = null;
PreparedStatement pstmt = null;
ResultSet rs = null;

try {
    conn = getConnection();
    conn.setAutoCommit(false); // Inicia transação

    // Busca dados atuais do utilizador
    pstmt = conn.prepareStatement("SELECT nome, email, telemovel, morada FROM utilizadores WHERE id = ?");
    pstmt.setInt(1, id_utilizador);
    rs = pstmt.executeQuery();

    if (!rs.next()) {
        throw new Exception("Erro ao buscar dados do utilizador.");
    }

    // Preenche variáveis com dados atuais
    nome = rs.getString("nome");
    email = rs.getString("email");
    telemovel = rs.getString("telemovel");
    morada = rs.getString("morada");

    // Se o formulário de atualização de dados foi submetido
    if ("POST".equals(request.getMethod()) && request.getParameter("atualizar") != null) {
        String novoEmail = request.getParameter("email");
        String novoTelemovel = request.getParameter("telemovel");
        String novaMorada = request.getParameter("morada");

        // Verifica se o novo email já existe para outro utilizador
        pstmt = conn.prepareStatement("SELECT id FROM utilizadores WHERE email = ? AND id != ?");
        pstmt.setString(1, novoEmail);
        pstmt.setInt(2, id_utilizador);
        rs = pstmt.executeQuery();

        if (rs.next()) {
            mensagem = "Este email já está a ser utilizado por outro utilizador.";
            tipo_mensagem = "danger";
        } else {
            // Atualiza dados do utilizador
            pstmt = conn.prepareStatement("UPDATE utilizadores SET email = ?, telemovel = ?, morada = ? WHERE id = ?");
            pstmt.setString(1, novoEmail);
            pstmt.setString(2, novoTelemovel);
            pstmt.setString(3, novaMorada);
            pstmt.setInt(4, id_utilizador);

            if (pstmt.executeUpdate() > 0) {
                conn.commit();
                mensagem = "Dados atualizados com sucesso!";
                tipo_mensagem = "success";
                // Atualiza variáveis para refletir as alterações
                email = novoEmail;
                telemovel = novoTelemovel;
                morada = novaMorada;
            } else {
                conn.rollback();
                mensagem = "Erro ao atualizar dados.";
                tipo_mensagem = "danger";
            }
        }
    }
    // Se o formulário de alteração de palavra-passe foi submetido
    else if ("POST".equals(request.getMethod()) && request.getParameter("alterar_senha") != null) {
        String senhaAtual = request.getParameter("senha_atual");
        String novaSenha = request.getParameter("nova_senha");
        String confirmarSenha = request.getParameter("confirmar_senha");

        if (!novaSenha.equals(confirmarSenha)) {
            mensagem = "A nova palavra-passe e a confirmação não coincidem.";
            tipo_mensagem = "danger";
        } else {
            // Busca hash da palavra-passe atual
            pstmt = conn.prepareStatement("SELECT pwd FROM utilizadores WHERE id = ?");
            pstmt.setInt(1, id_utilizador);
            rs = pstmt.executeQuery();

            if (rs.next()) {
                String pwdStored = rs.getString("pwd");
                boolean senhaValida = checkPassword(senhaAtual, pwdStored);

                if (senhaValida) {
                    // Atualiza palavra-passe para o novo hash
                    String hashedPwd = hashPassword(novaSenha);
                    pstmt = conn.prepareStatement("UPDATE utilizadores SET pwd = ? WHERE id = ?");
                    pstmt.setString(1, hashedPwd);
                    pstmt.setInt(2, id_utilizador);

                    if (pstmt.executeUpdate() > 0) {
                        conn.commit();
                        mensagem = "Palavra-passe alterada com sucesso!";
                        tipo_mensagem = "success";
                    } else {
                        conn.rollback();
                        mensagem = "Erro ao alterar a palavra-passe.";
                        tipo_mensagem = "danger";
                    }
                } else {
                    mensagem = "Palavra-passe atual incorreta.";
                    tipo_mensagem = "danger";
                }
            }
        }
    }
} catch (Exception e) {
    // Em caso de erro, faz rollback e mostra mensagem
    if (conn != null) {
        try {
            conn.rollback();
        } catch (SQLException ex) {
            // Ignorar
        }
    }
    mensagem = "Erro no sistema: " + e.getMessage();
    tipo_mensagem = "danger";
} finally {
    // Fecha recursos
    if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
    if (pstmt != null) try { pstmt.close(); } catch (SQLException e) { /* ignorar */ }
    if (conn != null) try { 
        conn.setAutoCommit(true);
        conn.close(); 
    } catch (SQLException e) { /* ignorar */ }
}
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="editar_perfil.css">
    <title>FelixBus - Editar o Meu Perfil</title>
    <script>
    function validarFormulario() {
        const email = document.getElementById('email').value;
        const telemovel = document.getElementById('telemovel').value;
        
        // Validar email
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('Por favor, insira um email válido.');
            return false;
        }
        
        // Validar telemóvel (9 dígitos)
        if (!/^[0-9]{9}$/.test(telemovel)) {
            alert('O telemóvel deve conter exatamente 9 dígitos.');
            return false;
        }
        
        return true;
    }

    function validarSenha() {
        const novaSenha = document.getElementById('nova_senha').value;
        const confirmarSenha = document.getElementById('confirmar_senha').value;
        
        // Verificar força da senha
        if (novaSenha.length < 8) {
            alert('A senha deve ter pelo menos 8 caracteres.');
            return false;
        }
        
        // Verificar se senhas coincidem
        if (novaSenha !== confirmarSenha) {
            alert('A nova senha e a confirmação não coincidem.');
            return false;
        }
        
        return true;
    }
    </script>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="pg_cliente.jsp">Página Inicial</a></div>
            <div class="link"> <a href="perfil_cliente.jsp">Voltar ao Perfil</a></div>
            <div class="link"> <a href="carteira_cliente.jsp">Carteira</a></div>
            <div class="link"> <a href="bilhetes_cliente.jsp">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
        </div>
    </nav>

    <section>
        <h1>Editar Perfil</h1>

        <% if (!mensagem.isEmpty()) { %>
            <div class="alert alert-<%= tipo_mensagem.equals("success") ? "success" : "danger" %>">
                <%= mensagem %>
            </div>
        <% } %>

        <div class="container">
            <div class="form-container">
                <h2>Dados Pessoais</h2>
                <form method="post" action="editar_perfil.jsp" onsubmit="return validarFormulario()">
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" value="<%= nome %>" readonly>
                        <small>O nome não pode ser alterado.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<%= email %>" required>
                    </div>

                    <div class="form-group">
                        <label for="telemovel">Telemóvel:</label>
                        <input type="text" id="telemovel" name="telemovel" value="<%= telemovel %>" required maxlength="9" minlength="9">
                    </div>

                    <div class="form-group">
                        <label for="morada">Morada:</label>
                        <input type="text" id="morada" name="morada" value="<%= morada %>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="atualizar" class="btn-primary">Atualizar Dados</button>
                    </div>
                </form>
            </div>

            <div class="form-container">
                <h2>Alterar Senha</h2>
                <form method="post" action="editar_perfil.jsp" onsubmit="return validarSenha()">
                    <div class="form-group">
                        <label for="senha_atual">Senha Atual:</label>
                        <input type="password" id="senha_atual" name="senha_atual" required>
                    </div>

                    <div class="form-group">
                        <label for="nova_senha">Nova Senha:</label>
                        <input type="password" id="nova_senha" name="nova_senha" required>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Nova Senha:</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="alterar_senha" class="btn-primary">Alterar Senha</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>




