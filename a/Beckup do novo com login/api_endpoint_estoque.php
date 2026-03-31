<?php
/**
 * API Endpoint para receber dados de NF-e extraídos pelo Groq
 * Sistema: ONN Móveis - Integração NFe → Estoque
 * Usuário: AdemirRed
 * Data: 2025-10-29 12:12:51 UTC
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuração do banco (mesmo do sistema existente)
$conn = new mysqli("192.168.0.201", "root", "", "onnmoveis");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Falha na conexão com banco de dados',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Função para log
function logOperation($conn, $operation, $details, $user = 'AdemirRed') {
    $stmt = $conn->prepare("INSERT INTO log_nfe_integracoes (usuario, operacao, detalhes, timestamp) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("sss", $user, $operation, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// Cria tabela de log se não existir
$conn->query("
    CREATE TABLE IF NOT EXISTS log_nfe_integracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(100),
        operacao VARCHAR(100),
        detalhes TEXT,
        timestamp DATETIME,
        INDEX idx_timestamp (timestamp),
        INDEX idx_usuario (usuario)
    )
");

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Recebe dados JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('JSON inválido recebido');
        }
        
        logOperation($conn, 'API_CALL', 'Dados recebidos: ' . substr($input, 0, 500));
        
        // Valida estrutura dos dados
        if (!isset($data['nfe_info']) || !isset($data['itens'])) {
            throw new Exception('Estrutura de dados inválida - nfe_info ou itens ausentes');
        }
        
        $nfe_info = $data['nfe_info'];
        $itens = $data['itens'];
        $metadata = $data['metadata'] ?? [];
        
        $conn->begin_transaction();
        
        $resultados = [
            'itens_processados' => 0,
            'itens_adicionados' => 0,
            'itens_atualizados' => 0,
            'itens_ignorados' => 0,
            'erros' => []
        ];
        
        foreach ($itens as $index => $item) {
            try {
                $resultados['itens_processados']++;
                
                // Sanitiza dados do item
                $nome = trim(substr($item['descricao'] ?? '', 0, 200));
                $codigo_original = trim($item['codigo'] ?? '');
                $unidade = trim($item['unidade'] ?? 'UN');
                $valor_unitario = floatval($item['valor_unitario'] ?? 0);
                $quantidade = floatval($item['quantidade'] ?? 0);
                $valor_total = floatval($item['valor_total'] ?? 0);
                
                if (empty($nome)) {
                    $resultados['itens_ignorados']++;
                    continue;
                }
                
                // Verifica se item já existe
                $stmt = $conn->prepare("
                    SELECT Codigo, Nome, EstoqueAtual, Valor 
                    FROM itens 
                    WHERE LOWER(Nome) LIKE LOWER(?) 
                    OR (Codigo != '' AND Codigo = ?)
                    LIMIT 1
                ");
                
                $nome_busca = "%{$nome}%";
                $stmt->bind_param("ss", $nome_busca, $codigo_original);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($existing) {
                    // Item existe - atualiza estoque
                    $codigo_existente = $existing['Codigo'];
                    $estoque_atual = floatval($existing['EstoqueAtual']);
                    $novo_estoque = $estoque_atual + $quantidade;
                    
                    // Atualiza apenas estoque e data de alteração
                    $stmt = $conn->prepare("
                        UPDATE itens 
                        SET EstoqueAtual = ?, 
                            DataAlteracao = NOW(),
                            Valor = CASE WHEN ? > 0 THEN ? ELSE Valor END
                        WHERE Codigo = ?
                    ");
                    $stmt->bind_param("dddi", $novo_estoque, $valor_unitario, $valor_unitario, $codigo_existente);
                    
                    if ($stmt->execute()) {
                        $resultados['itens_atualizados']++;
                        logOperation($conn, 'ITEM_ATUALIZADO', 
                            "Código: {$codigo_existente}, Nome: {$nome}, Estoque: {$estoque_atual} → {$novo_estoque}");
                    }
                    $stmt->close();
                    
                } else {
                    // Item novo - adiciona
                    $descricao_completa = "Importado da NF-e {$nfe_info['numero']} em " . date('d/m/Y H:i') . 
                                         "\nEmitente: " . ($nfe_info['emitente'] ?? 'N/A') .
                                         "\nCódigo original: {$codigo_original}";
                    
                    $stmt = $conn->prepare("
                        INSERT INTO itens 
                        (Nome, Unidade, Valor, Descricao, EstoqueMinimo, EstoqueAtual, DataCriacao, DataAlteracao)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    
                    $estoque_minimo = max(1, ceil($quantidade * 0.1)); // 10% da quantidade como estoque mínimo
                    
                    $stmt->bind_param("ssdsdd", 
                        $nome, 
                        $unidade, 
                        $valor_unitario, 
                        $descricao_completa, 
                        $estoque_minimo, 
                        $quantidade
                    );
                    
                    if ($stmt->execute()) {
                        $novo_codigo = $conn->insert_id;
                        $resultados['itens_adicionados']++;
                        logOperation($conn, 'ITEM_ADICIONADO', 
                            "Código: {$novo_codigo}, Nome: {$nome}, Quantidade: {$quantidade}");
                    }
                    $stmt->close();
                }
                
            } catch (Exception $e) {
                $resultados['erros'][] = "Item {$index}: " . $e->getMessage();
                logOperation($conn, 'ERRO_ITEM', "Item {$index}: " . $e->getMessage());
            }
        }
        
        $conn->commit();
        
        // Log do resultado final
        $resumo = "NF-e {$nfe_info['numero']}: {$resultados['itens_processados']} processados, " .
                 "{$resultados['itens_adicionados']} adicionados, {$resultados['itens_atualizados']} atualizados";
        logOperation($conn, 'PROCESSAMENTO_COMPLETO', $resumo);
        
        // Resposta de sucesso
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Dados processados com sucesso',
            'nfe_numero' => $nfe_info['numero'] ?? 'N/A',
            'resultados' => $resultados,
            'timestamp' => date('Y-m-d H:i:s'),
            'processado_por' => $metadata['processado_por'] ?? 'Sistema',
            'resumo' => $resumo
        ]);
        
    } else {
        // Método GET - retorna status da API
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_logs, 
                   MAX(timestamp) as ultimo_processamento,
                   COUNT(CASE WHEN operacao = 'ITEM_ADICIONADO' THEN 1 END) as itens_adicionados_hoje
            FROM log_nfe_integracoes 
            WHERE DATE(timestamp) = CURDATE()
        ");
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        http_response_code(200);
        echo json_encode([
            'status' => 'API funcionando',
            'sistema' => 'ONN Móveis - Integração NFe → Estoque',
            'usuario' => 'AdemirRed',
            'timestamp' => date('Y-m-d H:i:s'),
            'estatisticas_hoje' => $stats,
            'endpoints' => [
                'POST /' => 'Recebe dados de NF-e para processar',
                'GET /' => 'Status da API'
            ]
        ]);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    logOperation($conn, 'ERRO_API', $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>