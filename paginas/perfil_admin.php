<?php
session_start();
include '../basedados/basedados.h';

// Verificar se o usuário é administrador
if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 1) {
    header("Location: erro.php");
    exit();
}

$id_utilizador = $_SESSION["id_utilizador"];
$mensagem = '';
$tipo_mensagem = '';

// Buscar dados do utilizador
$sql = "SELECT nome, email, telemovel, morada FROM utilizadores WHERE id = $id_utilizador";
$resultado = mysqli_query($conn, $sql);

if (!$resultado || mysqli_num_rows($resultado) == 0) {
    die("Erro ao buscar dados do utilizador.");
}

$dados = mysqli_fetch_assoc($resultado);

// Processar formulário de atualização
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telemovel = mysqli_real_escape_string($conn, $_POST['telemovel']);
    $morada = mysqli_real_escape_string($conn, $_POST['morada']);

    // Verificar se o email já existe (exceto para o próprio utilizador)
    $sql_check = "SELECT * FROM utilizadores WHERE email = '$email' AND id != $id_utilizador";
    $result_check = mysqli_query($conn, $sql_check);

    if (mysqli_num_rows($result_check) > 0) {
        $mensagem = "Este email já está em uso por outro utilizador.";
        $tipo_mensagem = "error";
    } else {
        $sql_update = "UPDATE utilizadores SET email = '$email', telemovel = '$telemovel', morada = '$morada' WHERE id = $id_utilizador";

        if (mysqli_query($conn, $sql_update)) {
            $mensagem = "Dados atualizados com sucesso!";
            $tipo_mensagem = "success";

            // Atualizar dados na sessão
            $dados['email'] = $email;
            $dados['telemovel'] = $telemovel;
            $dados['morada'] = $morada;
        } else {
            $mensagem = "Erro ao atualizar dados: " . mysqli_error($conn);
            $tipo_mensagem = "error";
        }
    }
}

// Processar alteração de senha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Verificar se a nova senha e a confirmação são iguais
    if ($nova_senha != $confirmar_senha) {
        $mensagem = "A nova senha e a confirmação não coincidem.";
        $tipo_mensagem = "error";
    } else {
        // Verificar se a senha atual está correta
        $sql_senha = "SELECT pwd FROM utilizadores WHERE id = $id_utilizador";
        $result_senha = mysqli_query($conn, $sql_senha);
        $row_senha = mysqli_fetch_assoc($result_senha);

        if (password_verify($senha_atual, $row_senha['pwd']) || $senha_atual == $row_senha['pwd']) {
            // Gerar hash da nova senha
            $hashed_pwd = password_hash($nova_senha, PASSWORD_DEFAULT);

            $sql_update = "UPDATE utilizadores SET pwd = '$hashed_pwd' WHERE id = $id_utilizador";

            if (mysqli_query($conn, $sql_update)) {
                $mensagem = "Senha alterada com sucesso!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "Erro ao alterar senha: " . mysqli_error($conn);
                $tipo_mensagem = "error";
            }
        } else {
            $mensagem = "Senha atual incorreta.";
            $tipo_mensagem = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="perfil_admin.css">
    <title>FelixBus - Meu Perfil</title>
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
            <div class="link"> <a href="gerir_utilizadores.php">Utilizadores</a></div>
            <div class="link"> <a href="auditoria_transacoes.php">Auditoria</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
            <div class="btn-admin" style="color: white !important; font-weight: 600;">Área de Administrador</div>
        </div>
    </nav>

    <section>
        <h1>Meu Perfil</h1>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem == 'success' ? 'success' : 'danger'; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-container">
                <h2>Dados Pessoais</h2>
                <form method="post" action="perfil_admin.php">
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($dados['nome']); ?>" readonly>
                        <small>O nome não pode ser alterado.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dados['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="telemovel">Telemóvel:</label>
                        <input type="text" id="telemovel" name="telemovel" value="<?php echo htmlspecialchars($dados['telemovel']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="morada">Morada:</label>
                        <textarea id="morada" name="morada" required><?php echo htmlspecialchars($dados['morada']); ?></textarea>
                    </div>

                    <button type="submit" name="atualizar">Atualizar Dados</button>
                </form>
            </div>

            <div class="form-container">
                <h2>Alterar Senha</h2>
                <form method="post" action="perfil_admin.php">
                    <div class="form-group">
                        <label for="senha_atual">Senha Atual:</label>
                        <input type="password" id="senha_atual" name="senha_atual" required>
                    </div>

                    <div class="form-group">
                        <label for="nova_senha">Nova Senha:</label>
                        <input type="password" id="nova_senha" name="nova_senha" required>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Nova Senha:</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>

                    <button type="submit" name="alterar_senha">Alterar Senha</button>
                </form>
            </div>
        </div>
    </section>
</body>
</html>

