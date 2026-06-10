<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/UsuarioFactory.php';

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

try {
    $dbObj = new Database();
    $conn = $dbObj->getConnection();

    // Buscar usuário por email ou telefone
    $sql = "SELECT id, nome, email, telefone, senha, perfil FROM usuarios 
            WHERE (email = :identifier OR telefone = :identifier) AND ativo = 1 LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':identifier', $identifier);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($senha, $user['senha'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Email/telefone ou senha inválidos.']);
        exit;
    }

    // Usuário autenticado
    $_SESSION['user'] = [
        'id' => $user['id'],
        'nome' => $user['nome'],
        'email' => $user['email'],
        'telefone' => $user['telefone'],
        'perfil' => $user['perfil']
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