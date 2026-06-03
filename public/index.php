<?php
/**
 * Front Controller — public/index.php
 *
 * Ponto de entrada único da aplicação.
 * Inicia sessão, gera token CSRF e despacha para o controller correto.
 */

session_start();

// Geração de token CSRF (uma vez por sessão)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Autoload simples (alternativa ao Composer para projetos pequenos)
spl_autoload_register(function (string $class) {
    $dirs = [
        __DIR__ . '/../Controllers/',
        __DIR__ . '/../Models/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// -----------------------------------------------------------------------
// Validação CSRF para todos os POST
// -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
        http_response_code(403);
        die('Token inválido. Recarregue a página e tente novamente.');
    }
}

// -----------------------------------------------------------------------
// Roteamento por ?acao=
// -----------------------------------------------------------------------
$acao = trim($_GET['acao'] ?? 'formulario');

// Ações que pertencem ao AgendamentoController
$acoesAgendamento = ['formulario', 'agendar', 'horarios_livres', 'cancelar'];

if (in_array($acao, $acoesAgendamento, true)) {
    $controller = new AgendamentoController();
    $controller->executar($acao);
    exit;
}

// Fallback — redireciona para o formulário padrão
header('Location: /barbearia-vip/public/index.php');
exit;
