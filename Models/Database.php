<?php
/**
 * Padrão de Projeto Singleton: Database (com Auto-Setup Inteligente)
 * Garante que apenas uma instância de conexão com o banco de dados exista durante
 * o ciclo de vida da requisição, economizando recursos e evitando conexões duplicadas.
 * Caso o banco de dados ou as tabelas não existam no MySQL, cria e inicializa automaticamente.
 */
class Database {
    // 1. Guarda a instância única da classe
    private static ?Database $instance = null;

    // 2. Conexão PDO ativa
    private ?PDO $conn = null;

    // Credenciais de conexão
    private string $host = "localhost";
    private string $db_name = "barbearia_vip";
    private string $username = "root";
    private string $password = "";

    /**
     * Construtor privado: impede a criação direta de novos objetos com 'new Database()'.
     * A conexão PDO é inicializada aqui uma única vez com auto-recuperação.
     */
    private function __construct() {
        try {
            $this->conectar();
        } catch (PDOException $exception) {
            // Se o erro for de banco inexistente (código 1049 ou mensagem Unknown database)
            if ($exception->getCode() == 1049 || str_contains($exception->getMessage(), 'Unknown database')) {
                $this->inicializarBancoETabelas();
            } else {
                error_log("Erro de conexão com o banco de dados: " . $exception->getMessage());
                throw $exception;
            }
        }

        // Garante que as 4 tabelas fundamentais existam
        $this->assegurarTabelasExistentes();
    }

