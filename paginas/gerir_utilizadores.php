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

$mensagem = '';
$mostrar_inativos = isset($_GET['mostrar_inativos']) ? (int)$_GET['mostrar_inativos'] : 0;

// Adicionar utilizador
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    $user = mysqli_real_escape_string($conn, $_POST['user']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pwd = password_hash($_POST['pwd'], PASSWORD_DEFAULT);
    $telemovel = mysqli_real_escape_string($conn, $_POST['telemovel']);
    $morada = mysqli_real_escape_string($conn, $_POST['morada']);
    $tipo_perfil = intval($_POST['tipo_perfil']);

    if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM utilizadores WHERE user = '$user' OR email = '$email'")) > 0) {
        $mensagem = "Utilizador ou email já existe";
    } else {
        $sql = "INSERT INTO utilizadores (user, nome, email, pwd, telemovel, morada, tipo_perfil)
                VALUES ('$user', '$nome', '$email', '$pwd', '$telemovel', '$morada', $tipo_perfil)";

        if (mysqli_query($conn, $sql) && $tipo_perfil == 3) {
            mysqli_query($conn, "INSERT INTO carteiras (id_cliente, saldo) VALUES (" . mysqli_insert_id($conn) . ", 0.00)");
        }
    }
}

// Editar utilizador
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telemovel = mysqli_real_escape_string($conn, $_POST['telemovel']);
    $morada = mysqli_real_escape_string($conn, $_POST['morada']);

    $sql = "UPDATE utilizadores SET nome = '$nome', email = '$email', telemovel = '$telemovel', morada = '$morada'";
    if (!empty($_POST['pwd'])) {
        $sql .= ", pwd = '" . password_hash($_POST['pwd'], PASSWORD_DEFAULT) . "'";
    }
    mysqli_query($conn, $sql . " WHERE id = $id");
}

// Alterar estado
if (isset($_GET['alterar_estado']) && isset($_GET['id']) && $_GET['id'] != $_SESSION['id_utilizador']) {
    $id = intval($_GET['id']);
    $ativo = intval($_GET['ativo']);
    mysqli_query($conn, "UPDATE utilizadores SET ativo = $ativo WHERE id = $id");
}

// Alterar perfil
if (isset($_GET['alterar_perfil']) && isset($_GET['id']) && isset($_GET['perfil'])) {
    $id = intval($_GET['id']);
    $novo_perfil = intval($_GET['perfil']);

    // Se mudar para cliente (perfil 3), criar carteira
    if ($novo_perfil == 3) {
        mysqli_query($conn, "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id, 0.00)");
    }

    mysqli_query($conn, "UPDATE utilizadores SET tipo_perfil = $novo_perfil WHERE id = $id");
}

// Buscar utilizadores
$sql = "SELECT u.*, p.descricao as perfil_nome
        FROM utilizadores u
        JOIN perfis p ON u.tipo_perfil = p.id";
if (!$mostrar_inativos) {
    $sql .= " WHERE u.ativo = 1";
}
$sql .= " ORDER BY u.id ASC";
$utilizadores = mysqli_query($conn, $sql);

