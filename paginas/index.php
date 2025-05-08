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
        $sql = "SELECT r.id, r.origem, r.destino, r.preco, h.horario_partida, h.data_viagem
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

        $sql .= " ORDER BY r.origem ASC, r.destino ASC, h.data_viagem ASC, h.horario_partida ASC";

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

    // Debug: Mostrar todas as mensagens
    $sql_todas = "SELECT * FROM alertas";
    $result_todas = mysqli_query($conn, $sql_todas);
    echo "<!-- Todas as mensagens na tabela: -->";
    while ($row = mysqli_fetch_assoc($result_todas)) {
        echo "<!-- Mensagem: " . $row['mensagem'] . " -->";
    }

    // Busca alertas dinâmicos
    $mensagens = [];
    $data_atual = date('Y-m-d H:i:s');

    // Consulta simplificada
    $sql_mensagens = "SELECT * FROM alertas";
    $resultado_mensagens = mysqli_query($conn, $sql_mensagens);

    if (!$resultado_mensagens) {
        echo "<!-- Erro na consulta: " . mysqli_error($conn) . " -->";
    } else {
        while ($row = mysqli_fetch_assoc($resultado_mensagens)) {
            $mensagens[] = ['conteudo' => $row['mensagem']];
        }
    }

    // Debug: Número de mensagens encontradas
    echo "<!-- Número de mensagens: " . count($mensagens) . " -->";
?>

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
            <div class="btn"><a href="registar.php"><button class="register-btn">Registar</button></a></div>
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
                        <input type="text" id="origem" name="origem" placeholder="Ex: Lisboa" value="<?php echo $origem; ?>">
                    </div>

                    <div class="form-group">
                        <label for="destino">Destino:</label>
                        <input type="text" id="destino" name="destino" placeholder="Ex: Porto" value="<?php echo $destino; ?>">
                    </div>

                    <div class="form-group">
                        <button type="submit" name="pesquisar" class="search-btn">Pesquisar</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($pesquisa_realizada): ?> <!-- Verifica se a pesquisa foi realizada -->
            <div class="results-container" id="resultsContainer"> <!-- Contentor dos resultados -->
                <?php if (empty($resultados)): ?> <!-- Se não houver resultados -->
                    <p class="no-results">Nenhum resultado encontrado.</p>
                <?php else: ?>
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
                                <?php foreach ($resultados as $rota): ?>
                                    <tr>
                                        <td><?php echo $rota['origem']; ?></td> <!-- Mostra a origem -->
                                        <td><?php echo $rota['destino']; ?></td> <!-- Mostra o destino -->
                                        <td><?php echo date('d/m/Y', strtotime($rota['data_viagem'])); ?></td> <!-- Formata e mostra a data -->
                                        <td><?php echo $rota['horario_partida']; ?></td> <!-- Mostra o horário -->
                                        <td><?php echo number_format($rota['preco'], 2, ',', '.') . ' €'; ?></td> <!-- Formata e mostra o preço em formato PT -->
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

    <!-- Alertas -->
    <div class="alertas-box">
        <div class="alertas-titulo">AVISOS IMPORTANTES</div>
        <?php if (empty($mensagens)): ?> <!-- Se não houver mensagens -->
            <div class="alerta-item">Nenhum aviso no momento.</div>
        <?php else: ?> <!-- Se houver mensagens -->
            <?php foreach ($mensagens as $mensagem): ?> <!-- Itera sobre as mensagens -->
                <div class="alerta-item">
                    <?php echo $mensagem['conteudo']; ?> <!-- Mostra o conteúdo da mensagem -->
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>
</body>
</html>
