<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Principal</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #111;
            color: #fff;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
            text-shadow: 0 0 15px rgba(0, 0, 0, 0.7);
        }

        .button-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
        }

        .button {
            background: linear-gradient(145deg, #0072ff, #00c6ff);
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.2em;
            color: #fff;
            text-decoration: none;
            text-align: center;
            width: 250px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .button:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 123, 255, 0.5);
        }

        .button:active {
            transform: translateY(2px);
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
        }

        .button-container a {
            color: white;
            text-decoration: none;
        }

        .button-container a:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 30px;
            font-size: 1.1em;
            color: rgba(255, 255, 255, 0.6);
        }

        #meuBotao.desativado {
            pointer-events: none; /* Impede o clique */
            color: #ccc; /* Altera a cor do link para dar a aparência de desativado */
            cursor: not-allowed; /* Muda o cursor para indicar que o link não é clicável */
        }
    </style>
</head>
<body>
	<div>
	
	<button class="button" onclick="exibirMensagem()">Login</button>
</div>
    <h1>Bem-vindo ao Sistema de Materiais</h1>

    <div class="button-container">
        <a href="http://192.168.0.201/materiais_view.php" class="button">Ver Código das Chapas</a>
        <a href="http://192.168.0.201/retalhos_filtro.php" class="button" id="meuBotao">Procurar Retalhos por Tamanho</a>
        <a href="http://192.168.0.201/Ripado.html" class="button">Calcular Ripados</a>

 <a href="http://192.168.0.201/projetos.php" class="button">Ver Lista de Projetos</a>

    </div>

    <div class="footer">
        <p>&copy; 2025 RedBlack Ω</p>
    </div>

    <script>
    /*
    document.addEventListener("DOMContentLoaded", function() {
        // Adiciona a classe 'desativado' ao link para desabilitar o clique
        document.getElementById("meuBotao").classList.add("desativado");
    });
    */

function exibirMensagem() {
            alert("Este recurso está em desenvolvimento e estará disponível em breve.");
        }
</script>


</body>
</html>
