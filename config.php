<?php
/**
 * Ficheiro de Configuração da Base de Dados
 * KroosProject - Gestão de Clubes e Jogadores
 */

// Configuração da Base de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kroosproject');
define('DB_PORT', 3306);

// Criar conexão
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Verificar conexão
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Definir charset para UTF-8
$conn->set_charset("utf8mb4");

// Variável global para uso em todo o projeto
// $conn está disponível em todos os ficheiros que incluem este ficheiro
?>
