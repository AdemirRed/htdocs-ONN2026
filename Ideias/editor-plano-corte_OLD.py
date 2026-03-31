"""
Editor de Plano de Corte - Titanium
Aplicação principal para visualização e edição de arquivos .cutplanning
"""
import tkinter as tk
from tkinter import ttk, filedialog, messagebox
import xml.etree.ElementTree as ET
import os
from datetime import datetime

# Importar módulos do projeto
from data_parser import CutPlanningParser
from tree_view_manager import TreeViewManager
from canvas_renderer import CanvasRenderer
from edit_controls import EditControlsPanel


class EditorPlanoCorte:
    def __init__(self, root):
        self.root = root
        self.root.title("📋 Editor de Plano de Corte - Titanium")
        self.root.geometry("1600x900")
        try:
            self.root.state("zoomed")  # Windows
        except tk.TclError:
            pass

        # Cores do tema escuro profissional
        self.bg_dark = "#1a1a1a"
        self.bg_medium = "#2d2d2d"
        self.bg_light = "#3d3d3d"
        self.accent = "#00bcd4"
        self.accent_hover = "#00acc1"
        self.text_white = "#ffffff"
        self.text_gray = "#b0b0b0"
        self.success = "#4caf50"
        self.warning = "#ff9800"
        self.error = "#f44336"

        self.root.configure(bg=self.bg_dark)

        # Estado do aplicativo
        self.xml_tree = None
        self.xml_root = None
        self.current_file_path = None
        self.base_path = r"C:\Titanium\Titanium\lotes"
        
        # Inicializar parser
        self.parser = CutPlanningParser()
        
        # Managers (serão criados na interface)
        self.tree_manager = None
        self.canvas_renderer = None
        self.edit_panel = None

        self.configurar_estilo()
        self.criar_interface()
        self.configurar_atalhos()
    
    def configurar_atalhos(self):
        """Configura atalhos de teclado"""
        self.root.bind('<Control-s>', lambda e: self.salvar_arquivo())
        self.root.bind('<Control-o>', lambda e: self.selecionar_arquivo())
        self.root.bind('<F5>', lambda e: self.recarregar_arquivo())

    def configurar_estilo(self):
        """Configura estilo dos widgets ttk"""
        style = ttk.Style()
        try:
            style.theme_use("clam")
        except tk.TclError:
            pass

        style.configure(
            "Vertical.TScrollbar",
            background=self.bg_medium,
            bordercolor=self.bg_dark,
            arrowcolor=self.text_white,
            troughcolor=self.bg_dark,
        )

    def criar_interface(self):
        """Cria a interface principal"""
        # Frame principal
        main_frame = tk.Frame(self.root, bg=self.bg_dark, padx=10, pady=10)
        main_frame.pack(fill=tk.BOTH, expand=True)

        # ============ TOOLBAR ============
        toolbar_frame = tk.Frame(main_frame, bg=self.bg_medium, relief=tk.FLAT)
        toolbar_frame.pack(fill=tk.X, pady=(0, 10))

        btn_container = tk.Frame(toolbar_frame, bg=self.bg_medium)
        btn_container.pack(pady=15)

        # Botão Abrir
        self.btn_abrir = tk.Button(
            btn_container,
            text="📂 ABRIR ARQUIVO (Ctrl+O)",
            font=("Segoe UI", 12, "bold"),
            bg=self.accent,
            fg=self.text_white,
            activebackground=self.accent_hover,
            activeforeground=self.text_white,
            padx=30,
            pady=12,
            relief=tk.FLAT,
            cursor="hand2",
            command=self.selecionar_arquivo,
            borderwidth=0,
        )
        self.btn_abrir.pack(side=tk.LEFT, padx=8)

        # Botão Salvar
        self.btn_salvar_top = tk.Button(
            btn_container,
            text="💾 SALVAR (Ctrl+S)",
            font=("Segoe UI", 12, "bold"),
            bg=self.success,
            fg=self.text_white,
            activebackground="#45a049",
            activeforeground=self.text_white,
            padx=30,
            pady=12,
            relief=tk.FLAT,
            cursor="hand2",
            command=self.salvar_arquivo,
            borderwidth=0,
            state=tk.DISABLED,
        )
        self.btn_salvar_top.pack(side=tk.LEFT, padx=8)
        
        # Relógio
        self.clock_label = tk.Label(
            btn_container,
            text="",
            font=("Consolas", 14, "bold"),
            bg=self.bg_medium,
            fg=self.accent,
            padx=20
        )
        self.clock_label.pack(side=tk.RIGHT, padx=20)
        self.atualizar_relogio()

        # ============ INFO DO ARQUIVO ============
        info_frame = tk.Frame(main_frame, bg=self.bg_medium, relief=tk.FLAT)
        info_frame.pack(fill=tk.X, pady=(0, 10))

        info_inner = tk.Frame(info_frame, bg=self.bg_medium)
        info_inner.pack(fill=tk.X, padx=20, pady=15)

        tk.Label(
            info_inner,
            text="📄 ARQUIVO:",
            font=("Segoe UI", 10, "bold"),
            bg=self.bg_medium,
            fg=self.text_gray,
        ).pack(anchor="w")

        self.file_label = tk.Label(
            info_inner,
            text="Nenhum arquivo carregado",
            font=("Segoe UI", 11),
            bg=self.bg_medium,
            fg=self.text_gray,
            anchor="w",
        )
        self.file_label.pack(anchor="w", pady=(5, 0))

        # ============ LAYOUT PRINCIPAL: 3 COLUNAS ============
        # Coluna 1: TreeView (materiais/chapas) - 25%
        # Coluna 2: Canvas (visualização) - 50%
        # Coluna 3: Painel de edição - 25%
        
        content_frame = tk.Frame(main_frame, bg=self.bg_dark)
        content_frame.pack(fill=tk.BOTH, expand=True)
        
        # Configurar grid
        content_frame.grid_columnconfigure(0, weight=1, minsize=300)
        content_frame.grid_columnconfigure(1, weight=2, minsize=600)
        content_frame.grid_columnconfigure(2, weight=1, minsize=300)
        content_frame.grid_rowconfigure(0, weight=1)
        
        # Tema
        theme_colors = {
            'bg_dark': self.bg_dark,
            'bg_medium': self.bg_medium,
            'bg_light': self.bg_light,
            'accent': self.accent,
            'accent_hover': self.accent_hover,
            'text_white': self.text_white,
            'text_gray': self.text_gray,
            'success': self.success,
            'warning': self.warning,
            'error': self.error
        }
        
        # ====== COLUNA 1: TreeView Manager ======
        tree_container = tk.Frame(content_frame, bg=self.bg_dark)
        tree_container.grid(row=0, column=0, sticky='nsew', padx=(0, 5))
        
        self.tree_manager = TreeViewManager(
            tree_container,
            theme_colors,
            self.on_sheet_selected
        )
        
        # ====== COLUNA 2: Canvas Renderer ======
        canvas_container = tk.Frame(content_frame, bg=self.bg_dark)
        canvas_container.grid(row=0, column=1, sticky='nsew', padx=5)
        
        self.canvas_renderer = CanvasRenderer(canvas_container, theme_colors)
        
        # ====== COLUNA 3: Edit Controls Panel ======
        edit_container = tk.Frame(content_frame, bg=self.bg_dark)
        edit_container.grid(row=0, column=2, sticky='nsew', padx=(5, 0))
        
        self.edit_panel = EditControlsPanel(
            edit_container,
            theme_colors,
            self.xml_tree,
            self.salvar_arquivo
        )

        # ============ STATUS BAR ============
        status_frame = tk.Frame(main_frame, bg=self.bg_medium, relief=tk.FLAT)
        status_frame.pack(fill=tk.X, pady=(10, 0))

        self.status_label = tk.Label(
            status_frame,
            text="⚡ Pronto para carregar arquivo",
            font=("Segoe UI", 9),
            bg=self.bg_medium,
            fg=self.text_gray,
            anchor="w",
            padx=15,
            pady=8,
        )
        self.status_label.pack(fill=tk.X)

    def on_sheet_selected(self, material_data, program_data):
        """
        Callback quando uma chapa é selecionada na árvore
        Args:
            material_data: Dados do material
            program_data: Dados do programa/chapa
        """
        # Renderizar no canvas
        self.canvas_renderer.render_cutting_plan(material_data, program_data)
        
        # Carregar no painel de edição
        self.edit_panel.load_sheet_for_edit(material_data, program_data)
        
        self.status_label.config(
            text=f"✅ Chapa {program_data['number']} selecionada - {material_data['description']}",
            fg=self.success
        )

    def atualizar_relogio(self):
        """Atualiza o relógio em tempo real"""
        agora = datetime.now()
        tempo_str = agora.strftime("%H:%M:%S")
        data_str = agora.strftime("%d/%m/%Y")
        self.clock_label.config(text=f"🕐 {tempo_str}\n📅 {data_str}")
        self.root.after(1000, self.atualizar_relogio)

    def selecionar_arquivo(self):
        """Abre diálogo para selecionar arquivo"""
        initial_dir = self.base_path if os.path.exists(self.base_path) else os.path.expanduser("~")
        file_path = filedialog.askopenfilename(
            title="Selecionar arquivo .cutplanning",
            initialdir=initial_dir,
            filetypes=[("Cut Planning", "*.cutplanning"), ("XML", "*.xml"), ("Todos", "*.*")],
        )
        if file_path:
            self.carregar_arquivo(file_path)

    def carregar_arquivo(self, file_path):
        """Carrega arquivo e popula a interface"""
        try:
            # Usar o parser para carregar o arquivo
            self.xml_tree, self.xml_root = self.parser.load_file(file_path)

            # Normalizar caminho
            filename = os.path.basename(file_path)
            if os.path.dirname(file_path) == self.base_path:
                self.current_file_path = file_path
            else:
                self.current_file_path = os.path.join(self.base_path, filename)

            # Atualizar label do arquivo
            self.file_label.config(
                text=f"📁 {os.path.basename(file_path)}\nCaminho: {self.current_file_path}",
                fg=self.success,
            )
            
            # Parse all materials and programs
            materials_data = self.parser.parse_all_materials()
            
            # Atualizar tree view
            self.tree_manager.populate_tree(materials_data)
            
            # Atualizar edit panel com referência ao xml_tree
            self.edit_panel.xml_tree = self.xml_tree
            
            # Estatísticas
            total_materials = len(materials_data)
            total_sheets = sum(len(m['programs']) for m in materials_data)
            total_pieces = sum(sum(len(p['pieces']) for p in m['programs']) for m in materials_data)
            
            self.status_label.config(
                text=f"✅ Arquivo carregado! {total_materials} materiais, {total_sheets} chapas, {total_pieces} peças",
                fg=self.success,
            )

            # Habilitar botão salvar
            self.btn_salvar_top.config(state=tk.NORMAL)

        except Exception as e:
            self.status_label.config(text=f"❌ Erro ao carregar: {str(e)}", fg=self.error)
            messagebox.showerror("Erro ao Abrir", f"Erro ao abrir arquivo:\n{str(e)}")

    def salvar_arquivo(self):
        """Salva alterações no arquivo XML"""
        if self.xml_tree is None or self.current_file_path is None:
            messagebox.showerror("Erro", "Nenhum arquivo carregado!")
            return

        try:
            os.makedirs(os.path.dirname(self.current_file_path), exist_ok=True)
            
            # Ler o arquivo original para preservar formatação exata
            with open(self.current_file_path, 'r', encoding='utf-8') as f:
                original_content = f.read()
            
            # Extrair declaração XML original
            xml_declaration = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
            if original_content.startswith('<?xml'):
                declaration_end = original_content.find('?>') + 2
                xml_declaration = original_content[:declaration_end]
            
            # Gerar XML string mantendo formatação
            xml_str = ET.tostring(self.xml_root, encoding='unicode', method='xml')
            
            # Remover declaração gerada automaticamente se existir
            if xml_str.startswith('<?xml'):
                xml_str = xml_str[xml_str.find('?>') + 2:]
            
            # Ajustar formatação para match exato com original
            xml_str = xml_str.replace("'", '"')  # Aspas duplas
            xml_str = xml_str.replace(' />', '/>')  # Remover espaço antes de />
            xml_str = xml_str.replace('&amp;gt;', '>')  # Reverter entidades HTML desnecessárias
            xml_str = xml_str.replace('&amp;lt;', '<')
            
            # Escrever arquivo com formatação preservada
            final_content = xml_declaration + xml_str
            
            with open(self.current_file_path, 'w', encoding='utf-8') as f:
                f.write(final_content)

            file_size = os.path.getsize(self.current_file_path)
            self.status_label.config(
                text=f"✅ Arquivo salvo! ({file_size} bytes) - {self.current_file_path}",
                fg=self.success,
            )
            messagebox.showinfo(
                "✅ Sucesso",
                f"Arquivo salvo com sucesso!\n\n"
                f"📍 Localização:\n{self.current_file_path}\n\n"
                f"📊 Tamanho: {file_size:,} bytes",
            )
        except Exception as e:
            self.status_label.config(text=f"❌ Erro ao salvar: {str(e)}", fg=self.error)
            messagebox.showerror("❌ Erro ao Salvar", f"Erro ao salvar arquivo:\n{str(e)}")

    def recarregar_arquivo(self):
        """Recarrega o arquivo atual do disco"""
        if not self.current_file_path:
            messagebox.showwarning("🔄 Recarregar", "Nenhum arquivo está aberto para recarregar.")
            return
        
        resposta = messagebox.askyesno(
            "🔄 Recarregar Arquivo",
            "Deseja recarregar o arquivo do disco?\n\n⚠️ As alterações não salvas serão perdidas!",
        )
        if not resposta:
            return
        
        # Recarregar arquivo
        try:
            self.carregar_arquivo(self.current_file_path)
            self.status_label.config(
                text=f"✅ Arquivo recarregado: {os.path.basename(self.current_file_path)}",
                fg=self.success
            )
        except Exception as e:
            messagebox.showerror("❌ Erro ao Recarregar", f"Erro ao recarregar arquivo:\n{str(e)}")
            self.status_label.config(text="❌ Erro ao recarregar arquivo", fg=self.error)


if __name__ == "__main__":
    root = tk.Tk()
    app = EditorPlanoCorte(root)
    root.mainloop()
