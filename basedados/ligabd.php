<?php
/**
 * FelixBus - Módulo de Conexão à Base de Dados MySQL
 * Unidade Curricular: Linguagens de Programação para a Internet (LPI)
 * Autores: João Resina e Rafael Cruz (IPCB)
 */

$servidor   = getenv('DB_HOST') ?: "localhost";
$utilizador = getenv('DB_USER') ?: "root";
$password   = getenv('DB_PASS') ?: "";
$basedados  = getenv('DB_NAME') ?: "felixbus";
$porta      = getenv('DB_PORT') ?: 3306;

// Estabelecer ligação via MySQLi com suporte UTF-8
$conn = mysqli_connect($servidor, $utilizador, $password, $basedados, (int)$porta);

if (!$conn) {
    die("Erro ao ligar à Base de Dados MySQL: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
