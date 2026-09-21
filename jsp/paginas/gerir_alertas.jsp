<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*, java.util.UUID" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
// Verifica se o utilizador tem nível de administrador (id_nivel == 1).
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
    response.sendRedirect("erro.jsp");
    return;
}

// Gera um token CSRF para segurança dos formulários
String csrfToken = UUID.randomUUID().toString();
session.setAttribute("csrfToken", csrfToken);

// Variáveis para feedback ao utilizador e controlo de estado
String mensagem_feedback = "";
String tipo_mensagem = "";
Map<String, String> alerta_para_editar = null;

// Parâmetros de ordenação e filtro para a tabela de alertas
String ordenacao = request.getParameter("ordenacao") != null ? request.getParameter("ordenacao") : "id";
String direcao = request.getParameter("direcao") != null ? request.getParameter("direcao") : "asc";
String filtro = request.getParameter("filtro") != null ? request.getParameter("filtro") : "";

// Inicializa a ligação à base de dados
Connection conn = null;
PreparedStatement pstmt = null;
ResultSet rs = null;

try {
    // Obtém a ligação à base de dados
    conn = getConnection();
    
    // Se foi pedido para editar um alerta, carrega os dados desse alerta
    if (request.getParameter("editar") != null) {
        int id_editar = Integer.parseInt(request.getParameter("editar"));
        
        PreparedStatement stmt_editar = conn.prepareStatement("SELECT * FROM alertas WHERE id = ?");
        stmt_editar.setInt(1, id_editar);
        ResultSet rs_editar = stmt_editar.executeQuery();
        
        if (rs_editar.next()) {
            alerta_para_editar = new HashMap<>();
            alerta_para_editar.put("id", String.valueOf(rs_editar.getInt("id")));
            alerta_para_editar.put("mensagem", rs_editar.getString("mensagem"));
            
            // Formata as datas para o formato do input type="date"
            String dataInicio = rs_editar.getString("data_inicio");
            String dataFim = rs_editar.getString("data_fim");
            
            // Extrai apenas a parte da data (YYYY-MM-DD)
            if (dataInicio != null && dataInicio.length() >= 10) {
                dataInicio = dataInicio.substring(0, 10);
            }
            if (dataFim != null && dataFim.length() >= 10) {
                dataFim = dataFim.substring(0, 10);
            }
            
            alerta_para_editar.put("data_inicio", dataInicio);
            alerta_para_editar.put("data_fim", dataFim);
        }
        
        rs_editar.close();
        stmt_editar.close();
    }
    
    // Processa o formulário para adicionar um novo alerta
    if ("POST".equals(request.getMethod()) && request.getParameter("adicionar") != null) {
        // Verifica o token CSRF
        String requestToken = request.getParameter("csrfToken");
        String sessionToken = (String)session.getAttribute("csrfToken");
        
        if (sessionToken == null || !sessionToken.equals(requestToken)) {
            mensagem_feedback = "Erro de segurança: token inválido!";
            tipo_mensagem = "error";
        } else {
            String mensagem = request.getParameter("mensagem");
            // Sanitiza o conteúdo HTML da mensagem
            mensagem = mensagem.replace("<", "&lt;").replace(">", "&gt;");
            
            String data_inicio = request.getParameter("data_inicio");
            String data_fim = request.getParameter("data_fim");
            
            // Validação do lado do servidor
            if (mensagem == null || mensagem.trim().length() < 5) {
                mensagem_feedback = "A mensagem deve ter pelo menos 5 caracteres!";
                tipo_mensagem = "error";
            } else {
                PreparedStatement stmt = conn.prepareStatement("INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES (?, ?, ?)");
                stmt.setString(1, mensagem);
                stmt.setString(2, data_inicio);
                stmt.setString(3, data_fim);
                
                int linhasAfetadas = stmt.executeUpdate();
                stmt.close();
                
                if (linhasAfetadas > 0) {
                    // Redireciona para evitar reenvio do formulário
                    response.sendRedirect("gerir_alertas.jsp?msg=added");
                    return;
                } else {
                    mensagem_feedback = "Erro ao adicionar alerta!";
                    tipo_mensagem = "error";
                }
            }
        }
    }
    
    // Processa o formulário para atualizar um alerta existente
    if ("POST".equals(request.getMethod()) && request.getParameter("atualizar") != null) {
        // Verifica o token CSRF
        String requestToken = request.getParameter("csrfToken");
        String sessionToken = (String)session.getAttribute("csrfToken");
        
        if (sessionToken == null || !sessionToken.equals(requestToken)) {
            mensagem_feedback = "Erro de segurança: token inválido!";
            tipo_mensagem = "error";
        } else {
            // Converte o ID para inteiro para evitar injeção SQL
            int id_alerta = Integer.parseInt(request.getParameter("id_alerta"));
            String mensagem = request.getParameter("mensagem");
            // Sanitiza o conteúdo HTML da mensagem
            mensagem = mensagem.replace("<", "&lt;").replace(">", "&gt;");
            
            String data_inicio = request.getParameter("data_inicio");
            String data_fim = request.getParameter("data_fim");
            
            // Validação do lado do servidor
            if (mensagem == null || mensagem.trim().length() < 5) {
                mensagem_feedback = "A mensagem deve ter pelo menos 5 caracteres!";
                tipo_mensagem = "error";
            } else {
                PreparedStatement stmt = conn.prepareStatement("UPDATE alertas SET mensagem = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
                stmt.setString(1, mensagem);
                stmt.setString(2, data_inicio);
                stmt.setString(3, data_fim);
                stmt.setInt(4, id_alerta);
                
                int linhasAfetadas = stmt.executeUpdate();
                stmt.close();
                
                if (linhasAfetadas > 0) {
                    // Redireciona para evitar reenvio do formulário
                    response.sendRedirect("gerir_alertas.jsp?msg=updated&id=" + id_alerta);
                    return;
                } else {
                    mensagem_feedback = "Erro ao atualizar alerta!";
                    tipo_mensagem = "error";
                }
            }
        }
    }
    
    // Processa o pedido para excluir um alerta
    if (request.getParameter("excluir") != null) {
        int id = Integer.parseInt(request.getParameter("excluir"));
        
        PreparedStatement stmt = conn.prepareStatement("DELETE FROM alertas WHERE id = ?");
        stmt.setInt(1, id);
        
        int linhasAfetadas = stmt.executeUpdate();
        stmt.close();
        
        if (linhasAfetadas > 0) {
            // Redireciona após exclusão
            response.sendRedirect("gerir_alertas.jsp?msg=deleted&id=" + id);
            return;
        } else {
            mensagem_feedback = "Erro ao excluir alerta!";
            tipo_mensagem = "error";
        }
    }
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Alertas</title>
    <link rel="stylesheet" href="gerir_alertas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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

    <!-- MAIN CONTENT -->
    <section class="main-content">
        <div class="container">
            <h1>Gestão de Alertas</h1>
            
            <% if (mensagem_feedback != null && !mensagem_feedback.isEmpty()) { %>
                <div class="alert alert-<%= tipo_mensagem %>">
                    <%= mensagem_feedback %>
                </div>
            <% } %>
            
            <% if (request.getParameter("msg") != null) { 
                String msg = request.getParameter("msg");
                String id = request.getParameter("id") != null ? request.getParameter("id") : "";
                
                if ("added".equals(msg)) { %>
                    <div class="alert alert-success">
                        Alerta adicionado com sucesso!
                    </div>
                <% } else if ("updated".equals(msg)) { %>
                    <div class="alert alert-success">
                        Alerta com ID <%= id %> foi editado com sucesso!
                    </div>
                <% } else if ("deleted".equals(msg)) { %>
                    <div class="alert alert-success">
                        Alerta com ID <%= id %> foi excluído com sucesso!
                    </div>
                <% }
            } %>
            
            <div class="form-container">
                <h2><%= alerta_para_editar != null ? "Editar Alerta" : "Adicionar Novo Alerta" %></h2>
                <form method="post" id="alertaForm">
                    <input type="hidden" name="csrfToken" value="<%= csrfToken %>">
                    
                    <% if (alerta_para_editar != null) { %>
                        <input type="hidden" name="id_alerta" value="<%= alerta_para_editar.get("id") %>">
                    <% } %>
                    
                    <div class="form-group">
                        <label for="mensagem">Mensagem:</label>
                        <textarea id="mensagem" name="mensagem" rows="3" required minlength="5"><%= alerta_para_editar != null ? alerta_para_editar.get("mensagem") : "" %></textarea>
                        <small>Mínimo de 5 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_inicio">Data de Início:</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<%= alerta_para_editar != null ? alerta_para_editar.get("data_inicio") : "" %>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_fim">Data de Fim:</label>
                        <input type="date" id="data_fim" name="data_fim" value="<%= alerta_para_editar != null ? alerta_para_editar.get("data_fim") : "" %>" required>
                    </div>
                    
                    <div class="form-actions">
                        <% if (alerta_para_editar != null) { %>
                            <button type="submit" name="atualizar" value="1" class="btn-submit">Atualizar Alerta</button>
                            <a href="gerir_alertas.jsp" class="btn-cancel">Cancelar</a>
                        <% } else { %>
                            <button type="submit" name="adicionar" value="1" class="btn-submit">Adicionar Alerta</button>
                        <% } %>
                    </div>
                </form>
            </div>
            
            <div class="filter-container">
                <form method="get" action="gerir_alertas.jsp" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="filtro" placeholder="Filtrar por mensagem" value="<%= filtro %>">
                        <button type="submit" class="btn-filter">Filtrar</button>
                    </div>
                    
                    <div class="sort-options">
                        <label>Ordenar por:</label>
                        <select name="ordenacao" onchange="this.form.submit()">
                            <option value="id" <%= ordenacao.equals("id") ? "selected" : "" %>>ID</option>
                            <option value="mensagem" <%= ordenacao.equals("mensagem") ? "selected" : "" %>>Mensagem</option>
                            <option value="data_inicio" <%= ordenacao.equals("data_inicio") ? "selected" : "" %>>Data de Início</option>
                            <option value="data_fim" <%= ordenacao.equals("data_fim") ? "selected" : "" %>>Data de Fim</option>
                        </select>
                        
                        <select name="direcao" onchange="this.form.submit()">
                            <option value="asc" <%= direcao.equals("asc") ? "selected" : "" %>>Crescente</option>
                            <option value="desc" <%= direcao.equals("desc") ? "selected" : "" %>>Decrescente</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <h2>Alertas Existentes</h2>
                <table class="alertas-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mensagem</th>
                            <th>Data de Início</th>
                            <th>Data de Fim</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <%
                        // Busca todos os alertas para exibir na tabela
                        StringBuilder sqlQuery = new StringBuilder("SELECT * FROM alertas");
                        
                        // Adiciona filtro se fornecido
                        if (filtro != null && !filtro.trim().isEmpty()) {
                            sqlQuery.append(" WHERE mensagem LIKE ?");
                        }
                        
                        // Adiciona ordenação
                        sqlQuery.append(" ORDER BY ");
                        
                        // Verifica qual coluna usar para ordenação
                        if (ordenacao.equals("mensagem")) {
                            sqlQuery.append("mensagem");
                        } else if (ordenacao.equals("data_inicio")) {
                            sqlQuery.append("data_inicio");
                        } else if (ordenacao.equals("data_fim")) {
                            sqlQuery.append("data_fim");
                        } else {
                            sqlQuery.append("id"); // Ordenação padrão
                        }
                        
                        // Adiciona direção da ordenação
                        sqlQuery.append(" ").append(direcao.equals("desc") ? "DESC" : "ASC");
                        
                        pstmt = conn.prepareStatement(sqlQuery.toString());
                        
                        // Define parâmetro de filtro se necessário
                        if (filtro != null && !filtro.trim().isEmpty()) {
                            pstmt.setString(1, "%" + filtro + "%");
                        }
                        
                        rs = pstmt.executeQuery();
                        
                        // Formata as datas para exibição
                        SimpleDateFormat formatoData = new SimpleDateFormat("dd/MM/yyyy");
                        SimpleDateFormat formatoBD = new SimpleDateFormat("yyyy-MM-dd");
                        
                        while (rs.next()) {
                            int id = rs.getInt("id");
                            String mensagem = rs.getString("mensagem");
                            String dataInicio = rs.getString("data_inicio");
                            String dataFim = rs.getString("data_fim");
                            
                            // Formata as datas para exibição
                            String dataInicioFormatada = "";
                            String dataFimFormatada = "";
                            
                            try {
                                java.util.Date dataI = formatoBD.parse(dataInicio);
                                java.util.Date dataF = formatoBD.parse(dataFim);
                                dataInicioFormatada = formatoData.format(dataI);
                                dataFimFormatada = formatoData.format(dataF);
                            } catch (Exception e) {
                                dataInicioFormatada = dataInicio;
                                dataFimFormatada = dataFim;
                            }
                        %>
                        <tr>
                            <td><%= id %></td>
                            <td><%= mensagem %></td>
                            <td><%= dataInicioFormatada %></td>
                            <td><%= dataFimFormatada %></td>
                            <td>
                                <a href="javascript:void(0)" onclick="confirmarEdicao(<%= id %>)" class="btn-edit">Editar</a>
                                <a href="javascript:void(0)" onclick="confirmarExclusao(<%= id %>)" class="btn-delete">Excluir</a>
                            </td>
                        </tr>
                        <% } %>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    
    <!-- FOOTER -->
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

    <script>
        // Validação do formulário no lado do cliente
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('alertaForm');
            
            form.addEventListener('submit', function(e) {
                // Valida a mensagem
                const mensagem = document.getElementById('mensagem').value.trim();
                if (mensagem.length < 5) {
                    alert('A mensagem deve ter pelo menos 5 caracteres!');
                    e.preventDefault();
                    return false;
                }
                
                // Valida as datas
                const dataInicio = new Date(document.getElementById('data_inicio').value);
                const dataFim = new Date(document.getElementById('data_fim').value);
                
                if (isNaN(dataInicio.getTime()) || isNaN(dataFim.getTime())) {
                    alert('Por favor, insira datas válidas!');
                    e.preventDefault();
                    return false;
                }
                
                if (dataInicio >= dataFim) {
                    alert('A data de início deve ser anterior à data de fim!');
                    e.preventDefault();
                    return false;
                }
            });
        });
        
        // Confirmação antes de excluir um alerta
        function confirmarExclusao(id) {
            if (confirm("Tem a certeza que deseja eliminar o alerta ID " + id + "?")) {
                window.location.href = "gerir_alertas.jsp?excluir=" + id;
            }
        }
        
        // Confirmação antes de editar um alerta
        function confirmarEdicao(id) {
            if (confirm("Deseja editar o alerta ID " + id + "?")) {
                window.location.href = "gerir_alertas.jsp?editar=" + id;
            }
        }
    </script>

<%
} finally {
    // Fecha recursos da base de dados
    if (rs != null) {
        try { rs.close(); } catch (SQLException e) { e.printStackTrace(); }
    }
    if (pstmt != null) {
        try { pstmt.close(); } catch (SQLException e) { e.printStackTrace(); }
    }
    if (conn != null) {
        try { conn.close(); } catch (SQLException e) { e.printStackTrace(); }
    }
}
%>
</body>

