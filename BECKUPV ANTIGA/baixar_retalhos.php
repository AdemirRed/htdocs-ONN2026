<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Diretórios onde os materiais e retalhos estão armazenados
$directory_materials = 'C:/CC_DATA_BASE/MAT';
$directory_retalhos = 'C:/CC_DATA_BASE/CHP';

// Data/hora atual conforme fornecido
$current_utc = '2025-06-18 20:24:11'; // UTC exato fornecido
$current_user = 'RedBlack'; // Login exato fornecido
$local_time = date('Y-m-d H:i:s'); // Hora local do servidor

// PREVENÇÃO DE REENVIO NO F5: Usa sessão para rastrear processamento
session_start();

// Função para carregar os materiais a partir dos arquivos INI
function loadMaterials($directory_materials) {
    $files_materials = glob($directory_materials . '/*.INI');
    $materials = [];
    
    foreach ($files_materials as $file) {
        $filename = basename($file, '.INI');
        // M21.INI → código 21, M121.INI → código 121
        $code = intval(ltrim($filename, 'M'));
        
        $data = @parse_ini_file($file, true);
        if ($data === false) continue;
        
        $nome_material = isset($data['DESC']['CAMPO1']) ? str_replace(['(', ')'], '', $data['DESC']['CAMPO1']) : 'Desconhecido';
        $veio_vertical = isset($data['PROP_FISIC']['VEIO_VERTICAL']) ? $data['PROP_FISIC']['VEIO_VERTICAL'] : null;
        $giro = isset($data['PROP_FISIC']['GIRO']) ? $data['PROP_FISIC']['GIRO'] : null;
        $veio = (is_numeric($veio_vertical) && is_numeric($giro) && $veio_vertical == 1 && $giro == 0) ? 'Sim' : 'Não';
        $espessura = isset($data['PROP_FISIC']['ESPESSURA']) ? floatval($data['PROP_FISIC']['ESPESSURA']) : null;
        
        $materials[$code] = [
            'name' => $nome_material,
            'grain' => $veio,
            'espessura' => $espessura,
        ];
    }
    
    // Ordenar os materiais por nome
    uasort($materials, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $materials;
}

function getMaterialByCode($materials, $code) {
    return isset($materials[$code]) ? $materials[$code] : null;
}

// Função auxiliar para normalizar valores numéricos (trata .0 como inteiro)
function normalizeNumericValue($value) {
    $float_val = floatval(trim($value));
    // Se o valor é um número inteiro (sem decimais), retorna como inteiro
    if ($float_val == intval($float_val)) {
        return intval($float_val);
    }
    return $float_val;
}

// FUNÇÃO PARA LISTAR RETALHOS DE UM MATERIAL (seguindo o formato correto)
function listarRetalhosDoMaterial($directory_retalhos, $codigo_material, $materials) {
    // RET00021.TAB → código 21, CHP00213.TAB → código 213
    $possible_files = [
        $directory_retalhos . "/RET" . str_pad($codigo_material, 5, "0", STR_PAD_LEFT) . ".TAB",
        $directory_retalhos . "/CHP" . str_pad($codigo_material, 5, "0", STR_PAD_LEFT) . ".TAB"
    ];
    
    $file_path = null;
    foreach ($possible_files as $path) {
        if (file_exists($path)) {
            $file_path = $path;
            break;
        }
    }
    
    if (!$file_path) {
        return [];
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $retalhos = [];
    
    // Obter dados do material
    $material_data = isset($materials[$codigo_material]) ? $materials[$codigo_material] : null;
    $material_name = $material_data ? $material_data['name'] : 'Desconhecido';
    $material_espessura = $material_data ? $material_data['espessura'] : null;
    
    foreach ($lines as $line) {
        $fields = explode(',', $line);
        if (count($fields) >= 6) {
            // Formato: 1,+,1,1845.0,624.0,C1,,
            // [0] = ignore, [1] = ignore, [2] = quantidade, [3] = largura, [4] = altura, [5] = descrição, [6] = reservados
            $quantidade = intval(trim($fields[2]));
            if ($quantidade > 0) {
                $retalhos[] = [
                    'codigo' => trim($fields[0]),
                    'material' => $material_name,
                    'ativo' => trim($fields[1]),
                    'quantidade' => $quantidade,
                    'largura' => normalizeNumericValue(trim($fields[3])), // largura na posição 3
                    'altura' => normalizeNumericValue(trim($fields[4])),   // altura na posição 4
                    'espessura' => $material_espessura,
                    'descricao' => trim($fields[5]),
                    'reservado' => isset($fields[6]) ? trim($fields[6]) : ''
                ];
            }
        }
    }
    
    // Ordena os retalhos pela área (largura * altura)
    usort($retalhos, function($a, $b) {
        $areaA = $a['largura'] * $a['altura'];
        $areaB = $b['largura'] * $b['altura'];
        return ($areaA < $areaB) ? -1 : (($areaA > $areaB) ? 1 : 0);
    });
    
    return $retalhos;
}

// BUSCA INTELIGENTE QUE ACEITA QUALQUER ORDEM DAS DIMENSÕES (corrigida para largura/altura)
function baixarRetalhoInteligente($directory_retalhos, $codigo_material, $dimensao1, $dimensao2) {
    $possible_files = [
        $directory_retalhos . "/RET" . str_pad($codigo_material, 5, "0", STR_PAD_LEFT) . ".TAB",
        $directory_retalhos . "/CHP" . str_pad($codigo_material, 5, "0", STR_PAD_LEFT) . ".TAB"
    ];
    
    $file_path = null;
    foreach ($possible_files as $path) {
        if (file_exists($path)) {
            $file_path = $path;
            break;
        }
    }
    
    if (!$file_path) {
        return [
            'success' => false,
            'message' => "Arquivo de retalhos não encontrado para o material código $codigo_material.",
            'details' => "Arquivos procurados: RET" . str_pad($codigo_material, 5, "0", STR_PAD_LEFT) . ".TAB ou CHP" . str_pad($codigo_material, 5, "0", STR_PAD_LEFT) . ".TAB"
        ];
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updated_lines = [];
    $found = false;
    $material_info = "";
    $linha_removida = false;
    
    // Normaliza as dimensões de entrada para comparação
    $dim1_norm = normalizeNumericValue($dimensao1);
    $dim2_norm = normalizeNumericValue($dimensao2);
    
    foreach ($lines as $line) {
        $fields = explode(',', $line);
        if (count($fields) < 6) {
            $updated_lines[] = $line;
            continue;
        }
        
        // Normaliza as dimensões do arquivo para comparação
        // [3] = largura, [4] = altura
        $retalho_largura = normalizeNumericValue(trim($fields[3]));
        $retalho_altura = normalizeNumericValue(trim($fields[4]));
        $quantidade = intval(trim($fields[2]));
        $id_retalho = trim($fields[0]);
        $descricao = trim($fields[5]);
        $reservados = isset($fields[6]) ? trim($fields[6]) : '';
        
        // BUSCA INTELIGENTE: Verifica as duas combinações possíveis
        $match_caso1 = ($retalho_largura == $dim1_norm && $retalho_altura == $dim2_norm); // Ordem original
        $match_caso2 = ($retalho_largura == $dim2_norm && $retalho_altura == $dim1_norm); // Ordem invertida
        
        if (($match_caso1 || $match_caso2) && $quantidade > 0) {
            $nova_quantidade = $quantidade - 1;
            $found = true;
            
            // Determina qual caso foi encontrado
            $ordem_encontrada = $match_caso1 ? "Original ({$dim1_norm}x{$dim2_norm})" : "Invertida ({$dim2_norm}x{$dim1_norm})";
            
            if ($nova_quantidade > 0) {
                $fields[2] = $nova_quantidade;
                $updated_lines[] = implode(',', $fields);
                $material_info = "ID: $id_retalho | Descrição: $descricao | Quantidade restante: $nova_quantidade | Ordem: $ordem_encontrada";
            } else {
                $linha_removida = true;
                $material_info = "ID: $id_retalho | Descrição: $descricao | **LINHA REMOVIDA** (quantidade chegou a 0) | Ordem: $ordem_encontrada";
            }
            
            if ($reservados) {
                $material_info .= " | Reservados: $reservados";
            }
            
            // Adiciona informação sobre a correspondência encontrada
            $material_info .= " | Arquivo: {$retalho_largura}x{$retalho_altura}mm";
            
        } else {
            // Linha não corresponde, mantém como está
            $updated_lines[] = $line;
        }
    }
    
    if (!$found) {
        // Debug: mostra todas as dimensões disponíveis se não encontrou
        $debug_info = "\nDimensões disponíveis no arquivo:\n";
        $count = 0;
        foreach ($lines as $line) {
            $fields = explode(',', $line);
            if (count($fields) >= 6 && intval(trim($fields[2])) > 0) {
                $debug_largura = trim($fields[3]);
                $debug_altura = trim($fields[4]);
                $debug_qtd = trim($fields[2]);
                $debug_info .= "• {$debug_largura} x {$debug_altura} (Qtd: {$debug_qtd})\n";
                $count++;
                if ($count >= 5) {
                    $debug_info .= "• ... e mais itens";
                    break;
                }
            }
        }
        
        return [
            'success' => false,
            'message' => "Retalho não encontrado ou sem estoque.",
            'details' => "Material: {$codigo_material} | Procurado: {$dim1_norm}x{$dim2_norm}mm OU {$dim2_norm}x{$dim1_norm}mm | Arquivo: " . basename($file_path) . $debug_info
        ];
    }
    
    if (file_put_contents($file_path, implode(PHP_EOL, $updated_lines)) === false) {
        return [
            'success' => false,
            'message' => "Erro ao atualizar o arquivo de retalhos.",
            'details' => "Verifique as permissões do arquivo: $file_path"
        ];
    }
    
    return [
        'success' => true,
        'message' => $linha_removida ? "Baixa realizada com sucesso! Linha removida." : "Baixa realizada com sucesso!",
        'details' => "Material: {$codigo_material} | Dimensões: {$dim1_norm}x{$dim2_norm}mm | $material_info",
        'file_updated' => basename($file_path),
        'linha_removida' => $linha_removida,
        'total_linhas_restantes' => count($updated_lines)
    ];
}

// FUNÇÃO PARA EXTRAIR DIMENSÕES DE CÓDIGOS COM PONTOS
function extrairDimensoes($codigo_barras) {
    $codigo_barras = trim($codigo_barras);
    
    // Se tem pontos, trata como formato especial
    if (strpos($codigo_barras, '.') !== false) {
        $partes = explode('.', $codigo_barras);
        
        if (count($partes) >= 3) {
            $primeira_parte = $partes[0]; // 000002100555
            $segunda_parte = $partes[1];  // 000440
            
            // Material: primeiros 7 dígitos da primeira parte
            $codigo_material = intval(substr($primeira_parte, 0, 7)); // 0000021 → 21
            
            // Primeira dimensão = resto da primeira parte
            $primeira_dimensao_str = substr($primeira_parte, 7); // 00555
            $primeira_dimensao = intval($primeira_dimensao_str); // 555
            
            // Segunda dimensão = segunda parte inteira
            $segunda_dimensao = intval($segunda_parte); // 440
            
            return [
                'codigo_material' => $codigo_material,
                'dimensao1' => $primeira_dimensao,
                'dimensao2' => $segunda_dimensao,
                'formato' => 'com_pontos'
            ];
        }
    }
    
    // Código sem pontos - trata como numérico puro
    $codigo_limpo = preg_replace('/[^0-9]/', '', $codigo_barras);
    
    if (strlen($codigo_limpo) >= 19) {
        if (strlen($codigo_limpo) == 21) {
            // Formato padrão: 7 + 7 + 7
            $codigo_material = intval(substr($codigo_limpo, 0, 7));
            $dimensao1 = intval(substr($codigo_limpo, 7, 7));
            $dimensao2 = intval(substr($codigo_limpo, 14, 7));
        } else {
            // Formato flexível: divide em 3 partes
            $codigo_material = intval(substr($codigo_limpo, 0, 7));
            
            $restante = substr($codigo_limpo, 7);
            $tam_restante = strlen($restante);
            $meio = intval($tam_restante / 2);
            
            $dimensao1 = intval(substr($restante, 0, $meio));
            $dimensao2 = intval(substr($restante, $meio));
        }
        
        return [
            'codigo_material' => $codigo_material,
            'dimensao1' => $dimensao1,
            'dimensao2' => $dimensao2,
            'formato' => 'numerico'
        ];
    }
    
    return null;
}

function processarCodigoDeBarras($codigo_barras, $materials, $directory_retalhos, $local_time, $current_user) {
    $dados = extrairDimensoes($codigo_barras);
    
    if (!$dados) {
        return [
            'success' => false,
            'message' => "Código de barras inválido.",
            'details' => "Não foi possível extrair material e dimensões do código: '{$codigo_barras}'"
        ];
    }
    
    $codigo_material = $dados['codigo_material'];
    $dimensao1 = $dados['dimensao1'];
    $dimensao2 = $dados['dimensao2'];
    
    $material = getMaterialByCode($materials, $codigo_material);
    if (!$material) {
        return [
            'success' => false,
            'message' => "Material não encontrado.",
            'details' => "Código do material: {$codigo_material} não existe no sistema."
        ];
    }
    
    // USA A BUSCA INTELIGENTE QUE ACEITA QUALQUER ORDEM
    $resultado = baixarRetalhoInteligente($directory_retalhos, $codigo_material, $dimensao1, $dimensao2);
    $resultado['material_name'] = $material['name'];
    $resultado['material_code'] = $codigo_material;
    $resultado['barcode_info'] = [
        'codigo_original' => $codigo_barras,
        'codigo_material' => $codigo_material,
        'dimensao1' => $dimensao1,
        'dimensao2' => $dimensao2,
        'formato' => $dados['formato']
    ];
    $resultado['timestamp'] = $local_time;
    $resultado['user'] = $current_user;
    
    return $resultado;
}

// Carrega os materiais
$materials = loadMaterials($directory_materials);
$resultado = null;

// PREVENÇÃO DE REENVIO NO F5: Gera token único para cada requisição
$form_token = uniqid('form_', true);
$_SESSION['form_token'] = $form_token;

// Processa a requisição POST APENAS se houver dados E token válido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_valido = isset($_POST['form_token']) && isset($_SESSION['last_form_token']) && $_POST['form_token'] === $_SESSION['last_form_token'];
    
    if (!empty($_POST['codigo_barras']) && !$token_valido) {
        // Primeira vez processando este formulário
        $_SESSION['last_form_token'] = $_POST['form_token'];
        $codigo_barras = trim($_POST['codigo_barras']);
        $resultado = processarCodigoDeBarras($codigo_barras, $materials, $directory_retalhos, $local_time, $current_user);
    } elseif (!empty($_POST['codigo_barras']) && $token_valido) {
        // Tentativa de reenvio (F5) - ignora
        error_log("Tentativa de reenvio bloqueada para evitar baixa dupla");
    }
}

// API para busca em tempo real de materiais
if (isset($_GET['api']) && $_GET['api'] === 'material' && isset($_GET['codigo'])) {
    header('Content-Type: application/json');
    $codigo = intval($_GET['codigo']);
    $material = getMaterialByCode($materials, $codigo);
    echo json_encode($material);
    exit;
}

// API para listar retalhos de um material
if (isset($_GET['api']) && $_GET['api'] === 'retalhos' && isset($_GET['material'])) {
    header('Content-Type: application/json');
    $codigo_material = intval($_GET['material']);
    $retalhos = listarRetalhosDoMaterial($directory_retalhos, $codigo_material, $materials);
    echo json_encode($retalhos);
    exit;
}

// API para listar todos os materiais
if (isset($_GET['api']) && $_GET['api'] === 'materiais') {
    header('Content-Type: application/json');
    echo json_encode($materials);
    exit;
}

// Pega o IP da rede local para instruções
$server_ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '192.168.x.x';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>▲ NEXUS RETALHOS - Sistema Dark</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>▲</text></svg>">
    <link rel="stylesheet" href="styles_Baixa.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>▲ NEXUS RETALHOS</h1>
            <p>Sistema Dark de Gestão Avançada</p>
        </div>
        
        <!-- Grid Principal -->
        <div class="main-grid">
            <!-- Card de Scanner -->
            <div class="glass-card">
                <h2 class="card-title">Scanner de Códigos</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon">🕒</span>
                        <div class="stat-label">Local</div>
                        <div class="stat-value"><?= date('H:i') ?></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">👤</span>
                        <div class="stat-label">DEV</div>
                        <div class="stat-value"><?= htmlspecialchars($current_user) ?></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon">📦</span>
                        <div class="stat-label">Materials</div>
                        <div class="stat-value"><?= count($materials) ?></div>
                    </div>
                </div>
                
                <form method="POST" id="barcodeForm" class="form-section">
                    <!-- Token para prevenir reenvio no F5 -->
                    <input type="hidden" name="form_token" value="<?= htmlspecialchars($form_token) ?>">
                    
                    <div class="form-group">
                        <label for="codigo_barras" class="form-label">▶ Código de Barras</label>
                        <input type="text" 
                               name="codigo_barras" 
                               id="codigo_barras" 
                               class="form-input"
                               placeholder="000000000000.000000.0" 
                               autocomplete="off"
                               maxlength="50"
                               required>
                        <div id="decode-info" class="decode-display">
                            Aguardando código...
                        </div>
                    </div>
                    
                    <div class="btn-grid">
                        <button type="submit" class="btn btn-primary">
                            <span>✓ Confirmar Baixa</span>
                        </button>
                        <button type="button" class="btn btn-secondary" id="cameraBtn">
                            <span>📷 Ativar Scanner</span>
                        </button>
                    </div>
                </form>
                
                <!-- Container da Câmera -->
                <div class="camera-container" id="cameraContainer">
                    <div style="position: relative;">
                        <video id="preview" autoplay muted playsinline></video>
                        <div class="scanner-overlay"></div>
                        <div class="camera-popup" id="cameraPopup">🔍 Procurando código...</div>
                    </div>
                    <div class="camera-status" id="status">Clique em "Ativar Scanner" para começar</div>
                    <button type="button" class="btn btn-secondary" id="stopCameraBtn" style="margin-top: 15px;">
                        <span>⏹ Parar Scanner</span>
                    </button>
                </div>
            </div>
            
            <!-- Card de Resultado -->
            <div class="glass-card">
                <h2 class="card-title">Resultado da Operação</h2>
                
                <?php if ($resultado): ?>
                    <div class="result <?= $resultado['success'] ? 'success' : 'error' ?>">
                        <strong><?= htmlspecialchars($resultado['message']) ?></strong>
                        
                        <?php if (isset($resultado['linha_removida']) && $resultado['linha_removida']): ?>
                            <div class="linha-removida">
                                🗑️ LINHA REMOVIDA (quantidade = 0)
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($resultado['details'])): ?>
                            <div class="result-details">
                                <?= htmlspecialchars($resultado['details']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($resultado['material_name'])): ?>
                            <div class="result-details">
                                <strong>Material:</strong> <?= htmlspecialchars($resultado['material_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($resultado['user'])): ?>
                            <div class="result-details">
                                <strong>Usuário:</strong> <?= htmlspecialchars($resultado['user']) ?><br>
                                <strong>Timestamp:</strong> <?= htmlspecialchars($resultado['timestamp']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                        <div style="font-size: 4rem; margin-bottom: 20px;">⚡</div>
                        <h3>Sistema Pronto</h3>
                        <p>Aguardando processamento de código...</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Seção de Gerenciamento -->
        <div class="management-section">
            <h2 class="section-title">⚡ Painel de Controle</h2>
            
            <div class="management-grid">
                <!-- Card Listar Materiais -->
                <div class="management-card">
                    <span class="card-icon">📋</span>
                    <h3 class="card-title-mgmt">Database de Materiais</h3>
                    <p class="card-description">Visualize todos os materiais cadastrados no sistema com códigos, nomes e especificações técnicas detalhadas.</p>
                    <button class="btn btn-tertiary" id="listarMateriaisBtn">
                        <span>📋 Acessar Database</span>
                    </button>
                </div>
                
                <!-- Card Consultar Retalhos -->
                <div class="management-card">
                    <span class="card-icon">🔍</span>
                    <h3 class="card-title-mgmt">Consulta de Retalhos</h3>
                    <p class="card-description">Consulte retalhos disponíveis por material, incluindo dimensões, quantidades e status de reserva.</p>
                    <div style="margin-top: 20px;">
                        <input type="number" id="materialConsulta" class="form-input" placeholder="Código do Material" style="margin-bottom: 15px;">
                        <button class="btn btn-tertiary" id="consultarRetalhosBtn">
                            <span>🔍 Executar Consulta</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Materiais -->
    <div id="materiaisModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="closeMateriaisModal">&times;</button>
            <h2 class="modal-title">📋 Database de Materiais</h2>
            <div class="materials-list" id="materialsContainer">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <p class="loading-text">Carregando database...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Retalhos -->
    <div id="retalhosModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" id="closeRetalhosModal">&times;</button>
            <h2 class="modal-title">🔍 Retalhos do Material</h2>
            <div class="retalhos-list" id="retalhosContainer">
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <p class="loading-text">Carregando dados...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        const video = document.getElementById('preview');
        const codigoInput = document.getElementById('codigo_barras');
        const form = document.getElementById('barcodeForm');
        const status = document.getElementById('status');
        const cameraContainer = document.getElementById('cameraContainer');
        const cameraBtn = document.getElementById('cameraBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');
        const cameraPopup = document.getElementById('cameraPopup');
        let scanning = false;
        let stream = null;
        let barcodeDetector = null;
        let lastDetectionTime = 0;
        let debounceTimer = null;

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            checkCameraSupport();
            updateDecodeInfo(codigoInput.value);
            setupEventListeners();
        });

        // Setup de todos os event listeners
        function setupEventListeners() {
            // Botões principais
            cameraBtn.addEventListener('click', toggleCamera);
            stopCameraBtn.addEventListener('click', stopCamera);
            
            // Botões de gerenciamento
            document.getElementById('listarMateriaisBtn').addEventListener('click', listarMateriais);
            document.getElementById('consultarRetalhosBtn').addEventListener('click', consultarRetalhos);
            
            // Botões de fechar modais
            document.getElementById('closeMateriaisModal').addEventListener('click', () => fecharModal('materiaisModal'));
            document.getElementById('closeRetalhosModal').addEventListener('click', () => fecharModal('retalhosModal'));
            
            // Fechar modal clicando fora
            document.getElementById('materiaisModal').addEventListener('click', (e) => {
                if (e.target.id === 'materiaisModal') fecharModal('materiaisModal');
            });
            document.getElementById('retalhosModal').addEventListener('click', (e) => {
                if (e.target.id === 'retalhosModal') fecharModal('retalhosModal');
            });
            
            // Input de consulta com Enter
            document.getElementById('materialConsulta').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') consultarRetalhos();
            });
        }

        // Atualização em tempo real do código
        codigoInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                updateDecodeInfo(e.target.value);
                
                // Se o código tem pelo menos 7 dígitos, tenta buscar o material
                const codigo = e.target.value.replace(/[^0-9]/g, '');
                if (codigo.length >= 7) {
                    const codigoMaterial = parseInt(codigo.substring(0, 7));
                    buscarMaterialPorCodigo(codigoMaterial);
                }
            }, 500);
        });

        function buscarMaterialPorCodigo(codigo) {
            fetch(`?api=material&codigo=${codigo}`)
                .then(response => response.json())
                .then(material => {
                    if (material && material.name) {
                        const decodeInfo = document.getElementById('decode-info');
                        const currentInfo = decodeInfo.innerHTML.split('<br>')[0]; // Pega só a primeira linha
                        decodeInfo.innerHTML = currentInfo + `<br><strong>📦 ${material.name}</strong> (${material.espessura}mm)`;
                    }
                })
                .catch(err => console.log('Material não encontrado'));
        }

        function updateDecodeInfo(value) {
            const info = document.getElementById('decode-info');
            
            if (value.length < 15) {
                info.innerHTML = `⚠ Código muito curto (${value.length} chars)`;
                info.className = 'decode-display warning';
                return;
            }
            
            if (value.includes('.')) {
                const partes = value.split('.');
                if (partes.length >= 3) {
                    const primeira = partes[0];
                    const segunda = partes[1];
                    
                    const material = parseInt(primeira.substring(0, 7));
                    const dim1 = parseInt(primeira.substring(7));
                    const dim2 = parseInt(segunda);
                    
                    info.innerHTML = `▲ MAT: ${material} | DIM1: ${dim1}mm | DIM2: ${dim2}mm`;
                    info.className = 'decode-display active';
                } else {
                    info.innerHTML = `⚠ Formato com pontos incompleto (${value.length} chars)`;
                    info.className = 'decode-display warning';
                }
            } else {
                const numeros = value.replace(/[^0-9]/g, '');
                if (numeros.length >= 19) {
                    const material = parseInt(numeros.substring(0, 7));
                    const dim1 = parseInt(numeros.substring(7, 14));
                    const dim2 = parseInt(numeros.substring(14, 21));
                    
                    info.innerHTML = `▲ MAT: ${material} | DIM1: ${dim1}mm | DIM2: ${dim2}mm`;
                    info.className = 'decode-display active';
                } else {
                    info.innerHTML = `⚠ Formato numérico incompleto (${numeros.length}/21 dígitos)`;
                    info.className = 'decode-display warning';
                }
            }
        }

        // Funções da câmera
        function showCameraPopup(message, type = 'default') {
            cameraPopup.className = 'camera-popup show';
            if (type === 'success') {
                cameraPopup.classList.add('success');
            } else if (type === 'error') {
                cameraPopup.classList.add('error');
            } else if (type === 'detecting') {
                cameraPopup.classList.add('detecting');
            }
            cameraPopup.innerHTML = message;
        }

        function hideCameraPopup() {
            cameraPopup.className = 'camera-popup';
        }

        function checkCameraSupport() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                cameraBtn.disabled = true;
                cameraBtn.innerHTML = '<span>❌ Câmera Não Suportada</span>';
                return;
            }
            
            if ('BarcodeDetector' in window) {
                console.log('✅ BarcodeDetector suportado');
            } else {
                console.log('⚠️ BarcodeDetector não suportado');
            }
        }

        function toggleCamera() {
            if (!stream) {
                initCamera();
            } else {
                stopCamera();
            }
        }

        function stopCamera() {
            scanning = false;
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            video.style.display = 'none';
            video.srcObject = null;
            cameraContainer.classList.remove('active');
            hideCameraPopup();
            status.innerHTML = 'Scanner desativado.';
            cameraBtn.innerHTML = '<span>📷 Ativar Scanner</span>';
        }

        async function initCamera() {
            try {
                cameraContainer.classList.add('active');
                cameraBtn.innerHTML = '<span>🔄 Inicializando...</span>';
                status.innerHTML = '🔄 Solicitando acesso à câmera...';
                
                const constraints = {
                    video: { 
                        facingMode: { ideal: "environment" },
                        width: { ideal: 1920, min: 1280 },
                        height: { ideal: 1080, min: 720 },
                        frameRate: { ideal: 30, min: 15 }
                    }
                };

                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                video.style.display = 'block';
                status.innerHTML = '🚀 Scanner ativo! Posicione o código na área cyan.';
                cameraBtn.innerHTML = '<span>🛑 Parar Scanner</span>';
                showCameraPopup('🚀 Scanner futurista ativo...');
                
                video.addEventListener('loadedmetadata', () => {
                    video.play().then(() => {
                        initBarcodeDetection();
                    });
                });
                
            } catch (err) {
                let errorMessage = '❌ Erro: ';
                switch(err.name) {
                    case 'NotAllowedError':
                        errorMessage += 'Permissão negada';
                        break;
                    case 'NotFoundError':
                        errorMessage += 'Câmera não encontrada';
                        break;
                    default:
                        errorMessage += err.message;
                }
                
                status.innerHTML = errorMessage;
                showCameraPopup(errorMessage, 'error');
                cameraBtn.innerHTML = '<span>📷 Ativar Scanner</span>';
                video.style.display = 'none';
            }
        }

        function initBarcodeDetection() {
            if ('BarcodeDetector' in window) {
                try {
                    barcodeDetector = new BarcodeDetector({
                        formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'codabar', 'itf']
                    });
                    startBarcodeDetection();
                } catch (err) {
                    status.innerHTML = '⚠️ Detecção automática falhou. Digite manualmente.';
                    showCameraPopup('⚠️ Detecção manual necessária', 'error');
                }
            } else {
                status.innerHTML = '⚠️ Detecção automática não suportada.';
                showCameraPopup('⚠️ Detecção manual necessária', 'error');
            }
        }

        async function startBarcodeDetection() {
            if (!barcodeDetector || !stream) return;

            scanning = true;
            status.innerHTML = '🚀 Scanner ativo! Procurando códigos...';
            showCameraPopup('🔍 Escaneando...');

            const detectBarcodes = async () => {
                if (!scanning || !video.videoWidth || video.paused) {
                    if (scanning) setTimeout(detectBarcodes, 66);
                    return;
                }

                try {
                    const barcodes = await barcodeDetector.detect(video);
                    
                    if (barcodes.length > 0) {
                        const now = Date.now();
                        if (now - lastDetectionTime < 2000) {
                            setTimeout(detectBarcodes, 66);
                            return;
                        }
                        
                        const codigo = barcodes[0].rawValue;
                        showCameraPopup(`🚀 Detectado: ${codigo}`, 'detecting');
                        
                        if (codigo && codigo.length >= 15) {
                            lastDetectionTime = now;
                            scanning = false;
                            
                            codigoInput.value = codigo;
                            updateDecodeInfo(codigo);
                            
                            if (navigator.vibrate) {
                                navigator.vibrate([300, 100, 300]);
                            }
                            
                            status.innerHTML = '✅ Código detectado! Processando...';
                            showCameraPopup('✅ Processando...', 'success');
                            
                            setTimeout(() => {
                                stopCamera();
                                form.submit();
                            }, 2000);
                            
                            return;
                        }
                    } else {
                        showCameraPopup('🔍 Procurando código...');
                    }
                    
                    setTimeout(detectBarcodes, 66);
                } catch (err) {
                    setTimeout(detectBarcodes, 100);
                }
            };

            detectBarcodes();
        }

        // Funções de Gerenciamento
        function listarMateriais() {
            document.getElementById('materiaisModal').style.display = 'block';
            const container = document.getElementById('materialsContainer');
            
            // Reseta o conteúdo
            container.innerHTML = `
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <p class="loading-text">Carregando database...</p>
                </div>
            `;
            
            fetch('?api=materiais')
                .then(response => response.json())
                .then(materials => {
                    let html = '';
                    
                    Object.entries(materials).forEach(([code, material]) => {
                        html += `
                            <div class="material-item">
                                <div class="item-header">
                                    <div class="item-code">${code}</div>
                                    <div class="item-badge">${material.espessura}mm</div>
                                </div>
                                <div class="item-name">${material.name}</div>
                                <div class="item-details">Veio: ${material.grain} | Espessura: ${material.espessura}mm</div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: var(--error);">
                            <h3>❌ Erro ao carregar materiais</h3>
                            <p>${err.message}</p>
                        </div>
                    `;
                });
        }

        function consultarRetalhos() {
            const codigoMaterial = document.getElementById('materialConsulta').value;
            
            if (!codigoMaterial) {
                alert('Por favor, digite o código do material');
                return;
            }
            
            document.getElementById('retalhosModal').style.display = 'block';
            const container = document.getElementById('retalhosContainer');
            
            // Reseta o conteúdo
            container.innerHTML = `
                <div class="loading-container">
                    <div class="loading-spinner"></div>
                    <p class="loading-text">Carregando retalhos do material ${codigoMaterial}...</p>
                </div>
            `;
            
            fetch(`?api=retalhos&material=${codigoMaterial}`)
                .then(response => response.json())
                .then(retalhos => {
                    if (retalhos.length === 0) {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: var(--warning);">
                                <h3>⚠️ Nenhum retalho encontrado</h3>
                                <p>Material ${codigoMaterial} não possui retalhos disponíveis</p>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = `
                        <div style="margin-bottom: 20px; padding: 15px; background: var(--bg-tertiary); border-radius: 10px;">
                            <strong>Material:</strong> ${retalhos[0].material}<br>
                            <strong>Espessura:</strong> ${retalhos[0].espessura}mm<br>
                            <strong>Total de retalhos:</strong> ${retalhos.length}
                        </div>
                    `;
                    
                    retalhos.forEach(retalho => {
                        html += `
                            <div class="retalho-item">
                                <div class="item-header">
                                    <div class="item-code">${retalho.largura}x${retalho.altura}mm</div>
                                    <div class="item-badge">Qtd: ${retalho.quantidade}</div>
                                </div>
                                <div class="item-name">ID: ${retalho.codigo} | ${retalho.descricao}</div>
                                <div class="item-details">
                                    Área: ${(retalho.largura * retalho.altura / 1000000).toFixed(2)}m²
                                    ${retalho.reservado ? ` | Reservado: ${retalho.reservado}` : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                })
                .catch(err => {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: var(--error);">
                            <h3>❌ Erro ao carregar retalhos</h3>
                            <p>${err.message}</p>
                        </div>
                    `;
                });
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Event listeners
        window.addEventListener('beforeunload', stopCamera);

        form.addEventListener('submit', (e) => {
            const codigo = codigoInput.value.trim();
            
            if (!codigo || codigo.length < 15) {
                e.preventDefault();
                alert('Por favor, digite um código com pelo menos 15 caracteres.');
                return false;
            }
        });

        // Previne zoom no iOS
        document.addEventListener('touchstart', function(e) {
            if (e.touches.length > 1) {
                e.preventDefault();
            }
        }, { passive: false });

        // Limpar o campo quando a página carrega para evitar reenvios
        window.addEventListener('load', function() {
            codigoInput.value = '';
            updateDecodeInfo('');
        });
    </script>
</body>
</html>