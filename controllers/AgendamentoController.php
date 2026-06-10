<?php
require_once __DIR__ . '/../Models/Database.php';
require_once __DIR__ . '/../Models/Agendamento.php';

class AgendamentoController {
    private $db;
    private $agendamento;

    public function __construct() {
        $dbObj = new Database();
        $this->db = $dbObj->getConnection();
        $this->agendamento = new Agendamento($this->db);
    }

    public function listarPorData($data) {
        return $this->agendamento->getHorariosOcupados($data);
    }

    public function listarPorCliente($cliente) {
        $sql = "SELECT * FROM agendamentos WHERE cliente_nome LIKE :cliente ORDER BY data_agendada DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cliente', "%$cliente%");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($nome, $telefone, $servico_id, $data, $horario) {
        // Verifica conflitos
        $ocupados = $this->agendamento->getHorariosOcupados($data);
        $bloqueados = $this->agendamento->getHorariosBloqueados($data);
        if (in_array($horario, $ocupados) || in_array($horario, $bloqueados)) {
            return ['erro' => 'Horário indisponível.'];
        }

        $this->agendamento->cliente_nome = trim($nome);
        $this->agendamento->cliente_telefone = trim($telefone);
        $this->agendamento->servico_id = $servico_id;
        $this->agendamento->data_agendada = $data;
        $this->agendamento->horario = $horario;
        $this->agendamento->codigo = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

        if ($this->agendamento->criar()) {
            return ['sucesso' => 'Agendamento criado.', 'codigo' => $this->agendamento->codigo];
        }
        return ['erro' => 'Erro ao criar agendamento.'];
    }

    public function cancelarPorCodigo($codigo) {
        $res = $this->agendamento->cancelarPorCodigo($codigo);
        if ($res) {
            return ['sucesso' => 'Agendamento cancelado.'];
        }
        return ['erro' => 'Agendamento não encontrado.'];
    }

    public function exportarCSV($from = null, $to = null) {
        $sql = "SELECT * FROM agendamentos";
        $params = [];
        if ($from && $to) {
            $sql .= " WHERE data_agendada BETWEEN :from AND :to";
            $params[':from'] = $from;
            $params[':to'] = $to;
        } elseif ($from) {
            $sql .= " WHERE data_agendada = :from";
            $params[':from'] = $from;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
