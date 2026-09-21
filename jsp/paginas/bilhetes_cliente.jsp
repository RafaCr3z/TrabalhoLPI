<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*, java.util.Set, java.util.HashSet, java.util.logging.*" %>
<%@ include file="../basedados/basedados.jsp" %>

<%!
    // Método para registrar erros no log
    private void logError(String mensagem, Exception e) {
        Logger logger = Logger.getLogger("bilhetes_cliente.jsp");
        logger.log(Level.SEVERE, mensagem, e);
    }
%>

<%
// Verifica se o utilizador está autenticado e se é um cliente (nível 3)
if (session.getAttribute("id_nivel") == null || (Integer)session.getAttribute("id_nivel") != 3) {
    response.sendRedirect("erro.jsp");
    return;
}

// Obtém o ID do cliente a partir da sessão
int id_cliente = (Integer)session.getAttribute("id_utilizador");

// Inicializa variáveis para mensagens de feedback
String mensagem = "";
String tipo_mensagem = "";

// Verifica se existem mensagens passadas por URL (após redirecionamentos)
if (request.getParameter("msg") != null && request.getParameter("tipo") != null) {
    mensagem = request.getParameter("msg");
    tipo_mensagem = request.getParameter("tipo");
}

// Processar a compra de bilhete
if ("POST".equals(request.getMethod()) && request.getParameter("horario") != null) {
    Connection conn = null;
    PreparedStatement pstmt = null;
    ResultSet rs = null;

    try {
        conn = getConnection();

        // Obter e validar parâmetros do formulário
        int id_horario = 0;
        int quantidade = 0;
        int id_rota = 0;
        
        // Sanitização e validação de inputs
        try {
            id_horario = Integer.parseInt(request.getParameter("horario"));
            quantidade = Integer.parseInt(request.getParameter("quantidade"));
            id_rota = Integer.parseInt(request.getParameter("rota"));
            
            // Validação adicional dos valores
            if (id_horario <= 0 || id_rota <= 0) {
                throw new NumberFormatException("ID inválido");
            }
        } catch (NumberFormatException e) {
            mensagem = "Dados inválidos no formulário. Por favor, tente novamente.";
            tipo_mensagem = "danger";
            throw new Exception("Erro de validação de parâmetros: " + e.getMessage());
        }

        // Validar quantidade
        if (quantidade <= 0 || quantidade > 10) {
            mensagem = "Quantidade inválida. Por favor, escolha entre 1 e 10 bilhetes.";
            tipo_mensagem = "danger";
        } else {
            // Verificar se o horário existe e tem lugares disponíveis
            pstmt = conn.prepareStatement("SELECT h.lugares_disponiveis, r.preco, r.origem, r.destino FROM horarios h JOIN rotas r ON h.id_rota = r.id WHERE h.id = ? AND h.disponivel = 1");
            pstmt.setInt(1, id_horario);
            rs = pstmt.executeQuery();

            if (rs.next()) {
                int lugares_disponiveis = rs.getInt("lugares_disponiveis");
                double preco_unitario = rs.getDouble("preco");
                String origem = rs.getString("origem");
                String destino = rs.getString("destino");

                if (lugares_disponiveis >= quantidade) {
                    // Calcular preço total
                    double preco_total = preco_unitario * quantidade;

                    // Verificar saldo na carteira
                    pstmt.close();
                    rs.close();

                    pstmt = conn.prepareStatement("SELECT saldo FROM carteiras WHERE id_cliente = ?");
                    pstmt.setInt(1, id_cliente);
                    rs = pstmt.executeQuery();

                    if (rs.next()) {
                        double saldo = rs.getDouble("saldo");

                        if (saldo >= preco_total) {
                            // Iniciar transação
                            conn.setAutoCommit(false);

                            try {
                                // 1. Atualizar lugares disponíveis
                                pstmt = conn.prepareStatement("UPDATE horarios SET lugares_disponiveis = lugares_disponiveis - ? WHERE id = ?");
                                pstmt.setInt(1, quantidade);
                                pstmt.setInt(2, id_horario);
                                pstmt.executeUpdate();

                                // Obter data e hora da viagem
                                pstmt = conn.prepareStatement("SELECT data_viagem, horario_partida FROM horarios WHERE id = ?");
                                pstmt.setInt(1, id_horario);
                                ResultSet rsHorario = pstmt.executeQuery();

                                if (rsHorario.next()) {
                                    java.sql.Date dataViagem = rsHorario.getDate("data_viagem");
                                    java.sql.Time horaViagem = rsHorario.getTime("horario_partida");
                                    rsHorario.close();

                                    // 2. Registrar um único bilhete com a quantidade total
                                    try {
                                        // Preparar a consulta para inserção de bilhete
                                        pstmt = conn.prepareStatement("INSERT INTO bilhetes (id_cliente, id_rota, data_compra, data_viagem, hora_viagem, numero_lugar) VALUES (?, ?, NOW(), ?, ?, ?)");

                                        // Inserir um único bilhete com a quantidade total
                                        pstmt.setInt(1, id_cliente);
                                        pstmt.setInt(2, id_rota);
                                        pstmt.setDate(3, dataViagem);
                                        pstmt.setTime(4, horaViagem);
                                        pstmt.setInt(5, quantidade); // Número de lugar definido como a quantidade comprada
                                        pstmt.executeUpdate();
                                    } catch (SQLException sqle) {
                                        // Se ocorrer um erro, pode ser porque a tabela não tem a estrutura esperada
                                        // Vamos tentar uma consulta alternativa
                                        out.println("<!-- Erro na inserção padrão: " + sqle.getMessage() + " -->");
                                        out.println("<!-- Tentando inserção alternativa... -->");

                                        // Verificar quais colunas existem na tabela bilhetes
                                        DatabaseMetaData metaData = conn.getMetaData();
                                        ResultSet columns = metaData.getColumns(null, null, "bilhetes", null);
                                        Set<String> columnNames = new HashSet<>();
                                        while (columns.next()) {
                                            columnNames.add(columns.getString("COLUMN_NAME").toLowerCase());
                                        }
                                        columns.close();

                                        // Construir a consulta com base nas colunas existentes
                                        StringBuilder insertSQL = new StringBuilder("INSERT INTO bilhetes (");
                                        StringBuilder valuesSQL = new StringBuilder("VALUES (");

                                        insertSQL.append("id_cliente, id_rota");
                                        valuesSQL.append("?, ?");

                                        if (columnNames.contains("data_compra")) {
                                            insertSQL.append(", data_compra");
                                            valuesSQL.append(", NOW()");
                                        }

                                        if (columnNames.contains("data_viagem")) {
                                            insertSQL.append(", data_viagem");
                                            valuesSQL.append(", ?");
                                        }

                                        if (columnNames.contains("hora_viagem")) {
                                            insertSQL.append(", hora_viagem");
                                            valuesSQL.append(", ?");
                                        }

                                        if (columnNames.contains("numero_lugar")) {
                                            insertSQL.append(", numero_lugar");
                                            valuesSQL.append(", ?");
                                        }

                                        insertSQL.append(") ");
                                        valuesSQL.append(")");

                                        String finalSQL = insertSQL.toString() + valuesSQL.toString();
                                        out.println("<!-- SQL alternativo: " + finalSQL + " -->");

                                        pstmt = conn.prepareStatement(finalSQL);

                                        // Inserir um único bilhete com a quantidade total
                                        int paramIndex = 1;

                                        pstmt.setInt(paramIndex++, id_cliente);
                                        pstmt.setInt(paramIndex++, id_rota);

                                        if (columnNames.contains("data_viagem")) {
                                            pstmt.setDate(paramIndex++, dataViagem);
                                        }

                                        if (columnNames.contains("hora_viagem")) {
                                            pstmt.setTime(paramIndex++, horaViagem);
                                        }

                                        if (columnNames.contains("numero_lugar")) {
                                            pstmt.setInt(paramIndex++, quantidade); // Número de lugar definido como a quantidade comprada
                                        }

                                        pstmt.executeUpdate();
                                    }
                                }

                                // 3. Atualizar saldo na carteira
                                pstmt = conn.prepareStatement("UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?");
                                pstmt.setDouble(1, preco_total);
                                pstmt.setInt(2, id_cliente);
                                pstmt.executeUpdate();

                                // 4. Registrar transação
                                pstmt = conn.prepareStatement("INSERT INTO transacoes (id_cliente, valor, tipo, descricao, data_transacao) VALUES (?, ?, ?, ?, NOW())");
                                pstmt.setInt(1, id_cliente);
                                pstmt.setDouble(2, preco_total);
                                pstmt.setString(3, "compra");
                                pstmt.setString(4, "Compra de " + quantidade + " bilhete(s) para " + origem + " → " + destino);
                                pstmt.executeUpdate();

                                // Confirmar transação
                                conn.commit();

                                mensagem = quantidade > 1 ?
                                    quantidade + " bilhetes comprados com sucesso!" :
                                    "Bilhete comprado com sucesso!";
                                tipo_mensagem = "success";
                            } catch (Exception e) {
                                // Reverter transação em caso de erro
                                conn.rollback();
                                mensagem = "Erro ao processar a compra. Por favor, tente novamente.";
                                tipo_mensagem = "danger";
                                // Log do erro real para depuração (não mostrado ao usuário)
                                logError("Erro na transação", e);
                                e.printStackTrace();
                            } finally {
                                conn.setAutoCommit(true);
                            }
                        } else {
                            mensagem = "Saldo insuficiente na carteira. Por favor, carregue a sua carteira.";
                            tipo_mensagem = "danger";
                        }
                    } else {
                        mensagem = "Erro ao verificar saldo na carteira.";
                        tipo_mensagem = "danger";
                    }
                } else {
                    mensagem = "Não há lugares suficientes disponíveis para esta viagem.";
                    tipo_mensagem = "danger";
                }
            } else {
                mensagem = "Horário não encontrado ou indisponível.";
                tipo_mensagem = "danger";
            }
        }
    } catch (Exception e) {
        mensagem = "Erro ao processar a compra. Por favor, tente novamente mais tarde.";
        tipo_mensagem = "danger";
        // Log do erro real para depuração (não mostrado ao usuário)
        logError("Erro geral na compra de bilhete", e);
        e.printStackTrace();
    } finally {
        if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
        if (pstmt != null) try { pstmt.close(); } catch (SQLException e) { /* ignorar */ }
        if (conn != null) try { conn.close(); } catch (SQLException e) { /* ignorar */ }
    }
}

