<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
// Verificar se o utilizador é administrador
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
    response.sendRedirect("erro.jsp");
    return;
}

// Inicializa variáveis para mensagens de feedback e controlo de estado
String mensagem_feedback = "";
String tipo_mensagem = "";
Map<String, String> alerta_para_editar = null;

// Obter conexão com o banco de dados
Connection conn = null;
try {
    conn = getConnection();
    
    // Verifica se foi solicitada a edição de um alerta através do URL
    if (request.getParameter("editar") != null && !request.getParameter("editar").isEmpty()) {
        // Converte o ID para inteiro para evitar injeção SQL
        int id_editar = Integer.parseInt(request.getParameter("editar"));
        
        // Utiliza prepared statement para buscar o alerta a ser editado
        PreparedStatement stmt_editar = conn.prepareStatement("SELECT * FROM alertas WHERE id = ?");
        stmt_editar.setInt(1, id_editar);
        ResultSet result_editar = stmt_editar.executeQuery();

        // Se encontrar o alerta, guarda os dados para preencher o formulário
        if (result_editar.next()) {
            alerta_para_editar = new HashMap<>();
            alerta_para_editar.put("id", result_editar.getInt("id"));
            alerta_para_editar.put("mensagem", result_editar.getString("mensagem"));
            alerta_para_editar.put("data_inicio", result_editar.getString("data_inicio"));
            alerta_para_editar.put("data_fim", result_editar.getString("data_fim"));
        }
        // Liberta recursos da consulta
        result_editar.close();
        stmt_editar.close();
    }

    // Processa o formulário para adicionar um novo alerta
    if ("POST".equals(request.getMethod()) && request.getParameter("adicionar") != null) {
        String mensagem = request.getParameter("mensagem");
        String data_inicio = request.getParameter("data_inicio");
        String data_fim = request.getParameter("data_fim");
        
        // Valida as datas inseridas pelo utilizador
        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd");
        java.util.Date dataInicio = null;
        java.util.Date dataFim = null;
        
        try {
            dataInicio = sdf.parse(data_inicio);
            dataFim = sdf.parse(data_fim);
        } catch (ParseException e) {
            mensagem_feedback = "Data inválida!";
            tipo_mensagem = "error";
        }
        
        if (dataInicio != null && dataFim != null && dataInicio.after(dataFim)) {
            // Verifica se a data de início é anterior à data de fim
            mensagem_feedback = "A data de início deve ser anterior à data de fim!";
            tipo_mensagem = "error";
        } else if (dataInicio != null && dataFim != null) {
            // Utiliza prepared statement para inserir o novo alerta na base de dados
            PreparedStatement stmt = conn.prepareStatement("INSERT INTO alertas (mensagem, data_inicio, data_fim) VALUES (?, ?, ?)");
            stmt.setString(1, mensagem);
            stmt.setString(2, data_inicio);
            stmt.setString(3, data_fim);
            
            // Executa a inserção e verifica se foi bem-sucedida
            if(stmt.executeUpdate() > 0) {
                // Redireciona após sucesso para evitar reenvio do formulário ao atualizar a página
                response.sendRedirect("gerir_alertas.jsp?msg=added");
                return;
            } else {
                // Em caso de erro, mostra a mensagem de erro
                mensagem_feedback = "Erro ao adicionar alerta: " + conn.getWarnings();
                tipo_mensagem = "error";
            }
            // Liberta recursos da consulta
            stmt.close();
        }
    }

    // Processa o formulário para atualizar um alerta existente
    if ("POST".equals(request.getMethod()) && request.getParameter("atualizar") != null) {
        // Converte o ID para inteiro para evitar injeção SQL
        int id_alerta = Integer.parseInt(request.getParameter("id_alerta"));
        String mensagem = request.getParameter("mensagem");
        String data_inicio = request.getParameter("data_inicio");
        String data_fim = request.getParameter("data_fim");
        
        // Valida as datas inseridas pelo utilizador
        SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd");
        java.util.Date dataInicio = null;
        java.util.Date dataFim = null;
        
        try {
            dataInicio = sdf.parse(data_inicio);
            dataFim = sdf.parse(data_fim);
        } catch (ParseException e) {
            mensagem_feedback = "Data inválida!";
            tipo_mensagem = "error";
        }
        
        if (dataInicio != null && dataFim != null && dataInicio.after(dataFim)) {
            // Verifica se a data de início é anterior à data de fim
            mensagem_feedback = "A data de início deve ser anterior à data de fim!";
            tipo_mensagem = "error";
        } else if (dataInicio != null && dataFim != null) {
            // Utiliza prepared statement para atualizar o alerta na base de dados
            PreparedStatement stmt = conn.prepareStatement("UPDATE alertas SET mensagem = ?, data_inicio = ?, data_fim = ? WHERE id = ?");
            stmt.setString(1, mensagem);
            stmt.setString(2, data_inicio);
            stmt.setString(3, data_fim);
            stmt.setInt(4, id_alerta);
            
            // Executa a atualização e verifica se foi bem-sucedida
            if (stmt.executeUpdate() > 0) {
                mensagem_feedback = "Alerta com ID " + id_alerta + " foi editado com sucesso!";
                tipo_mensagem = "success";
                stmt.close();
                // Redireciona para limpar o formulário de edição e mostrar mensagem de sucesso
                response.sendRedirect("gerir_alertas.jsp?msg=updated&id=" + id_alerta);
                return;
            } else {
                // Em caso de erro, mostra a mensagem de erro
                mensagem_feedback = "Erro ao atualizar alerta ID " + id_alerta + ": " + conn.getWarnings();
                tipo_mensagem = "error";
            }
            // Liberta recursos da consulta
            stmt.close();
        }
    }

    // Processa o pedido para excluir um alerta
    if (request.getParameter("excluir") != null) {
        // Converte o ID para inteiro para evitar injeção SQL
        int id = Integer.parseInt(request.getParameter("excluir"));
        
        // Utiliza prepared statement para excluir o alerta da base de dados
        PreparedStatement stmt = conn.prepareStatement("DELETE FROM alertas WHERE id = ?");
        stmt.setInt(1, id);
        
        // Executa a exclusão e verifica se foi bem-sucedida
        if(stmt.executeUpdate() > 0) {
            // Redireciona após sucesso para evitar reenvio do comando ao atualizar a página
            response.sendRedirect("gerir_alertas.jsp?msg=deleted&id=" + id);
            return;
        } else {
            // Em caso de erro, mostra a mensagem de erro
            mensagem_feedback = "Erro ao excluir alerta ID " + id + ": " + conn.getWarnings();
            tipo_mensagem = "error";
        }
        // Liberta recursos da consulta
        stmt.close();
    }

    // Define mensagem se vier de um redirecionamento após operações
    if (request.getParameter("msg") != null) {
        String msg = request.getParameter("msg");
        if ("updated".equals(msg)) {
            int id = Integer.parseInt(request.getParameter("id"));
            mensagem_feedback = "Alerta com ID " + id + " foi atualizado com sucesso!";
            tipo_mensagem = "success";
        } else if ("added".equals(msg)) {
            mensagem_feedback = "Alerta adicionado com sucesso!";
            tipo_mensagem = "success";
        } else if ("deleted".equals(msg)) {
            int id = Integer.parseInt(request.getParameter("id"));
            mensagem_feedback = "Alerta com ID " + id + " foi excluído com sucesso!";
            tipo_mensagem = "success";
        }
    }
%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_alertas.css">
    <title>FelixBus - Gestão de Alertas</title>
</head>
<body>
    <!-- NAVBAR -->
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
    <section>
        <h1>Gestão de Alertas</h1>
        
        <% if (!mensagem_feedback.isEmpty()) { %>
            <div class="alert <%= "success".equals(tipo_mensagem) ? "alert-success" : "alert-danger" %>">
                <%= mensagem_feedback %>
            </div>
        <% } %>
        
        <div class="container">
            <div class="form-container">
                <h2><%= alerta_para_editar != null ? "Editar Alerta" : "Adicionar Novo Alerta" %></h2>
                <form method="post" action="gerir_alertas.jsp">
                    <% if (alerta_para_editar != null) { %>
                        <input type="hidden" name="id_alerta" value="<%= alerta_para_editar.get("id") %>">
                    <% } %>
                    
                    <div class="form-group">
                        <label for="mensagem">Mensagem:</label>
                        <textarea id="mensagem" name="mensagem" required><%= alerta_para_editar != null ? alerta_para_editar.get("mensagem") : "" %></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_inicio">Data de Início:</label>
                            <input type="date" id="data_inicio" name="data_inicio" value="<%= alerta_para_editar != null ? alerta_para_editar.get("data_inicio") : "" %>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_fim">Data de Fim:</label>
                            <input type="date" id="data_fim" name="data_fim" value="<%= alerta_para_editar != null ? alerta_para_editar.get("data_fim") : "" %>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="<%= alerta_para_editar != null ? "atualizar" : "adicionar" %>" class="btn-primary">
                            <%= alerta_para_editar != null ? "Atualizar Alerta" : "Adicionar Alerta" %>
                        </button>
                        <% if (alerta_para_editar != null) { %>
                            <a href="gerir_alertas.jsp" class="btn-secondary">Cancelar</a>
                        <% } %>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <h2>Alertas Existentes</h2>
                <table>
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
                        Statement stmt = conn.createStatement();
                        ResultSet rs = stmt.executeQuery("SELECT * FROM alertas ORDER BY data_inicio DESC");
                        
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
                                <a href="gerir_alertas.jsp?editar=<%= id %>" class="btn-edit">Editar</a>
                                <a href="javascript:void(0)" onclick="confirmarExclusao(<%= id %>)" class="btn-delete">Excluir</a>
                            </td>
                        </tr>
                        <% } 
                        // Fecha recursos
                        rs.close();
                        stmt.close();
                        %>
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
        function confirmarExclusao(id) {
            if (confirm("Tem certeza que deseja excluir o alerta ID " + id + "?")) {
                window.location.href = "gerir_alertas.jsp?excluir=" + id;
            }
        }
    </script>

<%
} finally {
    // Fechar conexão com o banco de dados
    if (conn != null) {
        try {
            conn.close();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }
}
%>
</body>

