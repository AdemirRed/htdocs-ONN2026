<?php
require_once 'config.php';
require_once 'fpdf.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Receber dados JSON
    $json = file_get_contents('php://input');
    $dados = json_decode($json, true);
    
    if (!$dados || !isset($dados['cliente']) || !isset($dados['itens'])) {
        throw new Exception('Dados inválidos');
    }
    
    $conn = getConnection();
    $conn->begin_transaction();
    
    // Inserir requisição
    $cliente = $conn->real_escape_string($dados['cliente']);
    $marceneiro = $conn->real_escape_string($dados['marceneiro']);
    $servico = $conn->real_escape_string($dados['servico']);
    
    $sql = "INSERT INTO requisicoes (cliente, marceneiro, servico, status) 
            VALUES ('$cliente', '$marceneiro', '$servico', 'pendente')";
    
    if (!$conn->query($sql)) {
        throw new Exception('Erro ao criar requisição: ' . $conn->error);
    }
    
    $requisicao_id = $conn->insert_id;
    
    // Gerar PDF usando FPDF
    $data_hora = date('d/m/Y H:i');
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);
    
    // Cabeçalho
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(74, 158, 255);
    $pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'ONN MOVEIS'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 14);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1', 'Requisição de Materiais'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $requisicao_numero = str_pad($requisicao_id, 6, '0', STR_PAD_LEFT);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'Requisição Nº ' . $requisicao_numero), 0, 1, 'C');
    
    $pdf->Ln(5);
    $pdf->SetDrawColor(74, 158, 255);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(8);
    
    // Caixa de informações
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor(74, 158, 255);
    $pdf->Rect(10, $pdf->GetY(), 190, 40, 'DF');
    
    $info_y = $pdf->GetY() + 5;
    $pdf->SetY($info_y);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(74, 158, 255);
    $pdf->Cell(35, 7, 'Cliente:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell(0, 7, iconv('UTF-8', 'ISO-8859-1', $cliente));
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(74, 158, 255);
    $pdf->Cell(35, 7, 'Marceneiro:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell(0, 7, iconv('UTF-8', 'ISO-8859-1', $marceneiro));
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(74, 158, 255);
    $pdf->Cell(35, 7, iconv('UTF-8', 'ISO-8859-1', 'Serviço:'), 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell(0, 7, iconv('UTF-8', 'ISO-8859-1', $servico));
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(74, 158, 255);
    $pdf->Cell(35, 7, 'Data/Hora:', 0, 0);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 7, $data_hora, 0, 1);
    
    $pdf->Ln(8);
    
    // Cabeçalho da tabela
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(74, 158, 255);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(74, 158, 255);
    
    $pdf->Cell(80, 8, 'Material', 1, 0, 'L', true);
    $pdf->Cell(20, 8, 'Qtd', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Unid', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Valor Unit.', 1, 0, 'R', true);
    $pdf->Cell(35, 8, 'Valor Total', 1, 1, 'R', true);
    
    // Itens da tabela
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(200, 200, 200);
    $fill = false;
    $total_geral = 0;
    
    foreach ($dados['itens'] as $item) {
        $codigo = $conn->real_escape_string($item['codigo']);
        $nome = $conn->real_escape_string($item['nome']);
        $quantidade = intval($item['quantidade']);
        $valor_unitario = round(floatval($item['valor_unitario']), 2);
        $valor_total = round($quantidade * $valor_unitario, 2);
        $total_geral += $valor_total;
        
        // Inserir item da requisição
        $sql = "INSERT INTO itens_requisicao (requisicao_id, item_codigo, item_nome, quantidade, valor_unitario, valor_total)
                VALUES ($requisicao_id, '$codigo', '$nome', $quantidade, $valor_unitario, $valor_total)";
        
        if (!$conn->query($sql)) {
            throw new Exception('Erro ao adicionar item: ' . $conn->error);
        }
        
        // Dar baixa no estoque
        $sql = "UPDATE itens SET EstoqueAtual = EstoqueAtual - $quantidade WHERE Codigo = '$codigo'";
        
        if (!$conn->query($sql)) {
            throw new Exception('Erro ao atualizar estoque: ' . $conn->error);
        }
        
        // Adicionar linha no PDF
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->Cell(80, 7, iconv('UTF-8', 'ISO-8859-1', substr($nome, 0, 40)), 1, 0, 'L', true);
        $pdf->Cell(20, 7, $quantidade, 1, 0, 'C', true);
        $pdf->Cell(20, 7, iconv('UTF-8', 'ISO-8859-1', $item['unidade']), 1, 0, 'C', true);
        $pdf->Cell(35, 7, formatarMoeda($valor_unitario), 1, 0, 'R', true);
        $pdf->Cell(35, 7, formatarMoeda($valor_total), 1, 1, 'R', true);
        
        $fill = !$fill;
    }
    
    // Total
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(74, 158, 255);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(155, 9, 'TOTAL GERAL:', 1, 0, 'R', true);
    $pdf->Cell(35, 9, formatarMoeda($total_geral), 1, 1, 'R', true);
    
    // Rodapé
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Este documento comprova a requisição de materiais do estoque.'), 0, 1, 'C');
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Gerado automaticamente pelo Sistema ONN Móveis em ' . $data_hora), 0, 1, 'C');
    
    // Salvar PDF
    $pdf_dir = __DIR__ . '/pdfs';
    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0777, true);
    }
    
    $pdf_filename = 'requisicao_' . $requisicao_id . '_' . date('YmdHis') . '.pdf';
    $pdf_path = $pdf_dir . '/' . $pdf_filename;
    
    $pdf->Output('F', $pdf_path);
    
    // Atualizar requisição com caminho do PDF
    $pdf_url = 'pdfs/' . $pdf_filename;
    $sql = "UPDATE requisicoes SET pdf_gerado = '$pdf_url', status = 'finalizada' WHERE id = $requisicao_id";
    $conn->query($sql);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'requisicao_id' => $requisicao_id,
        'pdf' => $pdf_url,
        'message' => 'Requisição gerada com sucesso!'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
