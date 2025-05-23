<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>
<%@ page import="java.util.*" %>
<%@ page import="java.security.MessageDigest" %>
<%@ page import="java.math.BigInteger" %>
<%@ include file="../basedados/basedados.jsp" %>

<%!
// Função para escapar caracteres HTML (evita XSS)
public String h(String string) {
    if (string == null) return "";
    return string.replace("&", "&amp;")
                .replace("<", "&lt;")
                .replace(">", "&gt;")
                .replace("\"", "&quot;")
                .replace("'", "&#x27;");
}

// Função para gerar hash SHA-256 da palavra-passe
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
// Verifica se o utilizador é administrador
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
    response.sendRedirect("erro.jsp");
    return;
}

// Inicializa ligação à base de dados e variáveis de controlo
Connection conn = null;
Statement stmt = null;
ResultSet rs = null;
PreparedStatement pstmt = null;
List<Map<String, Object>> utilizadores = new ArrayList<>();
String mensagem = "";
String tipo_mensagem = "";
int mostrar_inativos = 0;
String pesquisa = "";
Map<String, Object> utilizadorParaEditar = null;

try {
    conn = getConnection();
    stmt = conn.createStatement();
    
    // Garante que existe o campo 'ativo' na tabela utilizadores
    rs = stmt.executeQuery("SHOW COLUMNS FROM utilizadores LIKE 'ativo'");
    if (!rs.next()) {
        stmt.executeUpdate("ALTER TABLE utilizadores ADD ativo TINYINT(1) NOT NULL DEFAULT 1");
    }
    
    // Lê o parâmetro para mostrar utilizadores inativos
    mostrar_inativos = request.getParameter("mostrar_inativos") != null ? 
                      Integer.parseInt(request.getParameter("mostrar_inativos")) : 0;
    
    // Lê o parâmetro de pesquisa
    pesquisa = request.getParameter("pesquisa") != null ? 
              request.getParameter("pesquisa") : "";
    
    // Se for pedido para editar um utilizador, carrega os dados desse utilizador
    if (request.getParameter("editar") != null) {
        int idEditar = Integer.parseInt(request.getParameter("editar"));
        pstmt = conn.prepareStatement("SELECT id, user, nome, email, telemovel, morada, tipo_perfil FROM utilizadores WHERE id = ?");
        pstmt.setInt(1, idEditar);
        rs = pstmt.executeQuery();
        
        if (rs.next()) {
            utilizadorParaEditar = new HashMap<>();
            utilizadorParaEditar.put("id", rs.getInt("id"));
            utilizadorParaEditar.put("user", rs.getString("user"));
            utilizadorParaEditar.put("nome", rs.getString("nome"));
            utilizadorParaEditar.put("email", rs.getString("email"));
            utilizadorParaEditar.put("telemovel", rs.getString("telemovel"));
            utilizadorParaEditar.put("morada", rs.getString("morada"));
            utilizadorParaEditar.put("tipo_perfil", rs.getInt("tipo_perfil"));
        }
    }
    
    // Processa o formulário de adição de novo utilizador
    if ("POST".equals(request.getMethod()) && request.getParameter("adicionar") != null) {
        // Validação dos dados do formulário
        List<String> erros = new ArrayList<>();
        
        // Valida telemóvel (9 dígitos)
        String telemovel = request.getParameter("telemovel");
        if (telemovel == null || !telemovel.matches("^[0-9]{9}$")) {
            erros.add("O telemóvel deve conter exatamente 9 dígitos numéricos");
        }
        
        // Valida email
        String email = request.getParameter("email");
        if (email == null || !email.matches("^[\\w-\\.]+@([\\w-]+\\.)+[\\w-]{2,4}$")) {
            erros.add("O email é inválido");
        }
        
        // Se houver erros, mostra mensagem
        if (!erros.isEmpty()) {
            mensagem = "Erros de validação: " + String.join(", ", erros);
            tipo_mensagem = "danger";
        } else {
            // Captura e sanitiza dados do formulário
            String user = request.getParameter("user");
            String nome = request.getParameter("nome");
            String pwd = passwordHash(request.getParameter("pwd")); // Hash da palavra-passe
            int tipo_perfil = Integer.parseInt(request.getParameter("tipo_perfil"));
            String morada = request.getParameter("morada");
            
            // Verifica se o utilizador ou email já existem
            pstmt = conn.prepareStatement("SELECT * FROM utilizadores WHERE user = ? OR email = ?");
            pstmt.setString(1, user);
            pstmt.setString(2, email);
            rs = pstmt.executeQuery();
            
            if (rs.next()) {
                mensagem = "O utilizador ou email já existe";
                tipo_mensagem = "danger";
            } else {
                // Insere novo utilizador na base de dados
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
                        // Se for cliente, cria carteira com saldo inicial 0
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
    
    // Processa o formulário de edição de utilizador existente
    if ("POST".equals(request.getMethod()) && request.getParameter("editar") != null) {
        // Captura dados do formulário
        int id = Integer.parseInt(request.getParameter("id"));
        String nome = request.getParameter("nome");
        String email = request.getParameter("email");
        String telemovel = request.getParameter("telemovel");
        String morada = request.getParameter("morada");
        String pwd = request.getParameter("pwd");
        
        if (pwd != null && !pwd.trim().isEmpty()) {
            // Se for fornecida nova palavra-passe, atualiza também a palavra-passe
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
            // Sem atualização de palavra-passe
            pstmt = conn.prepareStatement(
                "UPDATE utilizadores SET nome = ?, email = ?, telemovel = ?, morada = ? WHERE id = ?"
            );
            pstmt.setString(1, nome);
            pstmt.setString(2, email);
            pstmt.setString(3, telemovel);
            pstmt.setString(4, morada);
            pstmt.setInt(5, id);
        }
        
        // Executa a atualização
        if (pstmt.executeUpdate() > 0) {
            mensagem = "Utilizador editado com sucesso!";
            tipo_mensagem = "success";
        } else {
            mensagem = "Erro ao editar utilizador";
            tipo_mensagem = "danger";
        }
    }
    
    // Processa pedido para ativar/inativar utilizador
    if (request.getParameter("alterar_estado") != null && request.getParameter("id") != null) {
        int id = Integer.parseInt(request.getParameter("id"));
        int id_utilizador = (Integer)session.getAttribute("id_utilizador");
        
        // Não permite que o administrador desative a sua própria conta
        if (id != id_utilizador) {
            int ativo = Integer.parseInt(request.getParameter("ativo"));
            
            // Atualiza estado do utilizador
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
    
    // Busca lista de utilizadores para mostrar na tabela
    StringBuilder sql = new StringBuilder();
    sql.append("SELECT u.id, u.user, u.nome, u.email, u.telemovel, u.morada, p.descricao as tipo, ");
    sql.append("u.tipo_perfil, u.ativo, IFNULL(c.saldo, 0) as saldo, ");
    sql.append("(SELECT COUNT(*) FROM bilhetes b WHERE b.id_cliente = u.id) as total_bilhetes ");
    sql.append("FROM utilizadores u ");
    sql.append("LEFT JOIN perfis p ON u.tipo_perfil = p.id ");
    sql.append("LEFT JOIN carteiras c ON u.id = c.id_cliente ");
    sql.append("WHERE 1=1 ");
    
    // Filtro por estado (ativo/inativo)
    if (mostrar_inativos == 0) {
        sql.append("AND u.ativo = 1 ");
    }
    
    // Filtro por pesquisa
    if (pesquisa != null && !pesquisa.trim().isEmpty()) {
        sql.append("AND (u.nome LIKE ? OR u.email LIKE ? OR u.user LIKE ?) ");
    }
    
    sql.append("ORDER BY u.id ASC");
    
    pstmt = conn.prepareStatement(sql.toString());
    
    // Define parâmetros de pesquisa se necessário
    if (pesquisa != null && !pesquisa.trim().isEmpty()) {
        String termoPesquisa = "%" + pesquisa + "%";
        pstmt.setString(1, termoPesquisa);
        pstmt.setString(2, termoPesquisa);
        pstmt.setString(3, termoPesquisa);
    }
    
    rs = pstmt.executeQuery();
    
    while (rs.next()) {
        Map<String, Object> utilizador = new HashMap<>();
        utilizador.put("id", rs.getInt("id"));
        utilizador.put("user", rs.getString("user"));
        utilizador.put("nome", rs.getString("nome"));
        utilizador.put("email", rs.getString("email"));
        utilizador.put("telemovel", rs.getString("telemovel"));
        utilizador.put("morada", rs.getString("morada"));
        utilizador.put("tipo", rs.getString("tipo"));
        utilizador.put("tipo_perfil", rs.getInt("tipo_perfil"));
        utilizador.put("ativo", rs.getInt("ativo"));
        utilizador.put("saldo", rs.getDouble("saldo"));
        utilizador.put("total_bilhetes", rs.getInt("total_bilhetes"));
        utilizadores.add(utilizador);
    }
    
} catch (Exception e) {
    mensagem = "Erro: " + e.getMessage();
    tipo_mensagem = "danger";
    e.printStackTrace();
} finally {
    if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
    if (stmt != null) try { stmt.close(); } catch (SQLException e) { /* ignorar */ }
    if (pstmt != null) try { pstmt.close(); } catch (SQLException e) { /* ignorar */ }
    if (conn != null) try { conn.close(); } catch (SQLException e) { /* ignorar */ }
}
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="pg_admin.css">
    <link rel="stylesheet" href="gerir_utilizadores.css">
    <title>FelixBus - Gestão de Utilizadores</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"><a href="pg_admin.jsp">Voltar para Página Inicial</a></div>
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
        
        <div class="admin-controls">
            <div class="search-filter">
                <form method="get" action="gerir_utilizadores.jsp">
                    <input type="text" name="pesquisa" placeholder="Pesquisar por nome, email ou username" value="<%= pesquisa %>">
                    <button type="submit">Pesquisar</button>
                </form>
            </div>
            
            <div class="filter-options">
                <a href="gerir_utilizadores.jsp?mostrar_inativos=<%= mostrar_inativos == 1 ? "0" : "1" %>" class="filter-btn">
                    <%= mostrar_inativos == 1 ? "Ocultar Inativos" : "Mostrar Inativos" %>
                </a>
                <button onclick="document.getElementById('modal-adicionar').style.display='block'" class="add-btn">Adicionar Utilizador</button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
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
                        <tr>
                            <td><%= utilizador.get("id") %></td>
                            <td><%= h((String)utilizador.get("user")) %></td>
                            <td><%= h((String)utilizador.get("nome")) %></td>
                            <td><%= h((String)utilizador.get("email")) %></td>
                            <td><%= h((String)utilizador.get("telemovel")) %></td>
                            <td><%= h((String)utilizador.get("tipo")) %></td>
                            <td>
                                <% if ((Integer)utilizador.get("tipo_perfil") == 3) { %>
                                    €<%= String.format("%.2f", (Double)utilizador.get("saldo")).replace('.', ',') %>
                                <% } else { %>
                                    -
                                <% } %>
                            </td>
                            <td><%= utilizador.get("total_bilhetes") %></td>
                            <td class="text-center">
                                <span class="status-badge <%= (Integer)utilizador.get("ativo") == 1 ? "status-active" : "status-inactive" %>">
                                    <%= (Integer)utilizador.get("ativo") == 1 ? "Ativo" : "Inativo" %>
                                </span>
                            </td>
                            <td class="actions-column">
                                <a href="javascript:void(0)" onclick="editarUtilizador(<%= utilizador.get("id") %>)" class="btn-edit">Editar</a>
                                
                                <% if ((Integer)utilizador.get("ativo") == 1) { %>
                                    <a href="gerir_utilizadores.jsp?alterar_estado=1&id=<%= utilizador.get("id") %>&ativo=0" 
                                       class="btn-delete" 
                                       onclick="return confirm('Tem certeza que deseja desativar este utilizador?')">Desativar</a>
                                <% } else { %>
                                    <a href="gerir_utilizadores.jsp?alterar_estado=1&id=<%= utilizador.get("id") %>&ativo=1" 
                                       class="btn-activate" 
                                       onclick="return confirm('Tem certeza que deseja ativar este utilizador?')">Ativar</a>
                                <% } %>
                            </td>
                        </tr>
                    <% } %>
                </tbody>
            </table>
        </div>
    </section>
    
    <!-- Modal para adicionar utilizador -->
    <div id="modal-adicionar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('modal-adicionar').style.display='none'">&times;</span>
            <h2>Adicionar Novo Utilizador</h2>
            <form method="post" action="gerir_utilizadores.jsp">
                <div class="form-group">
                    <label for="user">Nome de Utilizador:</label>
                    <input type="text" id="user" name="user" required>
                </div>
                <div class="form-group">
                    <label for="nome">Nome Completo:</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="pwd">Palavra-passe:</label>
                    <input type="password" id="pwd" name="pwd" required>
                </div>
                <div class="form-group">
                    <label for="telemovel">Telemóvel:</label>
                    <input type="text" id="telemovel" name="telemovel" required maxlength="9" minlength="9" 
                           pattern="[0-9]{9}" title="O telemóvel deve conter 9 dígitos">
                </div>
                <div class="form-group">
                    <label for="morada">Morada:</label>
                    <textarea id="morada" name="morada" required></textarea>
                </div>
                <div class="form-group">
                    <label for="tipo_perfil">Tipo de Utilizador:</label>
                    <select id="tipo_perfil" name="tipo_perfil" required>
                        <option value="1">Administrador</option>
                        <option value="2">Funcionário</option>
                        <option value="3" selected>Cliente</option>
                    </select>
                </div>
                <button type="submit" name="adicionar" value="1">Adicionar Utilizador</button>
            </form>
        </div>
    </div>
    
    <!-- Modal para editar utilizador -->
    <div id="modal-editar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('modal-editar').style.display='none'">&times;</span>
            <h2>Editar Utilizador</h2>
            <form method="post" action="gerir_utilizadores.jsp">
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
                    <input type="text" id="edit-telemovel" name="telemovel" required maxlength="9" minlength="9"
                           pattern="[0-9]{9}" title="O telemóvel deve conter 9 dígitos">
                </div>
                <div class="form-group">
                    <label for="edit-morada">Morada:</label>
                    <textarea id="edit-morada" name="morada" required></textarea>
                </div>
                <button type="submit" name="editar" value="1">Atualizar</button>
            </form>
        </div>
    </div>
    
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
    
    <script>
        // Função para abrir o modal de edição e preencher com os dados do utilizador
        function editarUtilizador(id) {
            // Fazer uma requisição AJAX para buscar os dados do utilizador
            fetch('gerir_utilizadores.jsp?editar=' + id)
                .then(response => {
                    // Recarregar a página para obter os dados do utilizador
                    window.location.href = 'gerir_utilizadores.jsp?editar=' + id;
                })
                .catch(error => {
                    console.error('Erro ao buscar dados:', error);
                });
        }
        
        // Se houver um utilizador para editar, abrir o modal automaticamente
        <% if (utilizadorParaEditar != null) { %>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('modal-editar');
                document.getElementById('edit-id').value = '<%= utilizadorParaEditar.get("id") %>';
                document.getElementById('edit-nome').value = '<%= h((String)utilizadorParaEditar.get("nome")) %>';
                document.getElementById('edit-email').value = '<%= h((String)utilizadorParaEditar.get("email")) %>';
                document.getElementById('edit-telemovel').value = '<%= h((String)utilizadorParaEditar.get("telemovel")) %>';
                document.getElementById('edit-morada').value = '<%= h((String)utilizadorParaEditar.get("morada")) %>';
                modal.style.display = 'block';
            });
        <% } %>
        
        // Fechar o modal quando o usuário clicar fora dele
        window.onclick = function(event) {
            var modalAdicionar = document.getElementById('modal-adicionar');
            var modalEditar = document.getElementById('modal-editar');
            if (event.target == modalAdicionar) {
                modalAdicionar.style.display = 'none';
            }
            if (event.target == modalEditar) {
                modalEditar.style.display = 'none';
            }
        }
    </script>
</body>
</html>


