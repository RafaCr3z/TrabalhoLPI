<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%!
// Adicionar esta função utilitária no topo do ficheiro
public String escapeHtml(String input) {
    if (input == null) return "";
    return input.replace("&", "&amp;")
                .replace("<", "&lt;")
                .replace(">", "&gt;")
                .replace("\"", "&quot;")
                .replace("'", "&#x27;");
}
%>

<%
// Verificar se o utilizador é administrador
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 1) {
    response.sendRedirect("erro.jsp");
    return;
}

// Inicializar variáveis
String mensagem = "";
String tipo_mensagem = "";
Map<String, Object> rota_para_editar = null;
Map<String, Object> horario_para_editar = null;
List<Map<String, Object>> rotas = new ArrayList<>();
List<Map<String, Object>> horarios = new ArrayList<>();

// Processar mensagens da sessão
if (session.getAttribute("mensagem_rota") != null) {
    mensagem = (String)session.getAttribute("mensagem_rota");
    tipo_mensagem = (String)session.getAttribute("tipo_mensagem_rota");
    session.removeAttribute("mensagem_rota");
    session.removeAttribute("tipo_mensagem_rota");
}

// Obter conexão com o banco de dados
Connection conn = null;
Statement stmt = null;
PreparedStatement pstmt = null;
ResultSet rs = null;

