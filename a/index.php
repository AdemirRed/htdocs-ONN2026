<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONN Móveis - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            width: 400px;
            overflow: hidden;
            animation: fadeIn 1s ease;
        }

        .login-header {
            background: linear-gradient(45deg, #3a7bd5, #00d2ff);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 15px;
            color: #3a7bd5;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #e1e1e1;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3a7bd5;
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(45deg, #3a7bd5, #00d2ff);
            border: none;
            border-radius: 30px;
            color: white;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 210, 255, 0.3);
        }

        .error-message {
            background-color: #ffdddd;
            color: #ff4444;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .success-message {
            background-color: #ddffdd;
            color: #44aa44;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>ONN Móveis</h1>
            <p>Sistema de Gerenciamento</p>
        </div>
        
        <div class="login-form">
            <?php
            session_start();
            
            // Mensagens de erro ou sucesso
            if (isset($_SESSION['error'])) {
                echo '<div class="error-message" style="display: block;">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="success-message" style="display: block;">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            
            // Verificar se o usuário já está logado
            if (isset($_SESSION['user_id'])) {
                // Redirecionar para a página de estoque
                header("Location: estoque.php");
                exit();
            }
            
            // Processar formulário quando enviado
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                // Conectar ao banco de dados
                $dbHost = "192.168.0.201";
                if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === $dbHost) {
                    $dbHost = "127.0.0.1";
                }
                $conn = new mysqli($dbHost, "root", "", "onnmoveis");
                
                // Verificar conexão
                if ($conn->connect_error) {
                    $_SESSION['error'] = "Falha na conexão com o banco de dados: " . $conn->connect_error;
                } else {
                    // Obter e sanitizar dados do formulário
                    $usuario = $conn->real_escape_string($_POST['usuario']);
                    $senha = $_POST['senha']; // Não usar real_escape_string em senhas
                    
                    // Consultar banco de dados
                    $sql = "SELECT id, Usuario, Senha FROM usuario WHERE Usuario = '$usuario'";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        
                        // Verificar se a senha está correta (usando password_verify caso esteja usando hash)
                        // Se não estiver usando hash, compare diretamente
                        if ($senha === $row['Senha']) {
                            // Login bem-sucedido
                            $_SESSION['user_id'] = $row['id'];
                            $_SESSION['usuario'] = $row['Usuario'];
                            
                            // Redirecionar para a página de estoque
                            header("Location: estoque.php");
                            exit();
                        } else {
                            $_SESSION['error'] = "Senha incorreta!";
                        }
                    } else {
                        $_SESSION['error'] = "Usuário não encontrado!";
                    }
                    
                    $conn->close();
                    
                    // Recarregar a página para mostrar o erro
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
            ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" name="usuario" placeholder="Usuário" required>
                </div>
                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" name="senha" placeholder="Senha" required>
                </div>
                <button type="submit" class="submit-btn">ENTRAR</button>
            </form>
        </div>
    </div>

    <script>
        // Esconder mensagens após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var errorMessage = document.querySelector('.error-message');
                var successMessage = document.querySelector('.success-message');
                
                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }
                
                if (successMessage) {
                    successMessage.style.display = 'none';
                }
            }, 5000);
        });
    </script>
</body>
</html>