// Inicializa variáveis para armazenar dados
List<Map<String, String>> rotas = new ArrayList<>();
Map<Integer, List<Map<String, String>>> horarios_por_rota = new HashMap<>();
List<Map<String, String>> bilhetes = new ArrayList<>();

// Obter conexão com o banco de dados
Connection conn = null;
PreparedStatement stmt = null;
ResultSet rs = null;

try {
    conn = getConnection();

    // Verificar a estrutura da tabela bilhetes
    try {
        String sql_check_bilhetes = "DESCRIBE bilhetes";
        stmt = conn.prepareStatement(sql_check_bilhetes);
        rs = stmt.executeQuery();
        out.println("<!-- Estrutura da tabela bilhetes: -->");
        boolean hasTable = false;
        while (rs.next()) {
            hasTable = true;
            out.println("<!-- Campo: " + rs.getString("Field") + ", Tipo: " + rs.getString("Type") + " -->");
        }
        rs.close();

        if (!hasTable) {
            out.println("<!-- Tabela bilhetes não encontrada, criando... -->");
            String sql_create_bilhetes = "CREATE TABLE bilhetes (" +
                "id INT AUTO_INCREMENT PRIMARY KEY, " +
                "id_cliente INT NOT NULL, " +
                "id_rota INT NOT NULL, " +
                "data_compra DATETIME DEFAULT NOW(), " +
                "data_viagem DATE NOT NULL, " +
                "hora_viagem TIME NOT NULL, " +
                "numero_lugar INT, " +
                "FOREIGN KEY (id_cliente) REFERENCES utilizadores(id) ON DELETE CASCADE, " +
                "FOREIGN KEY (id_rota) REFERENCES rotas(id)" +
                ")";
            stmt = conn.prepareStatement(sql_create_bilhetes);
            stmt.executeUpdate();
            out.println("<!-- Tabela bilhetes criada com sucesso -->");
        }
    } catch (Exception e) {
        // Comentário removido para não expor detalhes da estrutura no HTML
        logError("Erro ao verificar estrutura da tabela bilhetes", e);

        try {
            // Tentar criar a tabela bilhetes
            String sql_create_bilhetes = "CREATE TABLE IF NOT EXISTS bilhetes (" +
                "id INT AUTO_INCREMENT PRIMARY KEY, " +
                "id_cliente INT NOT NULL, " +
                "id_rota INT NOT NULL, " +
                "data_compra DATETIME DEFAULT NOW(), " +
                "data_viagem DATE NOT NULL, " +
                "hora_viagem TIME NOT NULL, " +
                "numero_lugar INT, " +
                "FOREIGN KEY (id_cliente) REFERENCES utilizadores(id) ON DELETE CASCADE, " +
                "FOREIGN KEY (id_rota) REFERENCES rotas(id)" +
                ")";
            stmt = conn.prepareStatement(sql_create_bilhetes);
            stmt.executeUpdate();
            // Comentário removido para não expor detalhes da estrutura no HTML
        } catch (Exception ex) {
            // Comentário removido para não expor detalhes da estrutura no HTML
            logError("Erro ao criar tabela bilhetes", ex);
        }
    }

    // Verificar se a tabela rotas tem dados
    String sql_count_rotas = "SELECT COUNT(*) as total FROM rotas";
    stmt = conn.prepareStatement(sql_count_rotas);
    rs = stmt.executeQuery();
    int totalRotas = 0;
    if (rs.next()) {
        totalRotas = rs.getInt("total");
    }
    rs.close();
    out.println("<!-- Total de rotas na tabela: " + totalRotas + " -->");

    // Se não houver rotas, vamos inserir algumas para teste
    if (totalRotas == 0) {
        out.println("<!-- Inserindo rotas de teste -->");
        String sql_insert_rotas = "INSERT INTO rotas (id, origem, destino, preco, capacidade, disponivel) VALUES " +
                                 "(1, 'Lisboa', 'Porto', 25.00, 50, 1), " +
                                 "(2, 'Porto', 'Coimbra', 15.00, 40, 1), " +
                                 "(3, 'Coimbra', 'Faro', 35.00, 30, 1), " +
                                 "(4, 'Lisboa', 'Faro', 30.00, 45, 1)";
        stmt = conn.prepareStatement(sql_insert_rotas);
        stmt.executeUpdate();
    }

    // Buscar rotas disponíveis
    String sql_rotas = "SELECT id, origem, destino, preco FROM rotas WHERE disponivel = 1 ORDER BY origem, destino";
    stmt = conn.prepareStatement(sql_rotas);
    rs = stmt.executeQuery();

    while (rs.next()) {
        Map<String, String> rota = new HashMap<>();
        rota.put("id", rs.getString("id"));
        rota.put("origem", rs.getString("origem"));
        rota.put("destino", rs.getString("destino"));
        rota.put("preco", rs.getString("preco"));
        rotas.add(rota);
    }
    rs.close();
    out.println("<!-- Número de rotas carregadas: " + rotas.size() + " -->");

    // Verificar se a tabela horarios tem dados
    String sql_count = "SELECT COUNT(*) as total FROM horarios";
    stmt = conn.prepareStatement(sql_count);
    rs = stmt.executeQuery();
    int totalHorarios = 0;
    if (rs.next()) {
        totalHorarios = rs.getInt("total");
    }
    rs.close();
    out.println("<!-- Total de horários na tabela: " + totalHorarios + " -->");

    // Se não houver horários, vamos inserir alguns para teste
    if (totalHorarios == 0) {
        out.println("<!-- Inserindo horários de teste -->");
        String sql_insert_horarios = "INSERT INTO horarios (id_rota, horario_partida, data_viagem, lugares_disponiveis, disponivel) VALUES " +
                                    "(1, '08:00:00', '2024-06-20', 50, 1), " +
                                    "(1, '14:00:00', '2024-06-21', 50, 1), " +
                                    "(2, '09:30:00', '2024-06-22', 40, 1), " +
                                    "(2, '15:30:00', '2024-06-23', 40, 1)";
        stmt = conn.prepareStatement(sql_insert_horarios);
        stmt.executeUpdate();
    }

    // Buscar horários disponíveis
    String sql_horarios = "SELECT h.id, h.id_rota, h.data_viagem, h.horario_partida, h.lugares_disponiveis, r.capacidade " +
                         "FROM horarios h " +
                         "JOIN rotas r ON h.id_rota = r.id " +
                         "ORDER BY h.data_viagem, h.horario_partida";

    stmt = conn.prepareStatement(sql_horarios);
    rs = stmt.executeQuery();

    // Inicializar o mapa para armazenar horários por rota
    horarios_por_rota = new HashMap<Integer, List<Map<String, String>>>();

    while (rs.next()) {
        int id_rota = rs.getInt("id_rota");

        // Criar a lista de horários para esta rota se ainda não existir
        if (!horarios_por_rota.containsKey(id_rota)) {
            horarios_por_rota.put(id_rota, new ArrayList<Map<String, String>>());
        }

        // Criar um mapa para armazenar os dados do horário
        Map<String, String> horario = new HashMap<String, String>();
        horario.put("id", rs.getString("id"));
        horario.put("id_rota", rs.getString("id_rota"));

        // Formatar a data para exibição
        java.sql.Date data_viagem = rs.getDate("data_viagem");
        SimpleDateFormat formatoData = new SimpleDateFormat("dd/MM/yyyy");
        String data_formatada = formatoData.format(data_viagem);
        horario.put("data_viagem", data_formatada);

        // Formatar a hora para exibição
        java.sql.Time hora_partida = rs.getTime("horario_partida");
        SimpleDateFormat formatoHora = new SimpleDateFormat("HH:mm");
        String hora_formatada = formatoHora.format(hora_partida);
        horario.put("hora_formatada", hora_formatada);

        // Armazenar os dados originais também
        horario.put("horario_partida", rs.getString("horario_partida"));
        horario.put("lugares_disponiveis", rs.getString("lugares_disponiveis"));
        horario.put("capacidade", rs.getString("capacidade"));

        // Adicionar o horário à lista de horários desta rota
        horarios_por_rota.get(id_rota).add(horario);
    }
    rs.close();

    // Não precisamos mais construir JSON, pois os dados são usados diretamente no HTML

    // Buscar bilhetes do cliente ordenados por data de compra (mais recentes primeiro)
    String sql_bilhetes = "SELECT b.id, b.data_compra, b.data_viagem, b.hora_viagem, b.numero_lugar, " +
                         "r.origem, r.destino, r.preco " +
                         "FROM bilhetes b " +
                         "JOIN rotas r ON b.id_rota = r.id " +
                         "WHERE b.id_cliente = ? " +
                         "ORDER BY b.data_compra DESC";

    stmt = conn.prepareStatement(sql_bilhetes);
    stmt.setInt(1, id_cliente);
    rs = stmt.executeQuery();

    while (rs.next()) {
        Map<String, String> bilhete = new HashMap<>();
        bilhete.put("id", rs.getString("id"));
        bilhete.put("origem", rs.getString("origem"));
        bilhete.put("destino", rs.getString("destino"));

        // Formatar a data para exibição
        java.sql.Date data_viagem = rs.getDate("data_viagem");
        SimpleDateFormat formatoData = new SimpleDateFormat("dd/MM/yyyy");
        String data_formatada = formatoData.format(data_viagem);
        bilhete.put("data_viagem", data_formatada);

        // Formatar a hora para exibição
        java.sql.Time hora_viagem = rs.getTime("hora_viagem");
        SimpleDateFormat formatoHora = new SimpleDateFormat("HH:mm");
        String hora_formatada = formatoHora.format(hora_viagem);
        bilhete.put("horario_partida", hora_formatada);

        // Formatar a data de compra
        java.sql.Timestamp data_compra = rs.getTimestamp("data_compra");
        String data_compra_formatada = new SimpleDateFormat("dd/MM/yyyy HH:mm").format(data_compra);
        bilhete.put("data_compra", data_compra_formatada);

        // Calcular preço total (preço unitário * número de lugares)
        double preco = rs.getDouble("preco");
        int lugares = rs.getInt("numero_lugar");
        double precoTotal = preco * lugares;
        bilhete.put("preco_total", String.format("%.2f", precoTotal));

        // Número do lugar
        int numero_lugar = rs.getInt("numero_lugar");
        bilhete.put("lugares", String.valueOf(numero_lugar));

        bilhetes.add(bilhete);
    }
    rs.close();

} catch (Exception e) {
    // Não expor detalhes do erro no HTML
    logError("Erro ao carregar dados", e);
    e.printStackTrace();
} finally {
    if (rs != null) try { rs.close(); } catch (SQLException e) { /* ignorar */ }
    if (stmt != null) try { stmt.close(); } catch (SQLException e) { /* ignorar */ }
    if (conn != null) try { conn.close(); } catch (SQLException e) { /* ignorar */ }
}

