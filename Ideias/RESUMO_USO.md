# Resumo de uso para um novo projeto (do zero)

## Visão geral
- A visualização é feita por uma classe de renderização (ex.: CanvasRenderer) que desenha a chapa, as peças e as linhas de corte.
- Você vai criar esses arquivos do zero no novo projeto.
- O renderer recebe dados já parseados (material e programa) e monta a visão no Canvas.

## Passos para criar do zero

### 1) Crie o arquivo do renderer
- Crie um arquivo novo para a classe de renderização (ex.: canvas_renderer.py).
- Implemente a classe com suporte a: criação do Canvas, zoom, pan, seleção e desenho.

### 2) Crie a janela e instancie o renderer
- Crie a janela Tkinter e passe o container/parent.
- Defina um dicionário de cores com chaves como: bg_dark, bg_light, accent, text_gray, warning, text_white, bg_medium, accent_hover.

### 3) Prepare os dados de entrada
- material_data deve ter: description, length, width.
- program_data deve ter:
  - number, length, width
  - pieces: lista com width, height, description, quantity
  - cuts: sequência de cortes (tipos como P, X, V, Y, U, S, e refilos RP, RX, RV, RY, RU, RS)
- Observação: se não houver cuts, o renderer deve usar um layout simples (shelf packing) para visualizar as peças.

### 4) Renderize o plano
- Crie um método principal (ex.: render_cutting_plan) que:
  1. Limpa o canvas
  2. Calcula escala para caber a chapa
  3. Desenha a chapa
  4. Desenha peças (por cortes reais ou layout simples)
  5. Desenha linhas de corte por cima das peças

### 5) Interações prontas
- Implementar zoom_in, zoom_out, reset_view.
- Implementar pan com mouse e seleção de peça no clique.

### 6) Linhas de corte
- As linhas vermelhas representam a trajetória de corte gerada a partir de cuts.
- Desenhe essas linhas somente quando houver cortes reais.

## Dicas rápidas
- Se quiser desativar linhas, não chame o método que desenha as linhas.
- Se seus cortes tiverem tipos diferentes, atualize os conjuntos de tipos no método que interpreta os cortes.

## Arquivos principais (sugestão)
- Renderer: canvas_renderer.py
- Aplicação principal: main.py
