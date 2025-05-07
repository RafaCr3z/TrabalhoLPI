<?php
session_start();
include '../basedados/basedados.h';

// Verificar se é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Verificar campo 'ativo'
if (mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM utilizadores LIKE 'ativo'")) == 0) {
    mysqli_query($conn, "ALTER TABLE utilizadores ADD ativo TINYINT(1) NOT NULL DEFAULT 1");
}

// Inicializar variáveis para mensagens de feedback
$mensagem = '';
$tipo_mensagem = '';
// Verificar se deve mostrar utilizadores inativos (parâmetro do URL)
$mostrar_inativos = isset($_GET['mostrar_inativos']) ? (int)$_GET['mostrar_inativos'] : 0;

// Processar formulário de adição de novo utilizador
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    // Capturar e sanitizar dados do formulário
    $user = mysqli_real_escape_string($conn, $_POST['user']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pwd = password_hash($_POST['pwd'], PASSWORD_DEFAULT); // Hash da palavra-passe para segurança
    $telemovel = mysqli_real_escape_string($conn, $_POST['telemovel']);
    $morada = mysqli_real_escape_string($conn, $_POST['morada']);
    $tipo_perfil = intval($_POST['tipo_perfil']);

    // Verificar se o utilizador ou email já existem no sistema
    if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM utilizadores WHERE user = '$user' OR email = '$email'")) > 0) {
        $mensagem = "Utilizador ou email já existe";
        $tipo_mensagem = "danger";
    } else {
        // Inserir novo utilizador na base de dados
        $sql = "INSERT INTO utilizadores (user, nome, email, pwd, telemovel, morada, tipo_perfil)
                VALUES ('$user', '$nome', '$email', '$pwd', '$telemovel', '$morada', $tipo_perfil)";

        if (mysqli_query($conn, $sql)) {
            $id_novo = mysqli_insert_id($conn);
            // Se for cliente (tipo_perfil 3), criar carteira com saldo inicial 0
            if ($tipo_perfil == 3) {
                mysqli_query($conn, "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_novo, 0.00)");
            }
            $mensagem = "Utilizador adicionado com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao adicionar utilizador: " . mysqli_error($conn);
            $tipo_mensagem = "danger";
        }
    }
}

// Processar formulário de edição de utilizador existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    // Capturar e sanitizar dados do formulário
    $id = intval($_POST['id']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telemovel = mysqli_real_escape_string($conn, $_POST['telemovel']);
    $morada = mysqli_real_escape_string($conn, $_POST['morada']);

    // Construir consulta de atualização
    $sql = "UPDATE utilizadores SET nome = '$nome', email = '$email', telemovel = '$telemovel', morada = '$morada'";
    // Adicionar atualização de palavra-passe apenas se uma nova palavra-passe foi fornecida
    if (!empty($_POST['pwd'])) {
        $sql .= ", pwd = '" . password_hash($_POST['pwd'], PASSWORD_DEFAULT) . "'";
    }

    // Executar a atualização
    if (mysqli_query($conn, $sql . " WHERE id = $id")) {
        $mensagem = "Utilizador editado com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao editar utilizador: " . mysqli_error($conn);
        $tipo_mensagem = "danger";
    }
}

// Processar solicitação para alterar estado (ativar/inativar utilizador)
if (isset($_GET['alterar_estado']) && isset($_GET['id']) && $_GET['id'] != $_SESSION['id_utilizador']) {
    // Não permitir que o administrador desative a sua própria conta
    $id = intval($_GET['id']);
    $ativo = intval($_GET['ativo']);

    // Atualizar estado do utilizador
    if (mysqli_query($conn, "UPDATE utilizadores SET ativo = $ativo WHERE id = $id")) {
        $mensagem = "Utilizador " . ($ativo == 1 ? "ativado" : "desativado") . " com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao alterar estado do utilizador: " . mysqli_error($conn);
        $tipo_mensagem = "danger";
    }
}

// Processar solicitação para alterar perfil do utilizador
if (isset($_GET['alterar_perfil']) && isset($_GET['id']) && isset($_GET['perfil'])) {
    $id = intval($_GET['id']);
    $novo_perfil = intval($_GET['perfil']);
    
    // Se o novo perfil for cliente (3), criar carteira se ainda não existir
    if ($novo_perfil == 3) {
        mysqli_query($conn, "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id, 0.00)");
    }

    // Atualizar tipo de perfil do utilizador
    if (mysqli_query($conn, "UPDATE utilizadores SET tipo_perfil = $novo_perfil WHERE id = $id")) {
        $mensagem = "Perfil do utilizador alterado com sucesso!";
        $tipo_mensagem = "success";
    } else {
        $mensagem = "Erro ao alterar perfil do utilizador: " . mysqli_error($conn);
        $tipo_mensagem = "danger";
    }
}

