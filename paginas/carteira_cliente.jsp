<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*, java.math.BigDecimal, java.math.RoundingMode" %>
<%@ include file="../basedados/basedados.jsp" %>

<%
// Verifica se o utilizador está autenticado e se é um cliente (nível 3)
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 3) {
    // Redireciona para a página de erro se não for um cliente
    response.sendRedirect("erro.jsp");
    return;
}

// Obtém o ID do cliente a partir da sessão
int id_cliente = (Integer)session.getAttribute("id_utilizador");
double saldo = 0.0;
List<Map<String, Object>> transacoes = new ArrayList<>();
String mensagem = null;
String tipo_mensagem = null;

// Recupera mensagens da sessão, se existirem
if (session.getAttribute("mensagem") != null) {
    mensagem = (String)session.getAttribute("mensagem");
    tipo_mensagem = (String)session.getAttribute("tipo_mensagem");
    
    // Limpa as mensagens da sessão após recuperá-las
    session.removeAttribute("mensagem");
    session.removeAttribute("tipo_mensagem");
}

// Obtém os parâmetros de filtro da consulta
String filtroTipo = request.getParameter("filtro_tipo");
String ordenacao = request.getParameter("ordenacao");
String periodoFiltro = request.getParameter("periodo");

Connection conn = null;
PreparedStatement pstmt = null;
ResultSet rs = null;

