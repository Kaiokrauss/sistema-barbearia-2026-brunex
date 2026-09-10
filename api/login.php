<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/UsuarioFactory.php';

// Verificação de sessão via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['user'])) {
        echo json_encode([
            'logged' => true,
            'user' => $_SESSION['user']
        ]);
    } else {
        echo json_encode([
            'logged' => false
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$identifier = trim($input['identifier'] ?? '');
$senha = $input['senha'] ?? '';

if (!$identifier || !$senha) {
    http_response_code(400);
    echo json_encode(['error' => 'Preencha seu e-mail, telefone ou usuário e a senha.']);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();

    // Apenas dígitos caso tenha digitado telefone com máscara (ex: (18) 99658-2492)
    $cleanPhone = preg_replace('/\D/', '', $identifier);

    // Buscar usuário por email, telefone (exato ou sem formatação) ou nome de usuário
    $phoneCond = "";
    if ($cleanPhone !== '') {
        $phoneCond = " OR REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = :cleanPhone";
    }

    $sql = "SELECT id, nome, email, telefone, senha, perfil, ativo FROM usuarios 
            WHERE (
                LOWER(email) = LOWER(:identifier) 
                OR LOWER(nome) = LOWER(:identifier)
                OR telefone = :identifier
                {$phoneCond}
            ) AND (ativo = 1 OR ativo IS NULL) LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':identifier', $identifier);
    if ($cleanPhone !== '') {
        $stmt->bindValue(':cleanPhone', $cleanPhone);
    }
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($senha, $user['senha'])) {
        http_response_code(401);
        echo json_encode(['error' => 'E-mail, telefone ou senha inválidos.']);
        exit;
    }

    // Usuário autenticado
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'nome' => $user['nome'],
        'email' => $user['email'],
        'telefone' => $user['telefone'],
        'perfil' => strtolower($user['perfil'])
    ];

    // Obter redirecionamento via Factory
    $redirect = null;
    try {
        $usuarioObj = UsuarioFactory::criarUsuario($user['perfil']);
        $redirect = $usuarioObj->getPainelRedirecionamento();
    } catch (Exception $e) {
        $redirect = 'Frontend/dashboard.php';
    }

    echo json_encode([
        'success' => 'Login realizado com sucesso!',
        'user' => $_SESSION['user'],
        'redirect' => $redirect
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao acessar o banco de dados.']);
}
?>