// Buscar todos os perfis disponíveis no sistema
$perfis = [];
$result_perfis = mysqli_query($conn, "SELECT * FROM perfis ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($result_perfis)) {
    $perfis[$row['id']] = $row['descricao'];
}

// Construir consulta para buscar utilizadores com informações de perfil
$sql = "SELECT u.*, p.descricao as perfil_nome
        FROM utilizadores u
        JOIN perfis p ON u.tipo_perfil = p.id";
// Filtrar apenas utilizadores ativos se não estiver a mostrar inativos
if (!$mostrar_inativos) {
    $sql .= " WHERE u.ativo = 1";
}
$sql .= " ORDER BY u.id ASC";
$utilizadores = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_utilizadores.css">
    <title>FelixBus - Gestão de Utilizadores</title>
</head>
<body>
    <!-- Barra de navegação -->
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

    <!-- Conteúdo principal -->
    <section>
        <h1>Gestão de Utilizadores</h1>

        <!-- Exibir mensagens de feedback (sucesso/erro) -->
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <!-- Formulário para adicionar novo utilizador -->
            <div class="form-container">
                <h2>Adicionar Utilizador</h2>
                <form method="post" action="gerir_utilizadores.php">
                    <div class="form-group">
                        <label for="user">Nome de Utilizador:</label>
                        <input type="text" id="user" name="user" required>
                    </div>
                    <div class="form-group">
                        <label for="nome">Nome Completo:</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="pwd">Palavra-passe:</label>
                        <input type="password" id="pwd" name="pwd" required>
                    </div>
                    <div class="form-group">
                        <label for="telemovel">Telemóvel:</label>
                        <input type="text" id="telemovel" name="telemovel" required maxlength="9" minlength="9">
                    </div>
                    <div class="form-group">
                        <label for="morada">Morada:</label>
                        <textarea id="morada" name="morada" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tipo_perfil">Tipo de Perfil:</label>
                        <select id="tipo_perfil" name="tipo_perfil" required>
                            <?php foreach ($perfis as $id => $descricao): ?>
                                <option value="<?php echo $id; ?>"><?php echo ucfirst($descricao); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="adicionar">Adicionar Utilizador</button>
                </form>
            </div>

            <!-- Tabela de utilizadores existentes -->
            <div class="table-container">
                <h2>Utilizadores</h2>
                <!-- Filtros para mostrar utilizadores ativos/todos -->
                <div class="filtro-container">
                    <a href="?mostrar_inativos=0" class="filtro-btn <?php echo !$mostrar_inativos ? 'active' : ''; ?>">Ativos</a>
                    <a href="?mostrar_inativos=1" class="filtro-btn <?php echo $mostrar_inativos ? 'active' : ''; ?>">Todos</a>
                </div>

                <table class="utilizadores-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilizador</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telemóvel</th>
                            <th>Morada</th>
                            <th>Perfil</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($utilizador = mysqli_fetch_assoc($utilizadores)): ?>
                            <!-- Aplicar classe para estilizar utilizadores inativos -->
                            <tr class="<?php echo isset($utilizador['ativo']) && !$utilizador['ativo'] ? 'utilizador-inativo' : ''; ?>">
                                <td><?php echo $utilizador['id']; ?></td>
                                <td><?php echo $utilizador['user']; ?></td>
                                <td><?php echo $utilizador['nome']; ?></td>
                                <td><?php echo $utilizador['email']; ?></td>
                                <td><?php echo $utilizador['telemovel']; ?></td>
                                <td><?php echo $utilizador['morada']; ?></td>
                                <td>
                                    <!-- Distintivo para indicar o tipo de perfil com cor específica -->
                                    <span class="perfil-badge perfil-<?php echo $utilizador['tipo_perfil']; ?>">
                                        <?php echo ucfirst($utilizador['perfil_nome']); ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Distintivo para indicar o estado (ativo/inativo) -->
                                    <span class="estado-badge estado-<?php echo $utilizador['ativo'] ? 'ativo' : 'inativo'; ?>">
                                        <?php echo $utilizador['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Contentor para botões de ação -->
                                    <div class="acoes-container">
                                        <!-- Botão para abrir modal de edição -->
                                        <button class="acao-btn editar-btn" onclick="abrirModalEditar(<?php echo $utilizador['id']; ?>, '<?php echo addslashes($utilizador['nome']); ?>', '<?php echo addslashes($utilizador['email']); ?>', '<?php echo addslashes($utilizador['telemovel']); ?>', '<?php echo addslashes($utilizador['morada']); ?>')">Editar</button>

                                        <!-- Menu suspenso para alterar perfil -->
                                        <div class="acoes-dropdown">
                                            <button class="dropdown-btn">Alterar Perfil</button>
                                            <div class="dropdown-content">
                                                <?php foreach ($perfis as $id => $descricao): ?>
                                                    <?php if ($id != $utilizador['tipo_perfil']): ?>
                                                        <a href="?alterar_perfil=1&id=<?php echo $utilizador['id']; ?>&perfil=<?php echo $id; ?>&mostrar_inativos=<?php echo $mostrar_inativos; ?>">
                                                            <?php echo ucfirst($descricao); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <!-- Botões para ativar/inativar (não mostrar para o próprio utilizador) -->
                                        <?php if ($utilizador['id'] != $_SESSION['id_utilizador']): ?>
                                            <?php if (isset($utilizador['ativo']) && $utilizador['ativo']): ?>
                                                <a href="?alterar_estado=1&id=<?php echo $utilizador['id']; ?>&ativo=0&mostrar_inativos=<?php echo $mostrar_inativos; ?>" class="acao-btn inativar-btn" onclick="return confirm('Tem a certeza que deseja inativar este utilizador?')">Inativar</a>
                                            <?php else: ?>
                                                <a href="?alterar_estado=1&id=<?php echo $utilizador['id']; ?>&ativo=1&mostrar_inativos=<?php echo $mostrar_inativos; ?>" class="acao-btn ativar-btn">Ativar</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($utilizadores) == 0): ?>
                            <!-- Mensagem quando não há utilizadores -->
                            <tr><td colspan="9" class="no-results">Nenhum utilizador encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Modal para edição de utilizador -->
    <div id="modal-editar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalEditar()">&times;</span>
            <h2>Editar Utilizador</h2>
            <form method="post" action="gerir_utilizadores.php?mostrar_inativos=<?php echo $mostrar_inativos; ?>">
                <!-- Campo oculto para ID do utilizador -->
                <input type="hidden" id="edit-id" name="id">
                <div class="form-group">
                    <label for="edit-nome">Nome Completo:</label>
                    <input type="text" id="edit-nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="edit-email">Email:</label>
                    <input type="email" id="edit-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit-pwd">Nova Palavra-passe:</label>
                    <input type="password" id="edit-pwd" name="pwd">
                    <small>Deixe em branco para manter a palavra-passe atual</small>
                </div>
                <div class="form-group">
                    <label for="edit-telemovel">Telemóvel:</label>
                    <input type="text" id="edit-telemovel" name="telemovel" required maxlength="9" minlength="9">
                </div>
                <div class="form-group">
                    <label for="edit-morada">Morada:</label>
                    <textarea id="edit-morada" name="morada" required></textarea>
                </div>
                <button type="submit" name="editar">Atualizar</button>
            </form>
        </div>
    </div>

    <!-- Rodapé da página -->
    <footer>
        © <?php echo date("Y"); ?> <img src="estcb.png" alt="ESTCB"> <span>João Resina & Rafael Cruz</span>
    </footer>

    <!-- Scripts JavaScript -->
    <script>
        // Função para abrir o modal de edição e preencher com dados do utilizador
        function abrirModalEditar(id, nome, email, telemovel, morada) {
            // Preencher campos do formulário com dados do utilizador
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nome').value = nome;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-telemovel').value = telemovel;
            document.getElementById('edit-morada').value = morada;
            document.getElementById('edit-pwd').value = ''; // Limpar campo de senha
            // Exibir o modal
            document.getElementById('modal-editar').style.display = 'block';
        }

        // Função para fechar o modal de edição
        function fecharModalEditar() {
            document.getElementById('modal-editar').style.display = 'none';
        }

        // Função para fechar o modal ao clicar fora dele
        window.onclick = function(event) {
            var modal = document.getElementById('modal-editar');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>



