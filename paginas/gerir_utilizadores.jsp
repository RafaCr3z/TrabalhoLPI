<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ page import="java.util.*" %>
<%@ page import="java.security.MessageDigest" %>
<%@ page import="java.math.BigInteger" %>
<%@ include file="../basedados/basedados.jsp" %>

<%!
// Método para escapar strings HTML
public String h(String string) {
    if (string == null) return "";
    return string.replace("&", "&amp;")
                .replace("<", "&lt;")
                .replace(">", "&gt;")
                .replace("\"", "&quot;")
                .replace("'", "&#x27;");
}

// Método para gerar hash da senha
public String passwordHash(String password) {
    try {
        MessageDigest md = MessageDigest.getInstance("SHA-256");
        byte[] messageDigest = md.digest(password.getBytes());
        BigInteger no = new BigInteger(1, messageDigest);
        String hashtext = no.toString(16);
        while (hashtext.length() < 32) {
            hashtext = "0" + hashtext;
        }
        return hashtext;
    } catch (Exception e) {
        throw new RuntimeException(e);
    }
}
%>

<%
// Verificar se é administrador
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
    response.sendRedirect("erro.jsp");
    return;
}

// Obter conexão com o banco de dados
Connection conn = null;
Statement stmt = null;
ResultSet rs = null;
PreparedStatement pstmt = null;
List<Map<String, Object>> utilizadores = new ArrayList<>();
String mensagem = "";
String tipo_mensagem = "";
int mostrar_inativos = 0;
String pesquisa = "";

