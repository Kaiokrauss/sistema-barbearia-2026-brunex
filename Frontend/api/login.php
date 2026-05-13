<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

$identifier = strtolower(trim($input['identifier'] ?? ''));
$senha = $input['senha'] ?? '';

if (!$identifier || !$senha) {
    http_response_code(400);
    echo json_encode(['error' => 'Preencha e-mail/telefone e senha.']);
    exit;
}

$usersFile = __DIR__ . '/users.json';
if (!file_exists($usersFile)) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário ou senha inválidos.']);
    exit;
}

$users = json_decode(file_get_contents($usersFile), true);
if (!is_array($users)) {
    $users = [];
}

$foundUser = null;
foreach ($users as $user) {
    if ((isset($user['email']) && strtolower($user['email']) === $identifier) ||
        (isset($user['telefone']) && $user['telefone'] === $identifier)) {
        $foundUser = $user;
        break;
    }
}

if (!$foundUser || !password_verify($senha, $foundUser['senha'])) {
    http_response_code(401);
    echo json_encode(['error' => 'E-mail/telefone ou senha inválidos.']);
    exit;
}

$_SESSION['user'] = [
    'nome' => $foundUser['nome'],
    'email' => $foundUser['email'],
    'telefone' => $foundUser['telefone']
];

echo json_encode(['success' => 'Bem-vindo, ' . $foundUser['nome'] . '!']);
