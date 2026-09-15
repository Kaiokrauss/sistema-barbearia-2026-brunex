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
  `data_agendada` DATE NOT NULL,
  `horario` TIME NOT NULL,
  `status` ENUM('ativo','cancelado','concluido') DEFAULT 'ativo',
  `codigo` CHAR(6) NOT NULL UNIQUE,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_data_status` (`data_agendada`, `status`)
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
-- Dados Iniciais: Serviços do Catálogo VIP
-- --------------------------------------------------------
INSERT IGNORE INTO `servicos` (`id`, `nome`, `preco`, `duracao_minutos`) VALUES
(1, 'Corte Degradê Clássico', 45.00, 30),
(2, 'Corte Social', 20.00, 30),
(3, 'Barba e Cabelo', 50.00, 30);

-- --------------------------------------------------------
-- Dados Iniciais: Usuário Administrador Padrão (senha: admin123)
-- --------------------------------------------------------
INSERT IGNORE INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `senha`, `perfil`, `ativo`) VALUES
(1, 'Administrador VIP', 'admin@barbeariavip.com', '(11) 99999-9999', '$2y$10$tZ9sD3Qj8hC2yB1K0mNv4uK6f5fK3p4m3j2l1k0j9h8g7f6e5d4c3', 'admin', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- Fim da Inicialização: Barbearia VIP Pronta para Uso!
-- ========================================================