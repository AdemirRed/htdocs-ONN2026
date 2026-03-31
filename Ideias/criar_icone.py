from PIL import Image, ImageDraw, ImageFont
import os

# Criar imagem 256x256 com fundo transparente
size = 256
img = Image.new('RGBA', (size, size), (0, 0, 0, 0))
draw = ImageDraw.Draw(img)

# Gradiente circular de fundo (azul para ciano)
margin = 10
draw.ellipse([margin, margin, size-margin, size-margin], fill='#1a1a1a', outline='#00bcd4', width=10)
draw.ellipse([margin+15, margin+15, size-margin-15, size-margin-15], fill='#2d2d2d', outline='#00e5ff', width=6)

# Desenhar ícone de documento com lápis mais visível e moderno
# Documento (papel)
doc_left = 60
doc_top = 50
doc_width = 110
doc_height = 150
doc_corner = 25

# Corpo do documento branco
points_doc = [
    (doc_left, doc_top + doc_corner),
    (doc_left + doc_width - doc_corner, doc_top),
    (doc_left + doc_width, doc_top + doc_corner),
    (doc_left + doc_width, doc_top + doc_height),
    (doc_left, doc_top + doc_height)
]
draw.polygon(points_doc, fill='#ffffff', outline='#00bcd4', width=4)

# Canto dobrado
fold_points = [
    (doc_left + doc_width - doc_corner, doc_top),
    (doc_left + doc_width - doc_corner, doc_top + doc_corner),
    (doc_left + doc_width, doc_top + doc_corner)
]
draw.polygon(fold_points, fill='#e0e0e0', outline='#00bcd4', width=4)

# Linhas no documento (simulando texto)
for i in range(4):
    y = doc_top + 50 + (i * 20)
    draw.rectangle([doc_left + 15, y, doc_left + doc_width - 20, y + 3], fill='#00bcd4')

# Lápis grande e visível sobre o documento
pencil_x = 140
pencil_y = 120
pencil_width = 35
pencil_length = 110

# Corpo do lápis (amarelo/laranja vibrante)
pencil_body = [
    (pencil_x - pencil_width//2, pencil_y + pencil_length),
    (pencil_x + pencil_width//2, pencil_y + pencil_length),
    (pencil_x + pencil_width//2, pencil_y + 40),
    (pencil_x - pencil_width//2, pencil_y + 40)
]
draw.polygon(pencil_body, fill='#ff9800', outline='#1a1a1a', width=4)

# Ponta do lápis (triangular, grafite)
pencil_tip = [
    (pencil_x, pencil_y),
    (pencil_x - pencil_width//2, pencil_y + 40),
    (pencil_x + pencil_width//2, pencil_y + 40)
]
draw.polygon(pencil_tip, fill='#ffd54f', outline='#1a1a1a', width=4)

# Ponta preta (grafite)
pencil_graphite = [
    (pencil_x, pencil_y),
    (pencil_x - 8, pencil_y + 15),
    (pencil_x + 8, pencil_y + 15)
]
draw.polygon(pencil_graphite, fill='#424242', outline='#1a1a1a', width=2)

# Borracha (rosa vibrante)
eraser_height = 20
draw.rectangle([pencil_x - pencil_width//2, pencil_y + pencil_length, 
                pencil_x + pencil_width//2, pencil_y + pencil_length + eraser_height], 
               fill='#ff4081', outline='#1a1a1a', width=4)

# Anel metálico da borracha
draw.rectangle([pencil_x - pencil_width//2, pencil_y + pencil_length - 5, 
                pencil_x + pencil_width//2, pencil_y + pencil_length + 5], 
               fill='#9e9e9e', outline='#1a1a1a', width=3)

# Adicionar brilho no lápis para efeito 3D
draw.rectangle([pencil_x - pencil_width//2 + 5, pencil_y + 45, 
                pencil_x - pencil_width//2 + 9, pencil_y + pencil_length - 10], 
               fill='#ffb74d')

# Salvar em múltiplos tamanhos para .ico
img_256 = img
img_128 = img.resize((128, 128), Image.Resampling.LANCZOS)
img_64 = img.resize((64, 64), Image.Resampling.LANCZOS)
img_48 = img.resize((48, 48), Image.Resampling.LANCZOS)
img_32 = img.resize((32, 32), Image.Resampling.LANCZOS)
img_16 = img.resize((16, 16), Image.Resampling.LANCZOS)

# Salvar como .ico com múltiplos tamanhos
icon_path = os.path.join(os.path.dirname(__file__), 'editor_icon.ico')
img_256.save(icon_path, format='ICO', sizes=[(256, 256), (128, 128), (64, 64), (48, 48), (32, 32), (16, 16)])

print(f"✅ Ícone profissional criado com sucesso: {icon_path}")
