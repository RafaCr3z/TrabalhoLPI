<?php
    session_start();
    include '../basedados/basedados.h';
    if (isset($_SESSION["id_nivel"]) && $_SESSION["id_nivel"] > 0){
        header("Location: erro.php");
    }

    // Inicializa variáveis para a pesquisa
    $origem = '';
    $destino = '';
    $resultados = [];
    $pesquisa_realizada = false;

    // Verifica se o formulário foi enviado
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pesquisar'])) {
        $origem = isset($_POST['origem']) ? trim($_POST['origem']) : '';
        $destino = isset($_POST['destino']) ? trim($_POST['destino']) : '';
        $pesquisa_realizada = true;

        // Constrói a consulta SQL
        $sql = "SELECT r.id, r.origem, r.destino, r.preco, h.horario_partida
                FROM rotas r
                JOIN horarios h ON r.id = h.id_rota
                WHERE r.disponivel = 1";

        // Adiciona filtros se foram fornecidos
        if (!empty($origem)) {
            $sql .= " AND r.origem LIKE '%" . mysqli_real_escape_string($conn, $origem) . "%'";
        }
        if (!empty($destino)) {
            $sql .= " AND r.destino LIKE '%" . mysqli_real_escape_string($conn, $destino) . "%'";
        }

        $sql .= " ORDER BY r.origem, r.destino, h.horario_partida";

        // Executa a consulta
        $resultado = mysqli_query($conn, $sql);

        if (!$resultado) {
            echo "<script>alert('Erro na consulta: " . mysqli_error($conn) . "');</script>";
        } else {
            // Armazena os resultados
            while ($row = mysqli_fetch_assoc($resultado)) {
                $resultados[] = $row;
            }
        }
    }

    // Busca alertas dinâmicos
    $mensagens = [];
    $data_atual = date('Y-m-d H:i:s'); // Data e hora atual
    $sql_mensagens = "SELECT mensagem AS conteudo FROM alertas WHERE data_inicio <= '$data_atual' AND data_fim >= '$data_atual'";
    $resultado_mensagens = mysqli_query($conn, $sql_mensagens);

    if ($resultado_mensagens) {
        while ($mensagem = mysqli_fetch_assoc($resultado_mensagens)) {
            $mensagens[] = $mensagem;
        }
    }
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo.css">
    <title>FelixBus</title>
</head>
<body>
    <nav>
        <a href="index.php" class="logo">
            <h1>Felix<span>Bus</span></h1>
        </a>
        <div class="links">
            <div class="link"><a href="index.php">HOME</a></div>
            <div class="link"><a href="servicos.php">SERVIÇOS</a></div>
            <div class="link"><a href="contactos.php">CONTACTOS</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="login.php"><button>Login</button></a></div>
            <div class="btn"><a href="registar.php"><button>Registar</button></a></div>
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
            <form method="post" action="index.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="origem">Origem:</label>
                        <input type="text" id="origem" name="origem" placeholder="Ex: Lisboa" value="<?php echo htmlspecialchars($origem); ?>">
                    </div>

                    <div class="form-group">
                        <label for="destino">Destino:</label>
                        <input type="text" id="destino" name="destino" placeholder="Ex: Porto" value="<?php echo htmlspecialchars($destino); ?>">
                    </div>

                    <div class="form-group">
                        <button type="submit" name="pesquisar" class="search-btn">Pesquisar</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($pesquisa_realizada): ?>
            <div class="results-container">
                <?php if (empty($resultados)): ?>
                    <p class="no-results">Nenhum resultado encontrado para a sua pesquisa.</p>
                <?php else: ?>
                    <h3>Resultados da Pesquisa</h3>
                    <div class="results-table-wrapper">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Horário</th>
                                    <th>Preço</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $rota): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rota['origem']); ?></td>
                                        <td><?php echo htmlspecialchars($rota['destino']); ?></td>
                                        <td><?php echo htmlspecialchars($rota['horario_partida']); ?></td>
                                        <td><?php echo '€' . number_format($rota['preco'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php
                                // Adicionar algumas linhas vazias para garantir que a tabela tenha rolagem
                                $num_rows = count($resultados);
                                if ($num_rows < 5) {
                                    for ($i = 0; $i < (5 - $num_rows); $i++) {
                                        echo '<tr class="spacer-row"><td colspan="4">&nbsp;</td></tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="alertas">
    <?php if (!empty($mensagens)): ?>
        <div class="alertas-container">
            <table class="alertas-table">
                <thead>
                    <tr>
                        <th>Informação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mensagem['conteudo']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Garantir que a tabela tenha rolagem
        const tableWrapper = document.querySelector('.results-table-wrapper');
        if (tableWrapper) {
            // Forçar a rolagem a ser visível
            tableWrapper.style.overflowY = 'scroll';
        }
    });
</script>
</body>
</html>
