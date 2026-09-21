-- ========================================================
-- Barbearia VIP - Script Completo de Inicialização do Banco
-- Compatível com phpMyAdmin, MySQL CLI e MariaDB (XAMPP)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";

-- Criação do Banco de Dados
CREATE DATABASE IF NOT EXISTS `barbearia_vip` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barbearia_vip`;

-- --------------------------------------------------------
-- Estrutura da tabela `usuarios`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) NOT NULL UNIQUE,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `perfil` ENUM('cliente','barbeiro','admin') NOT NULL DEFAULT 'cliente',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `servicos`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `servicos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `preco` DECIMAL(10,2) NOT NULL,
  `duracao_minutos` INT(11) NOT NULL DEFAULT 30,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `agendamentos`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agendamentos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_nome` VARCHAR(120) NOT NULL,
  `cliente_telefone` VARCHAR(20) DEFAULT NULL,
  `servico_id` INT(11) NOT NULL,
  `barbeiro_id` INT(11) DEFAULT NULL,
  `data_agendada` DATE NOT NULL,
  `horario` TIME NOT NULL,
  `status` ENUM('ativo','cancelado','concluido') DEFAULT 'ativo',
  `codigo` CHAR(6) NOT NULL UNIQUE,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_data_status` (`data_agendada`, `status`),
  INDEX `idx_barbeiro` (`barbeiro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `bloqueios_horario`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bloqueios_horario` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `data_bloqueio` DATE NOT NULL,
  `horario` TIME NOT NULL,
  `motivo` VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bloqueio` (`data_bloqueio`, `horario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `avaliacoes` (Funcionalidade 5)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_nome` VARCHAR(120) NOT NULL,
  `cliente_telefone` VARCHAR(20) DEFAULT NULL,
  `servico_nome` VARCHAR(100) DEFAULT 'Corte Clássico',
  `nota` TINYINT NOT NULL DEFAULT 5,
  `comentario` TEXT NOT NULL,
  `destaque` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_nota_data` (`nota`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `resgates_fidelidade` (Funcionalidade 3)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `resgates_fidelidade` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_telefone` VARCHAR(20) NOT NULL,
  `cliente_nome` VARCHAR(120) DEFAULT NULL,
  `codigo_voucher` VARCHAR(30) NOT NULL UNIQUE,
  `data_resgate` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_fidelidade_tel` (`cliente_telefone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dados Iniciais: Avaliações Demonstrativas
-- --------------------------------------------------------
INSERT IGNORE INTO `avaliacoes` (`id`, `cliente_nome`, `cliente_telefone`, `servico_nome`, `nota`, `comentario`, `destaque`, `criado_em`) VALUES
(1, 'Lucas Albuquerque', '(11) 98765-4321', 'Corte Degradê Clássico', 5, 'Atendimento impecável! O degradê navalhado ficou perfeito e o café cortesia é sensacional. Virei cliente fiel!', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'Rafael Fontana', '(11) 97777-8888', 'Barba e Cabelo', 5, 'Ambiente muito profissional, produtos de primeira linha e pontualidade britânica. Melhor barbearia da região!', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 'Guilherme Santos', '(11) 96543-2109', 'Corte Social', 5, 'Corte alinhado no capricho. O sistema de agendamento online facilita demais a vida. Recomendo de olhos fechados!', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(4, 'Matheus Ribeiro', '(11) 95555-4444', 'Corte Degradê Clássico', 4, 'Excelente experiência, os barbeiros entendem muito de visagismo. Nota 10!', 1, DATE_SUB(NOW(), INTERVAL 12 DAY));

-- --------------------------------------------------------
-- Dados Iniciais: Serviços do Catálogo VIP
-- --------------------------------------------------------
INSERT IGNORE INTO `servicos` (`id`, `nome`, `preco`, `duracao_minutos`) VALUES
(1, 'Corte Degradê Clássico', 45.00, 30),
(2, 'Corte Social', 20.00, 30),
(3, 'Barba e Cabelo', 50.00, 30);

-- --------------------------------------------------------
-- Dados Iniciais: Usuários (Administrador e Barbeiros)
-- --------------------------------------------------------
INSERT IGNORE INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `senha`, `perfil`, `ativo`) VALUES
(1, 'Administrador VIP', 'admin@barbeariavip.com', '(11) 99999-9999', '$2y$10$tZ9sD3Qj8hC2yB1K0mNv4uK6f5fK3p4m3j2l1k0j9h8g7f6e5d4c3', 'admin', 1),
(2, 'João Barbeiro', 'joao@barbearia.com', '(11) 98888-1111', '$2y$10$tZ9sD3Qj8hC2yB1K0mNv4uK6f5fK3p4m3j2l1k0j9h8g7f6e5d4c3', 'barbeiro', 1),
(3, 'Carlos Navalha', 'carlos@barbearia.com', '(11) 98888-2222', '$2y$10$tZ9sD3Qj8hC2yB1K0mNv4uK6f5fK3p4m3j2l1k0j9h8g7f6e5d4c3', 'barbeiro', 1),
(4, 'Lucas Degradê', 'lucas@barbearia.com', '(11) 98888-3333', '$2y$10$tZ9sD3Qj8hC2yB1K0mNv4uK6f5fK3p4m3j2l1k0j9h8g7f6e5d4c3', 'barbeiro', 1);

-- --------------------------------------------------------
-- Estrutura da tabela `planos_assinatura` (Funcionalidade 5: Clube VIP)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planos_assinatura` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `preco_mensal` DECIMAL(10,2) NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `beneficios` TEXT NOT NULL,
  `cor_badge` VARCHAR(30) DEFAULT 'amber',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura da tabela `assinantes_vip` (Funcionalidade 5: Clube VIP)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assinantes_vip` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_nome` VARCHAR(120) NOT NULL,
  `cliente_telefone` VARCHAR(20) NOT NULL,
  `cliente_email` VARCHAR(120) DEFAULT NULL,
  `plano_id` INT(11) NOT NULL,
  `status` ENUM('ativo','suspenso','cancelado') NOT NULL DEFAULT 'ativo',
  `data_inicio` DATE NOT NULL,
  `data_renovacao` DATE NOT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tel_status` (`cliente_telefone`, `status`),
  INDEX `idx_plano` (`plano_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dados Iniciais: Planos de Assinatura VIP
-- --------------------------------------------------------
INSERT IGNORE INTO `planos_assinatura` (`id`, `nome`, `slug`, `preco_mensal`, `descricao`, `beneficios`, `cor_badge`, `ativo`) VALUES
(1, 'VIP Silver', 'silver', 89.90, 'O plano ideal para quem mantém o corte sempre alinhado todo mês.', 'Cortes de Cabelo Ilimitados no Mês\nAtendimento com Horário Preferencial\nCafé Espresso Cortesia em Cada Visita\n10% de Desconto em Produtos de Barba', 'zinc', 1),
(2, 'VIP Gold', 'gold', 149.90, 'Experiência completa com cabelo impecável e barba sempre alinhada.', 'Cabelo & Barba Ilimitados no Mês\nBarboterapia com Toalha Quente\nCafé Gourmet & Água Mineral Premium\n15% de Desconto em Pomadas e Óleos', 'amber', 1),
(3, 'VIP Black Diamond', 'black', 199.90, 'Acesso ilimitado e exclusivo a todos os serviços da casa com tratamento VIP.', 'Acesso Total Ilimitado (Cabelo + Barba + Sobrancelha)\nCerveja Artesanal Gelada Cortesia por Visita\n20% de Desconto em Toda a Linha de Produtos\nVaga de Garagem VIP Reservada', 'emerald', 1);

-- --------------------------------------------------------
-- Dados Iniciais: Assinante VIP Demonstrativo
-- --------------------------------------------------------
INSERT IGNORE INTO `assinantes_vip` (`id`, `cliente_nome`, `cliente_telefone`, `cliente_email`, `plano_id`, `status`, `data_inicio`, `data_renovacao`) VALUES
(1, 'Marcos Assinante VIP', '(11) 99999-7777', 'marcos.vip@email.com', 2, 'ativo', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY));

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- Fim da Inicialização: Barbearia VIP Pronta para Uso!
-- ========================================================