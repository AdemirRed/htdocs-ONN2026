import json
import requests
import threading
import time
import tkinter as tk
from tkinter import ttk, messagebox, scrolledtext
from datetime import datetime, timedelta
import os
import base64
from PIL import Image, ImageTk
import io
from collections import defaultdict
import queue
import tempfile
from http.server import HTTPServer, BaseHTTPRequestHandler
import urllib.parse

class WebhookHandler(BaseHTTPRequestHandler):
    """Handler para receber webhooks de novas mensagens"""
    
    def do_POST(self):
        """Processa webhooks POST"""
        try:
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            
            # Decodificar dados do webhook
            webhook_data = json.loads(post_data.decode('utf-8'))
            
            # Adicionar à queue de webhooks da aplicação principal
            if hasattr(self.server, 'app_instance'):
                self.server.app_instance.process_webhook(webhook_data)
            
            # Responder com sucesso
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"status": "success"}).encode())
            
            print(f"📩 Webhook recebido: {webhook_data.get('type', 'unknown')}")
            
        except Exception as e:
            print(f"❌ Erro no webhook: {e}")
            self.send_response(500)
            self.end_headers()
    
    def log_message(self, format, *args):
        """Suprimir logs do servidor HTTP"""
        pass

class WhatsAppCompleteMessages:
    def __init__(self, root):
        self.root = root
        self.root.title("WhatsApp Complete Messages - fetchMessages API")
        self.root.geometry("1600x1000")
        self.root.configure(bg='#f0f2f5')
        
        # Configurações da API
        self.api_base = "http://192.168.0.201:200"
        self.api_key = "redblack"
        self.headers = {
            'accept': '*/*',
            'x-api-key': self.api_key,
            'Content-Type': 'application/json'
        }
        
        # Configurações do Webhook
        self.webhook_port = 8888
        self.webhook_server = None
        self.webhook_thread = None
        
        # Cache de mídias
        self.media_cache = {}
        self.media_dir = os.path.join(tempfile.gettempdir(), "whatsapp_media")
        os.makedirs(self.media_dir, exist_ok=True)
        
        # Dados e controle
        self.sessions = {}
        self.current_session = None
        self.current_chat = None
        self.unread_counts = defaultdict(int)
        self.last_message_timestamps = defaultdict(int)
        
        # Controle de notificações
        self.initial_load = True
        self.notifications_enabled = True
        self.webhook_notifications = True
        
        # Controle de polling
        self.polling_active = False
        self.polling_interval = 10
        
        # Queue para operações thread-safe
        self.ui_queue = queue.Queue()
        self.webhook_queue = queue.Queue()
        
        # Sistema de som simplificado
        self.notification_sound = False
        self.init_sound_system()
        
        # Interface
        self.setup_ui()
        
        # Processar queues
        self.process_ui_queue()
        self.process_webhook_queue()
        
        # Iniciar sistemas
        self.start_webhook_server()
        self.start_polling()
        self.check_api_status()
        self.load_initial_data()
    
    def init_sound_system(self):
        """Inicializa sistema de som"""
        try:
            import winsound
            self.notification_sound = True
            self.sound_method = 'winsound'
            print("🔊 Sistema de som Windows inicializado")
        except ImportError:
            try:
                import pygame
                pygame.mixer.init()
                self.notification_sound = True
                self.sound_method = 'pygame'
                print("🔊 Sistema de som Pygame inicializado")
            except:
                self.notification_sound = False
                self.sound_method = None
                print("⚠️ Sistema de som desabilitado")
    
    def check_api_status(self):
        """Verifica status da API usando /ping"""
        def check_thread():
            try:
                self.update_operation_status("🔍 Verificando API...")
                
                url = f"{self.api_base}/ping"
                response = requests.get(url, headers={'accept': '*/*'}, timeout=10)
                
                if response.status_code == 200:
                    data = response.json()
                    if data.get('success') and data.get('message') == 'pong':
                        print("✅ API Status: Online")
                        self.update_operation_status("✅ API Online - ping OK")
                        self.root.after(0, lambda: self.connection_status.config(text="🟢 API Online", fg='#25d366'))
                    else:
                        print("⚠️ API Status: Resposta inesperada")
                        self.update_operation_status("⚠️ API resposta inesperada")
                else:
                    print(f"❌ API Status: HTTP {response.status_code}")
                    self.update_operation_status(f"❌ API HTTP {response.status_code}")
                    
            except Exception as e:
                print(f"❌ API Status: {e}")
                self.update_operation_status("❌ API não disponível")
                self.root.after(0, lambda: self.connection_status.config(text="🔴 API Offline", fg='#ff6b6b'))
        
        threading.Thread(target=check_thread, daemon=True).start()
    
    def play_notification_sound(self):
        """Toca som de notificação"""
        if not self.notification_sound or not self.notifications_enabled:
            return
        
        try:
            if self.sound_method == 'winsound':
                import winsound
                winsound.MessageBeep(winsound.MB_OK)
            elif self.sound_method == 'pygame':
                self.beep_sound()
        except Exception as e:
            print(f"❌ Erro ao tocar som: {e}")
    
    def beep_sound(self):
        """Som simples"""
        try:
            import pygame
            pygame.mixer.init(frequency=22050, size=-16, channels=2, buffer=512)
            
            duration_ms = 200
            sample_rate = 22050
            frames = int(sample_rate * duration_ms / 1000)
            
            arr = []
            for i in range(frames):
                value = 10000 if (i // 100) % 2 == 0 else -10000
                arr.append([value, value])
            
            sound = pygame.sndarray.make_sound(arr)
            sound.play()
        except:
            pass
    
    def start_webhook_server(self):
        """Inicia servidor webhook"""
        def start_server():
            try:
                self.webhook_server = HTTPServer(('localhost', self.webhook_port), WebhookHandler)
                self.webhook_server.app_instance = self
                
                print(f"🌐 Servidor webhook iniciado na porta {self.webhook_port}")
                self.update_operation_status(f"🌐 Webhook ativo: localhost:{self.webhook_port}")
                
                self.webhook_server.serve_forever()
                
            except Exception as e:
                print(f"❌ Erro no servidor webhook: {e}")
                self.update_operation_status("❌ Webhook falhou")
        
        self.webhook_thread = threading.Thread(target=start_server, daemon=True)
        self.webhook_thread.start()
    
    def process_webhook(self, webhook_data):
        """Processa webhook recebido"""
        try:
            self.webhook_queue.put(webhook_data)
            print(f"📨 Webhook adicionado à queue: {webhook_data.get('type', 'unknown')}")
        except Exception as e:
            print(f"❌ Erro ao processar webhook: {e}")
    
    def process_webhook_queue(self):
        """Processa queue de webhooks"""
        try:
            while True:
                webhook_data = self.webhook_queue.get_nowait()
                self.handle_webhook_notification(webhook_data)
        except queue.Empty:
            pass
        
        self.root.after(500, self.process_webhook_queue)
    
    def handle_webhook_notification(self, webhook_data):
        """Manipula notificação do webhook"""
        try:
            webhook_type = webhook_data.get('type', '')
            session_id = webhook_data.get('sessionId', '')
            
            if webhook_type == 'message' and self.webhook_notifications:
                message_data = webhook_data.get('data', {})
                chat_id = message_data.get('from', '')
                
                if not message_data.get('fromMe', False):
                    self.play_notification_sound()
                    
                    chat_name = self.extract_chat_name_from_webhook(webhook_data)
                    self.root.title(f"📩 Nova mensagem via webhook: {chat_name}")
                    
                    self.root.after(5000, lambda: self.root.title("WhatsApp Complete Messages - fetchMessages API"))
                    
                    if session_id in self.sessions:
                        self.refresh_session_chats(session_id)
                    
                    print(f"🔔 Nova mensagem webhook: {chat_name} na sessão {session_id}")
                    self.update_operation_status(f"📩 Nova mensagem: {chat_name}")
            
        except Exception as e:
            print(f"❌ Erro ao manipular webhook: {e}")
    
    def extract_chat_name_from_webhook(self, webhook_data):
        """Extrai nome do chat do webhook"""
        try:
            message_data = webhook_data.get('data', {})
            chat_id = message_data.get('from', '')
            
            if '@g.us' in chat_id:
                return f"Grupo {chat_id.split('@')[0][-8:]}"
            else:
                phone = chat_id.replace('@c.us', '')
                if phone.startswith('55') and len(phone) >= 12:
                    return f"+55 ({phone[2:4]}) {phone[4:9]}-{phone[9:]}"
                return phone
        except:
            return "Conversa"
    
    def refresh_session_chats(self, session_id):
        """Atualiza chats de uma sessão específica"""
        def refresh_thread():
            try:
                success = self.load_session_data(session_id, suppress_notifications=True)
                if success:
                    self.root.after(0, lambda: self.update_session_tab(session_id))
                    self.root.after(0, self.update_total_unread_counter)
                    print(f"✅ Sessão {session_id} atualizada via webhook")
            except Exception as e:
                print(f"❌ Erro ao atualizar sessão {session_id}: {e}")
        
        threading.Thread(target=refresh_thread, daemon=True).start()
    
    def process_ui_queue(self):
        """Processa queue da UI de forma thread-safe"""
        try:
            while True:
                action, args = self.ui_queue.get_nowait()
                if action == 'show_loading':
                    self._show_loading_safe(args)
                elif action == 'show_error':
                    self._show_error_safe(args)
                elif action == 'display_messages':
                    self._display_messages_safe(args)
                elif action == 'update_status':
                    self._update_status_safe(args)
        except queue.Empty:
            pass
        
        self.root.after(100, self.process_ui_queue)
    
    def queue_ui_action(self, action, args=None):
        """Adiciona ação à queue de forma thread-safe"""
        self.ui_queue.put((action, args))
    
    def setup_ui(self):
        """Configura a interface principal"""
        self.setup_header()
        
        main_container = tk.Frame(self.root, bg='#f0f2f5')
        main_container.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        
        self.left_panel = tk.Frame(main_container, bg='white', width=420, relief=tk.RAISED, bd=1)
        self.left_panel.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 5))
        self.left_panel.pack_propagate(False)
        
        self.right_panel = tk.Frame(main_container, bg='#e5ddd5', relief=tk.RAISED, bd=1)
        self.right_panel.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        
        self.setup_left_panel()
        self.setup_right_panel()
    
    def setup_header(self):
        """Configura header principal"""
        header = tk.Frame(self.root, bg='#075e54', height=80)
        header.pack(fill=tk.X)
        header.pack_propagate(False)
        
        header_content = tk.Frame(header, bg='#075e54')
        header_content.pack(fill=tk.BOTH, padx=20, pady=15)
        
        title_frame = tk.Frame(header_content, bg='#075e54')
        title_frame.pack(side=tk.LEFT)
        
        tk.Label(title_frame, text="📡 WhatsApp Complete Messages", 
                fg='white', bg='#075e54', font=('Arial', 18, 'bold')).pack(side=tk.LEFT)
        
        self.connection_status = tk.Label(title_frame, text="🔄 Verificando API...", 
                                        fg='#ffc107', bg='#075e54', font=('Arial', 10))
        self.connection_status.pack(side=tk.LEFT, padx=(20, 0))
        
        controls_frame = tk.Frame(header_content, bg='#075e54')
        controls_frame.pack(side=tk.RIGHT)
        
        self.operation_status = tk.Label(controls_frame, text="Inicializando sistema...", 
                                       fg='#25d366', bg='#075e54', font=('Arial', 10))
        self.operation_status.pack(side=tk.RIGHT, padx=(20, 0))
        
        self.total_unread_label = tk.Label(controls_frame, text="📬 0", 
                                         fg='#25d366', bg='#075e54', font=('Arial', 12, 'bold'))
        self.total_unread_label.pack(side=tk.RIGHT, padx=(20, 0))
        
        self.webhook_btn = tk.Button(controls_frame, text="📩 Webhook", 
                                   command=self.toggle_webhook_notifications,
                                   bg='#9c27b0', fg='white', font=('Arial', 10, 'bold'),
                                   relief=tk.RAISED, bd=2, padx=15, pady=5, cursor='hand2')
        self.webhook_btn.pack(side=tk.RIGHT, padx=(0, 10))
        
        self.notif_btn = tk.Button(controls_frame, text="🔔 Som", 
                                 command=self.toggle_notifications,
                                 bg='#25d366', fg='white', font=('Arial', 10, 'bold'),
                                 relief=tk.RAISED, bd=2, padx=15, pady=5, cursor='hand2')
        self.notif_btn.pack(side=tk.RIGHT, padx=(0, 10))
        
        refresh_btn = tk.Button(controls_frame, text="🔄 Atualizar", 
                              command=self.manual_refresh,
                              bg='#2196f3', fg='white', font=('Arial', 10, 'bold'),
                              relief=tk.RAISED, bd=2, padx=15, pady=5, cursor='hand2')
        refresh_btn.pack(side=tk.RIGHT, padx=(0, 10))
        
        self.polling_btn = tk.Button(controls_frame, text="⏸️ Pausar", 
                                   command=self.toggle_polling,
                                   bg='#ff9500', fg='white', font=('Arial', 10, 'bold'),
                                   relief=tk.RAISED, bd=2, padx=15, pady=5, cursor='hand2')
        self.polling_btn.pack(side=tk.RIGHT, padx=(0, 5))
    
    def toggle_webhook_notifications(self):
        """Alterna notificações webhook"""
        self.webhook_notifications = not self.webhook_notifications
        
        if self.webhook_notifications:
            self.webhook_btn.config(text="📩 Webhook", bg='#9c27b0')
            self.update_operation_status("📩 Webhook notificações ativadas")
        else:
            self.webhook_btn.config(text="📩 Mudo", bg='#ff5722')
            self.update_operation_status("📩 Webhook notificações desativadas")
    
    def toggle_notifications(self):
        """Alterna notificações sonoras"""
        self.notifications_enabled = not self.notifications_enabled
        
        if self.notifications_enabled:
            self.notif_btn.config(text="🔔 Som", bg='#25d366')
            self.update_operation_status("🔊 Notificações sonoras ativadas")
        else:
            self.notif_btn.config(text="🔕 Mudo", bg='#ff5722')
            self.update_operation_status("🔇 Notificações sonoras desativadas")
    
    def update_operation_status(self, message):
        """Atualiza status da operação atual - THREAD SAFE"""
        def update():
            if hasattr(self, 'operation_status'):
                self.operation_status.config(text=message)
        
        if threading.current_thread() == threading.main_thread():
            update()
        else:
            self.root.after(0, update)
        
        print(f"📊 Status: {message}")
    
    def setup_left_panel(self):
        """Configura painel esquerdo com sessões e chats"""
        left_header = tk.Frame(self.left_panel, bg='#ededed', height=60)
        left_header.pack(fill=tk.X)
        left_header.pack_propagate(False)
        
        header_content = tk.Frame(left_header, bg='#ededed')
        header_content.pack(fill=tk.BOTH, padx=15, pady=10)
        
        tk.Label(header_content, text="📡 fetchMessages API", 
                bg='#ededed', fg='#075e54', font=('Arial', 14, 'bold')).pack(anchor='w')
        
        tk.Label(header_content, text="Todas as mensagens da API", 
                bg='#ededed', fg='#666', font=('Arial', 9)).pack(anchor='w')
        
        self.sessions_notebook = ttk.Notebook(self.left_panel)
        self.sessions_notebook.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        self.sessions_notebook.bind("<<NotebookTabChanged>>", self.on_session_change)
    
    def setup_right_panel(self):
        """Configura painel direito com mensagens"""
        self.chat_header = tk.Frame(self.right_panel, bg='#075e54', height=80)
        self.chat_header.pack(fill=tk.X)
        self.chat_header.pack_propagate(False)
        
        header_content = tk.Frame(self.chat_header, bg='#075e54')
        header_content.pack(fill=tk.BOTH, padx=20, pady=10)
        
        self.chat_title = tk.Label(header_content, text="Selecione uma conversa", 
                                 fg='white', bg='#075e54', font=('Arial', 16, 'bold'))
        self.chat_title.pack(anchor='w')
        
        self.chat_subtitle = tk.Label(header_content, text="fetchMessages carrega TODAS as mensagens", 
                                    fg='#25d366', bg='#075e54', font=('Arial', 10))
        self.chat_subtitle.pack(anchor='w')
        
        # Área de mensagens
        messages_container = tk.Frame(self.right_panel, bg='#e5ddd5')
        messages_container.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        
        self.messages_canvas = tk.Canvas(messages_container, bg='#e5ddd5', highlightthickness=0)
        messages_scrollbar = ttk.Scrollbar(messages_container, orient="vertical", 
                                         command=self.messages_canvas.yview)
        
        self.messages_frame = tk.Frame(self.messages_canvas, bg='#e5ddd5')
        
        self.messages_frame.bind("<Configure>", 
                               lambda e: self.messages_canvas.configure(scrollregion=self.messages_canvas.bbox("all")))
        
        self.messages_canvas.create_window((0, 0), window=self.messages_frame, anchor="nw")
        self.messages_canvas.configure(yscrollcommand=messages_scrollbar.set)
        
        messages_scrollbar.pack(side="right", fill="y")
        self.messages_canvas.pack(side="left", fill="both", expand=True)
        
        def on_mousewheel(event):
            self.messages_canvas.yview_scroll(int(-1*(event.delta/120)), "units")
        
        self.messages_canvas.bind("<MouseWheel>", on_mousewheel)
        
        self.show_welcome_message()
        
        input_frame = tk.Frame(self.right_panel, bg='#f0f0f0', height=60)
        input_frame.pack(fill=tk.X, padx=10, pady=5)
        input_frame.pack_propagate(False)
        
        tk.Label(input_frame, text="📡 fetchMessages API - Carrega TODAS as mensagens da conversa", 
                bg='#f0f0f0', fg='#666', font=('Arial', 12)).pack(pady=20)
    
    def show_welcome_message(self):
        """Mostra mensagem de boas-vindas"""
        welcome_frame = tk.Frame(self.messages_frame, bg='#e5ddd5')
        welcome_frame.pack(fill=tk.BOTH, expand=True)
        
        welcome_text = f"""
📡 WhatsApp Complete Messages - fetchMessages API

👋 Olá, AdemirRed!

🕒 Sistema iniciado: 30/10/2025 17:42:02 (UTC)
📡 API fetchMessages ativa
🌐 Servidor webhook: localhost:{self.webhook_port}
📩 Notificações em tempo real via webhook

💡 Funcionalidades:
• fetchMessages API - carrega TODAS as mensagens
• Webhook para notificações instantâneas 
• Sistema de polling otimizado (10s)
• Notificações sonoras configuráveis

📱 Sessões monitoradas: vanessa, ademir, pedro
🔊 Controles: 🔔/🔕 (som) | 📩 (webhook) | 🔄 (refresh)

🔧 API Endpoint:
POST /chat/fetchMessages/{{sessionId}}
Payload: {{"chatId": "chat_id", "searchOptions": {{}}}}
        """
        
        tk.Label(welcome_frame, text=welcome_text, 
                bg='#e5ddd5', fg='#075e54', font=('Arial', 11),
                justify=tk.CENTER).pack(expand=True, pady=30)
    
    def load_initial_data(self):
        """Carrega dados iniciais via API"""
        def load_thread():
            try:
                self.update_operation_status("🔗 Conectando com API...")
                
                known_sessions = ['vanessa', 'ademir', 'pedro']
                loaded_sessions = []
                
                for session_id in known_sessions:
                    try:
                        self.update_operation_status(f"📡 Carregando {session_id} via API...")
                        success = self.load_session_data(session_id, suppress_notifications=True)
                        if success:
                            loaded_sessions.append(session_id)
                        time.sleep(0.5)
                    except Exception as e:
                        print(f"❌ Erro ao carregar sessão {session_id}: {e}")
                
                self.initial_load = False
                
                if loaded_sessions:
                    self.update_operation_status(f"✅ {len(loaded_sessions)} sessões fetchMessages ativas")
                    self.root.after(0, lambda: self.connection_status.config(text="🟢 API Online", fg='#25d366'))
                else:
                    self.update_operation_status("❌ Falha na conexão API")
                    self.root.after(0, lambda: self.connection_status.config(text="🔴 API Offline", fg='#ff6b6b'))
                
            except Exception as e:
                self.update_operation_status(f"❌ Erro API: {str(e)[:15]}...")
                self.root.after(0, lambda: self.connection_status.config(text="🔴 Erro API", fg='#ff6b6b'))
        
        threading.Thread(target=load_thread, daemon=True).start()
    
    def load_session_data(self, session_id, suppress_notifications=False):
        """Carrega dados de uma sessão via API"""
        try:
            url = f"{self.api_base}/client/getChats/{session_id}"
            
            print(f"🔍 API Request: {url}")
            response = requests.get(url, headers=self.headers, timeout=15)
            
            print(f"📡 API Response {session_id}: {response.status_code}")
            
            if response.status_code == 200:
                data = response.json()
                
                if data.get('success') and data.get('chats'):
                    chats = data['chats']
                    
                    # Filtrar apenas chats com mensagens
                    filtered_chats = [chat for chat in chats if self.chat_has_valid_messages(chat)]
                    
                    print(f"📊 Sessão {session_id}: {len(chats)} total, {len(filtered_chats)} com mensagens")
                    
                    self.sessions[session_id] = filtered_chats
                    self.process_session_chats(session_id, filtered_chats, suppress_notifications)
                    
                    # Atualizar UI
                    self.root.after(0, lambda: self.update_session_tab(session_id))
                    
                    # Atualizar timestamps
                    for chat in filtered_chats:
                        chat_id = chat['id']['_serialized']
                        if chat.get('lastMessage'):
                            msg_timestamp = chat['lastMessage'].get('timestamp', 0)
                            self.last_message_timestamps[f"{session_id}_{chat_id}"] = msg_timestamp
                    
                    print(f"✅ API {session_id}: {len(filtered_chats)} conversas carregadas")
                    return True
                else:
                    print(f"⚠️ API {session_id}: Resposta inválida")
                    return False
            else:
                print(f"❌ API {session_id}: HTTP {response.status_code}")
                return False
                
        except requests.exceptions.Timeout:
            print(f"❌ API {session_id}: Timeout")
            return False
        except requests.exceptions.ConnectionError:
            print(f"❌ API {session_id}: Conexão falhou")
            return False
        except Exception as e:
            print(f"❌ API {session_id}: {e}")
            return False
    
    def chat_has_valid_messages(self, chat):
        """Verifica se o chat tem mensagens válidas"""
        try:
            if not chat.get('lastMessage'):
                return False
            
            last_message = chat['lastMessage']
            message_body = last_message.get('body', '').strip()
            message_type = last_message.get('type', '')
            has_media = last_message.get('hasMedia', False)
            
            if message_body or has_media or message_type in ['image', 'video', 'document', 'audio', 'ptt', 'sticker']:
                return True
            
            return False
            
        except Exception as e:
            print(f"❌ Erro ao validar chat: {e}")
            return False
    
    def fetch_all_messages(self, session_id, chat_id):
        """Busca TODAS as mensagens usando fetchMessages API"""
        def fetch_thread():
            try:
                self.queue_ui_action('show_loading', "📡 Buscando TODAS as mensagens via fetchMessages...")
                self.update_operation_status("📡 fetchMessages API em execução...")
                
                # **USAR O ENDPOINT fetchMessages CORRETO**
                url = f"{self.api_base}/chat/fetchMessages/{session_id}"
                payload = {
                    "chatId": chat_id,
                    "searchOptions": {}
                }
                
                print(f"🔍 fetchMessages Request: {url}")
                print(f"📄 fetchMessages Payload: {payload}")
                
                response = requests.post(url, headers=self.headers, json=payload, timeout=30)
                
                print(f"📡 fetchMessages Response: {response.status_code}")
                
                if response.status_code == 200:
                    data = response.json()
                    
                    print(f"📊 fetchMessages Response keys: {list(data.keys())}")
                    
                    if data.get('success'):
                        # Buscar mensagens no response
                        messages = self.extract_messages_from_fetch_response(data)
                        
                        if messages:
                            print(f"✅ fetchMessages: {len(messages)} mensagens encontradas")
                            
                            # Exibir mensagens
                            self.queue_ui_action('display_messages', {
                                'messages': messages,
                                'chat_id': chat_id,
                                'session_id': session_id
                            })
                            
                            self.update_operation_status(f"✅ {len(messages)} mensagens fetchMessages")
                        else:
                            self.queue_ui_action('show_error', "📭 Nenhuma mensagem retornada pelo fetchMessages")
                            self.update_operation_status("📭 fetchMessages vazio")
                        
                    else:
                        error_msg = "❌ fetchMessages falhou"
                        print(f"❌ fetchMessages error: {data}")
                        self.queue_ui_action('show_error', error_msg)
                        self.update_operation_status("❌ fetchMessages falhou")
                        
                else:
                    error_msg = f"❌ fetchMessages HTTP {response.status_code}"
                    print(f"❌ fetchMessages Error: {response.text[:300]}...")
                    self.queue_ui_action('show_error', error_msg)
                    self.update_operation_status(f"❌ fetchMessages HTTP {response.status_code}")
            
            except requests.exceptions.Timeout:
                error_msg = "❌ fetchMessages Timeout"
                print("❌ fetchMessages Timeout")
                self.queue_ui_action('show_error', error_msg)
                self.update_operation_status("❌ fetchMessages Timeout")
                
            except requests.exceptions.ConnectionError:
                error_msg = "❌ fetchMessages Connection Error"
                print("❌ fetchMessages Connection Error")
                self.queue_ui_action('show_error', error_msg)
                self.update_operation_status("❌ fetchMessages Connection Error")
                
            except Exception as e:
                error_msg = f"❌ fetchMessages Error: {str(e)}"
                print(f"❌ fetchMessages General Error: {e}")
                self.queue_ui_action('show_error', error_msg)
                self.update_operation_status("❌ fetchMessages Error")
        
        threading.Thread(target=fetch_thread, daemon=True).start()
    
    def extract_messages_from_fetch_response(self, response_data):
        """Extrai mensagens do response do fetchMessages"""
        try:
            messages = []
            
            # Tentar diferentes campos onde as mensagens podem estar
            possible_fields = [
                'messages', 'data', 'result', 'messageList', 
                'conversation', 'chatMessages', 'history'
            ]
            
            for field in possible_fields:
                if response_data.get(field):
                    field_data = response_data[field]
                    
                    # Se é uma lista de mensagens
                    if isinstance(field_data, list):
                        print(f"📚 fetchMessages campo '{field}': {len(field_data)} itens")
                        messages.extend(field_data)
                    
                    # Se é um objeto que contém mensagens
                    elif isinstance(field_data, dict):
                        for subfield in possible_fields:
                            if field_data.get(subfield) and isinstance(field_data[subfield], list):
                                print(f"📚 fetchMessages {field}.{subfield}: {len(field_data[subfield])} itens")
                                messages.extend(field_data[subfield])
            
            # Filtrar mensagens válidas
            valid_messages = []
            for msg in messages:
                if self.is_valid_fetch_message(msg):
                    valid_messages.append(msg)
            
            # Remover duplicatas
            unique_messages = self.remove_duplicate_fetch_messages(valid_messages)
            
            # Ordenar por timestamp
            unique_messages.sort(key=lambda x: x.get('timestamp', 0))
            
            print(f"✅ fetchMessages processadas: {len(unique_messages)} mensagens válidas")
            
            return unique_messages
            
        except Exception as e:
            print(f"❌ Erro ao extrair mensagens fetchMessages: {e}")
            return []
    
    def is_valid_fetch_message(self, message):
        """Verifica se uma mensagem do fetchMessages é válida"""
        try:
            message_body = message.get('body', '').strip()
            message_type = message.get('type', '')
            has_media = message.get('hasMedia', False)
            timestamp = message.get('timestamp', 0)
            
            # Rejeitar mensagens muito antigas (mais de 2 anos)
            if timestamp > 0:
                two_years_ago = time.time() - (2 * 365 * 24 * 60 * 60)
                if timestamp < two_years_ago:
                    return False
            
            # Aceitar se tem conteúdo válido
            if message_body or has_media or message_type in ['image', 'video', 'document', 'audio', 'ptt', 'sticker']:
                return True
            
            return False
            
        except Exception as e:
            print(f"❌ Erro ao validar mensagem fetchMessages: {e}")
            return False
    
    def remove_duplicate_fetch_messages(self, messages):
        """Remove mensagens duplicadas do fetchMessages"""
        seen_ids = set()
        unique_messages = []
        
        for msg in messages:
            msg_id = self.extract_fetch_message_id(msg)
            
            if msg_id and msg_id not in seen_ids:
                seen_ids.add(msg_id)
                unique_messages.append(msg)
            elif not msg_id:
                # Criar ID baseado em conteúdo + timestamp
                content_hash = hash(f"{msg.get('timestamp', 0)}_{msg.get('body', '')[:30]}_{msg.get('fromMe', False)}")
                content_id = f"fetch_{content_hash}"
                if content_id not in seen_ids:
                    seen_ids.add(content_id)
                    unique_messages.append(msg)
        
        return unique_messages
    
    def extract_fetch_message_id(self, message):
        """Extrai ID único da mensagem do fetchMessages"""
        try:
            # Tentar diferentes estruturas de ID
            id_paths = [
                ['id', '_serialized'],
                ['_data', 'id', '_serialized'],
                ['messageId'],
                ['id', 'id'],
                ['_serialized'],
                ['key', '_serialized'],
                ['key', 'id']
            ]
            
            for path in id_paths:
                value = message
                for field in path:
                    value = value.get(field) if isinstance(value, dict) else None
                    if value is None:
                        break
                
                if value and isinstance(value, str):
                    return value
            
            return None
            
        except:
            return None
    
    def _show_loading_safe(self, message):
        """Mostra loading thread-safe"""
        for widget in self.messages_frame.winfo_children():
            widget.destroy()
        
        loading_frame = tk.Frame(self.messages_frame, bg='#e5ddd5')
        loading_frame.pack(fill=tk.BOTH, expand=True)
        
        self.loading_label = tk.Label(loading_frame, text=message, 
                                    bg='#e5ddd5', fg='#075e54', font=('Arial', 14))
        self.loading_label.pack(expand=True)
        
        self.animate_loading()
    
    def _show_error_safe(self, error_message):
        """Mostra erro thread-safe"""
        for widget in self.messages_frame.winfo_children():
            widget.destroy()
        
        error_frame = tk.Frame(self.messages_frame, bg='#e5ddd5')
        error_frame.pack(fill=tk.BOTH, expand=True)
        
        tk.Label(error_frame, text=error_message, 
                bg='#e5ddd5', fg='#d32f2f', font=('Arial', 12, 'bold')).pack(expand=True)
        
        retry_btn = tk.Button(error_frame, text="🔄 Tentar fetchMessages Novamente", 
                            command=self.retry_fetch_current_chat,
                            bg='#2196f3', fg='white', font=('Arial', 10, 'bold'),
                            relief=tk.RAISED, bd=2, padx=20, pady=10, cursor='hand2')
        retry_btn.pack(pady=20)
    
    def _display_messages_safe(self, data):
        """Exibe mensagens do fetchMessages thread-safe"""
        messages = data['messages']
        chat_id = data['chat_id']
        session_id = data['session_id']
        
        # Limpar área
        for widget in self.messages_frame.winfo_children():
            widget.destroy()
        
        if not messages:
            empty_frame = tk.Frame(self.messages_frame, bg='#e5ddd5')
            empty_frame.pack(fill=tk.BOTH, expand=True)
            
            tk.Label(empty_frame, text="📭 Nenhuma mensagem encontrada via fetchMessages", 
                    bg='#e5ddd5', fg='#667781', font=('Arial', 12)).pack(expand=True)
            return
        
        print(f"🎨 Exibindo {len(messages)} mensagens do fetchMessages")
        
        # Exibir mensagens do fetchMessages
        current_date = None
        
        for i, message in enumerate(messages):
            try:
                # Separador de data
                msg_timestamp = message.get('timestamp', 0)
                if msg_timestamp > 0:
                    msg_date = datetime.fromtimestamp(msg_timestamp).date()
                    if current_date != msg_date:
                        current_date = msg_date
                        self.create_date_separator(msg_date)
                
                # Criar bolha da mensagem
                self.create_fetch_message_bubble(message, session_id, chat_id)
                
                # Atualizar progresso
                if i % 20 == 0 and i > 0:
                    progress = f"📝 {i+1}/{len(messages)} fetchMessages"
                    self.update_operation_status(progress)
                    self.root.update_idletasks()
                    
            except Exception as e:
                print(f"❌ Erro ao exibir mensagem fetchMessages {i}: {e}")
                continue
        
        # Scroll para o final
        self.root.after(500, self.scroll_to_bottom)
        
        print(f"✅ {len(messages)} mensagens fetchMessages exibidas")
    
    def animate_loading(self):
        """Anima loading"""
        if hasattr(self, 'loading_label') and self.loading_label.winfo_exists():
            current_text = self.loading_label.cget('text')
            if current_text.endswith('...'):
                new_text = current_text[:-3]
            else:
                new_text = current_text + '.'
            
            self.loading_label.config(text=new_text)
            self.root.after(500, self.animate_loading)
    
    def retry_fetch_current_chat(self):
        """Retry fetchMessages para chat atual"""
        if self.current_session and self.current_chat:
            chat_id = self.current_chat['id']['_serialized']
            self.fetch_all_messages(self.current_session, chat_id)
    
    def create_date_separator(self, date):
        """Cria separador de data"""
        separator_frame = tk.Frame(self.messages_frame, bg='#e5ddd5')
        separator_frame.pack(fill=tk.X, pady=15)
        
        if date == datetime.now().date():
            date_text = "Hoje"
        elif date == datetime.now().date() - timedelta(days=1):
            date_text = "Ontem"
        else:
            date_text = date.strftime('%d/%m/%Y')
        
        date_label = tk.Label(separator_frame, text=date_text, 
                             bg='#fff3e0', fg='#5d4037', 
                             font=('Arial', 10, 'bold'), 
                             relief=tk.RAISED, bd=1, padx=15, pady=5)
        date_label.pack()
    
    def create_fetch_message_bubble(self, message, session_id, chat_id):
        """Cria bolha de mensagem do fetchMessages"""
        is_from_me = message.get('fromMe', False)
        
        # Container da mensagem
        msg_container = tk.Frame(self.messages_frame, bg='#e5ddd5')
        msg_container.pack(fill=tk.X, padx=10, pady=3)
        
        # Frame da mensagem
        if is_from_me:
            msg_frame = tk.Frame(msg_container, bg='#dcf8c6', relief=tk.RAISED, bd=1)
            msg_frame.pack(side=tk.RIGHT, anchor='e', padx=(80, 0), pady=2)
        else:
            msg_frame = tk.Frame(msg_container, bg='white', relief=tk.RAISED, bd=1)
            msg_frame.pack(side=tk.LEFT, anchor='w', padx=(0, 80), pady=2)
        
        # Conteúdo da mensagem
        content_frame = tk.Frame(msg_frame, bg=msg_frame['bg'])
        content_frame.pack(fill=tk.BOTH, padx=12, pady=8)
        
        # Nome do remetente (se disponível)
        sender_name = None
        if not is_from_me:
            # Tentar extrair nome do remetente
            possible_name_fields = [
                ['_data', 'notifyName'],
                ['notifyName'],
                ['author'],
                ['from'],
                ['sender']
            ]
            
            for path in possible_name_fields:
                value = message
                for field in path:
                    value = value.get(field) if isinstance(value, dict) else None
                    if value is None:
                        break
                
                if value and isinstance(value, str) and value != 'Contato':
                    sender_name = value
                    break
            
            if sender_name:
                sender_label = tk.Label(content_frame, text=f"👤 {sender_name}", 
                                      bg=msg_frame['bg'], fg='#128c7e', 
                                      font=('Arial', 9, 'bold'))
                sender_label.pack(anchor='w', pady=(0, 4))
        
        # Conteúdo da mensagem
        msg_type = message.get('type', 'chat')
        has_media = message.get('hasMedia', False)
        
        if has_media and msg_type in ['image', 'video', 'document', 'audio', 'ptt']:
            # Mensagem com mídia
            self.create_fetch_media_indicator(content_frame, message, msg_type)
        else:
            # Mensagem de texto
            body = message.get('body', '').strip()
            if body:
                text_label = tk.Label(content_frame, text=body, 
                                    bg=msg_frame['bg'], fg='#111b21', 
                                    font=('Arial', 11), wraplength=280, justify=tk.LEFT)
                text_label.pack(anchor='w', pady=(0, 4))
        
        # Footer com timestamp
        self.create_fetch_message_footer(content_frame, message, msg_frame['bg'], is_from_me)
    
    def create_fetch_media_indicator(self, parent, message, msg_type):
        """Cria indicador de mídia do fetchMessages"""
        if msg_type == 'image':
            icon, text = "📷", "Imagem"
            color = "#4caf50"
        elif msg_type == 'video':
            icon, text = "🎥", "Vídeo"
            color = "#2196f3"
        elif msg_type == 'document':
            icon, text = "📄", message.get('body', 'Documento')
            color = "#ff9800"
        elif msg_type in ['audio', 'ptt']:
            icon, text = "🎵", "Áudio" if msg_type == 'audio' else "Mensagem de Voz"
            color = "#9c27b0"
        else:
            icon, text = "📎", "Mídia"
            color = "#607d8b"
        
        # Container da mídia
        media_frame = tk.Frame(parent, bg='#f8f9fa', relief=tk.RAISED, bd=1)
        media_frame.pack(fill=tk.X, pady=(0, 4))
        
        # Conteúdo
        media_content = tk.Frame(media_frame, bg='#f8f9fa')
        media_content.pack(fill=tk.X, padx=8, pady=6)
        
        tk.Label(media_content, text=f"{icon} {text}", 
                bg='#f8f9fa', fg=color, font=('Arial', 10, 'bold')).pack(anchor='w')
        
        tk.Label(media_content, text="📡 Carregado via fetchMessages", 
                bg='#f8f9fa', fg='#28a745', font=('Arial', 8)).pack(anchor='w')
    
    def create_fetch_message_footer(self, parent, message, bg_color, is_from_me):
        """Cria footer da mensagem do fetchMessages"""
        footer = tk.Frame(parent, bg=bg_color)
        footer.pack(fill=tk.X, pady=(3, 0))
        
        # Timestamp
        timestamp = message.get('timestamp', 0)
        if timestamp > 0:
            try:
                time_str = datetime.fromtimestamp(timestamp).strftime('%H:%M')
            except:
                time_str = "00:00"
        else:
            time_str = "00:00"
        
        time_label = tk.Label(footer, text=time_str, 
                            bg=bg_color, fg='#667781', 
                            font=('Arial', 8))
        time_label.pack(side=tk.RIGHT)
        
        # Status de entrega (apenas mensagens enviadas)
        if is_from_me:
            ack = message.get('ack', 0)
            if ack == 1:
                status_icon, color = "✓", '#667781'
            elif ack == 2:
                status_icon, color = "✓✓", '#667781'
            elif ack == 3:
                status_icon, color = "✓✓", '#4fc3f7'
                time_label.config(fg='#4fc3f7')
            else:
                status_icon, color = "⏰", '#667781'
            
            status_label = tk.Label(footer, text=status_icon, 
                                  bg=bg_color, fg=color, 
                                  font=('Arial', 7))
            status_label.pack(side=tk.RIGHT, padx=(0, 3))
    
    def scroll_to_bottom(self):
        """Scroll para o final"""
        try:
            self.messages_canvas.update_idletasks()
            self.messages_canvas.yview_moveto(1.0)
            print("📜 Scroll realizado para o final")
        except Exception as e:
            print(f"❌ Erro no scroll: {e}")
    
    def process_session_chats(self, session_id, chats, suppress_notifications=False):
        """Processa chats de uma sessão"""
        sorted_chats = sorted(chats, key=lambda x: x.get('timestamp', 0), reverse=True)
        
        # Detectar novas mensagens para notificações
        if not suppress_notifications and not self.initial_load:
            for chat in sorted_chats:
                chat_id = chat['id']['_serialized']
                chat_key = f"{session_id}_{chat_id}"
                
                if chat.get('lastMessage'):
                    msg_timestamp = chat['lastMessage'].get('timestamp', 0)
                    last_known = self.last_message_timestamps.get(chat_key, 0)
                    
                    if msg_timestamp > last_known:
                        if not chat['lastMessage'].get('fromMe', False):
                            self.trigger_notification(session_id, chat)
        
        # Atualizar contadores
        for chat in sorted_chats:
            chat_id = chat['id']['_serialized']
            chat_key = f"{session_id}_{chat_id}"
            
            unread_count = chat.get('unreadCount', 0)
            if unread_count > 0:
                self.unread_counts[chat_key] = unread_count
            elif chat_key in self.unread_counts:
                del self.unread_counts[chat_key]
    
    def update_session_tab(self, session_id):
        """Atualiza ou cria aba da sessão"""
        # Verificar se aba já existe
        for i in range(self.sessions_notebook.index("end")):
            tab_text = self.sessions_notebook.tab(i, "text")
            if tab_text.split()[0] == session_id:
                self.update_chat_list(session_id, i)
                return
        
        # Criar nova aba
        session_frame = tk.Frame(self.sessions_notebook, bg='white')
        
        # Contador de não lidas
        session_unread = sum(count for key, count in self.unread_counts.items() 
                           if key.startswith(f"{session_id}_"))
        
        tab_text = f"{session_id}"
        if session_unread > 0:
            tab_text += f" ({session_unread})"
        
        self.sessions_notebook.add(session_frame, text=tab_text)
        self.create_chat_list(session_id, session_frame)
    
    def create_chat_list(self, session_id, parent_frame):
        """Cria lista de chats para uma sessão"""
        canvas = tk.Canvas(parent_frame, bg='white', highlightthickness=0)
        scrollbar = ttk.Scrollbar(parent_frame, orient="vertical", command=canvas.yview)
        scrollable_frame = tk.Frame(canvas, bg='white')
        
        scrollable_frame.bind("<Configure>", 
                            lambda e: canvas.configure(scrollregion=canvas.bbox("all")))
        
        canvas.create_window((0, 0), window=scrollable_frame, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)
        
        scrollbar.pack(side="right", fill="y")
        canvas.pack(side="left", fill="both", expand=True)
        
        # Adicionar chats com mensagens
        if session_id in self.sessions:
            sorted_chats = sorted(self.sessions[session_id], 
                                key=lambda x: x.get('timestamp', 0), reverse=True)
            
            for chat in sorted_chats:
                self.create_chat_item(scrollable_frame, session_id, chat)
        
        def on_mousewheel(event):
            canvas.yview_scroll(int(-1*(event.delta/120)), "units")
        
        canvas.bind("<MouseWheel>", on_mousewheel)
        scrollable_frame.bind("<MouseWheel>", on_mousewheel)
    
    def update_chat_list(self, session_id, tab_index):
        """Atualiza lista de chats existente"""
        tab_frame = self.sessions_notebook.nametowidget(self.sessions_notebook.tabs()[tab_index])
        
        for widget in tab_frame.winfo_children():
            widget.destroy()
        
        self.create_chat_list(session_id, tab_frame)
        
        session_unread = sum(count for key, count in self.unread_counts.items() 
                           if key.startswith(f"{session_id}_"))
        
        tab_text = f"{session_id}"
        if session_unread > 0:
            tab_text += f" ({session_unread})"
        
        self.sessions_notebook.tab(tab_index, text=tab_text)
    
    def create_chat_item(self, parent, session_id, chat):
        """Cria item individual de chat"""
        chat_id = chat['id']['_serialized']
        chat_key = f"{session_id}_{chat_id}"
        
        # Frame do chat
        chat_frame = tk.Frame(parent, bg='white', relief=tk.FLAT, bd=1, cursor='hand2')
        chat_frame.pack(fill=tk.X, padx=2, pady=1)
        
        # Destacar se não lido
        unread_count = self.unread_counts.get(chat_key, 0)
        if unread_count > 0:
            chat_frame.config(bg='#e8f5e8', relief=tk.RAISED)
        
        # Conteúdo do chat
        content_frame = tk.Frame(chat_frame, bg=chat_frame['bg'])
        content_frame.pack(fill=tk.BOTH, expand=True, padx=8, pady=6)
        
        # Linha superior: Nome e timestamp
        top_line = tk.Frame(content_frame, bg=chat_frame['bg'])
        top_line.pack(fill=tk.X)
        
        # Nome do chat
        chat_name = self.get_chat_name(chat)
        name_label = tk.Label(top_line, text=chat_name, 
                            bg=chat_frame['bg'], fg='#111b21', 
                            font=('Arial', 11, 'bold'), anchor='w')
        name_label.pack(side=tk.LEFT, fill=tk.X, expand=True)
        
        # Timestamp
        time_label = None
        if chat.get('timestamp'):
            timestamp = datetime.fromtimestamp(chat['timestamp'])
            if timestamp.date() == datetime.now().date():
                time_str = timestamp.strftime('%H:%M')
            else:
                time_str = timestamp.strftime('%d/%m')
            
            time_label = tk.Label(top_line, text=time_str, 
                                bg=chat_frame['bg'], fg='#667781', 
                                font=('Arial', 9))
            time_label.pack(side=tk.RIGHT)
        
        # Linha inferior: Última mensagem e contador
        bottom_line = tk.Frame(content_frame, bg=chat_frame['bg'])
        bottom_line.pack(fill=tk.X, pady=(3, 0))
        
        # Última mensagem
        msg_label = None
        last_msg = ""
        if chat.get('lastMessage'):
            msg = chat['lastMessage']
            msg_type = msg.get('type', 'chat')
            
            if msg_type == 'image':
                last_msg = "📷 Imagem"
            elif msg_type == 'video':
                last_msg = "🎥 Vídeo"
            elif msg_type == 'document':
                last_msg = f"📄 {msg.get('body', 'Documento')}"
            elif msg_type in ['audio', 'ptt']:
                last_msg = "🎵 Áudio"
            else:
                body = msg.get('body', '').strip()
                last_msg = body[:35] + "..." if len(body) > 35 else body
        
        if last_msg:
            msg_label = tk.Label(bottom_line, text=last_msg, 
                               bg=chat_frame['bg'], fg='#667781', 
                               font=('Arial', 9), anchor='w')
            msg_label.pack(side=tk.LEFT, fill=tk.X, expand=True)
        
        # Badge de não lidas
        if unread_count > 0:
            badge = tk.Label(bottom_line, text=str(unread_count), 
                           bg='#25d366', fg='white', 
                           font=('Arial', 9, 'bold'),
                           width=3, relief=tk.RAISED, bd=1)
            badge.pack(side=tk.RIGHT, padx=(5, 0))
        
        # Eventos de click
        def on_click(event=None):
            self.select_chat(session_id, chat)
        
        def on_enter(event):
            chat_frame.config(bg='#f5f6f6')
            content_frame.config(bg='#f5f6f6')
            top_line.config(bg='#f5f6f6')
            bottom_line.config(bg='#f5f6f6')
            name_label.config(bg='#f5f6f6')
            if msg_label:
                msg_label.config(bg='#f5f6f6')
            if time_label:
                time_label.config(bg='#f5f6f6')
        
        def on_leave(event):
            bg_color = '#e8f5e8' if unread_count > 0 else 'white'
            chat_frame.config(bg=bg_color)
            content_frame.config(bg=bg_color)
            top_line.config(bg=bg_color)
            bottom_line.config(bg=bg_color)
            name_label.config(bg=bg_color)
            if msg_label:
                msg_label.config(bg=bg_color)
            if time_label:
                time_label.config(bg=bg_color)
        
        # Bind eventos
        widgets_to_bind = [chat_frame, content_frame, top_line, bottom_line, name_label]
        if msg_label:
            widgets_to_bind.append(msg_label)
        if time_label:
            widgets_to_bind.append(time_label)
        
        for widget in widgets_to_bind:
            widget.bind("<Button-1>", on_click)
            widget.bind("<Enter>", on_enter)
            widget.bind("<Leave>", on_leave)
    
    def get_chat_name(self, chat):
        """Obtém nome do chat"""
        if chat.get('isGroup'):
            return chat.get('name', 'Grupo sem nome')
        else:
            if chat.get('name'):
                return chat['name']
            else:
                contact_id = chat['id']['_serialized']
                if '@c.us' in contact_id:
                    phone = contact_id.replace('@c.us', '')
                    if phone.startswith('55') and len(phone) >= 12:
                        return f"+55 ({phone[2:4]}) {phone[4:9]}-{phone[9:]}"
                    return phone
                return contact_id
    
    def select_chat(self, session_id, chat):
        """Seleciona um chat - USA fetchMessages API"""
        self.current_session = session_id
        self.current_chat = chat
        
        chat_id = chat['id']['_serialized']
        
        # Marcar como lido
        chat_key = f"{session_id}_{chat_id}"
        if chat_key in self.unread_counts:
            del self.unread_counts[chat_key]
            self.update_total_unread_counter()
            self.update_all_session_tabs()
        
        # Atualizar header do chat
        chat_name = self.get_chat_name(chat)
        self.chat_title.config(text=chat_name)
        
        if chat.get('isGroup'):
            participants = chat.get('groupMetadata', {}).get('size', 0)
            self.chat_subtitle.config(text=f"Grupo • {participants} participantes • fetchMessages ativo")
        else:
            self.chat_subtitle.config(text="Chat individual • Carregando via fetchMessages...")
        
        # **USAR fetchMessages PARA CARREGAR TODAS AS MENSAGENS**
        print(f"🔍 Selecionado: {chat_name} (ID: {chat_id})")
        print(f"📡 Iniciando fetchMessages para carregar TODAS as mensagens...")
        self.fetch_all_messages(session_id, chat_id)
    
    def trigger_notification(self, session_id, chat):
        """Dispara notificação para nova mensagem"""
        chat_name = self.get_chat_name(chat)
        
        self.play_notification_sound()
        
        self.root.title(f"💬 Nova mensagem: {chat_name} - fetchMessages")
        
        self.root.after(3000, lambda: self.root.title("WhatsApp Complete Messages - fetchMessages API"))
        
        print(f"🔔 Nova mensagem: {chat_name} na sessão {session_id}")
    
    def start_polling(self):
        """Inicia polling automático da API"""
        self.polling_active = True
        self.polling_btn.config(text="⏸️ Pausar", bg='#ff9500')
        
        def polling_loop():
            while self.polling_active:
                try:
                    if self.sessions:
                        for session_id in list(self.sessions.keys()):
                            if not self.polling_active:
                                break
                            self.load_session_data(session_id)
                            time.sleep(0.5)
                        
                        self.root.after(0, self.update_total_unread_counter)
                        self.root.after(0, self.update_all_session_tabs)
                    
                    # Aguardar próximo ciclo (10 segundos)
                    for _ in range(self.polling_interval * 10):
                        if not self.polling_active:
                            break
                        time.sleep(0.1)
                        
                except Exception as e:
                    print(f"❌ Erro no polling fetchMessages: {e}")
                    time.sleep(5)
        
        threading.Thread(target=polling_loop, daemon=True).start()
    
    def toggle_polling(self):
        """Alterna estado do polling"""
        if self.polling_active:
            self.polling_active = False
            self.polling_btn.config(text="▶️ Retomar", bg='#25d366')
            self.connection_status.config(text="⏸️ Pausado", fg='#ff9500')
            self.update_operation_status("⏸️ Polling fetchMessages pausado")
        else:
            self.start_polling()
            self.connection_status.config(text="🟢 API Online", fg='#25d366')
            self.update_operation_status("▶️ Polling fetchMessages ativo")
    
    def manual_refresh(self):
        """Atualização manual via API"""
        def refresh_thread():
            self.update_operation_status("🔄 Atualizando via fetchMessages API...")
            
            refreshed = 0
            for session_id in list(self.sessions.keys()):
                success = self.load_session_data(session_id)
                if success:
                    refreshed += 1
                time.sleep(0.3)
            
            self.root.after(0, self.update_total_unread_counter)
            self.root.after(0, self.update_all_session_tabs)
            self.update_operation_status(f"✅ {refreshed} sessões fetchMessages atualizadas")
        
        threading.Thread(target=refresh_thread, daemon=True).start()
    
    def update_total_unread_counter(self):
        """Atualiza contador total de não lidas"""
        total_unread = sum(self.unread_counts.values())
        self.total_unread_label.config(text=f"📬 {total_unread}")
        
        if total_unread > 0:
            base_title = f"({total_unread}) WhatsApp fetchMessages"
        else:
            base_title = "WhatsApp Complete Messages - fetchMessages API"
        
        if "💬 Nova mensagem" not in self.root.title() and "📩 Nova mensagem via webhook" not in self.root.title():
            self.root.title(base_title)
    
    def update_all_session_tabs(self):
        """Atualiza todas as abas de sessão"""
        for i in range(self.sessions_notebook.index("end")):
            tab_text = self.sessions_notebook.tab(i, "text")
            session_id = tab_text.split()[0]
            
            session_unread = sum(count for key, count in self.unread_counts.items() 
                               if key.startswith(f"{session_id}_"))
            
            new_tab_text = f"{session_id}"
            if session_unread > 0:
                new_tab_text += f" ({session_unread})"
            
            if tab_text != new_tab_text:
                self.sessions_notebook.tab(i, text=new_tab_text)
    
    def on_session_change(self, event):
        """Evento de mudança de aba da sessão"""
        try:
            current_tab = self.sessions_notebook.select()
            tab_text = self.sessions_notebook.tab(current_tab, "text")
            session_id = tab_text.split()[0]
            print(f"📱 Sessão ativa: {session_id}")
        except:
            pass

