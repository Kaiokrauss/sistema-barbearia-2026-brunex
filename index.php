<?php
session_start();
// Se o usuário já estiver logado, redireciona para o dashboard
if (isset($_SESSION['user'])) {
    header('Location: Frontend/dashboard.php');
    exit;
}
// Caso contrário, vai para a página inicial pública
header('Location: Frontend/index.html');
exit;
?>

