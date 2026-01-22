<?php

$host = 'localhost'; // Endereço do servidor MySQL
$user = 'root'; // Nome de utilizador do MySQL
$pass = ''; // Palavra-passe do MySQL
$dbname = 'kroosproject'; // Nome da base de dados

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Falha na conexão: " . mysqli_connect_error());
}

?>