%>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="bilhetes_cliente.css">
    <title>FelixBus - Os Meus Bilhetes</title>
    <script>
        // Script para inicialização
    </script>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="perfil_cliente.jsp">Perfil</a></div>
            <div class="link"> <a href="pg_cliente.jsp">Página Inicial</a></div>
            <div class="link"> <a href="carteira_cliente.jsp">Carteira</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.jsp"><button>Logout</button></a></div>
            <div class="btn-cliente">Área do Cliente</div>
        </div>
    </nav>

    <section>
        <h1>Os Meus Bilhetes</h1>

        <% if (!mensagem.isEmpty()) { %>
            <div class="alert alert-<%= tipo_mensagem %>">
                <%= mensagem %>
            </div>
        <% } %>

        <div class="content-wrapper">
            <div class="comprar-bilhete">
                <div class="card-header">
                    <h2>Comprar Novo Bilhete</h2>
                </div>
                <div class="card-body">
                    <form id="comprarBilheteForm" method="post" action="bilhetes_cliente.jsp">
                        <div class="form-group">
                            <label for="rota">Selecione a Rota:</label>
                            <select id="rota" name="rota" required>
                                <option value="">Selecione uma rota</option>
                                <% for (Map<String, String> rota : rotas) { %>
                                    <option value="<%= rota.get("id") %>">
                                        <%= rota.get("origem") %> → <%= rota.get("destino") %> (€<%= rota.get("preco") %>)
                                    </option>
                                <% } %>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="horario">Selecione a Data e Hora:</label>
                            <select id="horario" name="horario" required>
                                <option value="">Selecione uma data e hora</option>
                                <%
                                // Pré-carregar alguns horários para cada rota
                                for (Map.Entry<Integer, List<Map<String, String>>> entry : horarios_por_rota.entrySet()) {
                                    int rotaId = entry.getKey();
                                    List<Map<String, String>> horariosList = entry.getValue();

                                    for (Map<String, String> horario : horariosList) {
                                        String id = horario.get("id");
                                        String dataViagem = horario.get("data_viagem");
                                        String horaFormatada = horario.get("hora_formatada");
                                        String lugaresDisponiveis = horario.get("lugares_disponiveis");
                                        if (lugaresDisponiveis == null || lugaresDisponiveis.isEmpty()) {
                                            lugaresDisponiveis = "0";
                                        }
                                %>
                                    <option value="<%= id %>" data-rota="<%= rotaId %>"
                                            data-data-viagem="<%= dataViagem %>"
                                            data-hora-formatada="<%= horaFormatada %>"
                                            data-lugares-disponiveis="<%= lugaresDisponiveis %>"
                                            style="display: none;">
                                        <%= dataViagem %> - <%= horaFormatada %> (<%= lugaresDisponiveis %> lugares)
                                    </option>
                                <%
                                    }
                                }
                                %>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quantidade">Quantidade de Bilhetes:</label>
                            <input type="number" id="quantidade" name="quantidade" min="1" max="10" value="1" required>
                            <p id="quantidadeDisponivel" class="info-text">Selecione uma rota e hora para ver disponibilidade</p>
                        </div>

                    <div class="form-group" id="infoViagem" style="display: none;">
                        <div class="info-viagem-box">
                            <h3>Detalhes da Viagem</h3>
                            <div class="info-viagem-grid">
                                <div class="info-item">
                                    <div class="info-label">Data</div>
                                    <div class="info-value" id="dataViagem"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Hora</div>
                                    <div class="info-value" id="horarioPartida"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Lugares</div>
                                    <div class="info-value" id="lugaresDisponiveis"></div>
                                </div>
                            </div>
                            <div id="resumoCompra" style="display: none; margin-top: 20px; border-top: 1px solid rgba(0, 86, 179, 0.2); padding-top: 20px;">
                                <h4 style="color: #0056b3; text-align: center; margin-bottom: 15px; font-size: 18px;">
                                    Resumo da Compra
                                </h4>
                                <div class="info-viagem-grid">
                                    <div class="info-item">
                                        <div class="info-label">Quantidade</div>
                                        <div class="info-value" id="quantidadeCompra"></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Preço Total</div>
                                        <div class="info-value" id="precoTotal"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="lugarGroup" style="display: none;">
                        <label>Selecione os Lugares:</label>
                        <div id="lugaresSelector" class="lugares-grid"></div>
                        <p id="lugaresInfo" class="info-text">Selecione os lugares no diagrama acima.</p>
                        <input type="hidden" id="lugares" name="lugares" value="">
                    </div>

                    <button type="submit" id="btnComprar" class="btn-primary" style="display: none;">
                        Comprar Bilhete
                    </button>
                </form>
            </div>

            <div class="meus-bilhetes">
                <div class="table-dropdown">
                    <div class="table-dropdown-header" onclick="toggleTableDropdown()">
                        <h2>Meus Bilhetes (<%= bilhetes.size() %>)</h2>
                        <span>▼</span>
                    </div>
                    <div class="table-dropdown-content open" id="tableDropdownContent">
                        <div class="bilhetes-list">
                                <% if (!bilhetes.isEmpty()) { %>
                                    <div class="table-responsive">
                                        <table class="bilhetes-table">
                                            <thead>
                                                <tr>
                                                    <th>Origem</th>
                                                    <th>Destino</th>
                                                    <th>Data</th>
                                                    <th>Hora</th>
                                                    <th>Lugares</th>
                                                    <th>Preço</th>
                                                    <th>Data Compra</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <% for (Map<String, String> bilhete : bilhetes) { %>
                                                    <tr>
                                                        <td><%= bilhete.get("origem") %></td>
                                                        <td><%= bilhete.get("destino") %></td>
                                                        <td><%= bilhete.get("data_viagem") %></td>
                                                        <td><%= bilhete.get("horario_partida") %></td>
                                                        <td><%= bilhete.get("lugares") %></td>
                                                        <td>€<%= bilhete.get("preco_total") %></td>
                                                        <td><%= bilhete.get("data_compra") %></td>
                                                    </tr>
                                                <% } %>
                                            </tbody>
                                        </table>
                                    </div>
                                <% } else { %>
                                    <div class="empty-state">
                                        <p>Ainda não possui nenhum bilhete. Compre o seu primeiro bilhete agora!</p>
                                    </div>
                                <% } %>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
    <%= new java.util.Date().getYear() + 1900 %><img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>

