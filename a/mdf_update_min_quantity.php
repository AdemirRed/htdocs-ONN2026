<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['codigo']) || !isset($_POST['qtd_minimo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = trim($_POST['codigo']);
$qtdMinimo = (int)$_POST['qtd_minimo'];

if ($qtdMinimo < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Quantidade mínima deve ser >= 0'], JSON_UNESCAPED_UNICODE);
    exit;
}

$basePath = getenv('CC_DATA_BASE_PATH') ?: 'C:\\CC_DATA_BASE';
$matDir = rtrim($basePath, "\\") . '\\MAT';

if (!is_dir($matDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Diretório MAT não encontrado'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Encontrar o arquivo INI do material
$iniFile = $matDir . DIRECTORY_SEPARATOR . 'M' . $codigo . '.INI';

if (!is_file($iniFile)) {
    // Tentar com padding
    $candidates = [
        'M' . str_pad($codigo, 4, '0', STR_PAD_LEFT) . '.INI',
        'M' . str_pad($codigo, 5, '0', STR_PAD_LEFT) . '.INI',
        'M' . str_pad($codigo, 6, '0', STR_PAD_LEFT) . '.INI',
    ];
    $iniFile = null;
    foreach ($candidates as $fileName) {
        $path = $matDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path)) {
            $iniFile = $path;
            break;
        }
    }

    if ($iniFile === null) {
        // Tentar glob
        $glob = glob($matDir . DIRECTORY_SEPARATOR . 'M*' . $codigo . '*.INI');
        if ($glob !== false && count($glob) > 0) {
            $iniFile = $glob[0];
        }
    }
}

if ($iniFile === null || !is_file($iniFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo INI não encontrado para o código ' . $codigo], JSON_UNESCAPED_UNICODE);
    exit;
}

$updated = updateIniMinQuantity($iniFile, $qtdMinimo);

if ($updated) {
    echo json_encode(['success' => 'Quantidade mínima atualizada com sucesso'], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar arquivo INI'], JSON_UNESCAPED_UNICODE);
}

function updateIniMinQuantity(string $filePath, int $qtdMinimo): bool
{
    $content = @file_get_contents($filePath);
    if ($content === false) {
        return false;
    }

    $lines = preg_split('/\r?\n/', $content);
    $inEstoque = false;
    $foundKey = false;
    $estoqueStartIndex = -1;
    $lastEstoqueIndex = -1;

    // Procurar a seção [ESTOQUE] e a chave QTD_MIN_CHP
    foreach ($lines as $i => $line) {
        $trimmed = trim($line);

        // Detectar início de seção
        if (preg_match('/^\s*\[(.+?)\]\s*$/u', $trimmed, $matches)) {
            if ($inEstoque && !$foundKey) {
                // Estávamos na seção ESTOQUE mas não encontramos a chave, inserir antes desta nova seção
                array_splice($lines, $i, 0, ['QTD_MIN_CHP=' . $qtdMinimo]);
                $foundKey = true;
                break;
            }
            $inEstoque = (strtoupper(trim($matches[1])) === 'ESTOQUE');
            if ($inEstoque) {
                $estoqueStartIndex = $i;
            }
            continue;
        }

        if ($inEstoque) {
            $lastEstoqueIndex = $i;

            // Verificar se é a chave QTD_MIN_CHP
            if (preg_match('/^\s*QTD_MIN_CHP\s*=/i', $trimmed)) {
                // Substituir o valor
                $key = preg_split('/\s*=\s*/', $trimmed, 2);
                $lines[$i] = $key[0] . '=' . $qtdMinimo;
                $foundKey = true;
                break;
            }
        }
    }

    // Se encontramos [ESTOQUE] mas não a chave, adicionar no final da seção
    if (!$foundKey && $estoqueStartIndex >= 0) {
        $insertAt = ($lastEstoqueIndex >= 0) ? $lastEstoqueIndex + 1 : $estoqueStartIndex + 1;
        array_splice($lines, $insertAt, 0, ['QTD_MIN_CHP=' . $qtdMinimo]);
        $foundKey = true;
    }

    // Se não existe seção [ESTOQUE], criar no final do arquivo
    if (!$foundKey) {
        $lines[] = '';
        $lines[] = '[ESTOQUE]';
        $lines[] = 'QTD_MIN_CHP=' . $qtdMinimo;
        $foundKey = true;
    }

    if ($foundKey) {
        // Detectar o line ending original
        $lineEnding = (strpos($content, "\r\n") !== false) ? "\r\n" : "\n";
        $newContent = implode($lineEnding, $lines);
        if (@file_put_contents($filePath, $newContent) !== false) {
            return true;
        }
    }

    return false;
}
