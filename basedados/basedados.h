<?php
    $dbhost = 'localhost';
    $dbuser = 'root';
    $dbpass = '';
    $dbname = 'felixbus';

    // Criar conexão
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

    // Verificar conexão
    if (!$conn) {
        die('Falha técnica: ' . mysqli_connect_error());
    }
?>