try {
    conn = getConnection();
    stmt = conn.createStatement();

    // Carregar rota para edição
    if (request.getParameter("editar") != null && !request.getParameter("editar").isEmpty()) {
        int id_editar = Integer.parseInt(request.getParameter("editar"));
        pstmt = conn.prepareStatement("SELECT * FROM rotas WHERE id = ?");
        pstmt.setInt(1, id_editar);
        rs = pstmt.executeQuery();
        
        if (rs.next()) {
            rota_para_editar = new HashMap<>();
            rota_para_editar.put("id", rs.getInt("id"));
            rota_para_editar.put("origem", rs.getString("origem"));
            rota_para_editar.put("destino", rs.getString("destino"));
            rota_para_editar.put("preco", rs.getDouble("preco"));
            rota_para_editar.put("capacidade", rs.getInt("capacidade"));
        }
        rs.close();
        pstmt.close();
    }

    // Carregar horário para edição
    if (request.getParameter("editar_horario") != null && !request.getParameter("editar_horario").isEmpty()) {
        int id_horario_editar = Integer.parseInt(request.getParameter("editar_horario"));
        pstmt = conn.prepareStatement("SELECT h.*, r.origem, r.destino FROM horarios h " +
                                     "JOIN rotas r ON h.id_rota = r.id " +
                                     "WHERE h.id = ?");
        pstmt.setInt(1, id_horario_editar);
        rs = pstmt.executeQuery();
        
        if (rs.next()) {
            horario_para_editar = new HashMap<>();
            horario_para_editar.put("id", rs.getInt("id"));
            horario_para_editar.put("id_rota", rs.getInt("id_rota"));
            horario_para_editar.put("horario_partida", rs.getString("horario_partida"));
            horario_para_editar.put("data_viagem", rs.getString("data_viagem"));
            horario_para_editar.put("origem", rs.getString("origem"));
            horario_para_editar.put("destino", rs.getString("destino"));
        }
        rs.close();
        pstmt.close();
    }

    // Adicionar nova rota
    if ("POST".equals(request.getMethod()) && request.getParameter("adicionar_rota") != null) {
        // Verificar se o token da sessão corresponde ao token do formulário
        String token_rota_session = (String)session.getAttribute("token_rota");
        String token_rota_request = request.getParameter("token_rota");
        
        if (token_rota_session != null && token_rota_request != null && token_rota_session.equals(token_rota_request)) {
            String origem = request.getParameter("origem").trim();
            String destino = request.getParameter("destino").trim();
            double preco = Double.parseDouble(request.getParameter("preco"));
            int capacidade = Integer.parseInt(request.getParameter("capacidade"));

            if (origem.isEmpty() || destino.isEmpty()) {
                session.setAttribute("mensagem_rota", "Origem e destino não podem estar vazios.");
                session.setAttribute("tipo_mensagem_rota", "error");
                response.sendRedirect("gerir_rotas.jsp");
                return;
            }

            if (preco <= 0 || capacidade <= 0) {
                session.setAttribute("mensagem_rota", "O preço e a capacidade devem ser valores positivos.");
                session.setAttribute("tipo_mensagem_rota", "error");
            } else {
                pstmt = conn.prepareStatement("INSERT INTO rotas (origem, destino, preco, capacidade, disponivel) VALUES (?, ?, ?, ?, 1)", Statement.RETURN_GENERATED_KEYS);
                pstmt.setString(1, origem);
                pstmt.setString(2, destino);
                pstmt.setDouble(3, preco);
                pstmt.setInt(4, capacidade);
                
                if (pstmt.executeUpdate() > 0) {
                    rs = pstmt.getGeneratedKeys();
                    int id_rota = 0;
                    if (rs.next()) {
                        id_rota = rs.getInt(1);
                    }
                    session.setAttribute("mensagem_rota", "Rota com ID " + id_rota + " adicionada com sucesso!");
                    session.setAttribute("tipo_mensagem_rota", "success");
                } else {
                    session.setAttribute("mensagem_rota", "Erro ao adicionar rota.");
                    session.setAttribute("tipo_mensagem_rota", "error");
                }
                rs.close();
                pstmt.close();
            }
        }
        // Após processar, gerar um novo token para evitar reenvio
        session.setAttribute("token_rota", java.util.UUID.randomUUID().toString());
        
        // Redirecionar para evitar reenvio do formulário
        response.sendRedirect("gerir_rotas.jsp");
        return;
    }

    // Adicionar horário
    if ("POST".equals(request.getMethod()) && request.getParameter("adicionar_horario") != null) {
        String token_rota_session = (String)session.getAttribute("token_rota");
        String token_rota_request = request.getParameter("token_rota");
        
        if (token_rota_session != null && token_rota_request != null && token_rota_session.equals(token_rota_request)) {
            int id_rota = Integer.parseInt(request.getParameter("id_rota"));
            String horario_partida = request.getParameter("horario_partida");
            String data_viagem = request.getParameter("data_viagem");

            // Validate date is in the future for new schedules
            java.sql.Date dataViagem = java.sql.Date.valueOf(data_viagem);
            java.util.Date hoje = new java.util.Date();
            
            if (dataViagem.before(new java.sql.Date(hoje.getTime()))) {
                session.setAttribute("mensagem_rota", "A data da viagem deve ser futura.");
                session.setAttribute("tipo_mensagem_rota", "error");
                response.sendRedirect("gerir_rotas.jsp");
                return;
            }

            pstmt = conn.prepareStatement("SELECT capacidade FROM rotas WHERE id = ?");
            pstmt.setInt(1, id_rota);
            rs = pstmt.executeQuery();
            
            if (rs.next()) {
                int capacidade = rs.getInt("capacidade");
                
                java.sql.Date sqlDate = java.sql.Date.valueOf(data_viagem);
                java.sql.Time sqlTime = java.sql.Time.valueOf(horario_partida + ":00");

                pstmt = conn.prepareStatement("INSERT INTO horarios (id_rota, horario_partida, data_viagem, lugares_disponiveis, disponivel) VALUES (?, ?, ?, ?, 1)", Statement.RETURN_GENERATED_KEYS);
                pstmt.setInt(1, id_rota);
                pstmt.setTime(2, sqlTime);
                pstmt.setDate(3, sqlDate);
                pstmt.setInt(4, capacidade);
                
                if (pstmt.executeUpdate() > 0) {
                    rs = pstmt.getGeneratedKeys();
                    int id_horario = 0;
                    if (rs.next()) {
                        id_horario = rs.getInt(1);
                    }
                    session.setAttribute("mensagem_rota", "Horário com ID " + id_horario + " adicionado com sucesso!");
                    session.setAttribute("tipo_mensagem_rota", "success");
                } else {
                    session.setAttribute("mensagem_rota", "Erro ao adicionar horário.");
                    session.setAttribute("tipo_mensagem_rota", "error");
                }
            } else {
                session.setAttribute("mensagem_rota", "Rota não encontrada.");
                session.setAttribute("tipo_mensagem_rota", "error");
            }
            rs.close();
            pstmt.close();
        }
        session.setAttribute("token_rota", java.util.UUID.randomUUID().toString());
        response.sendRedirect("gerir_rotas.jsp");
        return;
    }

    // Atualizar horário existente
    if ("POST".equals(request.getMethod()) && request.getParameter("atualizar_horario") != null) {
        int id_horario = Integer.parseInt(request.getParameter("id_horario"));
        int id_rota = Integer.parseInt(request.getParameter("id_rota"));
        String horario = request.getParameter("horario_partida");
        String data_viagem = request.getParameter("data_viagem");

        pstmt = conn.prepareStatement("UPDATE horarios SET id_rota = ?, horario_partida = ?, data_viagem = ? WHERE id = ?");
        pstmt.setInt(1, id_rota);
        pstmt.setString(2, horario);
        pstmt.setString(3, data_viagem);
        pstmt.setInt(4, id_horario);
        
        if (pstmt.executeUpdate() > 0) {
            response.sendRedirect("gerir_rotas.jsp?msg=horario_updated&id=" + id_horario);
            return;
        } else {
            mensagem = "Erro ao atualizar horário ID " + id_horario;
            tipo_mensagem = "error";
        }
        pstmt.close();
    }

    // Atualizar rota existente
    if ("POST".equals(request.getMethod()) && request.getParameter("atualizar_rota") != null) {
        int id_rota = Integer.parseInt(request.getParameter("id_rota"));
        String origem = request.getParameter("origem").trim();
        String destino = request.getParameter("destino").trim();
        double preco = Double.parseDouble(request.getParameter("preco"));
        int capacidade = Integer.parseInt(request.getParameter("capacidade"));

        if (origem.isEmpty() || destino.isEmpty()) {
            mensagem = "Origem e destino não podem estar vazios.";
            tipo_mensagem = "error";
        } else if (preco <= 0 || capacidade <= 0) {
            mensagem = "O preço e a capacidade devem ser valores positivos.";
            tipo_mensagem = "error";
        } else {
            pstmt = conn.prepareStatement("UPDATE rotas SET origem = ?, destino = ?, preco = ?, capacidade = ? WHERE id = ?");
            pstmt.setString(1, origem);
            pstmt.setString(2, destino);
            pstmt.setDouble(3, preco);
            pstmt.setInt(4, capacidade);
            pstmt.setInt(5, id_rota);
            
            int linhasAfetadas = pstmt.executeUpdate();
            pstmt.close();

            if (linhasAfetadas > 0) {
                session.setAttribute("mensagem_rota", "Rota com ID " + id_rota + " foi editada com sucesso!");
                session.setAttribute("tipo_mensagem_rota", "success");
            } else {
                session.setAttribute("mensagem_rota", "Erro ao atualizar rota ID " + id_rota);
                session.setAttribute("tipo_mensagem_rota", "error");
            }
            response.sendRedirect("gerir_rotas.jsp");
            return;
        }
    }

    // Eliminar rota
    if (request.getParameter("excluir_rota") != null && !request.getParameter("excluir_rota").isEmpty()) {
        // Obtém o ID da rota a ser eliminada do parâmetro do pedido
        int id_rota = Integer.parseInt(request.getParameter("excluir_rota"));
        
        // Verifica se a rota existe na base de dados
        pstmt = conn.prepareStatement("SELECT id FROM rotas WHERE id = ?");
        pstmt.setInt(1, id_rota);
        rs = pstmt.executeQuery();
        
        if (rs.next()) {
            // A rota existe, fecha o ResultSet e o PreparedStatement
            rs.close();
            pstmt.close();
            
            // Verifica se existem horários associados a esta rota
            pstmt = conn.prepareStatement("SELECT COUNT(*) as total FROM horarios WHERE id_rota = ?");
            pstmt.setInt(1, id_rota);
            rs = pstmt.executeQuery();
            
            if (rs.next() && rs.getInt("total") > 0) {
                // Existem horários associados, não pode eliminar
                mensagem = "Não é possível eliminar a rota ID " + id_rota + " pois existem horários associados a ela.";
                tipo_mensagem = "error";
            } else {
                // Não existem horários, fecha o ResultSet e o PreparedStatement
                rs.close();
                pstmt.close();
                
                // Verifica se existem bilhetes associados a esta rota
                pstmt = conn.prepareStatement("SELECT COUNT(*) as total FROM bilhetes WHERE id_rota = ?");
                pstmt.setInt(1, id_rota);
                rs = pstmt.executeQuery();
                
                if (rs.next() && rs.getInt("total") > 0) {
                    // Existem bilhetes associados, não pode eliminar
                    mensagem = "Não é possível eliminar a rota ID " + id_rota + " pois existem bilhetes associados a ela.";
                    tipo_mensagem = "error";
                } else {
                    // Não existem bilhetes, fecha o ResultSet e o PreparedStatement
                    rs.close();
                    pstmt.close();
                    
                    // Em vez de eliminar fisicamente, marca a rota como indisponível (eliminação lógica)
                    pstmt = conn.prepareStatement("UPDATE rotas SET disponivel = 0 WHERE id = ?");
                    pstmt.setInt(1, id_rota);
                    
                    // Executa a atualização e verifica se foi bem-sucedida
                    if (pstmt.executeUpdate() > 0) {
                        mensagem = "Rota com ID " + id_rota + " foi eliminada com sucesso!";
                        tipo_mensagem = "success";
                    } else {
                        mensagem = "Erro ao eliminar rota ID " + id_rota;
                        tipo_mensagem = "error";
                    }
                }
            }
        } else {
            // A rota não existe
            mensagem = "Rota ID " + id_rota + " não encontrada.";
            tipo_mensagem = "error";
        }
        // Fecha os recursos da base de dados
        rs.close();
        pstmt.close();
    }

    // Eliminar horário
    if (request.getParameter("excluir_horario") != null && !request.getParameter("excluir_horario").isEmpty()) {
        // Obtém o ID do horário a ser eliminado
        int id_horario = Integer.parseInt(request.getParameter("excluir_horario"));
        
        // Verifica se existem bilhetes associados a este horário
        pstmt = conn.prepareStatement("SELECT COUNT(*) as total FROM bilhetes WHERE id_horario = ?");
        pstmt.setInt(1, id_horario);
        rs = pstmt.executeQuery();
        
        if (rs.next() && rs.getInt("total") > 0) {
            // Existem bilhetes associados, não pode eliminar
            mensagem = "Não é possível eliminar o horário ID " + id_horario + " pois existem bilhetes associados a ele.";
            tipo_mensagem = "error";
        } else {
            // Não existem bilhetes, fecha o ResultSet e o PreparedStatement
            rs.close();
            pstmt.close();
            
            // Em vez de eliminar fisicamente, marca o horário como indisponível (eliminação lógica)
            pstmt = conn.prepareStatement("UPDATE horarios SET disponivel = 0 WHERE id = ?");
            pstmt.setInt(1, id_horario);
            
            // Executa a atualização e verifica se foi bem-sucedida
            if (pstmt.executeUpdate() > 0) {
                mensagem = "Horário com ID " + id_horario + " foi eliminado com sucesso!";
                tipo_mensagem = "success";
            } else {
                mensagem = "Erro ao eliminar horário ID " + id_horario;
                tipo_mensagem = "error";
            }
        }
        // Fecha os recursos da base de dados
        rs.close();
        pstmt.close();
    }

    // Definir mensagem se vier de um redirecionamento
    if (request.getParameter("msg") != null) {
        // Obtém a mensagem e o ID do parâmetro da URL
        String msg = request.getParameter("msg");
        int id = request.getParameter("id") != null ? Integer.parseInt(request.getParameter("id")) : 0;
        
        // Define a mensagem apropriada com base no parâmetro
        if ("updated".equals(msg)) {
            mensagem = "Rota com ID " + id + " foi editada com sucesso!";
            tipo_mensagem = "success";
        } else if ("horario_updated".equals(msg)) {
            mensagem = "Horário com ID " + id + " foi editado com sucesso!";
            tipo_mensagem = "success";
        }
    }

    // Buscar dados das rotas disponíveis
    rs = stmt.executeQuery("SELECT r.*, (SELECT COUNT(*) FROM horarios WHERE id_rota = r.id) as total_horarios " +
                          "FROM rotas r WHERE r.disponivel = 1 ORDER BY r.id ASC");

    // Processa os resultados e armazena numa lista
    while (rs.next()) {
        Map<String, Object> rota = new HashMap<>();
        rota.put("id", rs.getInt("id"));
        rota.put("origem", rs.getString("origem"));
        rota.put("destino", rs.getString("destino"));
        rota.put("preco", rs.getDouble("preco"));
        rota.put("capacidade", rs.getInt("capacidade"));
        rota.put("total_horarios", rs.getInt("total_horarios"));
        rotas.add(rota);
    }
    rs.close();

    // Buscar horários disponíveis
    rs = stmt.executeQuery("SELECT h.*, r.origem, r.destino " +
                          "FROM horarios h " +
                          "JOIN rotas r ON h.id_rota = r.id " +
                          "WHERE h.disponivel = 1 " +
                          "ORDER BY h.data_viagem DESC, h.horario_partida ASC");

    // Processa os resultados e armazena numa lista
    while (rs.next()) {
        Map<String, Object> horario = new HashMap<>();
        horario.put("id", rs.getInt("id"));
        horario.put("id_rota", rs.getInt("id_rota"));
        horario.put("origem", rs.getString("origem"));
        horario.put("destino", rs.getString("destino"));
        horario.put("horario_partida", rs.getString("horario_partida"));
        horario.put("data_viagem", rs.getString("data_viagem"));
        horario.put("lugares_disponiveis", rs.getInt("lugares_disponiveis"));
        horarios.add(horario);
    }
    rs.close();

    // Gerar token para formulários se não existir
    // Este token ajuda a prevenir ataques CSRF e reenvio de formulários
    if (session.getAttribute("token_rota") == null) {
        session.setAttribute("token_rota", java.util.UUID.randomUUID().toString());
    }

    // Define atributos para uso na página JSP
    pageContext.setAttribute("rotas", rotas);
    pageContext.setAttribute("horarios", horarios);
    pageContext.setAttribute("mensagem", mensagem);
    pageContext.setAttribute("tipo_mensagem", tipo_mensagem);
    pageContext.setAttribute("rota_para_editar", rota_para_editar);
    pageContext.setAttribute("horario_para_editar", horario_para_editar);
    
} catch (Exception e) {
    mensagem = "Erro: " + e.getMessage();
    tipo_mensagem = "error";
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
    <link rel="stylesheet" href="gerir_rotas.css">
    <title>FelixBus - Gestão de Rotas</title>
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
        <h1>Gestão de Rotas e Horários</h1>
        
        <% if (!mensagem.isEmpty()) { %>
            <div class="alert <%= "success".equals(tipo_mensagem) ? "alert-success" : "alert-danger" %>">
                <%= mensagem %>
            </div>
        <% } %>
        
        <div class="container">
            <div class="form-container">
                <h2><%= rota_para_editar != null ? "Editar Rota" : "Adicionar Nova Rota" %></h2>
                <!-- Formulário para adicionar/editar rota -->
                <form method="post" action="gerir_rotas.jsp">
                    <!-- Token CSRF para segurança e prevenção de reenvio de formulário -->
                    <input type="hidden" name="token_rota" value="<%= session.getAttribute("token_rota") %>">
                    
                    <% if (rota_para_editar != null) { %>
                        <!-- Se estiver a editar, inclui o ID da rota e marca como atualização -->
                        <input type="hidden" name="id_rota" value="<%= rota_para_editar.get("id") %>">
                        <input type="hidden" name="atualizar_rota" value="1">
                    <% } else { %>
                        <!-- Se estiver a adicionar, marca como adição -->
                        <input type="hidden" name="adicionar_rota" value="1">
                    <% } %>
                    
                    <!-- Campo para origem da rota -->
                    <div class="form-group">
                        <label for="origem">Origem:</label>
                        <!-- Preenche o valor se estiver a editar -->
                        <input type="text" id="origem" name="origem" value="<%= rota_para_editar != null ? rota_para_editar.get("origem") : "" %>" required>
                    </div>
                    
                    <!-- Campo para destino da rota -->
                    <div class="form-group">
                        <label for="destino">Destino:</label>
                        <input type="text" id="destino" name="destino" value="<%= rota_para_editar != null ? rota_para_editar.get("destino") : "" %>" required>
                    </div>
                    
                    <!-- Campo para preço da rota -->
                    <div class="form-group">
                        <label for="preco">Preço (€):</label>
                        <input type="number" id="preco" name="preco" step="0.01" min="0.01" value="<%= rota_para_editar != null ? rota_para_editar.get("preco") : "" %>" required>
                    </div>
                    
                    <!-- Campo para capacidade da rota (número de lugares) -->
                    <div class="form-group">
                        <label for="capacidade">Capacidade:</label>
                        <input type="number" id="capacidade" name="capacidade" min="1" value="<%= rota_para_editar != null ? rota_para_editar.get("capacidade") : "" %>" required>
                    </div>
                    
                    <!-- Botões de submissão e cancelamento -->
                    <div class="form-buttons">
                        <!-- Texto do botão muda conforme esteja a adicionar ou editar -->
                        <button type="submit" class="btn-submit"><%= rota_para_editar != null ? "Atualizar Rota" : "Adicionar Rota" %></button>
                        <% if (rota_para_editar != null) { %>
                            <!-- Botão de cancelar edição só aparece no modo de edição -->
                            <a href="gerir_rotas.jsp" class="btn-cancel">Cancelar</a>
                        <% } %>
                    </div>
                </form>
            </div>
            
            <% if (horario_para_editar != null) { %>
                <div class="form-container">
                    <h2>Editar Horário</h2>
                    <form method="post" action="gerir_rotas.jsp">
                        <input type="hidden" name="id_horario" value="<%= horario_para_editar.get("id") %>">
                        <input type="hidden" name="atualizar_horario" value="1">
                        
                        <div class="form-group">
                            <label for="id_rota_horario">Rota:</label>
                            <select id="id_rota_horario" name="id_rota" required>
                                <% for (Map<String, Object> rota : rotas) { %>
                                    <option value="<%= rota.get("id") %>" <%= horario_para_editar.get("id_rota").equals(rota.get("id")) ? "selected" : "" %>>
                                        <%= rota.get("origem") %> → <%= rota.get("destino") %>
                                    </option>
                                <% } %>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="horario_partida">Horário de Partida:</label>
                            <input type="time" id="horario_partida" name="horario_partida" value="<%= horario_para_editar.get("horario_partida") %>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_viagem">Data da Viagem:</label>
                            <input type="date" id="data_viagem" name="data_viagem" value="<%= horario_para_editar.get("data_viagem") %>" required>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" class="btn-submit">Atualizar Horário</button>
                            <a href="gerir_rotas.jsp" class="btn-cancel">Cancelar</a>
                        </div>
                    </form>
                </div>
            <% } else if (rota_para_editar == null) { %>
                <div class="form-container">
                    <h2>Adicionar Novo Horário</h2>
                    <form method="post" action="gerir_rotas.jsp">
                        <input type="hidden" name="token_rota" value="<%= session.getAttribute("token_rota") %>">
                        <input type="hidden" name="adicionar_horario" value="1">
                        
                        <div class="form-group">
                            <label for="id_rota_horario">Rota:</label>
                            <select id="id_rota_horario" name="id_rota" required>
                                <option value="">Selecione uma rota</option>
                                <% for (Map<String, Object> rota : rotas) { %>
                                    <option value="<%= rota.get("id") %>">
                                        <%= rota.get("origem") %> → <%= rota.get("destino") %>
                                    </option>
                                <% } %>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="horario_partida">Horário de Partida:</label>
                            <input type="time" id="horario_partida" name="horario_partida" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_viagem">Data da Viagem:</label>
                            <input type="date" id="data_viagem" name="data_viagem" required>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" class="btn-submit">Adicionar Horário</button>
                        </div>
                    </form>
                </div>
            <% } %>
        </div>
        
        <div class="tables-container">
            <div class="table-wrapper">
                <h2>Rotas Disponíveis</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Preço</th>
                            <th>Capacidade</th>
                            <th>Horários</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <% for (Map<String, Object> rota : rotas) { %>
                            <tr>
                                <td><%= rota.get("id") %></td>
                                <td><%= escapeHtml(rota.get("origem").toString()) %></td>
                                <td><%= escapeHtml(rota.get("destino").toString()) %></td>
                                <td>€<%= String.format("%.2f", rota.get("preco")) %></td>
                                <td><%= rota.get("capacidade") %></td>
                                <td><%= rota.get("total_horarios") %></td>
                                <td class="actions-column">
                                    <a href="gerir_rotas.jsp?editar=<%= rota.get("id") %>" class="btn-edit">Editar</a>
                                    <a href="javascript:void(0)" onclick="confirmarExclusaoRota(<%= rota.get("id") %>)" class="btn-delete">Excluir</a>
                                </td>
                            </tr>
                        <% } %>
                    </tbody>
                </table>
            </div>
            
            <div class="table-wrapper">
                <h2>Horários Disponíveis</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Rota</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Lugares</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <% for (Map<String, Object> horario : horarios) { %>
                            <tr>
                                <td><%= horario.get("id") %></td>
                                <td><%= escapeHtml(horario.get("origem").toString()) %> → <%= escapeHtml(horario.get("destino").toString()) %></td>
                                <td><%= horario.get("data_viagem") %></td>
                                <td><%= horario.get("horario_partida") %></td>
                                <td><%= horario.get("lugares_disponiveis") %></td>
                                <td class="actions-column">
                                    <a href="gerir_rotas.jsp?editar_horario=<%= horario.get("id") %>" class="btn-edit">Editar</a>
                                    <a href="javascript:void(0)" onclick="confirmarExclusaoHorario(<%= horario.get("id") %>)" class="btn-delete">Excluir</a>
                                </td>
                            </tr>
                        <% } %>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    
    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

    <script>
        function confirmarExclusaoRota(id) {
            if (confirm("Tem certeza que deseja excluir a rota ID " + id + "?")) {
                window.location.href = "gerir_rotas.jsp?excluir_rota=" + id;
            }
        }
        
        function confirmarExclusaoHorario(id) {
            if (confirm("Tem certeza que deseja excluir o horário ID " + id + "?")) {
                window.location.href = "gerir_rotas.jsp?excluir_horario=" + id;
            }
        }
    </script>
</body>
</html>





