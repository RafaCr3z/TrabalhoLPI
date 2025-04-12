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
                    <p class="no-results">Nenhum resultado encontrado.</p>
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

    <!-- Alertas -->
    <div class="alertas-box">
        <div class="alertas-titulo">AVISOS IMPORTANTES</div>
        <?php if (empty($mensagens)): ?>
            <div class="alerta-item">Nenhum aviso no momento.</div>
        <?php else: ?>
            <?php foreach ($mensagens as $mensagem): ?>
                <div class="alerta-item">
                    <?php echo htmlspecialchars($mensagem['conteudo']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Função para reposicionar os alertas
        function adjustAlertPosition() {
            const alertas = document.querySelector('.alertas');
            if (alertas) {
                const nav = document.querySelector('nav');
                const navHeight = nav ? nav.offsetHeight : 0;
                alertas.style.top = (navHeight + 20) + 'px';
            }
        }

        // Ajusta a posição inicial e quando a janela é redimensionada
        adjustAlertPosition();
        window.addEventListener('resize', adjustAlertPosition);
    });
    </script>
</body>
</html>
