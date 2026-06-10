<?php
// Interface que obriga todos os usuários a terem um método de redirecionamento
interface Usuario {
    public function getPainelRedirecionamento();
}

class Cliente implements Usuario {
    public function getPainelRedirecionamento() { 
        return "Frontend/dashboard.php"; // Painel do cliente
    }
}

class Barbeiro implements Usuario {
    public function getPainelRedirecionamento() { 
        return "Frontend/dashboard.php?perfil=barbeiro"; // Painel do barbeiro
    }
}

class Admin implements Usuario {
    public function getPainelRedirecionamento() { 
        return "Frontend/dashboard.php?perfil=admin"; // Painel do admin
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
            case 'admin':
                return new Admin();
            default:
                throw new Exception("Perfil não encontrado no sistema.");
        }
    }
}
?>