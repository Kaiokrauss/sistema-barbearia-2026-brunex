<?php
// Interface que obriga todos os usuários a terem um método de redirecionamento
interface Usuario {
    public function getPainelRedirecionamento();
}

class Cliente implements Usuario {
    public function getPainelRedirecionamento() { 
        return "views/cliente/agendar.php"; // Painel do cliente
    }
}

class Barbeiro implements Usuario {
    public function getPainelRedirecionamento() { 
        return "views/admin/dashboard.php"; // Painel do barbeiro
    }
}

// A Fábrica
class UsuarioFactory {
    public static function criarUsuario($tipoPerfil) {
        switch(strtolower($tipoPerfil)) {
            case 'cliente':
                return new Cliente();
            case 'barbeiro':
                return new Barbeiro();
            default:
                throw new Exception("Perfil não encontrado no sistema.");
        }
    }
}
?>