// Buscar todos os perfis
$perfis = [];
$result_perfis = mysqli_query($conn, "SELECT * FROM perfis ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($result_perfis)) {
    $perfis[$row['id']] = $row['descricao'];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="gerir_utilizadores.css?v=<?php echo time(); ?>">
    <title>FelixBus - Gestão de Utilizadores</title>
</head>
<body>
    <nav>
        <div class="logo">
            <h1>Felix<span>Bus</span></h1>
        </div>
        <div class="links">
            <div class="link"> <a href="pg_admin.php">Página Inicial</a></div>
            <div class="link"> <a href="gerir_alertas.php">Alertas</a></div>
            <div class="link"> <a href="gerir_rotas.php">Rotas</a></div>
            <div class="link"> <a href="auditoria_transacoes.php">Auditoria</a></div>
            <div class="link"> <a href="perfil_admin.php">Meu Perfil</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin">Área de Administrador</div>
        </div>
    </nav>

    <section>
        <h1>Gestão de Utilizadores</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem == 'success' ? 'success' : 'danger'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-container">
                <h2>Adicionar Novo Utilizador</h2>
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
                        <label for="pwd">Senha:</label>
                        <input type="password" id="pwd" name="pwd" required>
                    </div>

                    <div class="form-group">
                        <label for="telemovel">Telemóvel:</label>
                        <input type="text" id="telemovel" name="telemovel" required>
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

            <div class="table-container">
                <h2>Utilizadores Adicionados</h2>
                <div class="filtro-container">
                    <a href="?mostrar_inativos=0" class="filtro-btn <?php echo !$mostrar_inativos ? 'active' : ''; ?>">Utilizadores Ativos</a>
                    <a href="?mostrar_inativos=1" class="filtro-btn <?php echo $mostrar_inativos ? 'active' : ''; ?>">Todos os Utilizadores</a>
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
                            <tr class="<?php echo isset($utilizador['ativo']) && !$utilizador['ativo'] ? 'utilizador-inativo' : ''; ?>">
                                <td><?php echo $utilizador['id']; ?></td>
                                <td><?php echo htmlspecialchars($utilizador['user']); ?></td>
                                <td><?php echo htmlspecialchars($utilizador['nome']); ?></td>
                                <td><?php echo htmlspecialchars($utilizador['email']); ?></td>
                                <td><?php echo htmlspecialchars($utilizador['telemovel']); ?></td>
                                <td><?php echo htmlspecialchars($utilizador['morada']); ?></td>
                                <td>
                                    <span class="perfil-badge perfil-<?php echo $utilizador['tipo_perfil']; ?>">
                                        <?php echo ucfirst($utilizador['perfil_nome']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?php echo $utilizador['ativo'] ? 'ativo' : 'inativo'; ?>">
                                        <?php echo $utilizador['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acoes-container">
                                        <button class="acao-btn editar-btn" onclick="abrirModalEditar(<?php echo $utilizador['id']; ?>, '<?php echo addslashes($utilizador['nome']); ?>', '<?php echo addslashes($utilizador['email']); ?>', '<?php echo addslashes($utilizador['telemovel']); ?>', '<?php echo addslashes($utilizador['morada']); ?>')">Editar</button>

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

                                        <?php if ($utilizador['id'] != $_SESSION['id_utilizador']): ?>
                                            <?php if (isset($utilizador['ativo']) && $utilizador['ativo']): ?>
                                                <a href="?alterar_estado=1&id=<?php echo $utilizador['id']; ?>&ativo=0&mostrar_inativos=<?php echo $mostrar_inativos; ?>" class="acao-btn inativar-btn" onclick="return confirm('Tem certeza que deseja inativar este utilizador?')">Inativar</a>
                                            <?php else: ?>
                                                <a href="?alterar_estado=1&id=<?php echo $utilizador['id']; ?>&ativo=1&mostrar_inativos=<?php echo $mostrar_inativos; ?>" class="acao-btn ativar-btn">Ativar</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($utilizadores) == 0): ?>
                            <tr>
                                <td colspan="8" class="no-results">Nenhum utilizador encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Modal de Edição -->
    <div id="modal-editar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalEditar()">&times;</span>
            <h2>Editar Utilizador</h2>
            <form method="post" action="gerir_utilizadores.php?mostrar_inativos=<?php echo $mostrar_inativos; ?>">
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
                    <label for="edit-pwd">Nova Senha (deixe em branco para manter a atual):</label>
                    <input type="password" id="edit-pwd" name="pwd">
                </div>

                <div class="form-group">
                    <label for="edit-telemovel">Telemóvel:</label>
                    <input type="text" id="edit-telemovel" name="telemovel" required>
                </div>

                <div class="form-group">
                    <label for="edit-morada">Morada:</label>
                    <textarea id="edit-morada" name="morada" required></textarea>
                </div>

                <button type="submit" name="editar">Atualizar Utilizador</button>
            </form>
        </div>
    </div>

    <script>
        // Funções para o modal de edição
        function abrirModalEditar(id, nome, email, telemovel, morada) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nome').value = nome;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-telemovel').value = telemovel;
            document.getElementById('edit-morada').value = morada;
            document.getElementById('edit-pwd').value = '';

            document.getElementById('modal-editar').style.display = 'block';
        }

        function fecharModalEditar() {
            document.getElementById('modal-editar').style.display = 'none';
        }

        // Fechar o modal se o utilizador clicar fora dele
        window.onclick = function(event) {
            var modal = document.getElementById('modal-editar');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>




