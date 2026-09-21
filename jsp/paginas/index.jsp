<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
// Verifica se o utilizador já está autenticado
if (session.getAttribute("id_nivel") != null && (Integer)session.getAttribute("id_nivel") > 0) {
    response.sendRedirect("erro.jsp");
    return;
}

// Inicializa variáveis para a pesquisa de rotas
String origem = "";
String destino = "";
List<Map<String, String>> resultados = new ArrayList<>();
boolean pesquisa_realizada = false;

// Obter ligação à base de dados
Connection conn = null;
try {
    conn = getConnection();

    // Verifica se o formulário de pesquisa foi submetido
    if ("POST".equals(request.getMethod()) && request.getParameter("pesquisar") != null) {
        origem = request.getParameter("origem") != null ? request.getParameter("origem").trim() : "";
        destino = request.getParameter("destino") != null ? request.getParameter("destino").trim() : "";
        pesquisa_realizada = true;

        // Constrói a consulta SQL para pesquisar rotas
        StringBuilder sql = new StringBuilder("SELECT r.id, r.origem, r.destino, r.preco, h.horario_partida, h.data_viagem " +
                                             "FROM rotas r " +
                                             "JOIN horarios h ON r.id = h.id_rota " +
                                             "WHERE r.disponivel = 1");

        List<String> params = new ArrayList<>();

        // Adiciona filtros de origem e destino se fornecidos
        if (!origem.isEmpty()) {
            sql.append(" AND r.origem LIKE ?");
            params.add("%" + origem + "%");
        }
        if (!destino.isEmpty()) {
            sql.append(" AND r.destino LIKE ?");
            params.add("%" + destino + "%");
        }

        sql.append(" ORDER BY r.origem ASC, r.destino ASC, h.data_viagem ASC, h.horario_partida ASC");

        try (PreparedStatement stmt = conn.prepareStatement(sql.toString())) {
            // Define os parâmetros da pesquisa
            for (int i = 0; i < params.size(); i++) {
                stmt.setString(i + 1, params.get(i));
            }

            // Executa a consulta e armazena os resultados
            ResultSet rs = stmt.executeQuery();
            while (rs.next()) {
                Map<String, String> row = new HashMap<>();
                row.put("id", rs.getString("id"));
                row.put("origem", rs.getString("origem"));
                row.put("destino", rs.getString("destino"));
                row.put("preco", rs.getString("preco"));
                row.put("horario_partida", rs.getString("horario_partida"));
                row.put("data_viagem", rs.getString("data_viagem"));
                resultados.add(row);
            }
        } catch (SQLException e) {
            out.println("<script>alert('Erro na consulta: " + e.getMessage() + "');</script>");
        }
    }

    // Busca alertas dinâmicos para mostrar na página
    List<Map<String, String>> mensagens = new ArrayList<>();
    String data_atual = new java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss").format(new java.util.Date());

    try (Statement stmt = conn.createStatement();
         ResultSet rs = stmt.executeQuery("SELECT * FROM alertas")) {
        while (rs.next()) {
            Map<String, String> mensagem = new HashMap<>();
            mensagem.put("conteudo", rs.getString("mensagem"));
            mensagens.add(mensagem);
        }
    } catch (SQLException e) {
        out.println("<!-- Erro na consulta: " + e.getMessage() + " -->");
    }
    
    // Armazena as variáveis para uso no JSP
    pageContext.setAttribute("mensagens", mensagens);
    pageContext.setAttribute("resultados", resultados);
    pageContext.setAttribute("origem", origem);
    pageContext.setAttribute("destino", destino);
    pageContext.setAttribute("pesquisa_realizada", pesquisa_realizada);
    
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
    <link rel="stylesheet" href="estilo.css">
    <title>FelixBus - Home</title>
</head>
<body>
    <nav>
        <a href="index.jsp" class="logo">
            <h1>Felix<span>Bus</span></h1>
        </a>
        <div class="links">
            <div class="link"><a href="index.jsp">HOME</a></div>
            <div class="link"><a href="servicos.jsp">SERVIÇOS</a></div>
            <div class="link"><a href="contactos.jsp">CONTACTOS</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="login.jsp"><button>Login</button></a></div>
            <div class="btn"><a href="registar.jsp"><button class="register-btn">Registar</button></a></div>
        </div>
    </nav>

<section class="main-section">
    <div class="hero-content">
        <h1>Bem-vindo à FelixBus</h1>
        <p>A sua viagem começa aqui</p>
    </div>

    <div class="consulta-container">
        <h2>Descobre a sua viagem</h2>

        <div class="search-form">
            <form method="post" action="index.jsp">
                <div class="form-row">
                    <div class="form-group">
                        <label for="origem">Origem:</label>
                        <input type="text" id="origem" name="origem" placeholder="Ex: Lisboa" value="<%= origem %>">
                    </div>

                    <div class="form-group">
                        <label for="destino">Destino:</label>
                        <input type="text" id="destino" name="destino" placeholder="Ex: Porto" value="<%= destino %>">
                    </div>

                    <div class="form-group">
                        <button type="submit" name="pesquisar" class="search-btn">Pesquisar</button>
                    </div>
                </div>
            </form>
        </div>

        <% if (pesquisa_realizada) { %>
            <div class="results-container" id="resultsContainer">
                <% if (resultados.isEmpty()) { %>
                    <p class="no-results">Nenhum resultado encontrado.</p>
                <% } else { %>
                    <h3>Resultados da Pesquisa</h3>
                    <div class="results-table-wrapper">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Data</th>
                                    <th>Horário</th>
                                    <th>Preço</th>
                                </tr>
                            </thead>
                            <tbody>
                                <% for (Map<String, String> rota : resultados) { 
                                    java.text.SimpleDateFormat dateFormat = new java.text.SimpleDateFormat("yyyy-MM-dd");
                                    java.util.Date data = dateFormat.parse(rota.get("data_viagem"));
                                    java.text.SimpleDateFormat displayFormat = new java.text.SimpleDateFormat("dd/MM/yyyy");
                                %>
                                    <tr>
                                        <td><%= rota.get("origem") %></td>
                                        <td><%= rota.get("destino") %></td>
                                        <td><%= displayFormat.format(data) %></td>
                                        <td><%= rota.get("horario_partida") %></td>
                                        <td><%= String.format("%.2f", Double.parseDouble(rota.get("preco"))) %> €</td>
                                    </tr>
                                <% } %>
                            </tbody>
                        </table>
                    </div>
                <% } %>
            </div>
        <% } %>
    </div>
</section>

    <!-- Alertas -->
    <div class="alertas-box">
        <div class="alertas-titulo">AVISOS IMPORTANTES</div>
        <% 
        List<Map<String, String>> mensagensLista = (List<Map<String, String>>)pageContext.getAttribute("mensagens");
        if (mensagensLista == null || mensagensLista.isEmpty()) { 
        %>
            <div class="alerta-item">Nenhum aviso no momento.</div>
        <% } else { %>
            <% for (Map<String, String> mensagem : mensagensLista) { %>
                <div class="alerta-item">
                    <%= mensagem.get("conteudo").replaceAll("<", "&lt;").replaceAll(">", "&gt;") %>
                </div>
            <% } %>
        <% } %>
    </div>

    <footer>
        © <%= new java.text.SimpleDateFormat("yyyy").format(new java.util.Date()) %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>
