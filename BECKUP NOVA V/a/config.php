<?php
/**
 * Arquivo de configuração do sistema
 * Contém as configurações de banco de dados e outras configurações gerais
 */

// Configurações do banco de dados
define('DB_HOST', '192.168.0.201');
define('DB_NAME', 'onnmoveis'); // Altere para o nome do seu banco
define('DB_USER', 'root');
define('DB_PASS', ''); // Altere para sua senha do MySQL

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Mude para 1 se usar HTTPS
ini_set('session.use_strict_mode', 1);

// Configurações de erro (desabilite em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    // Criação da conexão PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Log do erro (em produção, não exiba detalhes do erro)
    error_log("Erro de conexão com o banco de dados: " . $e->getMessage());
    
    // Mensagem genérica para o usuário
    die("Erro de conexão com o banco de dados. Tente novamente mais tarde.");
}

// Funções auxiliares
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function format_date($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($datetime));
}

function get_status_text($status) {
    $status_array = [
        0 => 'Pendente',
        1 => 'Em Andamento',
        2 => 'Concluído',
        3 => 'Cancelado'
    ];
    return isset($status_array[$status]) ? $status_array[$status] : 'Desconhecido';
}

function get_status_class($status) {
    $class_array = [
        0 => 'warning',
        1 => 'info',
        2 => 'success',
        3 => 'danger'
    ];
    return isset($class_array[$status]) ? $class_array[$status] : 'secondary';
}

// Verificar se as tabelas existem, se não, criar
function check_and_create_tables($pdo) {
    try {
        // Verificar se a tabela fornecedor existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'fornecedor'");
        if ($stmt->rowCount() == 0) {
            $sql = "CREATE TABLE fornecedor (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                contato VARCHAR(255),
                email VARCHAR(255),
                telefone VARCHAR(50),
                endereco TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
        
        // Verificar se a tabela pedidos existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'pedidos'");
        if ($stmt->rowCount() == 0) {
            $sql = "CREATE TABLE pedidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome_produto VARCHAR(255) NOT NULL,
                nome_fornecedor VARCHAR(255) NOT NULL,
                contato VARCHAR(255),
                quantidade DECIMAL(10,2) NOT NULL,
                unidade VARCHAR(20) NOT NULL,
                observacao TEXT,
                data_pedido DATE NOT NULL,
                nome_usuario VARCHAR(255) NOT NULL,
                status TINYINT DEFAULT 0,
                adicionado_ao_estoque TINYINT DEFAULT 0,
                item_id INT,
                data_alteracao TIMESTAMP NULL,
                pedido_pessoa VARCHAR(255) NOT NULL,
                marceneiro VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
        
        // Verificar se a tabela usuarios existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
        if ($stmt->rowCount() == 0) {
            $sql = "CREATE TABLE usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                senha VARCHAR(255) NOT NULL,
                nivel TINYINT DEFAULT 1,
                ativo TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
            
            // Inserir usuário padrão (admin/admin)
            $senha_hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)");
            $stmt->execute(['Administrador', 'admin@admin.com', $senha_hash, 2]);
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao criar tabelas: " . $e->getMessage());
    }
}

// Executar verificação das tabelas
check_and_create_tables($pdo);

// Constantes do sistema
define('SITE_NAME', 'Sistema de Gestão');
define('SITE_URL', 'http://localhost/a/'); // Altere conforme necessário

// Configurações de upload (se necessário)
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Configurações de paginação
define('ITEMS_PER_PAGE', 20);
?>