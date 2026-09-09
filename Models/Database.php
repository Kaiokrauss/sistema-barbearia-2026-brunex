<?php
/**
 * Padrão de Projeto Singleton: Database
 * Garante que apenas uma instância de conexão com o banco de dados exista durante
 * o ciclo de vida da requisição, economizando recursos e evitando conexões duplicadas.
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
     * A conexão PDO é inicializada aqui uma única vez.
     */
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log("Erro de conexão com o banco de dados: " . $exception->getMessage());
            throw $exception;
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
?>