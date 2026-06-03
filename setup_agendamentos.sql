-- =============================================================================
-- setup_agendamentos.sql
-- Execute este script no seu banco de dados MySQL/MariaDB UMA ÚNICA VEZ.
-- =============================================================================

-- Garante o banco correto
USE barbearia_vip;

-- ---------------------------------------------------------------------------
-- Tabela de serviços (caso ainda não exista no seu banco)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS servicos (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nome              VARCHAR(120)    NOT NULL,
    preco             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    duracao_minutos   SMALLINT        NOT NULL DEFAULT 30,
    ativo             TINYINT(1)      NOT NULL DEFAULT 1,
    criado_em         DATETIME        DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dados de exemplo (remova ou ajuste conforme necessário)
INSERT IGNORE INTO servicos (id, nome, preco, duracao_minutos) VALUES
    (1, 'Corte de Cabelo',  40.00, 30),
    (2, 'Barba',            30.00, 20),
    (3, 'Corte e Barba',    60.00, 45),
    (4, 'Acabamento',       20.00, 15),
    (5, 'Coloração',        80.00, 60);

-- ---------------------------------------------------------------------------
-- Tabela de agendamentos
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS agendamentos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    cliente_nome        VARCHAR(120)    NOT NULL,
    cliente_telefone    VARCHAR(20)     DEFAULT NULL,
    servico_id          INT             NOT NULL,
    data_agendada       DATE            NOT NULL,
    horario             TIME            NOT NULL,
    status              ENUM('ativo','cancelado','concluido') DEFAULT 'ativo',
    codigo              CHAR(6)         NOT NULL UNIQUE,
    criado_em           DATETIME        DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_data_status (data_agendada, status),
    CONSTRAINT fk_servico FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Tabela de bloqueios de horário (admin bloqueia manualmente)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bloqueios_horario (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    data_bloqueio   DATE            NOT NULL,
    horario         TIME            NOT NULL,
    motivo          VARCHAR(200)    DEFAULT NULL,
    criado_em       DATETIME        DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_bloqueio (data_bloqueio, horario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fim do script