try {
    conn = getConnection();
    stmt = conn.createStatement();
    
    // Verificar campo 'ativo'
    rs = stmt.executeQuery("SHOW COLUMNS FROM utilizadores LIKE 'ativo'");
    if (!rs.next()) {
        stmt.executeUpdate("ALTER TABLE utilizadores ADD ativo TINYINT(1) NOT NULL DEFAULT 1");
    }
    
    // Inicializar variáveis para mensagens de feedback
    
    // Verificar se deve mostrar utilizadores inativos (parâmetro do URL)
    mostrar_inativos = request.getParameter("mostrar_inativos") != null ? 
                      Integer.parseInt(request.getParameter("mostrar_inativos")) : 0;
    
    // Adicionar após a inicialização de mostrar_inativos
    pesquisa = request.getParameter("pesquisa") != null ? 
              request.getParameter("pesquisa") : "";
    
    // Processar formulário de adição de novo utilizador
    if ("POST".equals(request.getMethod()) && request.getParameter("adicionar") != null) {
        // Validação de dados
        List<String> erros = new ArrayList<>();
        
        // Validar telemóvel (9 dígitos)
        String telemovel = request.getParameter("telemovel");
        if (telemovel == null || !telemovel.matches("^[0-9]{9}$")) {
            erros.add("O telemóvel deve conter exatamente 9 dígitos numéricos");
        }
        
        // Validar email
        String email = request.getParameter("email");
        if (email == null || !email.matches("^[\\w-\\.]+@([\\w-]+\\.)+[\\w-]{2,4}$")) {
            erros.add("O email é inválido");
        }
        
        // Verificar se há erros
        if (!erros.isEmpty()) {
            mensagem = "Erros de validação: " + String.join(", ", erros);
            tipo_mensagem = "danger";
        } else {
            // Capturar e sanitizar dados do formulário
            String user = request.getParameter("user");
            String nome = request.getParameter("nome");
            String pwd = passwordHash(request.getParameter("pwd")); // Hash da palavra-passe para segurança
            int tipo_perfil = Integer.parseInt(request.getParameter("tipo_perfil"));
            String morada = request.getParameter("morada");
            
            // Verificar se o utilizador ou email já existem no sistema
            pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE user = ? OR email = ?");
            pstmt.setString(1, user);
            pstmt.setString(2, email);
            rs = pstmt.executeQuery();
            
            if (rs.next()) {
                mensagem = "O utilizador ou email já existe";
                tipo_mensagem = "danger";
            } else {
                // Inserir novo utilizador na base de dados
                pstmt = conn.prepareStatement(
                    "INSERT INTO utilizadores (user, nome, email, pwd, telemovel, morada, tipo_perfil) " +
                    "VALUES (?, ?, ?, ?, ?, ?, ?)", 
                    Statement.RETURN_GENERATED_KEYS
                );
                pstmt.setString(1, user);
                pstmt.setString(2, nome);
                pstmt.setString(3, email);
                pstmt.setString(4, pwd);
                pstmt.setString(5, telemovel);
                pstmt.setString(6, morada);
                pstmt.setInt(7, tipo_perfil);
                
                if (pstmt.executeUpdate() > 0) {
                    rs = pstmt.getGeneratedKeys();
                    if (rs.next()) {
                        int id_novo = rs.getInt(1);
                        // Se for cliente (tipo_perfil 3), criar carteira com saldo inicial 0
                        if (tipo_perfil == 3) {
                            pstmt = conn.prepareStatement("INSERT INTO carteiras (id_cliente, saldo) VALUES (?, 0.00)");
                            pstmt.setInt(1, id_novo);
                            pstmt.executeUpdate();
                        }
                        mensagem = "Utilizador adicionado com sucesso!";
                        tipo_mensagem = "success";
                    }
                } else {
                    mensagem = "Erro ao adicionar utilizador";
                    tipo_mensagem = "danger";
                }
            }
        }
    }
    
    // Processar formulário de edição de utilizador existente
    if ("POST".equals(request.getMethod()) && request.getParameter("editar") != null) {
        // Capturar dados do formulário
        int id = Integer.parseInt(request.getParameter("id"));
        String nome = request.getParameter("nome");
        String email = request.getParameter("email");
        String telemovel = request.getParameter("telemovel");
        String morada = request.getParameter("morada");
        String pwd = request.getParameter("pwd");
        
        if (pwd != null && !pwd.trim().isEmpty()) {
            // Se senha fornecida, atualizar também a senha
            String pwd_hash = passwordHash(pwd);
            pstmt = conn.prepareStatement(
                "UPDATE utilizadores SET nome = ?, email = ?, telemovel = ?, morada = ?, pwd = ? WHERE id = ?"
            );
            pstmt.setString(1, nome);
            pstmt.setString(2, email);
            pstmt.setString(3, telemovel);
            pstmt.setString(4, morada);
            pstmt.setString(5, pwd_hash);
            pstmt.setInt(6, id);
        } else {
            // Sem atualização de senha
            pstmt = conn.prepareStatement(
                "UPDATE utilizadores SET nome = ?, email = ?, telemovel = ?, morada = ? WHERE id = ?"
            );
            pstmt.setString(1, nome);
            pstmt.setString(2, email);
            pstmt.setString(3, telemovel);
            pstmt.setString(4, morada);
            pstmt.setInt(5, id);
        }
        
        // Executar a atualização
        if (pstmt.executeUpdate() > 0) {
            mensagem = "Utilizador editado com sucesso!";
            tipo_mensagem = "success";
        } else {
            mensagem = "Erro ao editar utilizador";
            tipo_mensagem = "danger";
        }
    }
    
    // Processar solicitação para alterar estado (ativar/inativar utilizador)
    if (request.getParameter("alterar_estado") != null && request.getParameter("id") != null) {
        int id = Integer.parseInt(request.getParameter("id"));
        int id_utilizador = (Integer)session.getAttribute("id_utilizador");
        
        // Não permitir que o administrador desative a sua própria conta
        if (id != id_utilizador) {
            int ativo = Integer.parseInt(request.getParameter("ativo"));
            
            // Atualizar estado do utilizador
            pstmt = conn.prepareStatement("UPDATE utilizadores SET ativo = ? WHERE id = ?");
            pstmt.setInt(1, ativo);
            pstmt.setInt(2, id);
            
            if (pstmt.executeUpdate() > 0) {
                mensagem = "Estado do utilizador alterado com sucesso!";
                tipo_mensagem = "success";
            } else {
                mensagem = "Erro ao alterar estado do utilizador";
                tipo_mensagem = "danger";
            }
        } else {
            mensagem = "Não é possível desativar a sua própria conta!";
            tipo_mensagem = "danger";
        }
    }
    
    // Buscar utilizadores para exibição
    StringBuilder sql = new StringBuilder(
        "SELECT u.*, " +
        "(SELECT COUNT(*) FROM bilhetes b WHERE b.id_cliente = u.id) as total_bilhetes, " +
        "(SELECT saldo FROM carteiras c WHERE c.id_cliente = u.id) as saldo " +
        "FROM utilizadores u WHERE 1=1"
    );
    
    List<Object> params = new ArrayList<>();
    
    // Adicionar filtro para mostrar inativos se necessário
    if (mostrar_inativos == 0) {
        sql.append(" AND u.ativo = 1");
    }
    
    // Adicionar filtro de pesquisa se fornecido
    if (pesquisa != null && !pesquisa.trim().isEmpty()) {
        sql.append(" AND (u.nome LIKE ? OR u.email LIKE ? OR u.user LIKE ?)");
        String searchTerm = "%" + pesquisa + "%";
        params.add(searchTerm);
        params.add(searchTerm);
        params.add(searchTerm);
    }
    
    sql.append(" ORDER BY u.tipo_perfil, u.nome");
    
    pstmt = conn.prepareStatement(sql.toString());
    
    // Definir parâmetros da consulta
    for (int i = 0; i < params.size(); i++) {
        pstmt.setObject(i + 1, params.get(i));
    }
    
    rs = pstmt.executeQuery();
    
    // Armazenar resultados em uma lista para uso no HTML
    utilizadores = new ArrayList<>();
    while (rs.next()) {
        Map<String, Object> utilizador = new HashMap<>();
        utilizador.put("id", rs.getInt("id"));
        utilizador.put("user", rs.getString("user"));
        utilizador.put("nome", rs.getString("nome"));
        utilizador.put("email", rs.getString("email"));
        utilizador.put("telemovel", rs.getString("telemovel"));
        utilizador.put("morada", rs.getString("morada"));
        utilizador.put("tipo_perfil", rs.getInt("tipo_perfil"));
        utilizador.put("ativo", rs.getInt("ativo"));
        utilizador.put("total_bilhetes", rs.getInt("total_bilhetes"));
        utilizador.put("saldo", rs.getDouble("saldo"));
        utilizadores.add(utilizador);
    }
} catch (Exception e) {
    mensagem = "Erro: " + e.getMessage();
    tipo_mensagem = "danger";
    e.printStackTrace();
} finally {
    // Fechar recursos
    try {
        if (rs != null) rs.close();
        if (stmt != null) stmt.close();
        if (pstmt != null) pstmt.close();
        if (conn != null) conn.close();
    } catch (SQLException e) {
        e.printStackTrace();
    }
}
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_utilizadores.css">
    <title>FelixBus - Gestão de Utilizadores</title>
    <script>
        function editarUtilizador(id) {
            // Implementar a lógica para abrir o modal de edição
            document.getElementById('modal-editar').style.display = 'block';
            document.getElementById('edit-id').value = id;
            
            // Aqui você pode adicionar código para preencher os campos do formulário
            // com os dados do utilizador selecionado
        }
        
        function fecharModalEditar() {
            document.getElementById('modal-editar').style.display = 'none';
        }
    </script>
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
        <h1>Gestão de Utilizadores</h1>
        
        <% if (mensagem != null && !mensagem.isEmpty()) { %>
            <div class="alert alert-<%= tipo_mensagem %>">
                <%= mensagem %>
            </div>
        <% } %>

        <div class="container">
            <div class="filtros">
                <form action="gerir_utilizadores.jsp" method="get" class="form-filtro">
                    <div class="form-group">
                        <input type="text" name="pesquisa" placeholder="Pesquisar por nome, email ou username" value="<%= h(pesquisa) %>" style="width: 100%; min-width: 350px; flex: 4;">
                        <button type="submit">Pesquisar</button>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="mostrar_inativos" value="1" <%= mostrar_inativos == 1 ? "checked" : "" %> onchange="this.form.submit()">
                            Mostrar utilizadores inativos
                        </label>
                    </div>
                </form>
            </div>

            <div class="add-user">
                <h2>Adicionar Novo Utilizador</h2>
                <form action="gerir_utilizadores.jsp" method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="user">Username:</label>
                            <input type="text" id="user" name="user" required>
                        </div>
                        <div class="form-group">
                            <label for="nome">Nome:</label>
                            <input type="text" id="nome" name="nome" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="pwd">Senha:</label>
                            <input type="password" id="pwd" name="pwd" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telemovel">Telemóvel:</label>
                            <input type="text" id="telemovel" name="telemovel" required pattern="[0-9]{9}" title="O telemóvel deve ter 9 dígitos">
                        </div>
                        <div class="form-group">
                            <label for="tipo_perfil">Tipo de Perfil:</label>
                            <select id="tipo_perfil" name="tipo_perfil" required>
                                <option value="1">Administrador</option>
                                <option value="2">Funcionário</option>
                                <option value="3" selected>Cliente</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="morada">Morada:</label>
                            <input type="text" id="morada" name="morada" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <button type="submit" name="adicionar" value="1">Adicionar Utilizador</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="users-list">
                <h2>Lista de Utilizadores</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telemóvel</th>
                            <th>Tipo</th>
                            <th>Saldo</th>
                            <th>Bilhetes</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <% for (Map<String, Object> utilizador : utilizadores) { %>
                            <tr class="<%= (Integer)utilizador.get("ativo") == 0 ? "inativo" : "" %>">
                                <td><%= utilizador.get("id") %></td>
                                <td><%= h((String)utilizador.get("user")) %></td>
                                <td><%= h((String)utilizador.get("nome")) %></td>
                                <td><%= h((String)utilizador.get("email")) %></td>
                                <td><%= h((String)utilizador.get("telemovel")) %></td>
                                <td>
                                    <% 
                                    int tipo = (Integer)utilizador.get("tipo_perfil");
                                    if (tipo == 1) out.print("Admin");
                                    else if (tipo == 2) out.print("Funcionário");
                                    else out.print("Cliente");
                                    %>
                                </td>
                                <td>
                                    <% 
                                    if ((Integer)utilizador.get("tipo_perfil") == 3) {
                                        Double saldo = (Double)utilizador.get("saldo");
                                        out.print(String.format("€%.2f", saldo).replace('.', ','));
                                    } else {
                                        out.print("-");
                                    }
                                    %>
                                </td>
                                <td><%= utilizador.get("total_bilhetes") %></td>
                                <td>
                                    <span class="status-badge <%= (Integer)utilizador.get("ativo") == 1 ? "active" : "inactive" %>">
                                        <%= (Integer)utilizador.get("ativo") == 1 ? "Ativo" : "Inativo" %>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="edit-btn" onclick="editarUtilizador(<%= utilizador.get("id") %>)">Editar</button>
                                        
                                        <% if ((Integer)utilizador.get("id") != (Integer)session.getAttribute("id_utilizador")) { %>
                                            <% if ((Integer)utilizador.get("ativo") == 1) { %>
                                                <a href="gerir_utilizadores.jsp?alterar_estado=1&id=<%= utilizador.get("id") %>&ativo=0" class="deactivate-btn">Desativar</a>
                                            <% } else { %>
                                                <a href="gerir_utilizadores.jsp?alterar_estado=1&id=<%= utilizador.get("id") %>&ativo=1" class="activate-btn">Ativar</a>
                                            <% } %>
                                        <% } %>
                                    </div>
                                </td>
                            </tr>
                        <% } %>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Modal para edição de utilizador -->
    <div id="modal-editar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalEditar()">&times;</span>
            <h2>Editar Utilizador</h2>
            <form method="post" action="gerir_utilizadores.jsp">
                <!-- Campo oculto para ID do utilizador -->
                <input type="hidden" id="edit-id" name="id">
                <div class="form-group">
                    <label for="edit-nome">Nome Completo:</label>
                    <input type="text" id="edit-nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="edit-email">Email:</label>
                    <input type="email" id="edit-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit-pwd">Nova Palavra-passe:</label>
                    <input type="password" id="edit-pwd" name="pwd">
                    <small>Deixe em branco para manter a palavra-passe atual</small>
                </div>
                <div class="form-group">
                    <label for="edit-telemovel">Telemóvel:</label>
                    <input type="text" id="edit-telemovel" name="telemovel" required maxlength="9" minlength="9">
                </div>
                <div class="form-group">
                    <label for="edit-morada">Morada:</label>
                    <textarea id="edit-morada" name="morada" required></textarea>
                </div>
                <button type="submit" name="editar" value="1">Atualizar</button>
            </form>
        </div>
    </div>

    <!-- Rodapé da página -->
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>


