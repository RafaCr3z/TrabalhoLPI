<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*, java.util.*, java.text.*" %>
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

// Inicializa variáveis
Connection conn = null;
PreparedStatement pstmt = null;
ResultSet rs = null;
double saldo = 0.0;
String mensagem = "";
String tipo_mensagem = "";

try {
    conn = getConnection();
    
    // Obtém o ID da carteira FelixBus (sistema)
    pstmt = conn.prepareStatement("SELECT id FROM carteira_felixbus LIMIT 1");
    rs = pstmt.executeQuery();
    int id_carteira_felixbus = 0;
    if (rs.next()) {
        id_carteira_felixbus = rs.getInt("id");
    }
    rs.close();
    pstmt.close();
    
    // Consulta o saldo atual do cliente na base de dados
    pstmt = conn.prepareStatement("SELECT saldo FROM carteiras WHERE id_cliente = ?");
    pstmt.setInt(1, id_cliente);
    rs = pstmt.executeQuery();
    
    // Se o cliente não tiver carteira, cria uma com saldo zero
    if (!rs.next()) {
        rs.close();
        pstmt.close();
        
        pstmt = conn.prepareStatement("INSERT INTO carteiras (id_cliente, saldo) VALUES (?, 0.00)");
        pstmt.setInt(1, id_cliente);
        pstmt.executeUpdate();
        pstmt.close();
        
        saldo = 0.00;
    } else {
        saldo = rs.getDouble("saldo");
        rs.close();
        pstmt.close();
    }
    
    // Verifica se existem mensagens na sessão
    if (session.getAttribute("mensagem") != null) {
        mensagem = (String)session.getAttribute("mensagem");
        tipo_mensagem = (String)session.getAttribute("tipo_mensagem");
        
        // Limpa as mensagens da sessão após exibi-las
        session.removeAttribute("mensagem");
        session.removeAttribute("tipo_mensagem");
    }
    
    // Verifica se o formulário foi submetido
    if ("POST".equals(request.getMethod())) {
        // Obtém os valores do formulário
        double valor = Double.parseDouble(request.getParameter("valor"));
        String operacao = request.getParameter("operacao");
        
        // Verifica se o valor é válido (maior que zero)
        if (valor <= 0) {
            session.setAttribute("mensagem", "Valor inválido. Por favor, introduza um valor superior a zero.");
            session.setAttribute("tipo_mensagem", "danger");
            
            // Redireciona para evitar reenvio do formulário ao atualizar a página
            response.sendRedirect("carteira_cliente.jsp");
            return;
        } else {
            // Inicia uma transação para garantir a integridade dos dados
            conn.setAutoCommit(false);
            
            try {
                String sql_atualiza = null;
                String tipo_transacao = null;
                String descricao = null;
                
                // Define a operação a realizar com base na escolha do utilizador
                if ("depositar".equals(operacao)) {
                    // Adiciona valor à carteira
                    sql_atualiza = "UPDATE carteiras SET saldo = saldo + ? WHERE id_cliente = ?";
                    tipo_transacao = "deposito";
                    descricao = "Depósito de €" + valor + " na carteira";
                } else if ("levantar".equals(operacao) && saldo >= valor) {
                    // Retira valor da carteira se houver saldo suficiente
                    sql_atualiza = "UPDATE carteiras SET saldo = saldo - ? WHERE id_cliente = ?";
                    tipo_transacao = "levantamento";
                    descricao = "Levantamento de €" + valor + " da carteira";
                } else {
                    // Mensagem de erro se não houver saldo suficiente
                    session.setAttribute("mensagem", "Saldo insuficiente para realizar esta operação.");
                    session.setAttribute("tipo_mensagem", "danger");
                    
                    // Redireciona para evitar reenvio do formulário
                    response.sendRedirect("carteira_cliente.jsp");
                    return;
                }
                
                // Executa a atualização do saldo
                if (sql_atualiza != null) {
                    pstmt = conn.prepareStatement(sql_atualiza);
                    pstmt.setDouble(1, valor);
                    pstmt.setInt(2, id_cliente);
                    
                    if (pstmt.executeUpdate() > 0) {
                        pstmt.close();
                        
                        // Registra a transação
                        pstmt = conn.prepareStatement("INSERT INTO transacoes (id_cliente, valor, tipo, descricao, data_transacao) VALUES (?, ?, ?, ?, NOW())");
                        pstmt.setInt(1, id_cliente);
                        pstmt.setDouble(2, valor);
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
                            throw new Exception("Erro ao registrar transação");
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
            } catch (Exception e) {
                // Cancela a transação em caso de exceção
                conn.rollback();
                session.setAttribute("mensagem", e.getMessage());
                session.setAttribute("tipo_mensagem", "danger");
                
                // Redireciona para evitar reenvio do formulário
                response.sendRedirect("carteira_cliente.jsp");
                return;
            } finally {
                // Restaura o modo de auto-commit
                conn.setAutoCommit(true);
            }
        }
    }
} catch (Exception e) {
    mensagem = "Erro: " + e.getMessage();
    tipo_mensagem = "danger";
} finally {
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
        </div>
    </section>

    <footer>
        © <%= new java.util.Date().getYear() + 1900 %> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

</body>
</html>
