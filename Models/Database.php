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
    private string $host = "127.0.0.1";
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
                    `barbeiro_id` INT NULL DEFAULT NULL,
                    `data_agendada` DATE NOT NULL,
                    `horario` TIME NOT NULL,
                    `status` ENUM('ativo','cancelado','concluido') DEFAULT 'ativo',
                    `codigo` CHAR(6) NOT NULL UNIQUE,
                    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_data_status` (`data_agendada`, `status`),
                    INDEX `idx_barbeiro` (`barbeiro_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Migração automática se a coluna barbeiro_id não existir na tabela agendamentos
            try {
                $colCheck = $this->conn->query("SHOW COLUMNS FROM `agendamentos` LIKE 'barbeiro_id'");
                if (!$colCheck->fetch()) {
                    $this->conn->exec("ALTER TABLE `agendamentos` ADD COLUMN `barbeiro_id` INT NULL DEFAULT NULL, ADD INDEX `idx_barbeiro` (`barbeiro_id`)");
                }
            } catch (PDOException $e) {
                // Coluna já existe ou tratada
            }

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

            // 7. Tabela planos_assinatura (Funcionalidade 5: Clube VIP / Barber Pass)
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `planos_assinatura` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nome` VARCHAR(100) NOT NULL,
                    `slug` VARCHAR(50) NOT NULL UNIQUE,
                    `preco_mensal` DECIMAL(10,2) NOT NULL,
                    `descricao` VARCHAR(255) NOT NULL,
                    `beneficios` TEXT NOT NULL,
                    `cor_badge` VARCHAR(30) DEFAULT 'amber',
                    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
                    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 8. Tabela assinantes_vip (Funcionalidade 5: Clube VIP / Barber Pass)
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `assinantes_vip` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `cliente_nome` VARCHAR(120) NOT NULL,
                    `cliente_telefone` VARCHAR(20) NOT NULL,
                    `cliente_email` VARCHAR(120) DEFAULT NULL,
                    `plano_id` INT NOT NULL,
                    `status` ENUM('ativo','suspenso','cancelado') NOT NULL DEFAULT 'ativo',
                    `data_inicio` DATE NOT NULL,
                    `data_renovacao` DATE NOT NULL,
                    `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_tel_status` (`cliente_telefone`, `status`),
                    INDEX `idx_plano` (`plano_id`)
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

            // Garante campos de perfil estendido na tabela usuarios (especialidade, slug, avatar)
            try {
                $colEsp = $this->conn->query("SHOW COLUMNS FROM `usuarios` LIKE 'especialidade'");
                if (!$colEsp->fetch()) {
                    $this->conn->exec("ALTER TABLE `usuarios` ADD COLUMN `especialidade` VARCHAR(100) DEFAULT 'Mestre Barbeiro', ADD COLUMN `slug` VARCHAR(60) DEFAULT NULL, ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL");
                }
            } catch (PDOException $e) {}

            // Garante equipe de barbeiros cadastrada com perfil estendido
            $stBarbeiros = $this->conn->query("SELECT COUNT(*) FROM `usuarios` WHERE `perfil` = 'barbeiro'");
            if ((int)$stBarbeiros->fetchColumn() < 3) {
                $senhaPadrao = password_hash('barbeiro123', PASSWORD_BCRYPT);
                $barbeirosPadrao = [
                    ['João Barbeiro', 'joao@barbearia.com', '(11) 98888-1111', 'Cortes Clássicos & Barboterapia', 'joao'],
                    ['Carlos Navalha', 'carlos@barbearia.com', '(11) 98888-2222', 'Degradê Navalhado & Visagismo', 'carlos'],
                    ['Lucas Degradê', 'lucas@barbearia.com', '(11) 98888-3333', 'Fade Moderno, Riscas & Barba Esculpida', 'lucas']
                ];

                $stInsB = $this->conn->prepare("
                    INSERT INTO `usuarios` (`nome`, `email`, `telefone`, `senha`, `perfil`, `ativo`, `especialidade`, `slug`)
                    VALUES (:nome, :email, :tel, :senha, 'barbeiro', 1, :esp, :slug)
                    ON DUPLICATE KEY UPDATE `perfil` = 'barbeiro', `ativo` = 1, `especialidade` = VALUES(`especialidade`), `slug` = VALUES(`slug`)
                ");

                foreach ($barbeirosPadrao as $b) {
                    $stInsB->execute([
                        ':nome' => $b[0],
                        ':email' => $b[1],
                        ':tel' => $b[2],
                        ':senha' => $senhaPadrao,
                        ':esp' => $b[3],
                        ':slug' => $b[4]
                    ]);
                }
            }

            // Atualiza especialidade e slug dos barbeiros existentes caso vazios
            $this->conn->exec("UPDATE `usuarios` SET `especialidade` = 'Cortes Clássicos & Barboterapia', `slug` = 'joao' WHERE `email` = 'joao@barbearia.com' AND (`slug` IS NULL OR `slug` = '')");
            $this->conn->exec("UPDATE `usuarios` SET `especialidade` = 'Degradê Navalhado & Visagismo', `slug` = 'carlos' WHERE `email` = 'carlos@barbearia.com' AND (`slug` IS NULL OR `slug` = '')");
            $this->conn->exec("UPDATE `usuarios` SET `especialidade` = 'Fade Moderno, Riscas & Barba Esculpida', `slug` = 'lucas' WHERE `email` = 'lucas@barbearia.com' AND (`slug` IS NULL OR `slug` = '')");

            // Atualiza avatares dos barbeiros caso nulos
            $this->conn->exec("UPDATE `usuarios` SET `avatar` = 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=150&auto=format&fit=crop&q=80' WHERE `email` = 'joao@barbearia.com' AND (`avatar` IS NULL OR `avatar` = '')");
            $this->conn->exec("UPDATE `usuarios` SET `avatar` = 'https://images.unsplash.com/photo-1517832606589-7629c33971a6?w=150&auto=format&fit=crop&q=80' WHERE `email` = 'carlos@barbearia.com' AND (`avatar` IS NULL OR `avatar` = '')");
            $this->conn->exec("UPDATE `usuarios` SET `avatar` = 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=150&auto=format&fit=crop&q=80' WHERE `email` = 'lucas@barbearia.com' AND (`avatar` IS NULL OR `avatar` = '')");

            // Atualiza agendamentos legados que estejam sem barbeiro associado
            $primeiroBarbeiro = $this->conn->query("SELECT id FROM `usuarios` WHERE `perfil` = 'barbeiro' AND `ativo` = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
            if ($primeiroBarbeiro) {
                $this->conn->exec("UPDATE `agendamentos` SET `barbeiro_id` = {$primeiroBarbeiro} WHERE `barbeiro_id` IS NULL");
            }

            // Popula Planos de Assinatura VIP padrão se vazio
            $stPl = $this->conn->query("SELECT COUNT(*) FROM `planos_assinatura`");
            if ((int)$stPl->fetchColumn() === 0) {
                $this->conn->exec("
                    INSERT INTO `planos_assinatura` (`nome`, `slug`, `preco_mensal`, `descricao`, `beneficios`, `cor_badge`, `ativo`) VALUES
                    ('VIP Silver', 'silver', 89.90, 'O plano ideal para quem mantém o corte sempre alinhado todo mês.', 'Cortes de Cabelo Ilimitados no Mês\nAtendimento com Horário Preferencial\nCafé Espresso Cortesia em Cada Visita\n10% de Desconto em Produtos de Barba', 'zinc', 1),
                    ('VIP Gold', 'gold', 149.90, 'Experiência completa com cabelo impecável e barba sempre alinhada.', 'Cabelo & Barba Ilimitados no Mês\nBarboterapia com Toalha Quente\nCafé Gourmet & Água Mineral Premium\n15% de Desconto em Pomadas e Óleos', 'amber', 1),
                    ('VIP Black Diamond', 'black', 199.90, 'Acesso ilimitado e exclusivo a todos os serviços da casa com tratamento VIP.', 'Acesso Total Ilimitado (Cabelo + Barba + Sobrancelha)\nCerveja Artesanal Gelada Cortesia por Visita\n20% de Desconto em Toda a Linha de Produtos\nVaga de Garagem VIP Reservada', 'emerald', 1);
                ");
            }

            // Popula Assinante VIP demonstrativo se vazio
            $stAss = $this->conn->query("SELECT COUNT(*) FROM `assinantes_vip`");
            if ((int)$stAss->fetchColumn() === 0) {
                $stPlanoGold = $this->conn->query("SELECT id FROM `planos_assinatura` WHERE `slug` = 'gold' LIMIT 1")->fetchColumn() ?: 2;
                $this->conn->exec("
                    INSERT INTO `assinantes_vip` (`cliente_nome`, `cliente_telefone`, `cliente_email`, `plano_id`, `status`, `data_inicio`, `data_renovacao`)
                    VALUES ('Marcos Assinante VIP', '(11) 99999-7777', 'marcos.vip@email.com', {$stPlanoGold}, 'ativo', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY));
                ");
            }

            // Garante tabela produtos da Mini-Loja VIP
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS `produtos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nome` VARCHAR(150) NOT NULL,
                    `slug` VARCHAR(100) UNIQUE,
                    `categoria` VARCHAR(50) NOT NULL DEFAULT 'Geral',
                    `preco` DECIMAL(10,2) NOT NULL,
                    `estoque` INT NOT NULL DEFAULT 0,
                    `descricao` TEXT,
                    `imagem` VARCHAR(255) DEFAULT NULL,
                    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
                    `destaque` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Popula produtos padrão da barbearia se vazio
            $stProd = $this->conn->query("SELECT COUNT(*) FROM `produtos`");
            if ((int)$stProd->fetchColumn() === 0) {
                $this->conn->exec("
                    INSERT INTO `produtos` (`nome`, `slug`, `categoria`, `preco`, `estoque`, `descricao`, `imagem`, `ativo`, `destaque`) VALUES
                    ('Pomada Matte Efeito Seco (150g)', 'pomada-matte', 'Cabelo', 45.00, 20, 'Fixação extra-forte e acabamento natural sem brilho. Ideal para topetes, fades e penteados modernos.', 'https://images.unsplash.com/photo-1598452963314-b09f397a5c48?w=500&auto=format&fit=crop&q=80', 1, 1),
                    ('Óleo Nobre para Barba & Bigode (30ml)', 'oleo-barba', 'Barba', 38.00, 15, 'Hidratação profunda com óleos essenciais de argan e jojoba. Devolve maciez aos fios e aroma amadeirado VIP.', 'https://images.unsplash.com/photo-1621607512214-68297480165e?w=500&auto=format&fit=crop&q=80', 1, 1),
                    ('Balm Multifuncional de Barba (120g)', 'balm-barba', 'Barba', 35.00, 18, 'Alinha os fios rebeldes, elimina o frizz e refresca a pele no pós-barba sem engordurar.', 'https://images.unsplash.com/photo-1608248597359-25f00e93b169?w=500&auto=format&fit=crop&q=80', 1, 0),
                    ('Shampoo 3 em 1 Cabelo, Barba & Corpo (250ml)', 'shampoo-3em1', 'Cuidados', 42.00, 12, 'Fórmula com mentol e carvão ativado. Limpeza profunda revigorante para o dia a dia do homem moderno.', 'https://images.unsplash.com/photo-1535585209827-a15fcdbc4c2d?w=500&auto=format&fit=crop&q=80', 1, 1),
                    ('Tônico Fortalecedor Fator de Crescimento (60ml)', 'tonico-capilar', 'Tratamento', 59.90, 10, 'Fórmula biotina concentrada. Auxilia no preenchimento de falhas na barba e fortalecimento capilar.', 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=500&auto=format&fit=crop&q=80', 1, 0),
                    ('Pente Curvo de Madeira Nobre Anti-Frizz', 'pente-madeira', 'Acessórios', 25.00, 25, 'Artesanal em madeira maciça. Desembaraça sem quebrar os fios e distribui uniformemente o óleo na barba.', 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=500&auto=format&fit=crop&q=80', 1, 0);
                ");
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