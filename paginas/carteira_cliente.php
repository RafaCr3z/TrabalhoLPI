<?php
/*
session_start();
include '../basedados/basedados.h';

if (!isset($_SESSION["id_nivel"]) || $_SESSION["id_nivel"] != 3) {
    header("Location: erro.php");
    exit();
}

/**
 * Transfere saldo da carteira do cliente para a carteira da FelixBus
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $id_cliente ID do cliente
 * @param float $valor Valor a ser transferido
 * @param string $descricao Descrição da transação
 * @param int $id_rota ID da rota (opcional)
 * @return bool True se a transferência foi bem-sucedida, False caso contrário
 */
function transferirSaldoParaFelixBus($conn, $id_cliente, $valor, $descricao, $id_rota = null) {
    // Obter ID da carteira FelixBus
    $sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
    $result_felixbus = mysqli_query($conn, $sql_felixbus);
    $row_felixbus = mysqli_fetch_assoc($result_felixbus);
    $id_carteira_felixbus = $row_felixbus['id'];
    
    // Verificar saldo do cliente
    $sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
    $result_saldo = mysqli_query($conn, $sql_saldo);
    
    // Se o cliente não tiver carteira, retornar falso
    if (mysqli_num_rows($result_saldo) == 0) {
        return false;
    }
    
    $row_saldo = mysqli_fetch_assoc($result_saldo);
    
    // Verificar se há saldo suficiente
    if ($row_saldo['saldo'] < $valor) {
        return false;
    }
    
    // Iniciar transação
    mysqli_begin_transaction($conn);
    
    try {
        // Deduzir saldo do cliente
        $sql_deduzir = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
        if (!mysqli_query($conn, $sql_deduzir)) {
            throw new Exception("Erro ao deduzir saldo do cliente: " . mysqli_error($conn));
        }
        
        // Adicionar saldo à FelixBus
        $sql_adicionar = "UPDATE carteira_felixbus SET saldo = saldo + $valor WHERE id = $id_carteira_felixbus";
        if (!mysqli_query($conn, $sql_adicionar)) {
            throw new Exception("Erro ao adicionar saldo à FelixBus: " . mysqli_error($conn));
        }
        
        // Registrar transação
        $info_rota = $id_rota ? " (Rota ID: $id_rota)" : "";
        $descricao_completa = $descricao . $info_rota;
        
        $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao) 
                          VALUES ($id_cliente, $id_carteira_felixbus, $valor, 'compra', '$descricao_completa')";
        if (!mysqli_query($conn, $sql_transacao)) {
            throw new Exception("Erro ao registrar transação: " . mysqli_error($conn));
        }
        
        // Confirmar transação
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        mysqli_rollback($conn);
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Verifica se o cliente tem saldo suficiente para uma compra
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $id_cliente ID do cliente
 * @param float $valor Valor necessário
 * @return bool True se o cliente tem saldo suficiente, False caso contrário
 */
function verificarSaldoSuficiente($conn, $id_cliente, $valor) {
    $sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
    $result_saldo = mysqli_query($conn, $sql_saldo);
    
    if (mysqli_num_rows($result_saldo) == 0) {
        return false;
    }
    
    $row_saldo = mysqli_fetch_assoc($result_saldo);
    return ($row_saldo['saldo'] >= $valor);
}

/**
 * Obtém o saldo atual do cliente
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param int $id_cliente ID do cliente
 * @return float|false Saldo do cliente ou false se não encontrado
 */
function obterSaldoCliente($conn, $id_cliente) {
    $sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
    $result_saldo = mysqli_query($conn, $sql_saldo);
    
    if (mysqli_num_rows($result_saldo) == 0) {
        return false;
    }
    
    $row_saldo = mysqli_fetch_assoc($result_saldo);
    return $row_saldo['saldo'];
}

// Código principal da página
$id_cliente = $_SESSION["id_utilizador"];
$operacao_realizada = false; // Variável de controlo
$mensagem = ""; // Mensagem de feedback

// Obter ID da carteira FelixBus
$sql_felixbus = "SELECT id FROM carteira_felixbus LIMIT 1";
$result_felixbus = mysqli_query($conn, $sql_felixbus);
$row_felixbus = mysqli_fetch_assoc($result_felixbus);
$id_carteira_felixbus = $row_felixbus['id'];

// Exibe o saldo atual
$sql_saldo = "SELECT saldo FROM carteiras WHERE id_cliente = $id_cliente";
$result_saldo = mysqli_query($conn, $sql_saldo);

// Se o cliente não tiver carteira, criar uma
if (mysqli_num_rows($result_saldo) == 0) {
    $sql_criar_carteira = "INSERT INTO carteiras (id_cliente, saldo) VALUES ($id_cliente, 0.00)";
    mysqli_query($conn, $sql_criar_carteira);
    $result_saldo = mysqli_query($conn, $sql_saldo);
}

$row_saldo = mysqli_fetch_assoc($result_saldo);

// Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor = floatval($_POST["valor"]);
    $operacao = $_POST["operacao"];
    $descricao = isset($_POST["descricao"]) ? mysqli_real_escape_string($conn, $_POST["descricao"]) : "";

    if ($valor <= 0) {
        $mensagem = "<div class='alert alert-danger'>Valor inválido. Por favor, insira um valor positivo.</div>";
    } else {
        // Iniciar transação para garantir integridade dos dados
        mysqli_begin_transaction($conn);
        
        try {
            if ($operacao == "adicionar") {
                // Atualizar saldo do cliente
                $sql_atualiza = "UPDATE carteiras SET saldo = saldo + $valor WHERE id_cliente = $id_cliente";
                mysqli_query($conn, $sql_atualiza);
                
                // Registrar transação
                $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao) 
                                  VALUES ($id_cliente, $id_carteira_felixbus, $valor, 'deposito', 'Depósito de saldo na carteira')";
                mysqli_query($conn, $sql_transacao);
                
                $mensagem = "<div class='alert alert-success'>Saldo adicionado com sucesso!</div>";
            } 
            else if ($operacao == "retirar") {
                // Verificar se há saldo suficiente
                if ($row_saldo['saldo'] >= $valor) {
                    // Atualizar saldo do cliente
                    $sql_atualiza = "UPDATE carteiras SET saldo = saldo - $valor WHERE id_cliente = $id_cliente";
                    mysqli_query($conn, $sql_atualiza);
                    
                    // Registrar transação
                    $sql_transacao = "INSERT INTO transacoes (id_cliente, id_carteira_felixbus, valor, tipo, descricao) 
                                      VALUES ($id_cliente, $id_carteira_felixbus, $valor, 'retirada', 'Retirada de saldo da carteira')";
                    mysqli_query($conn, $sql_transacao);
                    
                    $mensagem = "<div class='alert alert-success'>Saldo retirado com sucesso!</div>";
                } else {
                    throw new Exception("Saldo insuficiente para realizar esta operação.");
                }
            }
            
            // Confirmar transação
            mysqli_commit($conn);
            $operacao_realizada = true;
            
            // Atualizar saldo após a operação
            $result_saldo = mysqli_query($conn, $sql_saldo);
            $row_saldo = mysqli_fetch_assoc($result_saldo);
            
        } catch (Exception $e) {
            // Reverter transação em caso de erro
            mysqli_rollback($conn);
            $mensagem = "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
    }
}

// Obter histórico de transações
$sql_historico = "SELECT t.id, t.valor, t.tipo, t.data_transacao, t.descricao 
                 FROM transacoes t 
                 WHERE t.id_cliente = $id_cliente 
                 ORDER BY t.data_transacao DESC 
                 LIMIT 10";
$result_historico = mysqli_query($conn, $sql_historico);

?>

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
            <div class="link"> <a href="perfil_cliente.php">Perfil</a></div>
            <div class="link"> <a href="pg_cliente.php">Página Inicial</a></div>
            <div class="link"> <a href="bilhetes_cliente.php">Bilhetes</a></div>
            <div class="link"> <a href="carteira_cliente.php">Carteira</a></div>
        </div>
        <div class="buttons">
            <div class="btn"><a href="logout.php"><button>Logout</button></a></div>
        </div>
    </nav>

    <section>
        <div class="carteira-container">
            <h2>Carteira</h2>
            
            <p>Esta página está temporariamente em manutenção.</p>
            
            <p>Por favor, tente novamente mais tarde.</p>
        </div>
    </section>
</body>
</html>
