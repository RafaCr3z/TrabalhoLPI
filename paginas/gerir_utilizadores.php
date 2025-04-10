<?php
session_start();
include '../basedados/basedados.h';

// Verificar se o usuário é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';

// Buscar todos os perfis
$sql_perfis = "SELECT * FROM perfis ORDER BY id";
$result_perfis = mysqli_query($conn, $sql_perfis);
$perfis = [];
while ($perfil = mysqli_fetch_assoc($result_perfis)) {
    $perfis[$perfil['id']] = $perfil['descricao'];
}

// Adicionar novo utilizador
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionar'])) {
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pwd = $_POST['pwd'];
    $telemovel = mysqli_real_escape_string($conn, $_POST['telemovel']);
    $morada = mysqli_real_escape_string($conn, $_POST['morada']);
    $tipo_perfil = intval($_POST['tipo_perfil']);
    
    // Verificar se o nome ou email já existem
    $sql_check = "SELECT * FROM utilizadores WHERE nome = '$nome' OR email = '$email'";
    $result_check = mysqli_query($conn, $sql_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        $mensagem = "Nome de utilizador ou email já existem no sistema.";
        $tipo_mensagem = "error";
    } else {
        // Gerar hash da senha
        $hashed_pwd = password_hash($pwd, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO utilizadores (nome, email, pwd, telemovel, morada, tipo_perfil) 
                VALUES ('$nome', '$email', '$hashed_pwd', '$telemovel', '$morada', $tipo_perfil)";
        
        if (mysqli_query($conn, $sql)) {
            $mensagem = "Utilizador adicionado com sucesso!";
            $tipo_mensagem = "success";
            
            // Se for cliente, criar carteira automaticamente
            if ($tipo_perfil == 3) {
                $id_cliente = mysqli_insert_id($conn);
                $sql_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
                mysqli_query($conn, $sql_carteira);
            }
        } else {
            $mensagem = "Erro ao adicionar utilizador: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
    }
}

// Alterar tipo de perfil
if (isset($_GET['alterar_perfil']) && isset($_GET['id']) && isset($_GET['perfil'])) {
    $id = intval($_GET['id']);
    $perfil = intval($_GET['perfil']);
    
    // Não permitir alterar o próprio perfil
    if ($id == $_SESSION['id_utilizador']) {
        $mensagem = "Não é possível alterar o seu próprio perfil.";
        $tipo_mensagem = "error";
    } else {
        $sql = "UPDATE utilizadores SET tipo_perfil = $perfil WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            $mensagem = "Perfil do utilizador alterado com sucesso!";
            $tipo_mensagem = "success";
            
            // Se for alterado para cliente e não tiver carteira, criar uma
            if ($perfil == 3) {
                $sql_check_carteira = "SELECT * FROM carteiras WHERE id_cliente = $id";
                $result_check_carteira = mysqli_query($conn, $sql_check_carteira);
                
                if (mysqli_num_rows($result_check_carteira) == 0) {
                    $sql_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id, 0.00)";
                    mysqli_query($conn, $sql_carteira);
                }
            }
        } else {
            $mensagem = "Erro ao alterar perfil: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
    }
}

// Buscar todos os utilizadores
$sql_utilizadores = "SELECT u.*, p.descricao as perfil_nome 
                    FROM utilizadores u 
                    JOIN perfis p ON u.tipo_perfil = p.id 
                    ORDER BY u.id";
$result_utilizadores = mysqli_query($conn, $sql_utilizadores);
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
                        <label for="nome">Nome:</label>
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
                <h2>Utilizadores Cadastrados</h2>
                <table class="utilizadores-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telemóvel</th>
                            <th>Perfil</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($utilizador = mysqli_fetch_assoc($result_utilizadores)): ?>
                            <tr>
                                <td><?php echo $utilizador['id']; ?></td>
                                <td><?php echo htmlspecialchars($utilizador['nome']); ?></td>
                                <td><?php echo htmlspecialchars($utilizador['email']); ?></td>
                                <td><?php echo htmlspecialchars($utilizador['telemovel']); ?></td>
                                <td>
                                    <span class="perfil-badge perfil-<?php echo $utilizador['tipo_perfil']; ?>">
                                        <?php echo ucfirst($utilizador['perfil_nome']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="acoes-dropdown">
                                        <button class="dropdown-btn">Alterar Perfil</button>
                                        <div class="dropdown-content">
                                            <?php foreach ($perfis as $id => $descricao): ?>
                                                <?php if ($id != $utilizador['tipo_perfil']): ?>
                                                    <a href="?alterar_perfil=1&id=<?php echo $utilizador['id']; ?>&perfil=<?php echo $id; ?>">
                                                        <?php echo ucfirst($descricao); ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($result_utilizadores) == 0): ?>
                            <tr>
                                <td colspan="6" class="no-results">Nenhum utilizador cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</body>
</html>
