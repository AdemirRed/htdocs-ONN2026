<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['codigo']) || !isset($_POST['quantidade'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = trim($_POST['codigo']);
$novaQtd = (int)$_POST['quantidade'];

if ($novaQtd < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Quantidade deve ser positiva'], JSON_UNESCAPED_UNICODE);
    exit;
}

$basePath = getenv('CC_DATA_BASE_PATH') ?: 'C:\\CC_DATA_BASE';
$chpDir = rtrim($basePath, "\\") . '\\CHP';

if (!is_dir($chpDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Diretório CHP não encontrado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$candidates = buildChpCandidates($codigo);
$chpFile = null;

foreach ($candidates as $fileName) {
    $path = $chpDir . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($path)) {
        $chpFile = $path;
        break;
    }
}

if ($chpFile === null) {
    $glob = glob($chpDir . DIRECTORY_SEPARATOR . 'CHP*' . $codigo . '*.TAB');
    if ($glob !== false && count($glob) > 0) {
        $chpFile = $glob[0];
    }
}

if ($chpFile === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo CHP não encontrado para o código ' . $codigo], JSON_UNESCAPED_UNICODE);
    exit;
}

$updated = updateChpQuantities($chpFile, $novaQtd);

if ($updated) {
    echo json_encode(['success' => 'Quantidade atualizada com sucesso'], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar arquivo CHP'], JSON_UNESCAPED_UNICODE);
}

function buildChpCandidates(string $code): array
{
    $padded4 = str_pad($code, 4, '0', STR_PAD_LEFT);
    $padded5 = str_pad($code, 5, '0', STR_PAD_LEFT);
    $padded6 = str_pad($code, 6, '0', STR_PAD_LEFT);

    return [
        "CHP00{$code}.TAB",
        "CHP0{$code}.TAB",
        "CHP{$code}.TAB",
        "CHP{$padded4}.TAB",
        "CHP{$padded5}.TAB",
        "CHP{$padded6}.TAB",
    ];
}

function updateChpQuantities(string $filePath, int $novaQtd): bool
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }

    $updated = false;

    foreach ($lines as $key => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === ';' || $trimmed[0] === '#') {
            continue;
        }

        // Parse para validação usando qualquer whitespace
        $parts = preg_split('/[\s;|]+/', $trimmed);
        if (count($parts) < 5) {
            continue;
        }

        $width = parseNumber($parts[3]);
        $height = parseNumber($parts[4]);

        if ($width === null || $height === null) {
            continue;
        }

        if (isBaseSheetDimension($width, $height)) {
            // Atualizar quantidade mantendo o formato correto com espaços fixos
            // Formato: col1(8 espaços)col2(6 espaços)col3(6 espaços)col4(1 espaço)col5(1 espaço)resto
            $parts[2] = (string)$novaQtd;
            
            // Reconstruir a linha com espaçamento fixo correto
            // Campo 1: valor + espaços para completar 8 posições
            // Campo 2: valor + espaços para completar 6 posições
            // Campo 3: valor + espaços para completar 6 posições
            // Campo 4: valor + 1 espaço
            // Campo 5: valor + 1 espaço
            // Resto: demais campos separados por 1 espaço
            $rebuilt = str_pad($parts[0], 8, ' ', STR_PAD_RIGHT);
            $rebuilt .= str_pad($parts[1], 7, ' ', STR_PAD_RIGHT);
            $rebuilt .= $parts[2] . ' ';
            $rebuilt .= $parts[3] . ' ';
            $rebuilt .= $parts[4] . ' ';
            
            // Adicionar campos restantes (6 em diante)
            for ($i = 5; $i < count($parts); $i++) {
                $rebuilt .= $parts[$i];
                if ($i < count($parts) - 1) {
                    $rebuilt .= ' ';
                }
            }
            
            $lines[$key] = $rebuilt;
            $updated = true;
        }
    }

    if ($updated) {
        $content = implode("\r\n", $lines) . "\r\n";
        if (@file_put_contents($filePath, $content) !== false) {
            return true;
        }
    }

    return $updated;
}

function parseNumber($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $value = str_replace([' ', 'mm', 'MM'], '', $value);
    $value = preg_replace('/[^0-9,.-]/', '', $value);

    if ($value === '') {
        return null;
    }

    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace(',', '.', $value);
    }

    if (!is_numeric($value)) {
        return null;
    }

    return floatval($value);
}

function isBaseSheetDimension($width, $height): bool
{
    return (
        ($width >= 1830 && $width <= 1850 && $height >= 2730 && $height <= 2750) ||
        ($width >= 2730 && $width <= 2750 && $height >= 1830 && $height <= 1850)
    );
}
