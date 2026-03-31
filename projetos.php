<?php
/**
 * Sistema de Gestão de Projetos - Corte Certo Plus
 * Versão melhorada com estrutura organizada e visual moderno
 */

// Configurações
define('PASTA_PROJETOS', 'C:/corte_certo_plus/PED');
define('TITULO_SISTEMA', 'Corte Certo Plus - Gestão de Projetos');

class ProjetoManager {
    private $pastaProjetos;
    
    public function __construct($pastaProjetos) {
        $this->pastaProjetos = $pastaProjetos;
    }
    
    /**
     * Lista todos os projetos disponíveis
     */
    public function listarProjetos() {
        return glob("{$this->pastaProjetos}/*.INI");
    }
    
    /**
     * Lista todos os projetos com detalhes para exibição
     */
    public function listarProjetosComDetalhes() {
        $arquivos = $this->listarProjetos();
        $projetos = [];
        
        foreach ($arquivos as $arquivo) {
            $id = preg_replace('/\D/', '', basename($arquivo, ".INI"));
            $detalhes = $this->obterDetalhesBasicosProjeto($arquivo);
            
            if (file_exists($arquivo)) {
                $dadosIni = @parse_ini_file($arquivo, false, INI_SCANNER_RAW);
                
                if ($dadosIni === false) {
                    // If parse fails, still show the project with basic info
                    $projetos[] = [
                        'id' => $id,
                        'nome' => $detalhes['nome'],
                        'marceneiro' => $detalhes['marceneiro'],
                        'status' => $detalhes['status'],
                        'statusTexto' => $this->obterStatusTexto($detalhes['status']),
                        'statusCor' => $this->obterStatusCor($detalhes['status']),
                        'dataCriacao' => 'Não informado',
                        'ultimaModificacao' => 'Não informado',
                        'dataOrdenacao' => 0
                    ];
                } else {
                    $ultimaMod = $dadosIni['ULT_MODIF'] ?? '';
                    $projetos[] = [
                        'id' => $id,
                        'nome' => $detalhes['nome'],
                        'marceneiro' => $detalhes['marceneiro'],
                        'status' => $detalhes['status'],
                        'statusTexto' => $this->obterStatusTexto($detalhes['status']),
                        'statusCor' => $this->obterStatusCor($detalhes['status']),
                        'dataCriacao' => $this->formatarData($dadosIni['CRIACAO'] ?? ''),
                        'ultimaModificacao' => $this->formatarData($ultimaMod),
                        'dataOrdenacao' => $this->converterDataParaTimestamp($ultimaMod)
                    ];
                }
            }
        }
        
        // Ordenar por código do projeto (maior para menor)
        usort($projetos, function($a, $b) {
            return (int)$b['id'] - (int)$a['id'];
        });
        
        return $projetos;
    }
    
    /**
     * Converte data para timestamp para ordenação
     */
    private function converterDataParaTimestamp($data) {
        if (empty($data) || $data === 'Desconhecido') {
            return 0;
        }
        
        // Formato YYYYMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $data, $matches)) {
            return strtotime($matches[1] . '-' . $matches[2] . '-' . $matches[3]);
        }
        