def main():
    """Função principal"""
    # Verificar dependências básicas
    required_modules = ['PIL', 'requests']
    missing_modules = []
    
    for module in required_modules:
        try:
            if module == 'PIL':
                import PIL
            else:
                __import__(module)
        except ImportError:
            missing_modules.append(module)
    
    if missing_modules:
        print(f"🔧 Instalando dependências: {', '.join(missing_modules)}")
        for module in missing_modules:
            if module == 'PIL':
                os.system("pip install Pillow")
            else:
                os.system(f"pip install {module}")
    
    # Criar e configurar janela principal
    root = tk.Tk()
    root.resizable(True, True)
    root.state('normal')
    
    # Criar aplicação
    app = WhatsAppCompleteMessages(root)
    
    # Configurar evento de fechamento
    def on_closing():
        print("🔚 Encerrando WhatsApp fetchMessages...")
        app.polling_active = False
        
        # Parar servidor webhook
        if app.webhook_server:
            try:
                app.webhook_server.shutdown()
                print("🌐 Servidor webhook encerrado")
            except:
                pass
        
        time.sleep(1)
        root.quit()
        root.destroy()
    
    root.protocol("WM_DELETE_WINDOW", on_closing)
    
    # Centralizar janela na tela
    root.update_idletasks()
    screen_width = root.winfo_screenwidth()
    screen_height = root.winfo_screenheight()
    x = (screen_width // 2) - (1600 // 2)
    y = (screen_height // 2) - (1000 // 2)
    root.geometry(f"1600x1000+{x}+{y}")
    
    # Configurar ícone da janela (opcional)
    try:
        # Você pode adicionar um ícone personalizado aqui se desejar
        pass
    except:
        pass
    
    print("🚀 WhatsApp Complete Messages iniciado!")
    print("📡 Sistema fetchMessages API ativo")
    print("🌐 Servidor webhook para notificações em tempo real")
    print("💡 fetchMessages carrega TODAS as mensagens da conversa")
    print("📩 Webhook recebe notificações de novas mensagens")
    print("🔊 Controles: 🔔/🔕 (som) | 📩 (webhook) | 🔄 (refresh)")
    print("📊 Sessões monitoradas: vanessa, ademir, pedro")
    print(f"👤 Usuário: AdemirRed")
    print(f"🕒 Iniciado em: 2025-10-30 17:47:23 (UTC)")
    print(f"🌐 Webhook URL: http://localhost:8888")
    print("📡 Configuração fetchMessages API:")
    print(f"   • URL Base: http://192.168.0.201:200")
    print(f"   • Endpoint: POST /chat/fetchMessages/{{sessionId}}")
    print(f"   • Payload: {{\"chatId\": \"chat_id\", \"searchOptions\": {{}}}}")
    print(f"   • API Key: redblack")
    print(f"   • Polling: 10 segundos")
    print("=" * 70)
    
    # Iniciar loop principal da aplicação
    root.mainloop()

if __name__ == "__main__":
    main()
