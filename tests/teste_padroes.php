<?php
/**
 * Testes Automatizados de Validação dos Padrões de Projeto:
 * 1. Singleton (Database)
 * 2. Adapter (ExportadorXml -> XmlParaJsonAdapter)
 */

echo "============================================================\n";
echo "    INICIANDO BATERIA DE TESTES - DESIGN PATTERNS (GoF)     \n";
echo "============================================================\n\n";

$erros = 0;
$testesPassados = 0;

function assertTeste($condicao, $titulo) {
    global $erros, $testesPassados;
    if ($condicao) {
        echo "  [PASSOU] $titulo\n";
        $testesPassados++;
    } else {
        echo "  [FALHOU] $titulo\n";
        $erros++;
    }
}

// -------------------------------------------------------------
// 1. TESTES DO PADRÃO SINGLETON
// -------------------------------------------------------------
echo "[1] TESTANDO O PADRÃO DE PROJETO SINGLETON (Database):\n";
require_once __DIR__ . '/../Models/Database.php';

try {
    // Teste 1.1: Obtenção de instância
    $db1 = Database::getInstance();
    assertTeste($db1 instanceof Database, "Database::getInstance() retorna uma instância válida de Database.");

    // Teste 1.2: Conexão PDO ativa
    $pdo1 = $db1->getConnection();
    assertTeste($pdo1 instanceof PDO, "getConnection() retorna uma instância ativa de PDO.");

    // Teste 1.3: Unicidade da Instância (Identidade referencial)
    $db2 = Database::getInstance();
    $pdo2 = $db2->getConnection();
    assertTeste($db1 === $db2, "Duas chamadas a Database::getInstance() retornam EXATAMENTE a mesma instância (\$db1 === \$db2).");
    assertTeste($pdo1 === $pdo2, "A conexão PDO interna é compartilhada e idêntica (\$pdo1 === \$pdo2).");

    // Teste 1.4: Construtor é privado (não permite new Database())
    $reflection = new ReflectionClass('Database');
    $constructor = $reflection->getConstructor();
    assertTeste($constructor && $constructor->isPrivate(), "O construtor da classe Database é estritamente PRIVADO.");

    // Teste 1.5: Método __clone é privado
    $cloneMethod = $reflection->getMethod('__clone');
    assertTeste($cloneMethod && $cloneMethod->isPrivate(), "O método __clone() é estritamente PRIVADO para impedir clonagem.");

} catch (Exception $e) {
    assertTeste(false, "Exceção inesperada no teste de Singleton: " . $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 2. PREPARAÇÃO DE DADOS PARA TESTE DO ADAPTER
// -------------------------------------------------------------
echo "[2] PREPARANDO DADOS DE TESTE NO BANCO DE DADOS:\n";
try {
    $conn = Database::getInstance()->getConnection();
    
    // Assegura existência das tabelas
    require_once __DIR__ . '/../Models/Agendamento.php';
    Agendamento::criarTabelas($conn);
    $conn->exec("CREATE TABLE IF NOT EXISTS servicos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        preco DECIMAL(10,2) NOT NULL,
        duracao_minutos INT NOT NULL DEFAULT 30
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Se a tabela servicos estiver vazia, insere serviço de teste
    $stmtServico = $conn->query("SELECT id FROM servicos LIMIT 1");
    $servicoId = $stmtServico->fetchColumn();
    if (!$servicoId) {
        $conn->exec("INSERT INTO servicos (nome, preco, duracao_minutos) VALUES ('Corte Degradê Clássico', 45.00, 30)");
        $servicoId = $conn->lastInsertId();
    }

    // Se agendamentos estiver vazia, insere um agendamento de teste
    $stmtAg = $conn->query("SELECT id FROM agendamentos LIMIT 1");
    $temAgendamento = $stmtAg->fetchColumn();
    if (!$temAgendamento) {
        $codigoTeste = 'TST' . rand(100, 999);
        $conn->exec("INSERT INTO agendamentos (cliente_nome, cliente_telefone, servico_id, data_agendada, horario, status, codigo)
                     VALUES ('Carlos Silva Teste', '(11) 98888-7777', $servicoId, CURDATE(), '14:00:00', 'ativo', '$codigoTeste')");
    }

    echo "  [OK] Dados do banco verificados/prontos com sucesso.\n\n";
} catch (Exception $e) {
    echo "  [AVISO] Erro ao preparar dados: " . $e->getMessage() . "\n\n";
}

// -------------------------------------------------------------
// 3. TESTES DO PADRÃO ADAPTER (ExportadorXml -> XmlParaJsonAdapter)
// -------------------------------------------------------------
echo "[3] TESTANDO O PADRÃO DE PROJETO ADAPTER (ExportadorXml -> XmlParaJsonAdapter):\n";
require_once __DIR__ . '/../Models/ExportadorInterface.php';
require_once __DIR__ . '/../Models/ExportadorXml.php';
require_once __DIR__ . '/../Models/XmlParaJsonAdapter.php';

try {
    // Teste 3.1: Instanciação do Adaptee
    $adapteeXml = new ExportadorXml();
    assertTeste($adapteeXml instanceof ExportadorInterface, "ExportadorXml (Adaptee) implementa ExportadorInterface.");

    // Teste 3.2: Geração de XML pelo Adaptee
    $xmlGerado = $adapteeXml->exportar();
    assertTeste(!empty($xmlGerado), "O ExportadorXml gerou uma saída não vazia.");
    
    $xmlCarregado = simplexml_load_string($xmlGerado);
    assertTeste($xmlCarregado !== false, "A saída do Adaptee é um XML estritamente bem formado e sintaticamente válido.");
    assertTeste($xmlCarregado->getName() === 'barbearia_agendamentos', "A tag raiz do XML gerado é <barbearia_agendamentos>.");
    assertTeste(strpos($adapteeXml->getTipoConteudo(), 'xml') !== false, "O MIME type do Adaptee é 'application/xml'.");

    // Teste 3.3: Instanciação do Adapter
    $adapter = new XmlParaJsonAdapter($adapteeXml);
    assertTeste($adapter instanceof ExportadorInterface, "XmlParaJsonAdapter (Adapter) implementa ExportadorInterface.");
    assertTeste($adapter->getAdaptee() === $adapteeXml, "O Adapter mantém a referência correta do seu Adaptee.");

    // Teste 3.4: Execução do método exportar() do Adapter (XML -> JSON)
    $jsonGerado = $adapter->exportar();
    assertTeste(!empty($jsonGerado), "O XmlParaJsonAdapter retornou uma saída não vazia.");

    // Teste 3.5: Validação do JSON adaptado
    $arrayDecodificado = json_decode($jsonGerado, true);
    assertTeste($arrayDecodificado !== null && json_last_error() === JSON_ERROR_NONE, "A saída adaptada é um JSON estritamente válido.");
    assertTeste(isset($arrayDecodificado['padrao_utilizado']) && strpos($arrayDecodificado['padrao_utilizado'], 'Adapter') !== false, "O JSON adaptado contém o metadado do padrão Adapter.");
    assertTeste(isset($arrayDecodificado['agendamentos']) && is_array($arrayDecodificado['agendamentos']), "O JSON adaptado contém o nó de lista 'agendamentos'.");
    assertTeste(strpos($adapter->getTipoConteudo(), 'json') !== false, "O MIME type do Adapter é 'application/json'.");

    // Teste 3.6: Integridade dos dados transferidos de XML para JSON
    $totalRegistrosXml = (int)($xmlCarregado['total_registros'] ?? 0);
    $totalRegistrosJson = (int)($arrayDecodificado['total_registros'] ?? 0);
    assertTeste($totalRegistrosXml === $totalRegistrosJson, "A quantidade de registros no XML ($totalRegistrosXml) é idêntica à do JSON ($totalRegistrosJson).");

} catch (Exception $e) {
    assertTeste(false, "Exceção inesperada no teste de Adapter: " . $e->getMessage());
}

echo "\n============================================================\n";
echo "                  RESUMO DOS RESULTADOS                     \n";
echo "============================================================\n";
echo "  Total de testes realizados: " . ($testesPassados + $erros) . "\n";
echo "  Testes aprovados: $testesPassados\n";
echo "  Testes reprovados: $erros\n";

if ($erros === 0) {
    echo "\n  >>> SUCESSO: Todos os padrões foram implementados e validados! <<<\n";
} else {
    echo "\n  >>> ATENÇÃO: Houve falhas em $erros teste(s). <<<\n";
}
echo "============================================================\n";
?>

