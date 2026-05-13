<?php
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

$nome = trim($input['nome'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$telefone = trim($input['telefone'] ?? '');
$senha = $input['senha'] ?? '';
$senha2 = $input['senha2'] ?? '';

if (!$nome || !$email || !$telefone || !$senha || !$senha2) {
    http_response_code(400);
    echo json_encode(['error' => 'Preencha todos os campos.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'E-mail inválido.']);
    exit;
}

if (strlen($senha) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'A senha precisa ter pelo menos 8 caracteres.']);
    exit;
}

if ($senha !== $senha2) {
    http_response_code(400);
    echo json_encode(['error' => 'As senhas não coincidem.']);
    exit;
}

$usersFile = __DIR__ . '/users.json';
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}

$users = json_decode(file_get_contents($usersFile), true);
if (!is_array($users)) {
    $users = [];
}

foreach ($users as $user) {
    if (isset($user['email']) && strtolower($user['email']) === $email) {
        http_response_code(409);
        echo json_encode(['error' => 'Já existe uma conta com esse e-mail.']);
        exit;
    }
    if (isset($user['telefone']) && $user['telefone'] === $telefone) {
        http_response_code(409);
        echo json_encode(['error' => 'Já existe uma conta com esse telefone.']);
        exit;
    }
}

$users[] = [
    'nome' => $nome,
    'email' => $email,
    'telefone' => $telefone,
    'senha' => password_hash($senha, PASSWORD_DEFAULT),
    'createdAt' => date('c')
];

file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => 'Conta criada com sucesso!']);
