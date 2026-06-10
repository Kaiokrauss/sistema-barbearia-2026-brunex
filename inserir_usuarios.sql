-- Execute isso no phpMyAdmin para inserir os usuários de teste
-- Senha para todos: 123456 (bcrypt)

INSERT INTO usuarios (nome, email, telefone, senha, perfil) VALUES
('João Barbeiro', 'joao@barbearia.com', '11987654321', '$2y$10$q6eSE6Gf8l7uV9kL8mN2O.uP1q2R3s4T5u6V7w8X9y0Z1a2B3c4D5', 'barbeiro');

INSERT INTO usuarios (nome, email, telefone, senha, perfil) VALUES
('Maria Cliente', 'maria@email.com', '11912345678', '$2y$10$q6eSE6Gf8l7uV9kL8mN2O.uP1q2R3s4T5u6V7w8X9y0Z1a2B3c4D5', 'cliente');

INSERT INTO usuarios (nome, email, telefone, senha, perfil) VALUES
('Admin Sistema', 'admin@barbearia.com', '11999999999', '$2y$10$q6eSE6Gf8l7uV9kL8mN2O.uP1q2R3s4T5u6V7w8X9y0Z1a2B3c4D5', 'admin');
