<?php
require_once __DIR__ . '/../Models/UsuarioFactory.php';

class UsuarioController {
    
    public function obterRedirecionamento($perfil) {
        try {
            $usuario = UsuarioFactory::criarUsuario($perfil);
            return $usuario->getPainelRedirecionamento();
        } catch (Exception $e) {
            return null;
        }
    }

    public function registrar($nome, $email, $telefone, $senha, $perfil = 'cliente') {
        $usersFile = __DIR__ . '/../api/users.json';
        if (!file_exists($usersFile)) {
            file_put_contents($usersFile, json_encode([]));
        }

        $users = json_decode(file_get_contents($usersFile), true);
        if (!is_array($users)) $users = [];

        // Validar duplicatas
        foreach ($users as $user) {
            if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                return ['erro' => 'Email já cadastrado.'];
            }
            if (isset($user['telefone']) && $user['telefone'] === $telefone) {
                return ['erro' => 'Telefone já cadastrado.'];
            }
        }

        $users[] = [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'senha' => password_hash($senha, PASSWORD_DEFAULT),
            'perfil' => $perfil,
            'criado_em' => date('c')
        ];

        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $redirect = $this->obterRedirecionamento($perfil);
        return ['sucesso' => 'Conta criada!', 'redirect' => $redirect];
    }

    public function autenticar($identifier, $senha) {
        $usersFile = __DIR__ . '/../api/users.json';
        if (!file_exists($usersFile)) {
            return ['erro' => 'Usuário ou senha inválidos.'];
        }

        $users = json_decode(file_get_contents($usersFile), true);
        if (!is_array($users)) $users = [];

        foreach ($users as $user) {
            if ((isset($user['email']) && strtolower($user['email']) === strtolower($identifier)) ||
                (isset($user['telefone']) && $user['telefone'] === $identifier)) {
                if (password_verify($senha, $user['senha'])) {
                    $redirect = isset($user['perfil']) ? $this->obterRedirecionamento($user['perfil']) : null;
                    return ['sucesso' => true, 'nome' => $user['nome'], 'email' => $user['email'], 'redirect' => $redirect];
                }
            }
        }
        return ['erro' => 'Usuário ou senha inválidos.'];
    }
}
?>