try {
    // Estabelece a ligação à base de dados usando o método getConnection()
    conn = getConnection();
    
    // Obtém o ID da carteira FelixBus (para transferências)
    int id_carteira_felixbus = 1; // Valor predefinido
    pstmt = conn.prepareStatement("SELECT id FROM carteira_felixbus LIMIT 1");
    rs = pstmt.executeQuery();
    if (rs.next()) {
        id_carteira_felixbus = rs.getInt("id");
    }
    rs.close();
    pstmt.close();
    
    // Obtém o saldo atual do cliente
    pstmt = conn.prepareStatement("SELECT saldo FROM carteiras WHERE id_cliente = ?");
    pstmt.setInt(1, id_cliente);
    rs = pstmt.executeQuery();
    
    if (rs.next()) {
        saldo = rs.getDouble("saldo");
    } else {
        // Se o cliente não tiver carteira, cria uma nova com saldo zero
        pstmt.close();
        pstmt = conn.prepareStatement("INSERT INTO carteiras (id_cliente, saldo) VALUES (?, 0.0)");
        pstmt.setInt(1, id_cliente);
        pstmt.executeUpdate();
    }
    rs.close();
    pstmt.close();
    
    // Constrói a consulta SQL para o histórico de transações com filtros
    StringBuilder sqlTransacoes = new StringBuilder(
        "SELECT id, valor, tipo, descricao, data_transacao " +
        "FROM transacoes " +
        "WHERE id_cliente = ? "
    );

    // Aplica filtro por tipo de transação
    if (filtroTipo != null && !filtroTipo.equals("todos")) {
        sqlTransacoes.append("AND tipo = ? ");
    }

    // Aplica filtro por período de tempo
    if (periodoFiltro != null) {
        if (periodoFiltro.equals("hoje")) {
            sqlTransacoes.append("AND DATE(data_transacao) = CURDATE() ");
        } else if (periodoFiltro.equals("semana")) {
            sqlTransacoes.append("AND data_transacao >= DATE_SUB(NOW(), INTERVAL 7 DAY) ");
        } else if (periodoFiltro.equals("mes")) {
            sqlTransacoes.append("AND data_transacao >= DATE_SUB(NOW(), INTERVAL 30 DAY) ");
        }
    }

    // Aplica ordenação dos resultados
    if (ordenacao != null && ordenacao.equals("valor")) {
        sqlTransacoes.append("ORDER BY valor DESC ");
    } else {
        sqlTransacoes.append("ORDER BY data_transacao DESC ");
    }

    // Limita o número de resultados para melhor desempenho
    sqlTransacoes.append("LIMIT 20");

    pstmt = conn.prepareStatement(sqlTransacoes.toString());
    pstmt.setInt(1, id_cliente);

    // Se tiver filtro por tipo, adiciona o parâmetro à consulta
    if (filtroTipo != null && !filtroTipo.equals("todos")) {
        pstmt.setString(2, filtroTipo);
    }

    rs = pstmt.executeQuery();
    
    // Processa os resultados da consulta
    while (rs.next()) {
        Map<String, Object> transacao = new HashMap<>();
        transacao.put("id", rs.getInt("id"));
        transacao.put("valor", rs.getDouble("valor"));
        transacao.put("tipo", rs.getString("tipo"));
        transacao.put("descricao", rs.getString("descricao"));
        transacao.put("data_transacao", rs.getTimestamp("data_transacao"));
        transacoes.add(transacao);
    }
    rs.close();
    pstmt.close();
    
    // Processa o formulário de depósito/levantamento se for um pedido POST
    if ("POST".equals(request.getMethod())) {
        // Sanitização e validação do valor introduzido
        String valorStr = request.getParameter("valor");
        String operacao = request.getParameter("operacao");
        BigDecimal valor = null;
        
        try {
            // Converte para BigDecimal com 2 casas decimais para evitar problemas de arredondamento
            valor = new BigDecimal(valorStr).setScale(2, RoundingMode.HALF_UP);
            
            // Verifica se o valor é positivo
            if (valor.compareTo(BigDecimal.ZERO) <= 0) {
                session.setAttribute("mensagem", "Valor inválido. Por favor, introduza um valor superior a zero.");
                session.setAttribute("tipo_mensagem", "danger");
                response.sendRedirect("carteira_cliente.jsp");
                return;
            }
        } catch (NumberFormatException e) {
            // Trata erro de formato de número inválido
            session.setAttribute("mensagem", "Formato de valor inválido. Utilize apenas números e ponto decimal.");
            session.setAttribute("tipo_mensagem", "danger");
            response.sendRedirect("carteira_cliente.jsp");
            return;
        }
        
        // Inicia uma transação para garantir consistência dos dados
        conn.setAutoCommit(false);
        
        try {
            String sql_atualiza = null;
            String tipo_transacao = null;
            String descricao = null;
            
            // Define a operação a realizar com base na escolha do utilizador
            if ("depositar".equals(operacao)) {
                sql_atualiza = "UPDATE carteiras SET saldo = saldo + ? WHERE id_cliente = ?";
                tipo_transacao = "deposito";
                descricao = "Depósito de €" + valor + " na carteira";
                
                // Atualiza a carteira FelixBus (sistema)
                pstmt = conn.prepareStatement("UPDATE carteira_felixbus SET saldo = saldo + ? WHERE id = ?");
                pstmt.setBigDecimal(1, valor);
                pstmt.setInt(2, id_carteira_felixbus);
                pstmt.executeUpdate();
                pstmt.close();
            } else if ("levantar".equals(operacao)) {
                // Verifica se há saldo suficiente para o levantamento
                BigDecimal saldoBD = new BigDecimal(saldo).setScale(2, RoundingMode.HALF_UP);
                if (saldoBD.compareTo(valor) >= 0) {
                    sql_atualiza = "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?";
                    tipo_transacao = "levantamento";
                    descricao = "Levantamento de €" + valor + " da carteira";
                    
                    // Atualiza a carteira FelixBus (sistema)
                    pstmt = conn.prepareStatement("UPDATE carteira_felixbus SET saldo = saldo - ? WHERE id = ?");
                    pstmt.setBigDecimal(1, valor);
                    pstmt.setInt(2, id_carteira_felixbus);
                    pstmt.executeUpdate();
                    pstmt.close();
                } else {
                    // Saldo insuficiente para realizar a operação
                    session.setAttribute("mensagem", "Saldo insuficiente para realizar esta operação.");
                    session.setAttribute("tipo_mensagem", "danger");
                    response.sendRedirect("carteira_cliente.jsp");
                    return;
                }
            }
            
            // Executa a atualização do saldo na carteira do cliente
            if (sql_atualiza != null) {
                pstmt = conn.prepareStatement(sql_atualiza);
                pstmt.setBigDecimal(1, valor);
                pstmt.setInt(2, id_cliente);
                
                if (pstmt.executeUpdate() > 0) {
                    pstmt.close();
                    
                    // Regista a transação no histórico
                    pstmt = conn.prepareStatement("INSERT INTO transacoes (id_cliente, valor, tipo, descricao, data_transacao) VALUES (?, ?, ?, ?, NOW())");
                    pstmt.setInt(1, id_cliente);
                    pstmt.setBigDecimal(2, valor);
                    pstmt.setString(3, tipo_transacao);
                    pstmt.setString(4, descricao);
                    
                    if (pstmt.executeUpdate() > 0) {
                        // Confirma a transação na base de dados
                        conn.commit();
                        
                        // Atualiza o saldo exibido
                        pstmt = conn.prepareStatement("SELECT saldo FROM carteiras WHERE id_cliente = ?");
                        pstmt.setInt(1, id_cliente);
                        rs = pstmt.executeQuery();
                        
                        if (rs.next()) {
                            saldo = rs.getDouble("saldo");
                        }
                        
                        // Define mensagem de sucesso
                        session.setAttribute("mensagem", "Operação realizada com sucesso!");
                        session.setAttribute("tipo_mensagem", "success");
                        
                        // Redireciona para evitar reenvio do formulário
                        response.sendRedirect("carteira_cliente.jsp");
                        return;
                    } else {
                        // Lança exceção se houver erro ao registar a transação
                        throw new Exception("Erro ao registar transação");
                    }
                } else {
                    // Cancela a transação e mostra mensagem de erro
                    conn.rollback();
                    session.setAttribute("mensagem", "Erro ao atualizar saldo");
                    session.setAttribute("tipo_mensagem", "danger");
                    
                    // Redireciona para evitar reenvio do formulário
                    response.sendRedirect("carteira_cliente.jsp");
                    return;
                }
            }
        } catch (SQLException sqlEx) {
            // Cancela a transação em caso de exceção SQL
            conn.rollback();
            
            // Mensagens mais específicas baseadas no código de erro SQL
            if (sqlEx.getErrorCode() == 1264) {
                session.setAttribute("mensagem", "O valor excede o limite permitido para transações.");
            } else if (sqlEx.getErrorCode() == 1452) {
                session.setAttribute("mensagem", "Erro de referência: verifique se a sua conta está ativa.");
            } else {
                session.setAttribute("mensagem", "Erro na base de dados: " + sqlEx.getMessage());
            }
            session.setAttribute("tipo_mensagem", "danger");
            
            // Redireciona para evitar reenvio do formulário
            response.sendRedirect("carteira_cliente.jsp");
            return;
        } catch (Exception e) {
            // Cancela a transação em caso de exceção genérica
            conn.rollback();
            
            // Mensagem mais detalhada sobre o tipo de erro
            String mensagemErro = "Erro ao processar a operação: ";
            if (e instanceof NumberFormatException) {
                mensagemErro += "formato de número inválido.";
            } else if (e instanceof NullPointerException) {
                mensagemErro += "dados incompletos ou inválidos.";
            } else {
                mensagemErro += e.getMessage();
            }
            
            session.setAttribute("mensagem", mensagemErro);
            session.setAttribute("tipo_mensagem", "danger");
            
            // Redireciona para evitar reenvio do formulário
            response.sendRedirect("carteira_cliente.jsp");
            return;
        } finally {
            // Restaura o modo de auto-commit
            conn.setAutoCommit(true);
        }
    }
} catch (Exception e) {
    // Trata exceções gerais
    mensagem = "Erro: " + e.getMessage();
    tipo_mensagem = "danger";
} finally {
    // Fecha todos os recursos da base de dados
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
    <link rel="stylesheet" href="carteira_cliente.css">
    <title>FelixBus - Carteira</title>
</head>
<body>

    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"><a href="pg_cliente.jsp">Página Inicial</a></div>
            <div class="link"><a href="perfil_cliente.jsp">Perfil</a></div>
            <div class="link"><a href="bilhetes_cliente.jsp">Bilhetes</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
        </div>
    </nav>

    <section>
        <h1>Minha Carteira</h1>

        <% if (mensagem != null && !mensagem.isEmpty()) { %>
            <div class="alert alert-<%= tipo_mensagem %>">
                <%= mensagem %>
            </div>
        <% } %>

        <div class="wallet-container">
            <div class="wallet-balance">
                <h2>Saldo Atual</h2>
                <div class="balance">€ <%= new DecimalFormat("#,##0.00").format(saldo) %></div>
            </div>

            <div class="wallet-actions">
                <h2>Operações</h2>
                <form method="post" action="carteira_cliente.jsp">
                    <div class="form-group">
                        <label for="valor">Valor (€):</label>
                        <input type="number" id="valor" name="valor" step="0.01" min="0.01" placeholder="Introduza o valor" required>
                    </div>

                    <div class="form-group">
                        <label>Operação:</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="operacao" value="depositar" checked>
                                Depositar
                            </label>
                            <label>
                                <input type="radio" name="operacao" value="levantar">
                                Levantar
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Confirmar</button>
                </form>
            </div>
            
            <div class="transaction-history">
                <h2>Histórico de Transações</h2>
                
                <!-- Formulário de filtros -->
                <div class="filter-form">
                    <form method="get" action="carteira_cliente.jsp">
                        <div class="filter-group">
                            <label for="filtro_tipo">Tipo:</label>
                            <select id="filtro_tipo" name="filtro_tipo">
                                <option value="todos" <%= "todos".equals(request.getParameter("filtro_tipo")) ? "selected" : "" %>>Todos</option>
                                <option value="deposito" <%= "deposito".equals(request.getParameter("filtro_tipo")) ? "selected" : "" %>>Depósitos</option>
                                <option value="levantamento" <%= "levantamento".equals(request.getParameter("filtro_tipo")) ? "selected" : "" %>>Levantamentos</option>
                                <option value="compra" <%= "compra".equals(request.getParameter("filtro_tipo")) ? "selected" : "" %>>Compras</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="periodo">Período:</label>
                            <select id="periodo" name="periodo">
                                <option value="todos" <%= "todos".equals(request.getParameter("periodo")) ? "selected" : "" %>>Todos</option>
                                <option value="hoje" <%= "hoje".equals(request.getParameter("periodo")) ? "selected" : "" %>>Hoje</option>
                                <option value="semana" <%= "semana".equals(request.getParameter("periodo")) ? "selected" : "" %>>Última semana</option>
                                <option value="mes" <%= "mes".equals(request.getParameter("periodo")) ? "selected" : "" %>>Último mês</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="ordenacao">Ordenar por:</label>
                            <select id="ordenacao" name="ordenacao">
                                <option value="data" <%= "data".equals(request.getParameter("ordenacao")) || request.getParameter("ordenacao") == null ? "selected" : "" %>>Data</option>
                                <option value="valor" <%= "valor".equals(request.getParameter("ordenacao")) ? "selected" : "" %>>Valor</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-filter">Filtrar</button>
                    </form>
                </div>
                
                <% if (transacoes.isEmpty()) { %>
                    <p class="no-transactions">Nenhuma transação encontrada.</p>
                <% } else { %>
                    <div class="transactions-table-wrapper">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Valor</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <% 
                                SimpleDateFormat dateFormat = new SimpleDateFormat("dd/MM/yyyy HH:mm");
                                for (Map<String, Object> transacao : transacoes) { 
                                    String tipo = (String)transacao.get("tipo");
                                    double valor = (Double)transacao.get("valor");
                                    java.sql.Timestamp dataTransacao = (java.sql.Timestamp)transacao.get("data_transacao");
                                    String descricao = (String)transacao.get("descricao");
                                    
                                    String classeValor = "";
                                    String valorFormatado = "";
                                    
                                    if ("deposito".equals(tipo)) {
                                        classeValor = "deposito";
                                        valorFormatado = "+€" + new DecimalFormat("#,##0.00").format(valor);
                                    } else if ("levantamento".equals(tipo)) {
                                        classeValor = "levantamento";
                                        valorFormatado = "-€" + new DecimalFormat("#,##0.00").format(valor);
                                    } else if ("compra".equals(tipo)) {
                                        classeValor = "compra";
                                        valorFormatado = "-€" + new DecimalFormat("#,##0.00").format(valor);
                                    } else {
                                        valorFormatado = "€" + new DecimalFormat("#,##0.00").format(valor);
                                    }
                                %>
                                <tr>
                                    <td><%= dateFormat.format(dataTransacao) %></td>
                                    <td><%= tipo.substring(0, 1).toUpperCase() + tipo.substring(1) %></td>
                                    <td class="<%= classeValor %>"><%= valorFormatado %></td>
                                    <td><%= descricao %></td>
                                </tr>
                                <% } %>
                            </tbody>
                        </table>
                    </div>
                <% } %>
            </div>
        </div>
    </section>

    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>
