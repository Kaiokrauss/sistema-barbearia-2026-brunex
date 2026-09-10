<?php
require_once __DIR__ . '/Database.php';

/**
 * Service: BackupService
 * Responsável por gerar dumps SQL completos e consistentes do banco de dados (DDL + DML)
 * utilizando PDO nativo, garantindo portabilidade sem necessidade de binários do sistema.
 */
class BackupService {
    private PDO $conn;

    public function __construct(?PDO $conn = null) {
        $this->conn = $conn ?? Database::getInstance()->getConnection();
    }

    /**
     * Gera o conteúdo SQL completo para backup de todas as tabelas.
     */
    public function gerarBackupSql(): string {
        $dataHora = date('Y-m-d H:i:s');
        $versaoMySql = $this->conn->getAttribute(PDO::ATTR_SERVER_VERSION);

        $sql = "-- ========================================================\n";
        $sql .= "-- Barbearia VIP - Backup do Banco de Dados\n";
        $sql .= "-- Data de Geração: {$dataHora}\n";
        $sql .= "-- Versão do Servidor MySQL: {$versaoMySql}\n";
        $sql .= "-- PHP Version: " . PHP_VERSION . "\n";
        $sql .= "-- ========================================================\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        // Obter todas as tabelas
        $stmtTables = $this->conn->query("SHOW TABLES");
        $tabelas = $stmtTables->fetchAll(PDO::FETCH_COLUMN);

        $totalRegistrosGeral = 0;

        foreach ($tabelas as $tabela) {
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Estrutura da tabela `{$tabela}`\n";
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$tabela}`;\n";

            // Obter CREATE TABLE
            $stmtCreate = $this->conn->query("SHOW CREATE TABLE `{$tabela}`");
            $rowCreate = $stmtCreate->fetch(PDO::FETCH_NUM);
            if ($rowCreate && isset($rowCreate[1])) {
                $sql .= $rowCreate[1] . ";\n\n";
            }

            // Obter Dados da tabela
            $sql .= "-- Dados da tabela `{$tabela}`\n";
            $stmtData = $this->conn->query("SELECT * FROM `{$tabela}`");
            $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $colunas = array_keys($rows[0]);
                $colunasSql = implode('`, `', $colunas);

                foreach ($rows as $row) {
                    $valoresFormatados = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $valoresFormatados[] = 'NULL';
                        } else {
                            $valoresFormatados[] = $this->conn->quote((string)$val);
                        }
                    }
                    $sql .= "INSERT INTO `{$tabela}` (`{$colunasSql}`) VALUES (" . implode(', ', $valoresFormatados) . ");\n";
                    $totalRegistrosGeral++;
                }
                $sql .= "\n";
            } else {
                $sql .= "-- (Nenhum registro encontrado nesta tabela)\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql .= "-- ========================================================\n";
        $sql .= "-- Fim do Backup: Total de " . count($tabelas) . " tabelas e {$totalRegistrosGeral} registros exportados.\n";
        $sql .= "-- ========================================================\n";

        return $sql;
    }

    /**
     * Retorna resumo estatístico das tabelas e quantidade de registros.
     */
    public function getEstatisticas(): array {
        $stmtTables = $this->conn->query("SHOW TABLES");
        $tabelas = $stmtTables->fetchAll(PDO::FETCH_COLUMN);
        $resumo = [];
        $totalGeral = 0;

        foreach ($tabelas as $tabela) {
            $stCount = $this->conn->query("SELECT COUNT(*) FROM `{$tabela}`");
            $qtd = (int)$stCount->fetchColumn();
            $resumo[$tabela] = $qtd;
            $totalGeral += $qtd;
        }

        return [
            'total_tabelas' => count($tabelas),
            'total_registros' => $totalGeral,
            'detalhes_tabelas' => $resumo
        ];
    }
}