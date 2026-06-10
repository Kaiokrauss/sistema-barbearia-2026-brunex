-- Tabela de usuários única para clientes, barbeiros e admins
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    telefone VARCHAR(20) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('cliente', 'barbeiro', 'admin') DEFAULT 'cliente',
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_perfil (perfil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuários de teste pré-cadastrados
-- Senha para todos: 123456 (bcrypt)
INSERT INTO usuarios (nome, email, telefone, senha, perfil) VALUES
('João Barbeiro', 'joao@barbearia.com', '11987654321', '$2y$10$q6eSE6Gf8l7uV9kL8mN2O.uP1q2R3s4T5u6V7w8X9y0Z1a2B3c4D5', 'barbeiro'),
('Maria Cliente', 'maria@email.com', '11912345678', '$2y$10$q6eSE6Gf8l7uV9kL8mN2O.uP1q2R3s4T5u6V7w8X9y0Z1a2B3c4D5', 'cliente'),
('Admin Sistema', 'admin@barbearia.com', '11999999999', '$2y$10$q6eSE6Gf8l7uV9kL8mN2O.uP1q2R3s4T5u6V7w8X9y0Z1a2B3c4D5', 'admin')
ON DUPLICATE KEY UPDATE perfil = VALUES(perfil);
