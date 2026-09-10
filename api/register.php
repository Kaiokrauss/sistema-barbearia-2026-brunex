<?php
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

$nome = trim($input['nome'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$telefone = trim($input['telefone'] ?? '');
$senha = $input['senha'] ?? '';
$senha2 = $input['senha2'] ?? '';
$perfil = trim($input['perfil'] ?? 'cliente');

// Debug: log para verificar o valor recebido
error_log("DEBUG: perfil recebido = '" . $perfil . "'");
error_log("DEBUG: perfil trim = '" . $perfil . "'");
error_log("DEBUG: array validação = " . json_encode(['cliente', 'barbeiro', 'admin']));

// Validações
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

if (strlen($senha) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'A senha precisa ter pelo menos 6 caracteres.']);
    exit;
}

if ($senha !== $senha2) {
    http_response_code(400);
    echo json_encode(['error' => 'As senhas não coincidem.']);
    exit;
}

// Validar perfil
if (!in_array($perfil, ['cliente', 'barbeiro', 'admin'])) {
    error_log("DEBUG: Perfil inválido detectado. Perfil = '" . $perfil . "'");
    http_response_code(400);
    echo json_encode(['error' => 'Perfil inválido: ' . $perfil]);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();

    // Verificar se email ou telefone já existe
    $checkSql = "SELECT id FROM usuarios WHERE email = :email OR telefone = :telefone LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindValue(':email', $email);
    $checkStmt->bindValue(':telefone', $telefone);
    $checkStmt->execute();

    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email ou telefone já cadastrado.']);
        exit;
    }

    // Inserir novo usuário
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    $sql = "INSERT INTO usuarios (nome, email, telefone, senha, perfil, ativo) 
            VALUES (:nome, :email, :telefone, :senha, :perfil, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':nome', $nome);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':telefone', $telefone);
    $stmt->bindValue(':senha', $senhaHash);
    $stmt->bindValue(':perfil', $perfil);

    if ($stmt->execute()) {
        $newId = (int)$conn->lastInsertId();

        // Autenticar automaticamente a sessão do novo usuário
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user'] = [
            'id' => $newId,
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'perfil' => strtolower($perfil)
        ];

        // Obter redirecionamento via Factory
        $redirect = null;
        try {
            $usuarioObj = UsuarioFactory::criarUsuario($perfil);
            $redirect = $usuarioObj->getPainelRedirecionamento();
        } catch (Exception $e) {
            $redirect = 'Frontend/dashboard.php';
        }

        echo json_encode([
            'success' => 'Conta criada com sucesso! Entrando no sistema...',
            'user' => $_SESSION['user'],
            'redirect' => $redirect
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao criar conta no banco de dados.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao acessar o banco de dados.']);
}

?>