<script>
    // Garantir que o script seja executado após o carregamento do DOM
    document.addEventListener('DOMContentLoaded', function() {
        // Elementos do DOM
        const rotaSelect = document.getElementById('rota');
        const horarioSelect = document.getElementById('horario');
        const quantidadeInput = document.getElementById('quantidade');
        const quantidadeDisponivel = document.getElementById('quantidadeDisponivel');
        const infoViagem = document.getElementById('infoViagem');
        const dataViagem = document.getElementById('dataViagem');
        const horarioPartida = document.getElementById('horarioPartida');
        const lugaresDisponiveis = document.getElementById('lugaresDisponiveis');
        const resumoCompra = document.getElementById('resumoCompra');
        const quantidadeCompra = document.getElementById('quantidadeCompra');
        const precoTotal = document.getElementById('precoTotal');
        const btnComprar = document.getElementById('btnComprar');

        // Preço unitário da rota selecionada
        let precoUnitario = 0;

        // Quando a rota é alterada, atualizar os horários disponíveis
        rotaSelect.addEventListener('change', function() {
            const rotaId = this.value;

            // Esconder todas as opções de horário
            Array.from(horarioSelect.options).forEach(option => {
                if (option.value === "") {
                    // Manter a opção padrão visível
                    option.style.display = "";
                } else {
                    option.style.display = "none";
                }
            });

            // Resetar o select de horários
            horarioSelect.value = "";

            // Esconder informações de viagem
            infoViagem.style.display = 'none';
            btnComprar.style.display = 'none';

            // Se uma rota foi selecionada
            if (rotaId) {
                // Mostrar apenas os horários para esta rota
                let horarioCount = 0;
                Array.from(horarioSelect.options).forEach(option => {
                    if (option.getAttribute('data-rota') === rotaId) {
                        option.style.display = "";
                        horarioCount++;
                    }
                });

                // Obter o preço da rota selecionada
                const rotaOption = rotaSelect.options[rotaSelect.selectedIndex];
                const rotaText = rotaOption.textContent;
                const precoMatch = rotaText.match(/\(€([0-9,.]+)\)/);
                if (precoMatch && precoMatch[1]) {
                    precoUnitario = parseFloat(precoMatch[1].replace(',', '.'));
                }

                if (horarioCount > 0) {
                    quantidadeDisponivel.textContent = 'Selecione uma data e hora para ver disponibilidade';
                } else {
                    quantidadeDisponivel.textContent = 'Não há horários disponíveis para esta rota';
                }
            } else {
                quantidadeDisponivel.textContent = 'Selecione uma rota e hora para ver disponibilidade';
            }
        });

    // Quando o horário é alterado, atualizar as informações da viagem
    horarioSelect.addEventListener('change', function() {
        const horarioId = this.value;

        // Esconder informações de viagem
        infoViagem.style.display = 'none';
        btnComprar.style.display = 'none';

        // Se um horário foi selecionado
        if (horarioId) {
            const selectedOption = this.options[this.selectedIndex];

            // Obter dados do dataset
            const dataViagemText = selectedOption.getAttribute('data-data-viagem');
            const horaFormatadaText = selectedOption.getAttribute('data-hora-formatada');
            const lugaresDisponiveisText = selectedOption.getAttribute('data-lugares-disponiveis');

            // Converter para número e garantir que seja um valor válido
            const lugaresDisponiveisNum = parseInt(lugaresDisponiveisText) || 0;

            // Atualizar informações da viagem
            dataViagem.textContent = dataViagemText;
            horarioPartida.textContent = horaFormatadaText;
            lugaresDisponiveis.textContent = lugaresDisponiveisNum + ' lugares disponíveis';

            // Mostrar informações da viagem
            infoViagem.style.display = 'block';

            // Atualizar texto de disponibilidade
            quantidadeDisponivel.textContent = `Máximo de ${Math.min(10, lugaresDisponiveisNum)} bilhetes por compra`;

            // Limitar a quantidade ao número de lugares disponíveis
            quantidadeInput.max = Math.min(10, lugaresDisponiveisNum);

            // Atualizar resumo da compra
            atualizarResumoCompra();

            // Mostrar botão de compra
            btnComprar.style.display = 'block';
        } else {
            quantidadeDisponivel.textContent = 'Selecione uma data e hora para ver disponibilidade';
        }
    });

    // Quando a quantidade é alterada, atualizar o resumo da compra
    quantidadeInput.addEventListener('change', function() {
        atualizarResumoCompra();
    });

    // Função para atualizar o resumo da compra
    function atualizarResumoCompra() {
        const quantidade = parseInt(quantidadeInput.value);
        const total = quantidade * precoUnitario;

        quantidadeCompra.textContent = quantidade;
        precoTotal.textContent = '€' + total.toFixed(2);

        resumoCompra.style.display = 'block';
    }

    }); 

    // Função para controlar o dropdown da tabela
    function toggleTableDropdown() {
        const content = document.getElementById('tableDropdownContent');

        if (content.classList.contains('open')) {
            content.classList.remove('open');
        } else {
            content.classList.add('open');
        }
    }
</script>
