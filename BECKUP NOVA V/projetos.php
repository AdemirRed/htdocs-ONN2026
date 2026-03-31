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
     * Lista todos os marceneiros únicos dos projetos
     */
    public function listarMarceneiros() {
        $projetos = $this->listarProjetos();
        $marceneiros = [];
        
        foreach ($projetos as $projeto) {
            if (file_exists($projeto)) {
                $dadosIni = parse_ini_file($projeto);
                $marceneiro = $this->limparTexto($dadosIni['OBS1'] ?? '');
                
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
            $dadosIni = parse_ini_file($arquivo);
            
            $detalhes['nome'] = $this->obterNomeProjeto($arquivo);
            $detalhes['marceneiro'] = $this->limparTexto($dadosIni['OBS1'] ?? 'Não informado');
            $detalhes['status'] = trim($dadosIni['STATUS'] ?? '0');
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
        
        $dadosIni = parse_ini_file($arquivoIni);
        $status = trim($dadosIni['STATUS'] ?? '0');
        
        $resultado['existe'] = true;
        $resultado['dados'] = [
            'id' => $idProjeto,
            'nome' => $this->obterNomeProjeto($arquivoIni),
            'status' => $this->obterStatusTexto($status),
            'statusCor' => $this->obterStatusCor($status),
            'marceneiro' => $this->limparTexto($dadosIni['OBS1'] ?? 'Não informado'),
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
        
        // Assumindo formato de data do sistema (adapte conforme necessário)
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
                $itens[] = [
                    'codigo' => $dados[0],
                    'quantidade' => $dados[2],
                    'altura' => $dados[3],
                    'largura' => $dados[4],
                    'material' => $this->removerAcentos($this->limparTexto($dados[5])),
                    'descricao' => $this->removerAcentos($this->limparTexto($dados[6])),
                    'ambiente' => $this->removerAcentos($this->limparTexto(end($dados)))
                ];
            }
        }
        
        return $itens;
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
            --nx-bg: #181820;
            --nx-glass: #232336e7;
            --nx-card: #222232f7;
            --nx-btn-grad: linear-gradient(90deg, #00ff88, #00d4ff);
            --nx-btn-grad-alt: linear-gradient(90deg, #00d4ff, #00ff88);
            --nx-accent: #00ff88;
            --nx-accent2: #00d4ff;
            --nx-title: #fff;
            --nx-sub: #bbc4cc;
            --nx-border: #00ff8890;
            --nx-shadow: 0 8px 32px #00ff881a;
        }
        html, body {
            min-height: 100vh;
        }
        body {
            background: var(--nx-bg);
            color: var(--nx-title);
            font-family: 'Inter', 'JetBrains Mono', Arial, sans-serif;
        }
        .main-container {
            padding: 2.2rem 0 2.5rem 0;
            max-width: 880px;
            margin: 0 auto;
        }
        .header-title {
            text-align: center;
            color: var(--nx-accent2);
            margin-bottom: 2rem;
            font-size: 2em;
            font-weight: 700;
            letter-spacing: 2px;
            text-shadow: 0 2px 32px #00ff8830;
            font-family: 'Inter', 'JetBrains Mono', Arial, sans-serif;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .project-card {
            background: var(--nx-card);
            border-radius: 17px;
            box-shadow: var(--nx-shadow);
            border: 1.5px solid var(--nx-border);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 2.5rem;
        }
        .project-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px #00ff8840;
        }
        .project-header {
            background: linear-gradient(90deg, #232336 74%, var(--nx-accent2) 120%);
            color: var(--nx-title);
            padding: 1.2rem 1.2rem 0.8rem 1.2rem;
            border-radius: 17px 17px 0 0;
            margin-bottom: 0;
        }
        .status-badge {
            font-size: 0.99rem;
            padding: 0.6rem 1.3rem;
            border-radius: 50px;
            font-weight: 700;
            border: none;
            color: var(--nx-title);
            background: var(--nx-accent2);
            box-shadow: 0 2px 8px #00d4ff40;
            margin-left: 12px;
        }
        .bg-success { background: #00ff8845 !important; color: var(--nx-accent)!important;}
        .bg-warning { background: #ffc10755 !important; color: #ffc107!important;}
        .bg-danger { background: #e74c3c37!important; color: #e74c3c!important;}
        .bg-info { background: #00d4ff50!important; color: var(--nx-accent2)!important;}
        .bg-secondary { background: #bbc4cc2a!important; color: #bbc4cc!important;}
        .bg-primary { background: #3498db30!important; color: #3498db!important;}
        .project-list-item {
            background: var(--nx-glass);
            color: var(--nx-sub);
            border-radius: 12px;
            margin-bottom: 1.2rem;
            transition: background 0.17s, box-shadow 0.13s, color 0.15s;
            border: 1px solid transparent;
        }
        .project-list-item:hover {
            background: #232342f7;
            color: var(--nx-title);
            box-shadow: 0 4px 24px #00ff886a;
            border-color: var(--nx-accent2);
        }
        .project-list-item .text-dark { color: var(--nx-title)!important; }
        .project-list-item .text-muted { color: var(--nx-accent2)!important; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
            margin-top: 1.1rem;
        }
        .info-item {
            background: #232346e0;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid var(--nx-accent2);
            color: var(--nx-title);
        }
        .table-modern {
            background: #171732e4;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px #00d4ff22;
            color: var(--nx-title);
        }
        .table-modern thead {
            background: #202031e4;
            color: var(--nx-accent2);
        }
        .table-modern tr, .table-modern th, .table-modern td {
            border-color: #282846 !important;
        }
        .table-modern tbody tr:hover {
            background-color: #282846 !important;
        }
        .bg-secondary {
            background: #3b3b5e!important;
            color: var(--nx-accent2)!important;
        }
        .btn-modern,
        .btn.btn-light.btn-modern.back-button {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            border: none;
            background: var(--nx-btn-grad);
            color: #181820 !important;
            box-shadow: 0 2px 12px #00ff8840;
        }
        .btn-modern:hover,
        .btn.btn-light.btn-modern.back-button:hover {
            background: var(--nx-btn-grad-alt);
            transform: translateY(-2px);
            color: #fff !important;
            box-shadow: 0 5px 15px #00ff8870;
        }
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        .project-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
        }
        .stat-item {
            text-align: center;
            color: var(--nx-accent2);
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        /* Responsive adjustments */
        @media (max-width: 700px) {
            .main-container { padding: 1.2rem 2vw 2.5rem 2vw; }
            .project-header { padding: 1rem 0.7rem 0.6rem 0.7rem; }
            .header-title { font-size: 1.23em; }
            .info-grid { gap: 0.7rem; grid-template-columns: 1fr;}
            .table-modern { font-size: 0.93em;}
            .status-badge { font-size: 0.92em; padding: 0.45rem 1.05rem; }
        }
        @media (max-width: 520px) {
            .header-title { font-size: 1em; }
            .main-container { padding: 0.55em 1vw 1.2em 1vw;}
        }
    </style>
</head>
<body>

    <?php if ($projetoSelecionado): ?>
        <a href="projetos.php" class="btn btn-light btn-modern back-button">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    <?php endif; ?>

    <div class="container main-container">
        <h1 class="header-title fade-in">
            <i class="fas fa-tools"></i>
            <?= TITULO_SISTEMA ?>
        </h1>

        <?php if ($projetoSelecionado): ?>
            <?php
            $idProjeto = preg_replace('/\D/', '', $projetoSelecionado);
            $detalhes = $projetoManager->obterDetalhesProjeto($idProjeto);
            ?>

            <?php if ($detalhes['existe']): ?>
                <div class="project-card fade-in">
                    <div class="project-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="fas fa-project-diagram"></i>
                                    Projeto #<?= $detalhes['dados']['id'] ?>
                                </h2>
                                <p class="mb-0 opacity-75"><?= $detalhes['dados']['nome'] ?></p>
                            </div>
                            <span class="badge status-badge bg-<?= $detalhes['dados']['statusCor'] ?>">
                                <?= $detalhes['dados']['status'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="info-grid">
                            <div class="info-item">
                                <h6><i class="fas fa-user-tie text-primary"></i> Marceneiro</h6>
                                <p class="mb-0"><?= $detalhes['dados']['marceneiro'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-user-edit text-success"></i> Editado por</h6>
                                <p class="mb-0"><?= $detalhes['dados']['editadoPor'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-cut text-warning"></i> Cortado por</h6>
                                <p class="mb-0"><?= $detalhes['dados']['cortadoPor'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-warehouse text-info"></i> Estoque Atualizado</h6>
                                <p class="mb-0"><?= $detalhes['dados']['estoqueAtualizado'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-calendar-plus text-secondary"></i> Data de Criação</h6>
                                <p class="mb-0"><?= $detalhes['dados']['dataCriacao'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-calendar-edit text-secondary"></i> Última Modificação</h6>
                                <p class="mb-0"><?= $detalhes['dados']['ultimaModificacao'] ?></p>
                            </div>
                        </div>

                        <?php if (!empty($detalhes['itens'])): ?>
                            <h4 class="mt-4 mb-3">
                                <i class="fas fa-list"></i>
                                Itens do Projeto
                                <span class="badge bg-primary"><?= count($detalhes['itens']) ?> itens</span>
                            </h4>

                            <div class="table-responsive">
                                <table class="table table-modern mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-barcode"></i> Código</th>
                                            <th><i class="fas fa-sort-numeric-up"></i> Qtd</th>
                                            <th><i class="fas fa-arrows-alt-v"></i> Altura</th>
                                            <th><i class="fas fa-arrows-alt-h"></i> Largura</th>
                                            <th><i class="fas fa-cube"></i> Material</th>
                                            <th><i class="fas fa-info-circle"></i> Descrição</th>
                                            <th><i class="fas fa-home"></i> Ambiente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalhes['itens'] as $item): ?>
                                            <tr>
                                                <td><code><?= $item['codigo'] ?></code></td>
                                                <td><span class="badge bg-secondary"><?= $item['quantidade'] ?></span></td>
                                                <td><?= $item['altura'] ?></td>
                                                <td><?= $item['largura'] ?></td>
                                                <td><?= $item['material'] ?></td>
                                                <td><?= $item['descricao'] ?></td>
                                                <td><?= $item['ambiente'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle"></i>
                                Nenhum item encontrado para este projeto.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    Projeto não encontrado.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Lista de Projetos -->
            <?php
            $projetos = $projetoManager->listarProjetos();
            ?>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="project-card fade-in">
                        <div class="project-header">
                            <h2 class="mb-0">
                                <i class="fas fa-folder-open"></i>
                                Projetos Disponíveis
                            </h2>
                            <div class="project-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= count($projetos) ?></span>
                                    <small>Total de Projetos</small>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <?php if (empty($projetos)): ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-folder-open"></i>
                                    Nenhum projeto encontrado.
                                </div>
                            <?php else: ?>
                                <?php foreach ($projetos as $projeto): ?>
                                    <?php
                                    $id = preg_replace('/\D/', '', basename($projeto, ".INI"));
                                    $nomeProjeto = $projetoManager->obterNomeProjeto($projeto);
                                    ?>
                                    <div class="project-list-item">
                                        <a href="projetos.php?projeto=<?= $id ?>" class="text-decoration-none">
                                            <div class="p-4 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1 text-dark">
                                                        <i class="fas fa-file-alt text-primary"></i>
                                                        Projeto #<?= $id ?>
                                                    </h5>
                                                    <p class="mb-0 text-muted"><?= $nomeProjeto ?></p>
                                                </div>
                                                <i class="fas fa-chevron-right text-muted"></i>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>