    /**
     * Estabelece a conexão com a base de dados barbearia_vip.
     */
    private function conectar(): void {
        $this->conn = new PDO(
            "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
            $this->username,
            $this->password
        );
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Cria a base de dados no MySQL caso ela ainda não exista.
     */
    private function inicializarBancoETabelas(): void {
        try {
            $pdoServer = new PDO(
                "mysql:host=" . $this->host . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $pdoServer->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Reconecta agora apontando para o banco criado
            $this->conectar();
        } catch (PDOException $e) {
            error_log("Falha ao criar banco de dados automaticamente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Assegura a criação das tabelas e população inicial se o banco estiver vazio.
     */
    private function assegurarTabelasExistentes(): void {
        if (!$this->conn) return;

        try {
            // 1. Tabela usuarios
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `usuarios` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nome` VARCHAR(120) NOT NULL,
                    `email` VARCHAR(120) NOT NULL UNIQUE,
                    `telefone` VARCHAR(20) DEFAULT NULL,
                    `senha` VARCHAR(255) NOT NULL,
                    `perfil` ENUM('cliente', 'barbeiro', 'admin') NOT NULL DEFAULT 'cliente',
                    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
                    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 2. Tabela servicos
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `servicos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nome` VARCHAR(100) NOT NULL,
                    `preco` DECIMAL(10,2) NOT NULL,
                    `duracao_minutos` INT NOT NULL DEFAULT 30
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 3. Tabela agendamentos
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `agendamentos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_nome` VARCHAR(120) NOT NULL,
                    `cliente_telefone` VARCHAR(20) DEFAULT NULL,
                    `servico_id` INT NOT NULL,
                    `data_agendada` DATE NOT NULL,
                    `horario` TIME NOT NULL,
                    `status` ENUM('ativo','cancelado','concluido') DEFAULT 'ativo',
                    `codigo` CHAR(6) NOT NULL UNIQUE,
                    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_data_status` (`data_agendada`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 4. Tabela bloqueios_horario
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `bloqueios_horario` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `data_bloqueio` DATE NOT NULL,
                    `horario` TIME NOT NULL,
                    `motivo` VARCHAR(200) DEFAULT NULL,
                    UNIQUE KEY `uq_bloqueio` (`data_bloqueio`, `horario`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 5. Tabela avaliacoes (Funcionalidade 5)
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `avaliacoes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_nome` VARCHAR(120) NOT NULL,
                    `cliente_telefone` VARCHAR(20) DEFAULT NULL,
                    `servico_nome` VARCHAR(100) DEFAULT 'Corte Clássico',
                    `nota` TINYINT NOT NULL DEFAULT 5,
                    `comentario` TEXT NOT NULL,
                    `destaque` TINYINT(1) NOT NULL DEFAULT 1,
                    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_nota_data` (`nota`, `criado_em`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 6. Tabela resgates_fidelidade (Funcionalidade 3)
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `resgates_fidelidade` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_telefone` VARCHAR(20) NOT NULL,
                    `cliente_nome` VARCHAR(120) DEFAULT NULL,
                    `codigo_voucher` VARCHAR(30) NOT NULL UNIQUE,
                    `data_resgate` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_fidelidade_tel` (`cliente_telefone`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Popula serviços padrão caso tabela esteja vazia
            $stSvc = $this->conn->query("SELECT COUNT(*) FROM `servicos`");
            if ((int)$stSvc->fetchColumn() === 0) {
                $this->conn->exec("
                    INSERT INTO `servicos` (`nome`, `preco`, `duracao_minutos`) VALUES
                    ('Corte Degradê Clássico', 45.00, 30),
                    ('Corte Social', 20.00, 30),
                    ('Barba e Cabelo', 50.00, 30);
                ");
            }

            // Popula avaliações iniciais demonstrativas caso vazia
            $stAv = $this->conn->query("SELECT COUNT(*) FROM `avaliacoes`");
            if ((int)$stAv->fetchColumn() === 0) {
                $this->conn->exec("
                    INSERT INTO `avaliacoes` (`cliente_nome`, `cliente_telefone`, `servico_nome`, `nota`, `comentario`, `destaque`, `criado_em`) VALUES
                    ('Lucas Albuquerque', '(11) 98765-4321', 'Corte Degradê Clássico', 5, 'Atendimento impecável! O degradê navalhado ficou perfeito e o café cortesia é sensacional. Virei cliente fiel!', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
                    ('Rafael Fontana', '(11) 97777-8888', 'Barba e Cabelo', 5, 'Ambiente muito profissional, produtos de primeira linha e pontualidade britânica. Melhor barbearia da região!', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
                    ('Guilherme Santos', '(11) 96543-2109', 'Corte Social', 5, 'Corte alinhado no capricho. O sistema de agendamento online facilita demais a vida. Recomendo de olhos fechados!', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
                    ('Matheus Ribeiro', '(11) 95555-4444', 'Corte Degradê Clássico', 4, 'Excelente experiência, os barbeiros entendem muito de visagismo. Nota 10!', 1, DATE_SUB(NOW(), INTERVAL 12 DAY));
                ");
            }

            // Popula usuário Admin padrão caso tabela usuarios não possua admin
            $stAdm = $this->conn->query("SELECT COUNT(*) FROM `usuarios` WHERE `perfil` = 'admin'");
            if ((int)$stAdm->fetchColumn() === 0) {
                $senhaHash = password_hash('admin123', PASSWORD_BCRYPT);
                $stInsAdm = $this->conn->prepare("
                    INSERT INTO `usuarios` (`nome`, `email`, `telefone`, `senha`, `perfil`, `ativo`)
                    VALUES ('Administrador VIP', 'admin@barbeariavip.com', '(11) 99999-9999', :senha, 'admin', 1)
                    ON DUPLICATE KEY UPDATE `perfil` = 'admin', `ativo` = 1
                ");
                $stInsAdm->execute([':senha' => $senhaHash]);
            }

        } catch (PDOException $e) {
            error_log("Aviso ao assegurar tabelas do banco: " . $e->getMessage());
        }
    }

    /**
     * Ponto de acesso global: retorna a instância única da classe Database (Singleton).
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna o objeto PDO de conexão com o banco de dados.
     */
    public function getConnection(): ?PDO {
        return $this->conn;
    }

    /**
     * Previne a clonagem do objeto para manter a unicidade da instância.
     */
    private function __clone() {}

    /**
     * Previne a desserialização do objeto para manter a unicidade da instância.
     */
    public function __wakeup() {
        throw new Exception("Não é permitido desserializar uma instância Singleton.");
    }
}