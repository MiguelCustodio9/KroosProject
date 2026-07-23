<?php
/**
 * Logout - Termina a sessão do utilizador
 */

session_start();
session_unset();
// Destruir a sessão
session_destroy();

// Redirecionar para o login
header('Location: login.php');
exit();
?>
