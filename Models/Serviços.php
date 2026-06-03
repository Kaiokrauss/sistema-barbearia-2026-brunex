<?php
class Servico {
    private $conn;
    private $table_name = "servicos";

    // Atributos da classe (espelham as colunas do banco)
    public $id;
    public $nome;
    public $preco;
    public $duracao_minutos;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Método READ (Ler todos os serviços)
    public function lerTodos() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY nome ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Método CREATE (Cadastrar novo serviço)
    public function criar() {
        $query = "INSERT INTO " . $this->table_name . " SET nome=:nome, preco=:preco, duracao_minutos=:duracao_minutos";
        $stmt = $this->conn->prepare($query);
        
        // O bindParam protege o sistema contra SQL Injection
        $stmt->bindParam(":nome", $this->nome);
        $stmt->bindParam(":preco", $this->preco);
        $stmt->bindParam(":duracao_minutos", $this->duracao_minutos);
        
        return $stmt->execute(); // Retorna true se salvou com sucesso
    }
    
    // Futuramente você pode adicionar aqui o atualizar() e deletar()
}
?>