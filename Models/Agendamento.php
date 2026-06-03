<?php
/**
 * Model: Agendamento
 * Responsável por todas as operações de banco relacionadas a agendamentos.
 */
class Agendamento {
    private $conn;
    private $table = "agendamentos";

    // Atributos
    public $id;
    public $cliente_nome;
    public $cliente_telefone;
    public $servico_id;
    public $data_agendada;
    public $horario;
    public $status;       // 'ativo' | 'cancelado' | 'concluido'
    public $codigo;       // código de 6 chars para o cliente cancelar
    public $criado_em;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Retorna todos os horários JÁ ocupados (status = 'ativo') em uma data.
     */
    public function getHorariosOcupados(string $data): array {
        $sql = "SELECT horario FROM {$this->table}
                WHERE data_agendada = :data AND status = 'ativo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);   // ['09:00', '14:00', ...]
    }

    /**
     * Retorna os horários bloqueados pelo admin em uma data.
     * Pressupõe a tabela `bloqueios_horario`.
     */
    public function getHorariosBloqueados(string $data): array {
        try {
            $sql = "SELECT horario FROM bloqueios_horario WHERE data_bloqueio = :data";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':data', $data);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            // Tabela pode não existir ainda — retorna vazio silenciosamente
            return [];
        }
    }

    /**
     * Insere um novo agendamento. Retorna true em caso de sucesso.
     */
    public function criar(): bool {
        $sql = "INSERT INTO {$this->table}
                    (cliente_nome, cliente_telefone, servico_id, data_agendada, horario, status, codigo)
                VALUES
                    (:nome, :telefone, :servico_id, :data, :horario, 'ativo', :codigo)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':nome',       $this->cliente_nome);
        $stmt->bindParam(':telefone',   $this->cliente_telefone);
        $stmt->bindParam(':servico_id', $this->servico_id);
        $stmt->bindParam(':data',       $this->data_agendada);
        $stmt->bindParam(':horario',    $this->horario);
        $stmt->bindParam(':codigo',     $this->codigo);

        return $stmt->execute();
    }

    /**
     * Cancela um agendamento pelo código único.
     * Retorna o agendamento cancelado ou null se não encontrado.
     */
    public function cancelarPorCodigo(string $codigo): ?array {
        // Busca primeiro
        $sql = "SELECT * FROM {$this->table} WHERE codigo = :codigo AND status = 'ativo' LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Cancela
        $upd = "UPDATE {$this->table} SET status = 'cancelado' WHERE id = :id";
        $stmt2 = $this->conn->prepare($upd);
        $stmt2->bindParam(':id', $row['id']);
        $stmt2->execute();

        return $row;
    }

    /**
     * DDL de criação das tabelas (útil para setup inicial).
     * Execute apenas uma vez no ambiente de produção.
     */
    public static function criarTabelas(PDO $conn): void {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS agendamentos (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                cliente_nome    VARCHAR(120)    NOT NULL,
                cliente_telefone VARCHAR(20)    DEFAULT NULL,
                servico_id      INT             NOT NULL,
                data_agendada   DATE            NOT NULL,
                horario         TIME            NOT NULL,
                status          ENUM('ativo','cancelado','concluido') DEFAULT 'ativo',
                codigo          CHAR(6)         NOT NULL UNIQUE,
                criado_em       DATETIME        DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_data_status (data_agendada, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $conn->exec("
            CREATE TABLE IF NOT EXISTS bloqueios_horario (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                data_bloqueio   DATE            NOT NULL,
                horario         TIME            NOT NULL,
                motivo          VARCHAR(200)    DEFAULT NULL,
                UNIQUE KEY uq_bloquio (data_bloqueio, horario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}