        // Tenta converter diretamente
        $timestamp = strtotime($data);
        return $timestamp !== false ? $timestamp : 0;
    }
    
    /**
     * Lista todos os marceneiros únicos dos projetos
     */
    public function listarMarceneiros() {
        $projetos = $this->listarProjetos();
        $marceneiros = [];
        
        foreach ($projetos as $projeto) {
            if (file_exists($projeto)) {
                $dadosIni = @parse_ini_file($projeto, false, INI_SCANNER_RAW);
                if ($dadosIni === false) {
                    continue; // Skip files with parse errors
                }
                $marceneiro = $this->limparTexto($dadosIni['TIPO_PRJ'] ?? '');
                
                if (!empty($marceneiro) && $marceneiro !== 'Não informado') {
                    $marceneiros[] = $marceneiro;
                }
            }
        }
        
        $marceneiros = array_unique($marceneiros);
        sort($marceneiros);
        
        return $marceneiros;
    }
    
    /**
     * Filtra projetos baseado nos critérios fornecidos
     */
    public function filtrarProjetos($filtros = []) {
        $projetos = $this->listarProjetos();
        $projetosFiltrados = [];
        
        foreach ($projetos as $projeto) {
            $id = preg_replace('/\D/', '', basename($projeto, ".INI"));
            $detalhes = $this->obterDetalhesBasicosProjeto($projeto);
            
            // Aplicar filtros
            $passouFiltro = true;
            
            // Filtro por número do projeto
            if (!empty($filtros['numero']) && strpos($id, $filtros['numero']) === false) {
                $passouFiltro = false;
            }
            
            // Filtro por marceneiro
            if (!empty($filtros['marceneiro']) && $filtros['marceneiro'] !== 'todos') {
                if (stripos($detalhes['marceneiro'], $filtros['marceneiro']) === false) {
                    $passouFiltro = false;
                }
            }
            
            // Filtro por nome do cliente (usando o campo CAMPO1 que contém o nome do projeto/cliente)
            if (!empty($filtros['cliente'])) {
                if (stripos($detalhes['nome'], $filtros['cliente']) === false) {
                    $passouFiltro = false;
                }
            }
            
            if ($passouFiltro) {
                $projetosFiltrados[] = [
                    'arquivo' => $projeto,
                    'id' => $id,
                    'detalhes' => $detalhes
                ];
            }
        }
        
        return $projetosFiltrados;
    }
    
    /**
     * Obtém detalhes básicos do projeto para filtros
     */
    private function obterDetalhesBasicosProjeto($arquivo) {
        $detalhes = [
            'nome' => 'Projeto Desconhecido',
            'marceneiro' => 'Não informado',
            'status' => '0'
        ];
        
        if (file_exists($arquivo)) {
            $dadosIni = @parse_ini_file($arquivo, false, INI_SCANNER_RAW);
            
            if ($dadosIni !== false) {
                $detalhes['nome'] = $this->obterNomeProjeto($arquivo);
                $detalhes['marceneiro'] = $this->limparTexto($dadosIni['TIPO_PRJ'] ?? 'Não informado');
                $detalhes['status'] = trim($dadosIni['STATUS'] ?? '0');
            } else {
                $detalhes['nome'] = $this->obterNomeProjeto($arquivo);
            }
        }
        
        return $detalhes;
    }
    
    /**
     * Limpa e formata texto removendo caracteres especiais
     */
    public function limparTexto($texto) {
        $replacements = [
            '#|#' => ' ',
            '_' => ' ',
            '(' => ' ',
            ')' => ' ',
            '°' => 'GRAU',
            'º' => 'GRAU'
        ];
        
        $texto = str_replace(array_keys($replacements), array_values($replacements), $texto);
        $texto = preg_replace('/@[A-Za-z0-9]+/', '', $texto);
        
        return trim($texto);
    }
    
    /**
     * Remove acentos e caracteres especiais
     */
    public function removerAcentos($string) {
        $acentos = ['á','ã','â','à','é','ê','í','ó','õ','ô','ú','ç','Á','Ã','Â','À','É','Ê','Í','Ó','Õ','Ô','Ú','Ç','¿'];
        $semAcentos = ['a','a','a','a','e','e','i','o','o','o','u','c','A','A','A','A','E','E','I','O','O','O','U','C','?'];
        
        return str_replace($acentos, $semAcentos, $string);
    }
    
    /**
     * Obtém o nome do projeto do arquivo INI
     */
    public function obterNomeProjeto($arquivo) {
        if (!file_exists($arquivo)) {
            return "Projeto Desconhecido";
        }
        
        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($linhas as $linha) {
            if (stripos($linha, 'CAMPO1=') === 0) {
                $nomeProjeto = trim(substr($linha, 7));
                return $this->removerAcentos($this->limparTexto($nomeProjeto));
            }
        }
        
        return "Projeto Desconhecido";
    }
    
    /**
     * Obtém detalhes completos do projeto
     */
    public function obterDetalhesProjeto($idProjeto) {
        $arquivoIni = "{$this->pastaProjetos}/DSC{$idProjeto}.INI";
        $arquivoTab = "{$this->pastaProjetos}/PED{$idProjeto}.TAB";
        
        $resultado = [
            'existe' => false,
            'dados' => [],
            'itens' => []
        ];
        
        if (!file_exists($arquivoIni)) {
            return $resultado;
        }
        
        $dadosIni = @parse_ini_file($arquivoIni, false, INI_SCANNER_RAW);
        if ($dadosIni === false) {
            return $resultado; // Return not found if parse fails
        }
        $status = trim($dadosIni['STATUS'] ?? '0');
        
        $resultado['existe'] = true;
        $resultado['dados'] = [
            'id' => $idProjeto,
            'nome' => $this->obterNomeProjeto($arquivoIni),
            'status' => $this->obterStatusTexto($status),
            'statusCor' => $this->obterStatusCor($status),
            'marceneiro' => $this->limparTexto($dadosIni['TIPO_PRJ'] ?? 'Não informado'),
            'editadoPor' => $this->limparTexto($dadosIni['CAMPO10'] ?? 'Não informado'),
            'cortadoPor' => $this->limparTexto($dadosIni['CAMPO2'] ?? 'Não informado'),
            'estoqueAtualizado' => ($dadosIni['ESTOQUE _ATUALIZADO'] ?? 0) == 1 ? 'Sim' : 'Não',
            'dataCriacao' => $this->formatarData($dadosIni['CRIACAO'] ?? ''),
            'ultimaModificacao' => $this->formatarData($dadosIni['ULT_MODIF'] ?? '')
        ];
        
        // Processar itens do arquivo TAB
        if (file_exists($arquivoTab)) {
            $resultado['itens'] = $this->processarItensTab($arquivoTab);
        }
        
        return $resultado;
    }
    
    /**
     * Converte código de status em texto legível
     */
    private function obterStatusTexto($status) {
        return match ($status) {
            '7' => 'Desconhecido',
            '6' => 'Material Indefinido!',
            '5' => 'Em Análise',
            '4' => 'Esperando Chapas',
            '3' => 'Cancelado',
            '2' => 'Concluído',
            '1' => 'Cortando',
            '0' => 'Aberto',
            default => 'Desconhecido'
        };
    }
    
    /**
     * Retorna cor para o status
     */
    private function obterStatusCor($status) {
        return match ($status) {
            '2' => 'success',    // Concluído
            '1' => 'warning',    // Cortando
            '3' => 'danger',     // Cancelado
            '6' => 'danger',     // Material Indefinido
            '5' => 'info',       // Em Análise
            '4' => 'secondary',  // Esperando Chapas
            '0' => 'primary',    // Aberto
            default => 'secondary'
        };
    }
    
    /**
     * Formata data para exibição
     */
    private function formatarData($data) {
        if (empty($data) || $data === 'Desconhecido') {
            return 'Não informado';
        }
        
        // Tenta converter diferentes formatos de data
        // Formato esperado: YYYYMMDD ou YYYY-MM-DD ou DD/MM/YYYY
        $data = trim($data);
        
        // Se já estiver em formato legível, retorna
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $data)) {
            return $data;
        }
        
        // Formato YYYYMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $data, $matches)) {
            return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
        }
        
        // Formato YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $matches)) {
            return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
        }
        
        // Tenta usar strtotime
        $timestamp = strtotime($data);
        if ($timestamp !== false) {
            return date('d/m/Y', $timestamp);
        }
        
        return $data;
    }
    
    /**
     * Processa itens do arquivo TAB
     */
    private function processarItensTab($arquivoTab) {
        $itens = [];
        $linhas = file($arquivoTab, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($linhas as $linha) {
            $dados = preg_split('/\s+/', $linha);
            
            if (count($dados) >= 9) {
                $materialCodigo = $this->removerAcentos($this->limparTexto($dados[5]));
                $itens[] = [
                    'codigo' => $dados[0],
                    'quantidade' => $dados[2],
                    'altura' => $dados[3],
                    'largura' => $dados[4],
                    'material' => $materialCodigo,
                    'materialNome' => $this->obterNomeMaterial($materialCodigo),
                    'descricao' => $this->removerAcentos($this->limparTexto($dados[6])),
                    'ambiente' => $this->removerAcentos($this->limparTexto(end($dados)))
                ];
            }
        }
        
        return $itens;
    }
    
    /**
     * Lista todos os materiais disponíveis no banco de dados
     */
    public function listarTodosMateriais() {
        $pastaMateriais = 'C:/CC_DATA_BASE/MAT';
        $arquivos = glob("{$pastaMateriais}/M*.INI");
        $materiais = [];
        
        foreach ($arquivos as $arquivo) {
            $codigo = preg_replace('/\D/', '', basename($arquivo, '.INI'));
            
            if (file_exists($arquivo)) {
                $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($linhas as $linha) {
                    if (stripos($linha, 'CAMPO1=') === 0) {
                        $nomeMaterial = trim(substr($linha, 7));
                        $materiais[$codigo] = [
                            'codigo' => $codigo,
                            'nome' => $this->removerAcentos($this->limparTexto($nomeMaterial)),
                            'nomeOriginal' => $nomeMaterial
                        ];
                        break;
                    }
                }
            }
        }
        
        // Ordenar por código
        ksort($materiais);
        
        return $materiais;
    }
    
    /**
     * Obtém o nome do material a partir do código
     * Lê o arquivo M{código}.INI da pasta C:\CC_DATA_BASE\MAT
     */
    private function obterNomeMaterial($codigoMaterial) {
        // Remove caracteres não numéricos do código
        $codigo = preg_replace('/\D/', '', $codigoMaterial);
        
        if (empty($codigo)) {
            return $codigoMaterial; // Retorna o código original se não houver número
        }
        
        $arquivoMaterial = "C:/CC_DATA_BASE/MAT/M{$codigo}.INI";
        
        if (!file_exists($arquivoMaterial)) {
            return $codigoMaterial; // Retorna o código se o arquivo não existir
        }
        
        $linhas = file($arquivoMaterial, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($linhas as $linha) {
            if (stripos($linha, 'CAMPO1=') === 0) {
                $nomeMaterial = trim(substr($linha, 7));
                return $this->removerAcentos($this->limparTexto($nomeMaterial));
            }
        }
        
        return $codigoMaterial; // Retorna o código se não encontrar o campo
    }
    
    /**
     * Calcula estatísticas dos projetos
     */
    public function calcularEstatisticas() {
        $projetos = $this->listarProjetos();
        $stats = [
            'total' => count($projetos),
            'porStatus' => [
                '0' => 0, // Aberto
                '1' => 0, // Cortando
                '2' => 0, // Concluído
                '3' => 0, // Cancelado
                '4' => 0, // Esperando Chapas
                '5' => 0, // Em Análise
                '6' => 0, // Material Indefinido
                '7' => 0  // Desconhecido
            ]
        ];
        
        foreach ($projetos as $projeto) {
            if (file_exists($projeto)) {
                $dadosIni = @parse_ini_file($projeto, false, INI_SCANNER_RAW);
                if ($dadosIni === false) {
                    continue; // Skip files with parse errors
                }
                $status = trim($dadosIni['STATUS'] ?? '0');
                
                if (isset($stats['porStatus'][$status])) {
                    $stats['porStatus'][$status]++;
                }
            }
        }
        
        return $stats;
    }
}

// Inicialização

$projetoManager = new ProjetoManager(PASTA_PROJETOS);
$projetoSelecionado = $_GET['projeto'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= TITULO_SISTEMA ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Inter:400,700|JetBrains+Mono:400,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --nx-bg: #0e0f14;
            --nx-card: #151820;
            --nx-card-2: #1b1f2a;
            --nx-border: #2a3142;
            --nx-accent: #00ff88;
            --nx-accent2: #00d4ff;
            --nx-text: #ffffff;
            --nx-sub: #c7d0e0;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--nx-bg);
            color: var(--nx-text);
            font-family: 'Inter', 'JetBrains Mono', Arial, sans-serif;
            margin: 0;
        }
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.2rem 3rem;
        }
        .header-section {
            background: linear-gradient(135deg, var(--nx-card) 0%, var(--nx-card-2) 100%);
            border: 1px solid var(--nx-border);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--nx-accent), var(--nx-accent2), var(--nx-accent));
            animation: gradient-shift 3s ease infinite;
        }
        @keyframes gradient-shift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .header-title {
            text-align: center;
            color: var(--nx-text);
            font-weight: 700;
            font-size: 2.5rem;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            background: linear-gradient(90deg, var(--nx-accent2), var(--nx-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header-subtitle {
            text-align: center;
            color: var(--nx-sub);
            font-size: 1rem;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        .header-icon {
            display: inline-block;
            margin: 0 0.5rem;
            color: var(--nx-accent);
            font-size: 2rem;
        }
        .project-header {
            background: linear-gradient(135deg, rgba(21, 24, 32, 0.96), rgba(27, 31, 42, 0.96));
            border: 1px solid var(--nx-border);
            border-radius: 18px;
            padding: 1.4rem 1.6rem;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
            position: relative;
            overflow: hidden;
        }
        .project-header::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(0, 212, 255, 0.12), transparent 40%);
            pointer-events: none;
        }
        .project-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
            color: var(--nx-text);
            letter-spacing: 0.8px;
        }
        .project-subtitle {
            color: var(--nx-sub);
            font-size: 0.95rem;
            margin-top: 0.35rem;
        }
        .project-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.9rem;
        }
        .project-meta .meta-pill {
            background: var(--nx-card-2);
            border: 1px solid var(--nx-border);
            color: var(--nx-text);
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .project-status {
            align-self: flex-start;
            font-size: 0.75rem;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
        }
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        .btn-modern {
            border-radius: 50px;
            padding: 0.7rem 1.6rem;
            font-weight: 600;
            border: none;
            background: linear-gradient(90deg, var(--nx-accent), var(--nx-accent2));
            color: #0e0f14;
            box-shadow: 0 6px 18px rgba(0, 255, 136, 0.2);
        }
        .btn-modern:hover {
            color: #fff;
            transform: translateY(-1px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.2rem;
        }
        .stat-card {
            background: var(--nx-card);
            border: 1px solid var(--nx-border);
            border-radius: 14px;
            padding: 1.2rem;
            text-align: center;
        }
        .stat-card i {
            font-size: 1.8rem;
            color: var(--nx-accent2);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            color: var(--nx-sub);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .controls {
            background: var(--nx-card);
            border: 1px solid var(--nx-border);
            border-radius: 14px;
            padding: 1rem;
            margin-bottom: 1.4rem;
        }
        .search-box {
            position: relative;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--nx-accent2);
        }
        .form-control, .form-select {
            background: var(--nx-card-2);
            border: 1px solid var(--nx-border);
            color: var(--nx-text);
        }
        .form-control:focus, .form-select:focus {
            background: var(--nx-card-2);
            border-color: var(--nx-accent2);
            box-shadow: 0 0 0 0.2rem rgba(0, 212, 255, 0.15);
            color: var(--nx-text);
        }

        .status-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }
        .status-filter-btn {
            background: var(--nx-card-2);
            border: 1px solid var(--nx-border);
            color: var(--nx-text);
            padding: 0.5rem 1rem;
            border-radius: 999px;
            cursor: pointer;
        }
        .status-filter-btn.active {
            background: var(--nx-accent);
            color: #0e0f14;
            border-color: var(--nx-accent);
            font-weight: 700;
        }

        .table-wrap {
            background: #ffffff;
            border: 1px solid var(--nx-border);
            border-radius: 14px;
            overflow: hidden;
        }
        .table-projects {
            width: 100%;
            margin: 0;
            color: #000000;
            background: #ffffff;
        }
        .table-projects thead th {
            background: #121520;
            color: var(--nx-accent);
            border-bottom: 2px solid var(--nx-accent);
            border-right: 1px solid var(--nx-border);
            padding: 1rem 1.2rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.7px;
        }
        .table-projects thead th:last-child {
            border-right: none;
        }
        .table-projects tbody td {
            border-bottom: 1px solid var(--nx-border);
            border-right: 1px solid var(--nx-border);
            padding: 0.95rem 1.2rem;
            font-size: 0.95rem;
            color: #000000;
            background: #ffffff;
        }
        .table-projects tbody td:last-child {
            border-right: none;
        }
        .table-projects tbody tr:hover {
            background: #f0f0f0;
        }
        .project-link {
            color: #000000;
            text-decoration: none;
            font-weight: 600;
        }
        .project-link:hover { color: #000000; }
        .col-id .project-link { color: #000000; }
        .badge-status {
            font-size: 0.75rem;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .scroll-fab {
            position: fixed;
            right: 18px;
            bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1050;
        }
        .scroll-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(90deg, var(--nx-accent), var(--nx-accent2));
            color: #0e0f14;
            box-shadow: 0 6px 16px rgba(0, 255, 136, 0.25);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .scroll-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--nx-card) 0%, var(--nx-card-2) 100%);
            border: 1px solid var(--nx-border);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--nx-accent), var(--nx-accent2), var(--nx-accent));
            animation: gradient-shift 3s ease infinite;
        }
        @keyframes gradient-shift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .header-title {
            text-align: center;
            color: var(--nx-text);
            font-weight: 700;
            font-size: 2.5rem;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            background: linear-gradient(90deg, var(--nx-accent2), var(--nx-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .header-subtitle {
            text-align: center;
            color: var(--nx-sub);
            font-size: 1rem;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        .header-icon {
            display: inline-block;
            margin: 0 0.5rem;
            color: var(--nx-accent);
            font-size: 2rem;
            -webkit-text-fill-color: var(--nx-accent);
        }

        .project-materials {
            position: fixed;
            bottom: 18px;
            left: 18px;
            background: var(--nx-card);
            border: 1px solid var(--nx-border);
            border-radius: 12px;
            padding: 0;
            font-size: 0.85rem;
            color: var(--nx-text);
            z-index: 1050;
            max-width: 320px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
        }
        .materials-header {
            padding: 10px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .materials-header:hover {
            background: var(--nx-card-2);
        }
        .materials-header h6 {
            font-size: 0.9rem;
            margin: 0;
            color: var(--nx-accent2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
        }
        .materials-toggle {
            background: none;
            border: none;
            color: var(--nx-accent);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s;
            flex-shrink: 0;
        }
        .materials-toggle.collapsed {
            transform: rotate(180deg);
        }
        .materials-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .materials-content.expanded {
            max-height: 400px;
            overflow-y: auto;
            padding: 0 12px 12px 12px;
        }
        .materials-content::-webkit-scrollbar {
            width: 6px;
        }
        .materials-content::-webkit-scrollbar-track {
            background: var(--nx-card-2);
            border-radius: 3px;
        }
        .materials-content::-webkit-scrollbar-thumb {
            background: var(--nx-accent2);
            border-radius: 3px;
        }
        .material-item-compact {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            padding: 6px 8px;
            background: var(--nx-card-2);
            border-radius: 6px;
            border: 1px solid var(--nx-border);
            transition: all 0.2s;
        }
        .material-item-compact:hover {
            border-color: var(--nx-accent);
            transform: translateX(2px);
        }
        .material-item-compact:last-child {
            margin-bottom: 0;
        }
        .material-code-compact {
            background: linear-gradient(135deg, var(--nx-accent2), var(--nx-accent));
            color: var(--nx-bg);
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            min-width: 35px;
            text-align: center;
            flex-shrink: 0;
        }
        .material-name-compact {
            color: var(--nx-text);
            font-size: 0.8rem;
            line-height: 1.3;
            flex: 1;
        }
        .color-palette h6 {
            font-size: 0.7rem;
            margin: 0 0 6px 0;
            color: var(--nx-sub);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .color-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid var(--nx-border);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .info-item {
            background: var(--nx-card);
            border: 1px solid var(--nx-border);
            border-radius: 12px;
            padding: 0.9rem 1rem;
        }
        .table-modern {
            background: var(--nx-card);
            border: 1px solid var(--nx-border);
            border-radius: 12px;
            overflow: hidden;
        }
        .table-modern thead {
            background: #121520;
            color: var(--nx-accent);
        }
        .table-modern th, .table-modern td {
            border-right: 1px solid var(--nx-border);
        }
        .table-modern th:last-child, .table-modern td:last-child {
            border-right: none;
        }

        @media (max-width: 992px) {
            .table-projects thead { display: none; }
            .table-projects tbody, .table-projects tr, .table-projects td { display: block; width: 100%; }
            .table-projects tbody tr { border-bottom: 1px solid var(--nx-border); }
            .table-projects tbody td { border-right: none; padding: 0.7rem 1rem; }
            .table-projects tbody td::before {
                content: attr(data-label);
                color: var(--nx-sub);
                font-weight: 600;
                display: inline-block;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <?php if ($projetoSelecionado): ?>
        <a href="projetos.php" class="btn btn-light btn-modern back-button">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    <?php endif; ?>

    <div class="main-container">
        <div class="header-section">
            <h1 class="header-title">
                <i class="fas fa-layer-group header-icon"></i>
                <?= TITULO_SISTEMA ?>
                <i class="fas fa-cut header-icon"></i>
            </h1>
            <p class="header-subtitle">
                <i class="fas fa-chart-line"></i> Sistema Profissional de Gestão e Otimização de Projetos
            </p>
        </div>

        <?php if ($projetoSelecionado): ?>
            <?php
            $idProjeto = preg_replace('/\D/', '', $projetoSelecionado);
            $detalhes = $projetoManager->obterDetalhesProjeto($idProjeto);
            ?>

            <?php if ($detalhes['existe']): ?>
                <div class="table-wrap p-3">
                    <div class="project-header mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <h2 class="project-title">
                                    <i class="fas fa-folder-open"></i>
                                    Projeto #<?= $detalhes['dados']['id'] ?>
                                </h2>
                                <div class="project-subtitle">
                                    <?= $detalhes['dados']['nome'] ?>
                                </div>
                                <div class="project-meta">
                                    <span class="meta-pill"><i class="fas fa-user"></i><?= $detalhes['dados']['marceneiro'] ?></span>
                                    <span class="meta-pill"><i class="fas fa-calendar"></i><?= $detalhes['dados']['dataCriacao'] ?></span>
                                    <span class="meta-pill"><i class="fas fa-clock"></i><?= $detalhes['dados']['ultimaModificacao'] ?></span>
                                </div>
                            </div>
                            <span class="project-status bg-<?= $detalhes['dados']['statusCor'] ?>">
                                <?= $detalhes['dados']['status'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="info-grid mb-3">
                        <div class="info-item"><strong>🧑‍🔧 Marceneiro:</strong> <?= $detalhes['dados']['marceneiro'] ?></div>
                        <div class="info-item"><strong>✍️ Editado por:</strong> <?= $detalhes['dados']['editadoPor'] ?></div>
                        <div class="info-item"><strong>✂️ Cortado por:</strong> <?= $detalhes['dados']['cortadoPor'] ?></div>
                        <div class="info-item"><strong>📦 Estoque Atualizado:</strong> <?= $detalhes['dados']['estoqueAtualizado'] ?></div>
                        <div class="info-item"><strong>🗓️ Data de Criação:</strong> <?= $detalhes['dados']['dataCriacao'] ?></div>
                        <div class="info-item"><strong>⏱️ Última Modificação:</strong> <?= $detalhes['dados']['ultimaModificacao'] ?></div>
                    </div>

                    <h4 class="mt-3">Itens do Projeto <span class="badge bg-primary"><?= count($detalhes['itens']) ?> itens</span></h4>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>🔢 Código</th>
                                    <th>🧮 Qtd</th>
                                    <th>↕️ Altura</th>
                                    <th>↔️ Largura</th>
                                    <th>🧱 Material</th>
                                    <th>📝 Descrição</th>
                                    <th>🏠 Ambiente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalhes['itens'] as $item): ?>
                                    <tr>
                                        <td><?= $item['codigo'] ?></td>
                                        <td><?= $item['quantidade'] ?></td>
                                        <td><?= $item['altura'] ?></td>
                                        <td><?= $item['largura'] ?></td>
                                        <td>
                                            <div><?= $item['material'] ?></div>
                                            <?php if ($item['materialNome'] !== $item['material']): ?>
                                                <small class="text-muted"><?= $item['materialNome'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $item['descricao'] ?></td>
                                        <td><?= $item['ambiente'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">Projeto não encontrado.</div>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $projetos = $projetoManager->listarProjetosComDetalhes();
            $marceneiros = $projetoManager->listarMarceneiros();
            $stats = $projetoManager->calcularEstatisticas();
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-folder-open"></i>
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?= $stats['porStatus']['2'] ?></div>
                    <div class="stat-label">Concluídos</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-cut"></i>
                    <div class="stat-number"><?= $stats['porStatus']['1'] ?></div>
                    <div class="stat-label">Cortando</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-play-circle"></i>
                    <div class="stat-number"><?= $stats['porStatus']['0'] ?></div>
                    <div class="stat-label">Abertos</div>
                </div>
            </div>

            <div class="controls">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nome ou número do projeto...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="marceneiroFilter" class="form-select">
                            <option value="">Todos os Marceneiros</option>
                            <?php foreach ($marceneiros as $marceneiro): ?>
                                <option value="<?= htmlspecialchars($marceneiro) ?>"><?= htmlspecialchars($marceneiro) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="sortSelect" class="form-select">
                            <option value="data-desc">Mais Recente</option>
                            <option value="data-asc">Mais Antigo</option>
                            <option value="id-desc">ID Maior</option>
                            <option value="id-asc">ID Menor</option>
                            <option value="nome-asc">Nome A-Z</option>
                            <option value="nome-desc">Nome Z-A</option>
                        </select>
                    </div>
                </div>

                <div class="status-filters mt-3">
                    <button class="status-filter-btn active" data-status="all">Todos</button>
                    <button class="status-filter-btn" data-status="0">Aberto</button>
                    <button class="status-filter-btn" data-status="1">Cortando</button>
                    <button class="status-filter-btn" data-status="2">Concluído</button>
                    <button class="status-filter-btn" data-status="4">Aguardando</button>
                    <button class="status-filter-btn" data-status="5">Análise</button>
                </div>
            </div>

            <div class="table-wrap" id="projectsContainer">
                <div class="table-responsive">
                    <table class="table table-projects" id="projectsTable">
                        <thead>
                            <tr>
                                <th>📁 Projeto</th>
                                <th>📝 Descrição</th>
                                <th>🧑‍🔧 Marceneiro</th>
                                <th>📅 Data</th>
                                <th>🏷️ Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projetos as $projeto): ?>
                                <tr class="project-row"
                                    data-id="<?= $projeto['id'] ?>"
                                    data-nome="<?= strtolower($projeto['nome']) ?>"
                                    data-marceneiro="<?= strtolower($projeto['marceneiro']) ?>"
                                    data-status="<?= $projeto['status'] ?>"
                                    data-data="<?= $projeto['dataOrdenacao'] ?? 0 ?>">
                                    <td class="col-id" data-label="Projeto">
                                        <a href="projetos.php?projeto=<?= $projeto['id'] ?>" class="project-link">#<?= $projeto['id'] ?></a>
                                    </td>
                                    <td class="col-desc" data-label="Descrição">
                                        <a href="projetos.php?projeto=<?= $projeto['id'] ?>" class="project-link"><?= $projeto['nome'] ?></a>
                                    </td>
                                    <td data-label="Marceneiro"><?= $projeto['marceneiro'] ?></td>
                                    <td data-label="Data"><?= $projeto['ultimaModificacao'] ?></td>
                                    <td data-label="Status"><span class="badge-status bg-<?= $projeto['statusCor'] ?>"><?= $projeto['statusTexto'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="noResults" class="alert alert-info mt-3" style="display:none;">Nenhum projeto encontrado com os filtros selecionados.</div>
        <?php endif; ?>
    </div>

    <div class="scroll-fab">
        <button id="scrollToBottom" class="scroll-btn" title="Ir para o fim">
            <i class="fas fa-arrow-down"></i>
        </button>
        <button id="scrollToTop" class="scroll-btn" title="Voltar ao topo" style="display: none;">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>

    <!-- Materiais do Projeto -->
    <?php if ($projetoSelecionado && isset($detalhes) && $detalhes['existe']): ?>
        <?php
        // Extrair materiais únicos do projeto
        $materiaisUnicos = [];
        foreach ($detalhes['itens'] as $item) {
            $codigoMat = $item['material'];
            if (!isset($materiaisUnicos[$codigoMat])) {
                $materiaisUnicos[$codigoMat] = [
                    'codigo' => $codigoMat,
                    'nome' => $item['materialNome']
                ];
            }
        }
        ksort($materiaisUnicos);
        ?>
        <?php if (!empty($materiaisUnicos)): ?>
        <div class="project-materials" id="projectMaterials">
            <div class="materials-header" onclick="toggleMaterials()">
                <h6>
                    <i class="fas fa-layer-group"></i>
                    Materiais (<?= count($materiaisUnicos) ?>)
                </h6>
                <button class="materials-toggle collapsed" id="materialsToggle">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
            <div class="materials-content" id="materialsContent">
                <?php foreach ($materiaisUnicos as $material): ?>
                <div class="material-item-compact">
                    <div class="material-code-compact"><?= htmlspecialchars($material['codigo']) ?></div>
                    <div class="material-name-compact"><?= htmlspecialchars($material['nome']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle do painel de materiais
        function toggleMaterials() {
            const content = document.getElementById('materialsContent');
            const toggle = document.getElementById('materialsToggle');
            
            if (content && toggle) {
                content.classList.toggle('expanded');
                toggle.classList.toggle('collapsed');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const marceneiroFilter = document.getElementById('marceneiroFilter');
            const sortSelect = document.getElementById('sortSelect');
            const statusButtons = document.querySelectorAll('.status-filter-btn');
            const projectsTable = document.getElementById('projectsTable');
            const projectsContainer = document.getElementById('projectsContainer');
            const noResults = document.getElementById('noResults');

            if (!searchInput || !projectsTable) return;

            const tbody = projectsTable.querySelector('tbody');
            let currentFilters = {
                search: '',
                marceneiro: '',
                status: 'all',
                sort: 'id-desc'
            };

            searchInput.addEventListener('input', function(e) {
                currentFilters.search = e.target.value.toLowerCase();
                applyFilters();
            });

            marceneiroFilter.addEventListener('change', function(e) {
                currentFilters.marceneiro = e.target.value.toLowerCase();
                applyFilters();
            });

            statusButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    statusButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilters.status = this.getAttribute('data-status');
                    applyFilters();
                });
            });

            sortSelect.addEventListener('change', function(e) {
                currentFilters.sort = e.target.value;
                applyFilters();
            });

            function applyFilters() {
                const rows = Array.from(tbody.querySelectorAll('.project-row'));
                let visibleCount = 0;

                rows.forEach(row => {
                    const id = row.getAttribute('data-id');
                    const nome = row.getAttribute('data-nome');
                    const marceneiro = row.getAttribute('data-marceneiro');
                    const status = row.getAttribute('data-status');

                    let shouldShow = true;

                    if (currentFilters.search && !nome.includes(currentFilters.search) && !id.includes(currentFilters.search)) {
                        shouldShow = false;
                    }

                    if (currentFilters.marceneiro && !marceneiro.includes(currentFilters.marceneiro)) {
                        shouldShow = false;
                    }

                    if (currentFilters.status !== 'all' && status !== currentFilters.status) {
                        shouldShow = false;
                    }

                    if (shouldShow) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    projectsContainer.style.display = 'none';
                    noResults.style.display = 'block';
                } else {
                    projectsContainer.style.display = 'block';
                    noResults.style.display = 'none';
                }

                const visibleRows = rows.filter(row => row.style.display !== 'none');
                sortRows(visibleRows);
            }

            function sortRows(rows) {
                rows.sort((a, b) => {
                    const idA = parseInt(a.getAttribute('data-id'));
                    const idB = parseInt(b.getAttribute('data-id'));
                    const nomeA = a.getAttribute('data-nome');
                    const nomeB = b.getAttribute('data-nome');
                    const dataA = parseInt(a.getAttribute('data-data') || '0');
                    const dataB = parseInt(b.getAttribute('data-data') || '0');

                    switch(currentFilters.sort) {
                        case 'id-asc': return idA - idB;
                        case 'id-desc': return idB - idA;
                        case 'nome-asc': return nomeA.localeCompare(nomeB);
                        case 'nome-desc': return nomeB.localeCompare(nomeA);
                        case 'data-asc': return dataA - dataB;
                        case 'data-desc': return dataB - dataA;
                        default: return dataB - dataA;
                    }
                });

                rows.forEach(row => tbody.appendChild(row));
            }

            const scrollToBottom = document.getElementById('scrollToBottom');
            const scrollToTop = document.getElementById('scrollToTop');

            if (scrollToBottom && scrollToTop) {
                const updateScrollButtons = () => {
                    const atTop = window.scrollY < 200;
                    const atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 200);

                    scrollToTop.style.display = atTop ? 'none' : 'inline-flex';
                    scrollToBottom.disabled = atBottom;
                };

                scrollToBottom.addEventListener('click', () => {
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                });
                scrollToTop.addEventListener('click', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });

                window.addEventListener('scroll', updateScrollButtons);
                updateScrollButtons();
            }
        });
    </script>
</body>
</html>