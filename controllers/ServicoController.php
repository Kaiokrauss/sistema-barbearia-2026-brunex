<?php
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Servico.php';

class ServicoController {
    private $db;
    private $servico;

    public function __construct() {
        $dbObj = new Database();
        $this->db = $dbObj->getConnection();
        $this->servico = new Servico($this->db);
    }

    public function listar() {
        $stmt = $this->servico->lerTodos();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($nome, $preco, $duracao) {
        $this->servico->nome = trim($nome);
        $this->servico->preco = $preco;
        $this->servico->duracao_minutos = $duracao;
        return $this->servico->criar();
    }

    public function atualizar($id, $nome, $preco, $duracao) {
        $this->servico->id = $id;
        $this->servico->nome = trim($nome);
        $this->servico->preco = $preco;
        $this->servico->duracao_minutos = $duracao;
        return $this->servico->atualizar();
    }

    public function deletar($id) {
        $this->servico->id = $id;
        return $this->servico->deletar();
    }
}
?>
