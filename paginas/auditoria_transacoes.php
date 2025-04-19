<?php
session_start();
include '../basedados/basedados.h';

// Verificar se o usuário é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Inicializar variáveis de filtro
$filtro_cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// Construir a consulta SQL com filtros
$sql = "SELECT t.*, u.nome as nome_cliente, u.email as email_cliente
        FROM transacoes t
        JOIN utilizadores u ON t.id_cliente = u.id
        WHERE 1=1";

if ($filtro_cliente_id > 0) {
    $sql .= " AND t.id_cliente = " . $filtro_cliente_id;
}

if (!empty($filtro_tipo)) {
    $sql .= " AND t.tipo = '" . mysqli_real_escape_string($conn, $filtro_tipo) . "'";
}

if (!empty($filtro_data_inicio)) {
    $sql .= " AND DATE(t.data_transacao) >= '" . mysqli_real_escape_string($conn, $filtro_data_inicio) . "'";
}

if (!empty($filtro_data_fim)) {
    $sql .= " AND DATE(t.data_transacao) <= '" . mysqli_real_escape_string($conn, $filtro_data_fim) . "'";
}

$sql .= " ORDER BY t.data_transacao DESC";

// Executar a consulta
$result = mysqli_query($conn, $sql);

// Obter tipos de transação para o filtro
$sql_tipos = "SELECT DISTINCT tipo FROM transacoes ORDER BY tipo";
$result_tipos = mysqli_query($conn, $sql_tipos);
$tipos = [];
while ($row = mysqli_fetch_assoc($result_tipos)) {
    $tipos[] = $row['tipo'];
}

// Obter lista de clientes para o dropdown
$sql_clientes = "SELECT DISTINCT u.id, u.nome, u.email
                FROM utilizadores u
                JOIN transacoes t ON u.id = t.id_cliente
                WHERE u.tipo_perfil = 3
                ORDER BY u.nome";
$result_clientes = mysqli_query($conn, $sql_clientes);

// Calcular totais
$sql_totais = "SELECT
                SUM(CASE WHEN tipo = 'deposito' THEN valor ELSE 0 END) as total_depositos,
                SUM(CASE WHEN tipo = 'retirada' THEN valor ELSE 0 END) as total_retiradas,
                SUM(CASE WHEN tipo = 'compra' THEN valor ELSE 0 END) as total_compras
               FROM transacoes";
$result_totais = mysqli_query($conn, $sql_totais);
$totais = mysqli_fetch_assoc($result_totais);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="auditoria_transacoes.css">
    <title>FelixBus - Auditoria de Transações</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links" style="display: flex; justify-content: center; width: 50%;">
            <div class="link"> <a href="pg_admin.php" style="font-size: 1.2rem; font-weight: 500;">Voltar para Página Inicial</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área do Administrador</div>
        </div>
    </nav>

    <section>
        <h1>Auditoria de Transações</h1>

        <div class="resumo-container">
            <div class="resumo-card">
                <h3>Total de Depósitos</h3>
                <p class="valor deposito">€<?php echo number_format($totais['total_depositos'], 2, ',', '.'); ?></p>
            </div>
            <div class="resumo-card">
                <h3>Total de Retiradas</h3>
                <p class="valor retirada">€<?php echo number_format($totais['total_retiradas'], 2, ',', '.'); ?></p>
            </div>
            <div class="resumo-card">
                <h3>Total de Compras</h3>
                <p class="valor compra">€<?php echo number_format($totais['total_compras'], 2, ',', '.'); ?></p>
            </div>
            <div class="resumo-card">
                <h3>Saldo FelixBus</h3>
                <p class="valor">€<?php
                    $sql_saldo = "SELECT saldo FROM carteira_felixbus LIMIT 1";
                    $result_saldo = mysqli_query($conn, $sql_saldo);
                    $saldo = mysqli_fetch_assoc($result_saldo);
                    echo number_format($saldo['saldo'], 2, ',', '.');
                ?></p>
            </div>
        </div>

        <div class="filtros-container">
            <h2>Filtros</h2>
            <form method="get" action="auditoria_transacoes.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="cliente_id">Cliente:</label>
                        <select id="cliente_id" name="cliente_id">
                            <option value="0">Todos os Clientes</option>
                            <?php while ($cliente = mysqli_fetch_assoc($result_clientes)): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo $filtro_cliente_id == $cliente['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome']) . ' (' . htmlspecialchars($cliente['email']) . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tipo">Tipo de Transação:</label>
                        <select id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo; ?>" <?php echo $filtro_tipo == $tipo ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($tipo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="data_inicio">Data Início:</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?php echo $filtro_data_inicio; ?>">
                    </div>

                    <div class="form-group">
                        <label for="data_fim">Data Fim:</label>
                        <input type="date" id="data_fim" name="data_fim" value="<?php echo $filtro_data_fim; ?>">
                    </div>

                    <div class="form-group">
                        <button type="submit">Filtrar</button>
                        <a href="auditoria_transacoes.php" class="limpar-btn">Limpar Filtros</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="transacoes-container">
            <h2>REGISTRO DE TRANSAÇÕES</h2>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="transacoes-table-wrapper">
                    <table class="transacoes-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data/Hora</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($transacao = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $classe_valor = '';
                            if ($transacao['tipo'] == 'deposito') {
                                $classe_valor = 'deposito';
                                $valor_formatado = '+€' . number_format($transacao['valor'], 2, ',', '.');
                            } elseif ($transacao['tipo'] == 'retirada') {
                                $classe_valor = 'retirada';
                                $valor_formatado = '-€' . number_format($transacao['valor'], 2, ',', '.');
                            } else {
                                $classe_valor = 'compra';
                                $valor_formatado = '€' . number_format($transacao['valor'], 2, ',', '.');
                            }
                            ?>
                            <tr>
                                <td><?php echo $transacao['id']; ?></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($transacao['data_transacao'])); ?></td>
                                <td><?php echo htmlspecialchars($transacao['nome_cliente']) . ' (' . htmlspecialchars($transacao['email_cliente']) . ')'; ?></td>
                                <td><?php echo ucfirst($transacao['tipo']); ?></td>
                                <td class="<?php echo $classe_valor; ?>"><?php echo $valor_formatado; ?></td>
                                <td><?php echo htmlspecialchars($transacao['descricao']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php
                        // Adicionar algumas linhas vazias para garantir que a tabela tenha rolagem
                        $num_rows = mysqli_num_rows($result);
                        if ($num_rows < 5) {
                            for ($i = 0; $i < (5 - $num_rows); $i++) {
                                echo '<tr class="spacer-row"><td colspan="6">&nbsp;</td></tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <p class="no-results">Nenhuma transação encontrada com os filtros selecionados.</p>
            <?php endif; ?>
        </div>
    </section>

     <!-- FOOTER -->
     <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Garantir que a tabela tenha rolagem
        const tableWrapper = document.querySelector('.transacoes-table-wrapper');
        if (tableWrapper) {
            // Forçar a rolagem a ser visível
            tableWrapper.style.overflowY = 'scroll';
        }
    });
</script>
</body>
</html>
