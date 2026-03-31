import json
import base64
import os
import tkinter as tk
from tkinter import ttk, scrolledtext, messagebox, filedialog
from datetime import datetime, timedelta
from PIL import Image, ImageTk
import io
from collections import defaultdict
import re
import shutil
import requests
import threading
import webbrowser

class WhatsAppTreeInterface:
    def __init__(self, root):
        self.root = root
        self.root.title("WhatsApp Desktop - Tree View (Auto Download Todas as Mídias)")
        self.root.geometry("1400x900")
        self.root.configure(bg='#f0f0f0')
        
        # Caminhos dos arquivos
        self.source_file = r"C:\xampp\htdocs\chat_data.json"
        self.destination_dir = r"C:\xampp\htdocs\VIZUALIZAR"
        self.destination_file = os.path.join(self.destination_dir, "chat_data.json")
        self.history_file = os.path.join(self.destination_dir, "chat_history_complete.json")
        
        # PASTA ÚNICA PARA TODAS AS MÍDIAS (como era antes)
        self.whatsapp_images_dir = os.path.join(self.destination_dir, "whatsapp_images")
        self.notifications_file = os.path.join(self.destination_dir, "notifications.json")
        
        # Criar diretório para todas as mídias
        os.makedirs(self.whatsapp_images_dir, exist_ok=True)
        
        # Dados das conversas
        self.contacts_data = {}
        self.groups_data = {}
        self.current_contact = None
        self.current_view = "contacts"
        
        # Sistema de notificações
        self.unread_conversations = set()
        self.last_check_time = datetime.now() - timedelta(hours=1)
        
        # Configurar interface
        self.setup_ui()
        
        # Copiar arquivo e carregar dados
        self.copy_and_load_data()
    
    def auto_save_media_to_files(self, sessions_data):
        """Salva TODAS as mídias automaticamente na pasta whatsapp_images"""
        saved_count = {'images': 0, 'videos': 0, 'audios': 0}
        
        try:
            for session_id, session_data in sessions_data.items():
                # Criar subpasta por sessão
                session_dir = os.path.join(self.whatsapp_images_dir, session_id)
                os.makedirs(session_dir, exist_ok=True)
                
                for contact_id, contact_data in session_data.items():
                    # Criar subpasta por contato/grupo
                    contact_name = self.get_safe_filename(self.get_contact_name(contact_id, contact_data.get('notify_name')))
                    contact_dir = os.path.join(session_dir, contact_name)
                    os.makedirs(contact_dir, exist_ok=True)
                    
                    # Salvar imagens
                    for i, image in enumerate(contact_data.get('images', [])):
                        try:
                            timestamp = datetime.fromtimestamp(image['timestamp']).strftime('%Y%m%d_%H%M%S')
                            filename = f"IMG_{timestamp}_{i+1}.jpg"
                            filepath = os.path.join(contact_dir, filename)
                            
                            if not os.path.exists(filepath):  # Só salva se não existir
                                image_data = base64.b64decode(image['base64_data'])
                                with open(filepath, 'wb') as f:
                                    f.write(image_data)
                                saved_count['images'] += 1
                        except Exception as e:
                            print(f"Erro ao salvar imagem: {e}")
                    
                    # Salvar vídeos
                    for i, video in enumerate(contact_data.get('videos', [])):
                        try:
                            timestamp = datetime.fromtimestamp(video['timestamp']).strftime('%Y%m%d_%H%M%S')
                            
                            # Salvar thumbnail do vídeo se disponível
                            if video.get('thumbnail'):
                                thumb_filename = f"VID_THUMB_{timestamp}_{i+1}.jpg"
                                thumb_filepath = os.path.join(contact_dir, thumb_filename)
                                
                                if not os.path.exists(thumb_filepath):
                                    thumb_data = base64.b64decode(video['thumbnail'])
                                    with open(thumb_filepath, 'wb') as f:
                                        f.write(thumb_data)
                            
                            # Salvar dados do vídeo se disponível
                            if video.get('base64_data'):
                                ext = '.mp4'  # padrão
                                if video.get('mimetype'):
                                    if 'webm' in video['mimetype']:
                                        ext = '.webm'
                                    elif 'avi' in video['mimetype']:
                                        ext = '.avi'
                                
                                vid_filename = f"VID_{timestamp}_{i+1}{ext}"
                                vid_filepath = os.path.join(contact_dir, vid_filename)
                                
                                if not os.path.exists(vid_filepath):
                                    vid_data = base64.b64decode(video['base64_data'])
                                    with open(vid_filepath, 'wb') as f:
                                        f.write(vid_data)
                                    saved_count['videos'] += 1
                            
                            # Salvar info do vídeo
                            info_filename = f"VID_INFO_{timestamp}_{i+1}.txt"
                            info_filepath = os.path.join(contact_dir, info_filename)
                            
                            if not os.path.exists(info_filepath):
                                info_text = f"Informações do Vídeo:\n"
                                info_text += f"Timestamp: {timestamp}\n"
                                info_text += f"Duração: {video.get('duration', 'N/A')}s\n"
                                info_text += f"Resolução: {video.get('width', 'N/A')}x{video.get('height', 'N/A')}\n"
                                info_text += f"Tamanho: {video.get('size', 'N/A')} bytes\n"
                                info_text += f"URL: {video.get('url', 'N/A')}\n"
                                info_text += f"Tipo MIME: {video.get('mimetype', 'N/A')}\n"
                                
                                with open(info_filepath, 'w', encoding='utf-8') as f:
                                    f.write(info_text)
                        
                        except Exception as e:
                            print(f"Erro ao salvar vídeo: {e}")
                    
                    # Salvar áudios
                    for i, audio in enumerate(contact_data.get('audios', [])):
                        try:
                            timestamp = datetime.fromtimestamp(audio['timestamp']).strftime('%Y%m%d_%H%M%S')
                            
                            # Determinar extensão
                            ext = '.mp3'  # padrão
                            if audio.get('mimetype'):
                                if 'ogg' in audio['mimetype']:
                                    ext = '.ogg'
                                elif 'mp4' in audio['mimetype'] or 'm4a' in audio['mimetype']:
                                    ext = '.m4a'
                                elif 'wav' in audio['mimetype']:
                                    ext = '.wav'
                            
                            # Prefixo baseado no tipo
                            prefix = "VOZ" if audio.get('type') == 'ptt' else "AUD"
                            
                            if audio.get('base64_data'):
                                aud_filename = f"{prefix}_{timestamp}_{i+1}{ext}"
                                aud_filepath = os.path.join(contact_dir, aud_filename)
                                
                                if not os.path.exists(aud_filepath):
                                    aud_data = base64.b64decode(audio['base64_data'])
                                    with open(aud_filepath, 'wb') as f:
                                        f.write(aud_data)
                                    saved_count['audios'] += 1
                            
                            # Salvar info do áudio
                            info_filename = f"{prefix}_INFO_{timestamp}_{i+1}.txt"
                            info_filepath = os.path.join(contact_dir, info_filename)
                            
                            if not os.path.exists(info_filepath):
                                info_text = f"Informações do Áudio:\n"
                                info_text += f"Timestamp: {timestamp}\n"
                                info_text += f"Tipo: {'Mensagem de Voz' if audio.get('type') == 'ptt' else 'Áudio'}\n"
                                info_text += f"Duração: {audio.get('duration', 'N/A')}s\n"
                                info_text += f"Tamanho: {audio.get('size', 'N/A')} bytes\n"
                                info_text += f"URL: {audio.get('url', 'N/A')}\n"
                                info_text += f"Tipo MIME: {audio.get('mimetype', 'N/A')}\n"
                                
                                with open(info_filepath, 'w', encoding='utf-8') as f:
                                    f.write(info_text)
                        
                        except Exception as e:
                            print(f"Erro ao salvar áudio: {e}")
            
            # Relatório de salvamento
            if any(saved_count.values()):
                total_saved = sum(saved_count.values())
                self.show_loading_status(f"💾 {total_saved} mídias salvas: {saved_count['images']} imgs, {saved_count['videos']} vids, {saved_count['audios']} auds")
                
                messagebox.showinfo("Mídias Salvas", 
                                  f"✅ Mídias salvas automaticamente!\n\n"
                                  f"📷 {saved_count['images']} imagens\n"
                                  f"🎥 {saved_count['videos']} vídeos\n"
                                  f"🎵 {saved_count['audios']} áudios\n\n"
                                  f"📁 Local: {self.whatsapp_images_dir}")
            
        except Exception as e:
            print(f"Erro ao salvar mídias: {e}")
    
    def get_safe_filename(self, filename):
        """Converte nome para nome de arquivo seguro"""
        # Remover caracteres não permitidos
        safe_name = re.sub(r'[<>:"/\\|?*]', '_', filename)
        safe_name = safe_name.replace('+55', '').replace('(', '').replace(')', '').replace(' ', '_').replace('-', '_')
        # Limitar tamanho
        return safe_name[:50] if len(safe_name) > 50 else safe_name
    
    def load_notifications(self):
        """Carrega estado das notificações"""
        try:
            if os.path.exists(self.notifications_file):
                with open(self.notifications_file, 'r', encoding='utf-8') as file:
                    notif_data = json.load(file)
                    self.last_check_time = datetime.fromisoformat(notif_data.get('last_check', (datetime.now() - timedelta(hours=1)).isoformat()))
                    self.unread_conversations = set(notif_data.get('unread_conversations', []))
        except:
            self.last_check_time = datetime.now() - timedelta(hours=1)
            self.unread_conversations = set()
    
    def save_notifications(self):
        """Salva estado das notificações"""
        try:
            notif_data = {
                'last_check': self.last_check_time.isoformat(),
                'unread_conversations': list(self.unread_conversations)
            }
            with open(self.notifications_file, 'w', encoding='utf-8') as file:
                json.dump(notif_data, file, ensure_ascii=False, indent=2)
        except:
            pass
    
    def mark_conversation_as_read(self, session_id, contact_id):
        """Marca uma conversa como lida"""
        conv_key = f"{session_id}_{contact_id}"
        if conv_key in self.unread_conversations:
            self.unread_conversations.remove(conv_key)
            self.save_notifications()
            self.populate_tree()
    
    def copy_and_append_data(self):
        """Copia arquivo e ANEXA aos dados existentes, mantendo histórico"""
        try:
            # Carregar notificações antes de processar
            self.load_notifications()
            
            # Mostrar status de carregamento
            self.show_loading_status("📂 Copiando novos dados...")
            
            # Verificar se arquivo de origem existe
            if not os.path.exists(self.source_file):
                messagebox.showerror("Erro", f"Arquivo de origem não encontrado:\n{self.source_file}")
                return
            
            # Criar diretório de destino se não existir
            os.makedirs(self.destination_dir, exist_ok=True)
            
            # Copiar arquivo atual
            shutil.copy2(self.source_file, self.destination_file)
            
            # Carregar dados novos
            with open(self.destination_file, 'r', encoding='utf-8') as file:
                new_data = json.load(file)
            
            self.show_loading_status(f"📊 {len(new_data)} novos registros encontrados...")
            
            # Carregar dados históricos existentes (se existirem)
            historical_data = []
            if os.path.exists(self.history_file):
                try:
                    with open(self.history_file, 'r', encoding='utf-8') as file:
                        historical_data = json.load(file)
                    self.show_loading_status(f"📚 {len(historical_data)} registros históricos carregados...")
                except:
                    historical_data = []
            
            # Criar um set de IDs únicos para evitar duplicatas
            existing_ids = set()
            for item in historical_data:
                unique_id = self.create_unique_id(item)
                if unique_id:
                    existing_ids.add(unique_id)
            
            # Adicionar apenas dados novos (não duplicados) e detectar notificações
            new_items_added = 0
            new_conversations = set()
            
            for item in new_data:
                unique_id = self.create_unique_id(item)
                if unique_id and unique_id not in existing_ids:
                    historical_data.append(item)
                    existing_ids.add(unique_id)
                    new_items_added += 1
                    
                    # Detectar novas mensagens para notificações
                    item_time = self.get_item_datetime(item)
                    if item_time and item_time > self.last_check_time:
                        conv_key = self.get_conversation_key_from_item(item)
                        if conv_key:
                            new_conversations.add(conv_key)
            
            # Adicionar novas conversas às não lidas
            self.unread_conversations.update(new_conversations)
            
            # Atualizar último check
            self.last_check_time = datetime.now()
            self.save_notifications()
            
            # Ordenar por timestamp para manter ordem cronológica
            historical_data.sort(key=lambda x: self.get_timestamp_from_item(x))
            
            # Salvar dados históricos completos
            with open(self.history_file, 'w', encoding='utf-8') as file:
                json.dump(historical_data, file, ensure_ascii=False, indent=2)
            
            # Informações finais
            total_size = os.path.getsize(self.history_file)
            current_time = datetime.now().strftime('%H:%M:%S')
            
            status_msg = f"✅ {new_items_added} novos | {len(historical_data)} total | 🔔 {len(new_conversations)} notificações | {current_time}"
            self.show_loading_status(status_msg)
            
            # Processar dados históricos completos
            self.load_historical_data()
            
            # Mostrar relatório de atualização
            if new_items_added > 0:
                notif_text = f"\n🔔 {len(new_conversations)} novas conversas com atividade" if new_conversations else ""
                messagebox.showinfo("Atualização Completa", 
                                  f"✅ Dados atualizados com sucesso!\n\n"
                                  f"📈 {new_items_added} novos registros adicionados\n"
                                  f"📚 {len(historical_data)} registros totais no histórico\n"
                                  f"💾 Arquivo: {total_size:,} bytes\n"
                                  f"🕒 Última atualização: {current_time}{notif_text}")
            else:
                messagebox.showinfo("Dados Atualizados", 
                                  "ℹ️ Nenhum dado novo encontrado.\n"
                                  "Todos os registros já estavam no histórico.")
            
        except PermissionError:
            messagebox.showerror("Erro de Permissão", 
                               f"Sem permissão para acessar:\n{self.source_file}\nou\n{self.destination_dir}")
        except Exception as e:
            messagebox.showerror("Erro", f"Erro ao processar dados:\n{str(e)}")
    
    def get_item_datetime(self, item):
        """Extrai datetime de um item"""
        try:
            data_recebimento = item.get('data_recebimento', '')
            if data_recebimento:
                return datetime.strptime(data_recebimento, '%Y-%m-%d %H:%M:%S')
        except:
            pass
        return None
    
    def get_conversation_key_from_item(self, item):
        """Extrai chave da conversa de um item"""
        try:
            session_id = item.get('conteudo', {}).get('sessionId', '')
            data_type = item.get('conteudo', {}).get('dataType', '')
            
            if data_type in ['message_create', 'message']:
                message = item.get('conteudo', {}).get('data', {}).get('message', {})
                from_contact = message.get('from', '')
                to_contact = message.get('to', '')
                
                is_group = '@g.us' in from_contact or '@g.us' in to_contact
                
                if is_group:
                    contact_key = from_contact if '@g.us' in from_contact else to_contact
                else:
                    contact_key = from_contact if not message.get('fromMe', False) else to_contact
                
                if contact_key and contact_key not in ['555198804804@c.us']:
                    return f"{session_id}_{contact_key}"
        except:
            pass
        return None
    
    def create_unique_id(self, item):
        """Cria um ID único para um item baseado no seu conteúdo"""
        try:
            conteudo = item.get('conteudo', {})
            data_type = conteudo.get('dataType', '')
            session_id = conteudo.get('sessionId', '')
            
            if data_type in ['message_create', 'message']:
                message = conteudo.get('data', {}).get('message', {})
                msg_id = message.get('id', {}).get('_serialized', '')
                timestamp = message.get('timestamp', 0)
                from_contact = message.get('from', '')
                
                if msg_id:
                    return f"{session_id}_{msg_id}_{timestamp}"
                else:
                    body = message.get('body', '')[:50]
                    return f"{session_id}_{from_contact}_{timestamp}_{hash(body)}"
            
            elif data_type == 'media':
                message = conteudo.get('data', {}).get('message', {})
                msg_id = message.get('id', {}).get('_serialized', '')
                timestamp = message.get('timestamp', 0)
                
                if msg_id:
                    return f"{session_id}_media_{msg_id}_{timestamp}"
            
            elif data_type == 'unread_count':
                chat_data = conteudo.get('data', {}).get('chat', {})
                chat_id = chat_data.get('id', {}).get('_serialized', '')
                timestamp = chat_data.get('timestamp', 0)
                unread_count = chat_data.get('unreadCount', 0)
                
                return f"{session_id}_unread_{chat_id}_{timestamp}_{unread_count}"
            
            return f"{session_id}_{data_type}_{hash(str(item))}"
            
        except:
            return None
    
    def get_timestamp_from_item(self, item):
        """Extrai timestamp de um item para ordenação"""
        try:
            conteudo = item.get('conteudo', {})
            data_type = conteudo.get('dataType', '')
            
            if data_type in ['message_create', 'message', 'media']:
                message = conteudo.get('data', {}).get('message', {})
                return message.get('timestamp', 0)
            elif data_type == 'unread_count':
                chat_data = conteudo.get('data', {}).get('chat', {})
                return chat_data.get('timestamp', 0)
            
            data_recebimento = item.get('data_recebimento', '')
            if data_recebimento:
                try:
                    dt = datetime.strptime(data_recebimento, '%Y-%m-%d %H:%M:%S')
                    return int(dt.timestamp())
                except:
                    pass
            
            return 0
        except:
            return 0
    
    def copy_and_load_data(self):
        """Método principal que chama a função de anexar dados"""
        self.copy_and_append_data()
    
    def show_loading_status(self, message):
        """Mostra status de carregamento"""
        if hasattr(self, 'status_label'):
            self.status_label.config(text=message)
            self.root.update()
    
    def setup_ui(self):
        """Configura a interface do usuário"""
        # Frame principal
        main_frame = tk.Frame(self.root, bg='#f0f0f0')
        main_frame.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Frame superior com controles
        control_frame = tk.Frame(main_frame, bg='#075e54', height=100)
        control_frame.pack(fill=tk.X, pady=(0, 5))
        control_frame.pack_propagate(False)
        
        # Configurar controles
        self.setup_controls(control_frame)
        
        # Frame principal dividido
        content_frame = tk.Frame(main_frame, bg='#f0f0f0')
        content_frame.pack(fill=tk.BOTH, expand=True)
        
        # TreeView (lado esquerdo)
        tree_frame = tk.Frame(content_frame, bg='white', width=480, relief=tk.SUNKEN, bd=2)
        tree_frame.pack(side=tk.LEFT, fill=tk.Y, padx=(0, 5))
        tree_frame.pack_propagate(False)
        
        # Cabeçalho da árvore
        tree_header = tk.Frame(tree_frame, bg='#e3f2fd', height=60)
        tree_header.pack(fill=tk.X)
        tree_header.pack_propagate(False)
        
        header_content = tk.Frame(tree_header, bg='#e3f2fd')
        header_content.pack(fill=tk.BOTH, padx=10, pady=8)
        
        tk.Label(header_content, text="📚 Histórico + Auto Download", bg='#e3f2fd', 
                fg='#1976d2', font=('Arial', 12, 'bold')).pack(side=tk.LEFT)
        
        # Botões do header
        buttons_frame = tk.Frame(header_content, bg='#e3f2fd')
        buttons_frame.pack(side=tk.RIGHT)
        
        # Botão para limpar notificações
        clear_notif_btn = tk.Button(buttons_frame, text="🔔", 
                                   command=self.clear_all_notifications,
                                   bg='#ff9800', fg='white', font=('Arial', 10, 'bold'),
                                   relief=tk.RAISED, bd=1, padx=8, pady=2, cursor='hand2')
        clear_notif_btn.pack(side=tk.RIGHT, padx=(5, 0))
        
        # Botão para limpar histórico
        clear_btn = tk.Button(buttons_frame, text="🗑️", 
                             command=self.clear_history,
                             bg='#f44336', fg='white', font=('Arial', 10, 'bold'),
                             relief=tk.RAISED, bd=1, padx=8, pady=2, cursor='hand2')
        clear_btn.pack(side=tk.RIGHT, padx=(5, 0))
        
        # Botão para abrir pasta de mídias
        folder_btn = tk.Button(buttons_frame, text="📁", 
                              command=self.open_media_folder,
                              bg='#4caf50', fg='white', font=('Arial', 10, 'bold'),
                              relief=tk.RAISED, bd=1, padx=8, pady=2, cursor='hand2')
        folder_btn.pack(side=tk.RIGHT)
        
        # TreeView com scrollbars
        tree_container = tk.Frame(tree_frame, bg='white')
        tree_container.pack(fill=tk.BOTH, expand=True, padx=2, pady=2)
        
        # Configurar TreeView
        self.setup_treeview(tree_container)
        
        # Área de chat (lado direito)
        self.chat_frame = tk.Frame(content_frame, bg='#e5ddd5', relief=tk.SUNKEN, bd=2)
        self.chat_frame.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True)
        
        # Configurar área de chat
        self.setup_chat_area()
    
    def clear_all_notifications(self):
        """Limpa todas as notificações"""
        self.unread_conversations.clear()
        self.save_notifications()
        self.populate_tree()
        self.show_loading_status("🔔 Todas as notificações foram limpas")
    
    def open_media_folder(self):
        """Abre a pasta de mídias whatsapp_images"""
        try:
            os.startfile(self.whatsapp_images_dir)
        except:
            messagebox.showinfo("Pasta de Mídias", f"Pasta de todas as mídias:\n{self.whatsapp_images_dir}")
    
    def setup_controls(self, parent):
        """Configura os controles superiores"""
        # Frame interno
        controls_inner = tk.Frame(parent, bg='#075e54')
        controls_inner.pack(fill=tk.BOTH, padx=20, pady=12)
        
        # Linha superior
        top_row = tk.Frame(controls_inner, bg='#075e54')
        top_row.pack(fill=tk.X)
        
        # Título
        tk.Label(top_row, text="WhatsApp Desktop - Auto Download Todas as Mídias", 
                fg='white', bg='#075e54', font=('Arial', 16, 'bold')).pack(side=tk.LEFT)
        
        # Info do usuário
        user_info = tk.Label(top_row, text=f"👤 AdemirRed | 🕒 {datetime.now().strftime('%d/%m/%Y %H:%M')}", 
                           fg='#25d366', bg='#075e54', font=('Arial', 10))
        user_info.pack(side=tk.RIGHT)
        
        # Linha inferior
        bottom_row = tk.Frame(controls_inner, bg='#075e54')
        bottom_row.pack(fill=tk.X, pady=(8, 0))
        
        # Botões à esquerda
        buttons_frame = tk.Frame(bottom_row, bg='#075e54')
        buttons_frame.pack(side=tk.LEFT)
        
        # Botão de atualização DESTACADO
        self.update_button = tk.Button(buttons_frame, text="🔄 ANEXAR + AUTO DOWNLOAD", 
                               command=self.copy_and_load_data,
                               bg='#25d366', fg='white', font=('Arial', 11, 'bold'),
                               relief=tk.RAISED, bd=3, padx=20, pady=8, cursor='hand2')
        self.update_button.pack(side=tk.LEFT, padx=(0, 10))
        
        # Abas
        self.contacts_tab = tk.Button(buttons_frame, text="💬 CONVERSAS", 
                               command=lambda: self.switch_view("contacts"),
                               bg='#128c7e', fg='white', font=('Arial', 10, 'bold'),
                               relief=tk.RAISED, bd=2, padx=15, pady=6, cursor='hand2')
        self.contacts_tab.pack(side=tk.LEFT, padx=(0, 5))
        
        self.groups_tab = tk.Button(buttons_frame, text="👥 GRUPOS", 
                               command=lambda: self.switch_view("groups"),
                               bg='#0d7377', fg='white', font=('Arial', 10),
                               relief=tk.FLAT, bd=1, padx=15, pady=6, cursor='hand2')
        self.groups_tab.pack(side=tk.LEFT)
        
        # Status à direita
        self.status_label = tk.Label(bottom_row, text="🔄 Inicializando sistema...", 
                                   fg='#b3b3b3', bg='#075e54', font=('Arial', 10))
        self.status_label.pack(side=tk.RIGHT)
    
    def clear_history(self):
        """Limpa o histórico de conversas"""
        result = messagebox.askyesno("Confirmar Limpeza", 
                                   "⚠️ Tem certeza que deseja limpar TODO o histórico?\n\n"
                                   "Esta ação NÃO pode ser desfeita!\n"
                                   "Todos os dados históricos E MÍDIAS BAIXADAS serão perdidos.")
        
        if result:
            try:
                # Remover arquivo de histórico
                if os.path.exists(self.history_file):
                    os.remove(self.history_file)
                
                # Limpar pasta de mídias
                if os.path.exists(self.whatsapp_images_dir):
                    shutil.rmtree(self.whatsapp_images_dir)
                    os.makedirs(self.whatsapp_images_dir, exist_ok=True)
                
                # Limpar notificações
                self.unread_conversations.clear()
                self.save_notifications()
                
                # Limpar dados na memória
                self.contacts_data = {}
                self.groups_data = {}
                
                # Limpar árvore
                for item in self.tree.get_children():
                    self.tree.delete(item)
                
                # Limpar chat
                for widget in self.messages_scrollable_frame.winfo_children():
                    widget.destroy()
                
                self.contact_name_label.config(text="Histórico limpo - Clique em 🔄 para carregar novos dados")
                self.contact_info_label.config(text="Todos os dados históricos e mídias foram removidos")
                
                self.show_loading_status("🗑️ Histórico e mídias limpos - Sistema pronto")
                
                messagebox.showinfo("Limpeza Concluída", "✅ Histórico e mídias limpos!\n\nClique em '🔄 ANEXAR + AUTO DOWNLOAD' para começar novo.")
                
            except Exception as e:
                messagebox.showerror("Erro", f"Erro ao limpar:\n{str(e)}")
    
    def setup_treeview(self, parent):
        """Configura o TreeView"""
        # Estilo personalizado
        style = ttk.Style()
        style.theme_use("clam")
        
        style.configure("Custom.Treeview", 
                       background="white",
                       foreground="black",
                       fieldbackground="white",
                       font=('Arial', 10))
        
        style.configure("Custom.Treeview.Heading",
                       background="#e3f2fd",
                       foreground="#1976d2",
                       font=('Arial', 10, 'bold'))
        
        # Frame para TreeView com scrollbars
        tree_scroll_frame = tk.Frame(parent, bg='white')
        tree_scroll_frame.pack(fill=tk.BOTH, expand=True)
        
        # TreeView com 4 colunas
        self.tree = ttk.Treeview(tree_scroll_frame, 
                                style="Custom.Treeview",
                                columns=('msgs', 'imgs', 'videos', 'audios'),
                                show='tree headings',
                                selectmode='browse')
        
        # Configurar colunas
        self.tree.heading('#0', text='🔔 Sessões e Conversas (Auto DL)', anchor='w')
        self.tree.heading('msgs', text='Msgs', anchor='center')
        self.tree.heading('imgs', text='Imgs', anchor='center')
        self.tree.heading('videos', text='Vids', anchor='center')
        self.tree.heading('audios', text='Auds', anchor='center')
        
        # Largura das colunas
        self.tree.column('#0', width=220, minwidth=180)
        self.tree.column('msgs', width=50, minwidth=40, anchor='center')
        self.tree.column('imgs', width=50, minwidth=40, anchor='center')
        self.tree.column('videos', width=50, minwidth=40, anchor='center')
        self.tree.column('audios', width=50, minwidth=40, anchor='center')
        
        # Scrollbars
        v_scrollbar = ttk.Scrollbar(tree_scroll_frame, orient="vertical", command=self.tree.yview)
        h_scrollbar = ttk.Scrollbar(tree_scroll_frame, orient="horizontal", command=self.tree.xview)
        
        self.tree.configure(yscrollcommand=v_scrollbar.set, xscrollcommand=h_scrollbar.set)
        
        # Pack TreeView e scrollbars
        self.tree.grid(row=0, column=0, sticky='nsew')
        v_scrollbar.grid(row=0, column=1, sticky='ns')
        h_scrollbar.grid(row=1, column=0, sticky='ew')
        
        # Configurar grid
        tree_scroll_frame.grid_rowconfigure(0, weight=1)
        tree_scroll_frame.grid_columnconfigure(0, weight=1)
        
        # Bind para seleção
        self.tree.bind('<<TreeviewSelect>>', self.on_tree_select)
        
        # Bind para expandir/colapsar
        self.tree.bind('<Double-1>', self.on_tree_double_click)
    
    def setup_chat_area(self):
        """Configura a área de chat"""
        # Cabeçalho do chat
        self.chat_header = tk.Frame(self.chat_frame, bg='#075e54', height=80)
        self.chat_header.pack(fill=tk.X)
        self.chat_header.pack_propagate(False)
        
        # Informações do contato
        header_content = tk.Frame(self.chat_header, bg='#075e54')
        header_content.pack(fill=tk.BOTH, padx=20, pady=15)
        
        self.contact_name_label = tk.Label(header_content, text="📚 Selecione uma conversa - Mídias são baixadas automaticamente!", 
                                          fg='white', bg='#075e54', 
                                          font=('Arial', 14, 'bold'), anchor='w')
        self.contact_name_label.pack(fill=tk.X)
        
        self.contact_info_label = tk.Label(header_content, text="💾 Todas as mídias (Imagens, Vídeos, Áudios) são salvas automaticamente na pasta whatsapp_images!", 
                                         fg='#25d366', bg='#075e54', 
                                         font=('Arial', 11), anchor='w')
        self.contact_info_label.pack(fill=tk.X, pady=(3, 0))
        
        # Área de mensagens
        messages_frame = tk.Frame(self.chat_frame, bg='#e5ddd5')
        messages_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        
        # Canvas para mensagens
        self.messages_canvas = tk.Canvas(messages_frame, bg='#e5ddd5', highlightthickness=0)
        messages_scrollbar = ttk.Scrollbar(messages_frame, orient="vertical", 
                                         command=self.messages_canvas.yview)
        
        self.messages_scrollable_frame = tk.Frame(self.messages_canvas, bg='#e5ddd5')
        
        # Configurar scroll
        self.messages_scrollable_frame.bind(
            "<Configure>",
            lambda e: self.messages_canvas.configure(scrollregion=self.messages_canvas.bbox("all"))
        )
        
        self.messages_canvas.create_window((0, 0), window=self.messages_scrollable_frame, anchor="nw")
        self.messages_canvas.configure(yscrollcommand=messages_scrollbar.set)
        
        # Pack
        messages_scrollbar.pack(side="right", fill="y")
        self.messages_canvas.pack(side="left", fill="both", expand=True)
        
        # Scroll com mouse
        def on_mousewheel(event):
            self.messages_canvas.yview_scroll(int(-1*(event.delta/120)), "units")
        
        self.messages_canvas.bind("<MouseWheel>", on_mousewheel)
        self.messages_scrollable_frame.bind("<MouseWheel>", on_mousewheel)
        
        # Área de entrada (desabilitada)
        input_frame = tk.Frame(self.chat_frame, bg='#f0f0f0', height=50)
        input_frame.pack(fill=tk.X, padx=10, pady=5)
        input_frame.pack_propagate(False)
        
        tk.Label(input_frame, text="📚 Visualizador + Auto Download (Todas as Mídias são Salvas Automaticamente)", 
                bg='#f0f0f0', fg='#666', font=('Arial', 10)).pack(pady=15)
    
    def load_historical_data(self):
        """Carrega dados históricos completos"""
        try:
            if not os.path.exists(self.history_file):
                self.show_loading_status("ℹ️ Nenhum histórico encontrado - use 🔄 para carregar dados")
                return
            
            self.show_loading_status("📚 Carregando histórico completo...")
            
            with open(self.history_file, 'r', encoding='utf-8') as file:
                historical_data = json.load(file)
            
            # Processar dados históricos
            sessions_contacts = defaultdict(lambda: defaultdict(lambda: {
                'messages': [],
                'images': [],
                'videos': [],
                'audios': [],
                'last_timestamp': 0,
                'notify_name': '',
                'is_group': False,
                'participants': set()
            }))
            
            processed_count = 0
            for item in historical_data:
                try:
                    session_id = item.get('conteudo', {}).get('sessionId', 'unknown')
                    data_type = item.get('conteudo', {}).get('dataType', 'unknown')
                    
                    if data_type in ['message_create', 'message']:
                        message_data = item.get('conteudo', {}).get('data', {})
                        message = message_data.get('message', {})
                        
                        from_contact = message.get('from', '')
                        to_contact = message.get('to', '')
                        timestamp = message.get('timestamp', 0)
                        notify_name = message.get('_data', {}).get('notifyName', '') or \
                                    message_data.get('notifyName', '')
                        
                        is_group = '@g.us' in from_contact or '@g.us' in to_contact
                        
                        if is_group:
                            contact_key = from_contact if '@g.us' in from_contact else to_contact
                        else:
                            contact_key = from_contact if not message.get('fromMe', False) else to_contact
                        
                        if contact_key and contact_key not in ['555198804804@c.us']:
                            contact_data = sessions_contacts[session_id][contact_key]
                            contact_data['is_group'] = is_group
                            
                            if is_group and not message.get('fromMe', False):
                                contact_data['participants'].add(notify_name or from_contact)
                            
                            message_info = {
                                'id': message.get('id', {}).get('_serialized', ''),
                                'timestamp': timestamp,
                                'formatted_time': datetime.fromtimestamp(timestamp).strftime('%H:%M'),
                                'formatted_date': datetime.fromtimestamp(timestamp).strftime('%d/%m/%Y'),
                                'body': message.get('body', ''),
                                'type': message.get('type', ''),
                                'fromMe': message.get('fromMe', False),
                                'hasMedia': message.get('hasMedia', False),
                                'sender_name': notify_name if is_group else None,
                                'mimetype': message.get('_data', {}).get('mimetype', '') or message.get('mimetype', ''),
                                'duration': message.get('_data', {}).get('duration', '') or message.get('duration', ''),
                                'size': message.get('_data', {}).get('size', 0) or message.get('size', 0),
                                'width': message.get('_data', {}).get('width', 0) or message.get('width', 0),
                                'height': message.get('_data', {}).get('height', 0) or message.get('height', 0),
                                'directPath': message.get('_data', {}).get('directPath', '') or message.get('directPath', ''),
                                'deprecatedMms3Url': message.get('_data', {}).get('deprecatedMms3Url', '') or message.get('deprecatedMms3Url', ''),
                                'mediaKey': message.get('mediaKey', '')
                            }
                            
                            contact_data['messages'].append(message_info)
                            contact_data['last_timestamp'] = max(contact_data['last_timestamp'], timestamp)
                            
                            if notify_name:
                                if not is_group:
                                    contact_data['notify_name'] = notify_name
                                elif not contact_data['notify_name']:
                                    contact_data['notify_name'] = notify_name
                            
                            # Classificar mídia por tipo
                            if message_info['type'] == 'image':
                                if message_info['body'] and (message_info['body'].startswith('/9j/') or message_info['body'].startswith('iVBOR')):
                                    contact_data['images'].append({
                                        'timestamp': timestamp,
                                        'base64_data': message_info['body'],
                                        'message_id': message_info['id']
                                    })
                            elif message_info['type'] == 'video':
                                video_info = {
                                    'timestamp': timestamp,
                                    'message_id': message_info['id'],
                                    'duration': message_info['duration'],
                                    'size': message_info['size'],
                                    'width': message_info['width'],
                                    'height': message_info['height'],
                                    'directPath': message_info['directPath'],
                                    'url': message_info['deprecatedMms3Url'],
                                    'mediaKey': message_info['mediaKey'],
                                    'mimetype': message_info['mimetype']
                                }
                                if message_info['body'] and message_info['body'].startswith('/9j/'):
                                    video_info['thumbnail'] = message_info['body']
                                
                                contact_data['videos'].append(video_info)
                            elif message_info['type'] in ['audio', 'ptt']:
                                contact_data['audios'].append({
                                    'timestamp': timestamp,
                                    'message_id': message_info['id'],
                                    'duration': message_info['duration'],
                                    'size': message_info['size'],
                                    'directPath': message_info['directPath'],
                                    'url': message_info['deprecatedMms3Url'],
                                    'mediaKey': message_info['mediaKey'],
                                    'mimetype': message_info['mimetype'],
                                    'type': message_info['type']
                                })
                    
                    elif data_type == 'media':
                        media_data = item.get('conteudo', {}).get('data', {})
                        message_media = media_data.get('messageMedia', {})
                        message = media_data.get('message', {})
                        
                        from_contact = message.get('from', '')
                        to_contact = message.get('to', '')
                        
                        is_group = '@g.us' in from_contact or '@g.us' in to_contact
                        
                        if is_group:
                            contact_key = from_contact if '@g.us' in from_contact else to_contact
                        else:
                            contact_key = from_contact if not message.get('fromMe', False) else to_contact
                        
                        if contact_key and contact_key not in ['555198804804@c.us']:
                            if message_media.get('data'):
                                mimetype = message_media.get('mimetype', '')
                                
                                if mimetype.startswith('image/'):
                                    sessions_contacts[session_id][contact_key]['images'].append({
                                        'timestamp': message.get('timestamp', 0),
                                        'base64_data': message_media.get('data', ''),
                                        'message_id': message.get('id', {}).get('_serialized', ''),
                                        'mimetype': mimetype
                                    })
                                elif mimetype.startswith('video/'):
                                    sessions_contacts[session_id][contact_key]['videos'].append({
                                        'timestamp': message.get('timestamp', 0),
                                        'base64_data': message_media.get('data', ''),
                                        'message_id': message.get('id', {}).get('_serialized', ''),
                                        'mimetype': mimetype,
                                        'filesize': message_media.get('filesize', 0)
                                    })
                                elif mimetype.startswith('audio/'):
                                    sessions_contacts[session_id][contact_key]['audios'].append({
                                        'timestamp': message.get('timestamp', 0),
                                        'base64_data': message_media.get('data', ''),
                                        'message_id': message.get('id', {}).get('_serialized', ''),
                                        'mimetype': mimetype,
                                        'filesize': message_media.get('filesize', 0),
                                        'type': 'audio'
                                    })
                    
                    processed_count += 1
                    if processed_count % 1000 == 0:
                        self.show_loading_status(f"⚙️ Processando histórico... {processed_count}/{len(historical_data)}")
                
                except Exception as e:
                    continue
            
            # Separar contatos e grupos
            self.contacts_data = {}
            self.groups_data = {}
            
            for session_id in sessions_contacts:
                for contact_id, contact_data in sessions_contacts[session_id].items():
                    contact_data['messages'].sort(key=lambda x: x['timestamp'])
                    
                    if contact_data['is_group']:
                        if session_id not in self.groups_data:
                            self.groups_data[session_id] = {}
                        self.groups_data[session_id][contact_id] = contact_data
                    else:
                        if session_id not in self.contacts_data:
                            self.contacts_data[session_id] = {}
                        self.contacts_data[session_id][contact_id] = contact_data
            
            # *** SALVAR TODAS AS MÍDIAS AUTOMATICAMENTE ***
            self.show_loading_status("💾 Salvando todas as mídias automaticamente...")
            all_contacts = {}
            all_contacts.update(self.contacts_data)
            all_contacts.update(self.groups_data)
            self.auto_save_media_to_files(all_contacts)
            
            # Atualizar interface
            total_contacts = sum(len(contacts) for contacts in self.contacts_data.values())
            total_groups = sum(len(groups) for groups in self.groups_data.values())
            total_messages = sum(
                len(contact_data['messages']) 
                for session in [self.contacts_data, self.groups_data] 
                for contacts in session.values() 
                for contact_data in contacts.values()
            )
            total_videos = sum(
                len(contact_data['videos']) 
                for session in [self.contacts_data, self.groups_data] 
                for contacts in session.values() 
                for contact_data in contacts.values()
            )
            total_audios = sum(
                len(contact_data['audios']) 
                for session in [self.contacts_data, self.groups_data] 
                for contacts in session.values() 
                for contact_data in contacts.values()
            )
            
            update_time = datetime.now().strftime('%H:%M:%S')
            unread_count = len(self.unread_conversations)
            
            self.show_loading_status(f"✅ {total_contacts} conversas | {total_groups} grupos | {total_messages:,} msgs | {total_videos} vids | {total_audios} auds | 🔔 {unread_count} | {update_time}")
            
            # Popular árvore
            self.populate_tree()
            
        except Exception as e:
            messagebox.showerror("Erro", f"Erro ao carregar histórico: {e}")
    
    def on_tree_select(self, event):
        """Evento de seleção na árvore"""
        selection = self.tree.selection()
        if not selection:
            return
        
        item = selection[0]
        item_data = self.tree.item(item)
        
        # Verificar se é uma conversa
        tags = item_data.get('tags', [])
        if len(tags) >= 2:
            session_id = tags[0]
            contact_id = tags[1]
            
            # Marcar como lida
            self.mark_conversation_as_read(session_id, contact_id)
            
            current_data = self.contacts_data if self.current_view == "contacts" else self.groups_data
            if session_id in current_data and contact_id in current_data[session_id]:
                contact_data = current_data[session_id][contact_id]
                self.show_conversation(session_id, contact_id, contact_data)
    
    def on_tree_double_click(self, event):
        """Evento de duplo clique"""
        item = self.tree.identify('item', event.x, event.y)
        if item:
            children = self.tree.get_children(item)
            if children:
                if self.tree.item(item, 'open'):
                    self.tree.item(item, open=False)
                else:
                    self.tree.item(item, open=True)
    
    def switch_view(self, view_type):
        """Alterna entre conversas e grupos"""
        self.current_view = view_type
        
        if view_type == "contacts":
            self.contacts_tab.config(bg='#128c7e', relief=tk.RAISED, bd=2, font=('Arial', 10, 'bold'))
            self.groups_tab.config(bg='#0d7377', relief=tk.FLAT, bd=1, font=('Arial', 10))
        else:
            self.contacts_tab.config(bg='#0d7377', relief=tk.FLAT, bd=1, font=('Arial', 10))
            self.groups_tab.config(bg='#128c7e', relief=tk.RAISED, bd=2, font=('Arial', 10, 'bold'))
        
        self.populate_tree()
    
    def populate_tree(self):
        """Popula a árvore hierárquica com notificações"""
        # Limpar árvore
        for item in self.tree.get_children():
            self.tree.delete(item)
        
        current_data = self.contacts_data if self.current_view == "contacts" else self.groups_data
        
        if not current_data:
            return
        
        # Adicionar cada sessão
        for session_id, contacts in current_data.items():
            total_msgs = sum(len(contact_data['messages']) for contact_data in contacts.values())
            total_imgs = sum(len(contact_data['images']) for contact_data in contacts.values())
            total_videos = sum(len(contact_data['videos']) for contact_data in contacts.values())
            total_audios = sum(len(contact_data['audios']) for contact_data in contacts.values())
            
            session_icon = "📱" if self.current_view == "contacts" else "🏢"
            session_text = f"{session_icon} {session_id}"
            
            session_node = self.tree.insert('', 'end', 
                                          text=session_text,
                                          values=(total_msgs, total_imgs, total_videos, total_audios),
                                          open=True)
            
            contacts_sorted = sorted(contacts.items(), 
                                   key=lambda x: x[1]['last_timestamp'], 
                                   reverse=True)
            
            for contact_id, contact_data in contacts_sorted:
                contact_name = self.get_contact_name(contact_id, contact_data['notify_name'])
                
                if contact_data['is_group']:
                    contact_icon = "👥"
                else:
                    contact_icon = "👤"
                
                # Verificar se tem notificação
                conv_key = f"{session_id}_{contact_id}"
                if conv_key in self.unread_conversations:
                    contact_text = f"🔴 {contact_icon} {contact_name}"  # Bolinha vermelha
                else:
                    contact_text = f"{contact_icon} {contact_name}"
                
                msg_count = len(contact_data['messages'])
                img_count = len(contact_data['images'])
                video_count = len(contact_data['videos'])
                audio_count = len(contact_data['audios'])
                
                self.tree.insert(session_node, 'end',
                               text=contact_text,
                               values=(msg_count, img_count, video_count, audio_count),
                               tags=(session_id, contact_id))
    
    def get_contact_name(self, contact_id, notify_name=None):
        """Obtém nome do contato"""
        if '@g.us' in contact_id:
            if notify_name and notify_name.strip():
                return notify_name
            return f"Grupo {contact_id.replace('@g.us', '')[:8]}..."
        else:
            if notify_name and notify_name.strip():
                return notify_name
            
            phone = contact_id.replace('@c.us', '')
            if phone.startswith('55') and len(phone) >= 12:
                return f"+55 ({phone[2:4]}) {phone[4:9]}-{phone[9:]}"
            return phone
    
    def show_conversation(self, session_id, contact_id, contact_data):
        """Mostra histórico completo da conversa com todas as mídias"""
        # Atualizar cabeçalho
        contact_name = self.get_contact_name(contact_id, contact_data['notify_name'])
        self.contact_name_label.config(text=f"📚 {contact_name}")
        
        if contact_data['is_group']:
            participants = len(contact_data['participants'])
            info_text = f"📱 {session_id} | 👥 {participants} participantes | 💬 {len(contact_data['messages'])} msgs | 📷 {len(contact_data['images'])} imgs | 🎥 {len(contact_data['videos'])} vids | 🎵 {len(contact_data['audios'])} auds | 💾 Auto Download ON"
        else:
            phone = self.get_contact_name(contact_id, None)
            info_text = f"📱 {session_id} | 📞 {phone} | 💬 {len(contact_data['messages'])} msgs | 📷 {len(contact_data['images'])} imgs | 🎥 {len(contact_data['videos'])} vids | 🎵 {len(contact_data['audios'])} auds | 💾 Auto Download ON"
        
        self.contact_info_label.config(text=info_text)
        
        # Limpar mensagens
        for widget in self.messages_scrollable_frame.winfo_children():
            widget.destroy()
        
        # Mostrar todas as mensagens do histórico
        current_date = None
        for message in contact_data['messages']:
            msg_date = message['formatted_date']
            
            if current_date != msg_date:
                current_date = msg_date
                date_frame = tk.Frame(self.messages_scrollable_frame, bg='#e5ddd5')
                date_frame.pack(fill=tk.X, pady=15)
                
                date_label = tk.Label(date_frame, text=msg_date, 
                                     bg='#fff3e0', fg='#5d4037', 
                                     font=('Arial', 10, 'bold'), 
                                     relief=tk.RAISED, bd=1, padx=15, pady=5)
                date_label.pack()
            
            self.create_message_bubble(message, contact_data)
        
        # Scroll para o final
        self.root.after(100, lambda: self.messages_canvas.yview_moveto(1.0))
    
    def create_message_bubble(self, message, contact_data):
        """Cria bolha de mensagem com suporte para todas as mídias"""
        is_from_me = message['fromMe']
        is_group = contact_data['is_group']
        
        msg_container = tk.Frame(self.messages_scrollable_frame, bg='#e5ddd5')
        msg_container.pack(fill=tk.X, padx=10, pady=4)
        
        if is_from_me:
            msg_frame = tk.Frame(msg_container, bg='#dcf8c6', 
                               relief=tk.RAISED, bd=1, padx=15, pady=10)
            msg_frame.pack(side=tk.RIGHT, anchor='e', padx=(80, 0))
        else:
            msg_frame = tk.Frame(msg_container, bg='white', 
                               relief=tk.RAISED, bd=1, padx=15, pady=10)
            msg_frame.pack(side=tk.LEFT, anchor='w', padx=(0, 80))
        
        # Nome do remetente em grupos
        if is_group and not is_from_me and message.get('sender_name'):
            sender_label = tk.Label(msg_frame, text=f"👤 {message['sender_name']}", 
                                  bg=msg_frame['bg'], fg='#128c7e',
                                  font=('Arial', 10, 'bold'))
            sender_label.pack(anchor='w', pady=(0, 5))
        
        # Conteúdo baseado no tipo
        if message['type'] == 'image':
            self.add_image_to_message(msg_frame, message, contact_data)
        elif message['type'] == 'video':
            self.add_video_to_message(msg_frame, message, contact_data)
        elif message['type'] in ['audio', 'ptt']:
            self.add_audio_to_message(msg_frame, message, contact_data)
        else:
            # Mensagem de texto
            if message['body'].strip():
                text_label = tk.Label(msg_frame, text=message['body'], 
                                    bg=msg_frame['bg'], fg='#303030',
                                    font=('Arial', 11), 
                                    wraplength=400, justify=tk.LEFT, anchor='w')
                text_label.pack(anchor='w', pady=(0, 5))
        
        # Timestamp
        time_label = tk.Label(msg_frame, text=message['formatted_time'], 
                            bg=msg_frame['bg'], fg='#999', 
                            font=('Arial', 8))
        time_label.pack(anchor='e')
    
    def add_image_to_message(self, msg_frame, message, contact_data):
        """Adiciona imagem à mensagem - AUTO SALVA"""
        try:
            image_data = None
            for img in contact_data['images']:
                if img['message_id'] == message['id'] or abs(img['timestamp'] - message['timestamp']) < 5:
                    image_data = img['base64_data']
                    break
            
            if not image_data and message['body'] and (message['body'].startswith('/9j/') or message['body'].startswith('iVBOR')):
                image_data = message['body']
            
            if image_data:
                image_bytes = base64.b64decode(image_data)
                image = Image.open(io.BytesIO(image_bytes))
                image.thumbnail((300, 300), Image.Resampling.LANCZOS)
                photo = ImageTk.PhotoImage(image)
                
                # Frame para imagem com indicador de auto-save
                img_container = tk.Frame(msg_frame, bg=msg_frame['bg'])
                img_container.pack(anchor='w', pady=(0, 5))
                
                img_label = tk.Label(img_container, image=photo, bg=msg_frame['bg'])
                img_label.image = photo
                img_label.pack()
                
                # Indicador de que foi salva automaticamente
                save_indicator = tk.Label(img_container, text="💾 Salva automaticamente", 
                                        bg=msg_frame['bg'], fg='#4caf50',
                                        font=('Arial', 8))
                save_indicator.pack(pady=(2, 0))
            else:
                placeholder_frame = tk.Frame(msg_frame, bg='#f0f0f0', relief=tk.RAISED, bd=1)
                placeholder_frame.pack(anchor='w', pady=(0, 5))
                
                tk.Label(placeholder_frame, text="📷 Imagem\n(não disponível)", 
                        bg='#f0f0f0', fg='#666',
                        font=('Arial', 10), justify=tk.CENTER, padx=15, pady=15).pack()
        except Exception as e:
            error_frame = tk.Frame(msg_frame, bg='#ffebee', relief=tk.RAISED, bd=1)
            error_frame.pack(anchor='w', pady=(0, 5))
            
            tk.Label(error_frame, text="📷 Erro ao carregar imagem", 
                    bg='#ffebee', fg='#c62828',
                    font=('Arial', 10), padx=10, pady=10).pack()
    
    def add_video_to_message(self, msg_frame, message, contact_data):
        """Adiciona vídeo à mensagem - AUTO SALVA"""
        try:
            # Procurar dados do vídeo
            video_data = None
            for vid in contact_data['videos']:
                if vid['message_id'] == message['id'] or abs(vid['timestamp'] - message['timestamp']) < 5:
                    video_data = vid
                    break
            
            # Frame do vídeo
            video_frame = tk.Frame(msg_frame, bg='#e1f5fe', relief=tk.RAISED, bd=2)
            video_frame.pack(anchor='w', pady=(0, 5))
            
            # Cabeçalho do vídeo
            video_header = tk.Frame(video_frame, bg='#01579b')
            video_header.pack(fill=tk.X)
            
            tk.Label(video_header, text="🎥 VÍDEO - AUTO SALVO", bg='#01579b', fg='white', 
                    font=('Arial', 10, 'bold'), padx=10, pady=5).pack()
            
            # Thumbnail se disponível
            if message.get('body') and message['body'].startswith('/9j/'):
                try:
                    thumb_data = base64.b64decode(message['body'])
                    thumb_image = Image.open(io.BytesIO(thumb_data))
                    thumb_image.thumbnail((200, 150), Image.Resampling.LANCZOS)
                    thumb_photo = ImageTk.PhotoImage(thumb_image)
                    
                    thumb_label = tk.Label(video_frame, image=thumb_photo, bg='#e1f5fe')
                    thumb_label.image = thumb_photo
                    thumb_label.pack(padx=10, pady=5)
                except:
                    tk.Label(video_frame, text="🎬 Thumbnail não disponível", 
                            bg='#e1f5fe', fg='#01579b',
                            font=('Arial', 9), padx=10, pady=10).pack()
            
            # Informações do vídeo
            info_frame = tk.Frame(video_frame, bg='#e1f5fe')
            info_frame.pack(fill=tk.X, padx=10, pady=5)
            
            if video_data:
                duration = video_data.get('duration', '0')
                size = video_data.get('size', 0)
                width = video_data.get('width', 0)
                height = video_data.get('height', 0)
                
                if size > 1024*1024:
                    size_str = f"{size/(1024*1024):.1f} MB"
                elif size > 1024:
                    size_str = f"{size/1024:.1f} KB"
                else:
                    size_str = f"{size} bytes"
                
                info_text = f"⏱️ Duração: {duration}s | 📏 {width}x{height} | 💾 {size_str}"
            else:
                duration = message.get('duration', '0')
                size = message.get('size', 0)
                width = message.get('width', 0)
                height = message.get('height', 0)
                
                if size > 1024*1024:
                    size_str = f"{size/(1024*1024):.1f} MB"
                elif size > 1024:
                    size_str = f"{size/1024:.1f} KB"
                else:
                    size_str = f"{size} bytes"
                
                info_text = f"⏱️ Duração: {duration}s | 📏 {width}x{height} | 💾 {size_str}"
            
            tk.Label(info_frame, text=info_text, bg='#e1f5fe', fg='#01579b',
                    font=('Arial', 9), justify=tk.LEFT).pack(anchor='w')
            
            # Status de salvamento automático
            save_frame = tk.Frame(video_frame, bg='#e1f5fe')
            save_frame.pack(fill=tk.X, padx=10, pady=(0, 10))
            
            tk.Label(save_frame, text="💾 Vídeo salvo automaticamente na pasta whatsapp_images", 
                    bg='#e1f5fe', fg='#4caf50', font=('Arial', 8, 'bold')).pack(anchor='w')
            
            # Botão para abrir pasta
            folder_btn = tk.Button(save_frame, text="📁 Abrir Pasta", 
                                 command=self.open_media_folder,
                                 bg='#0277bd', fg='white', font=('Arial', 8),
                                 relief=tk.FLAT, padx=10, pady=3, cursor='hand2')
            folder_btn.pack(side=tk.LEFT, pady=(5, 0))
            
            # Link se disponível
            download_url = video_data.get('url') if video_data else message.get('deprecatedMms3Url', '')
            if download_url:
                link_btn = tk.Button(save_frame, text="🌐 Link Original", 
                                   command=lambda: webbrowser.open(download_url),
                                   bg='#0277bd', fg='white', font=('Arial', 8),
                                   relief=tk.FLAT, padx=10, pady=3, cursor='hand2')
                link_btn.pack(side=tk.LEFT, padx=(5, 0), pady=(5, 0))
            
        except Exception as e:
            error_frame = tk.Frame(msg_frame, bg='#ffebee', relief=tk.RAISED, bd=1)
            error_frame.pack(anchor='w', pady=(0, 5))
            
            tk.Label(error_frame, text="🎥 Erro ao processar vídeo", 
                    bg='#ffebee', fg='#c62828',
                    font=('Arial', 10), padx=10, pady=10).pack()
    
    def add_audio_to_message(self, msg_frame, message, contact_data):
        """Adiciona áudio à mensagem - AUTO SALVA"""
        try:
            # Procurar dados do áudio
            audio_data = None
            for aud in contact_data['audios']:
                if aud['message_id'] == message['id'] or abs(aud['timestamp'] - message['timestamp']) < 5:
                    audio_data = aud
                    break
            
            # Frame do áudio
            audio_frame = tk.Frame(msg_frame, bg='#f3e5f5', relief=tk.RAISED, bd=2)
            audio_frame.pack(anchor='w', pady=(0, 5))
            
            # Cabeçalho do áudio
            audio_header = tk.Frame(audio_frame, bg='#4a148c')
            audio_header.pack(fill=tk.X)
            
            audio_type = "🎵 ÁUDIO - AUTO SALVO" if message['type'] == 'audio' else "🎤 MENSAGEM DE VOZ - AUTO SALVA"
            tk.Label(audio_header, text=audio_type, bg='#4a148c', fg='white', 
                    font=('Arial', 10, 'bold'), padx=10, pady=5).pack()
            
            # Informações do áudio
            info_frame = tk.Frame(audio_frame, bg='#f3e5f5')
            info_frame.pack(fill=tk.X, padx=10, pady=10)
            
            if audio_data:
                duration = audio_data.get('duration', '0')
                size = audio_data.get('size', 0)
                
                if size > 1024*1024:
                    size_str = f"{size/(1024*1024):.1f} MB"
                elif size > 1024:
                    size_str = f"{size/1024:.1f} KB"
                else:
                    size_str = f"{size} bytes"
                
                info_text = f"⏱️ Duração: {duration}s | 💾 {size_str}"
            else:
                duration = message.get('duration', '0')
                size = message.get('size', 0)
                
                if size > 1024*1024:
                    size_str = f"{size/(1024*1024):.1f} MB"
                elif size > 1024:
                    size_str = f"{size/1024:.1f} KB"
                else:
                    size_str = f"{size} bytes"
                
                info_text = f"⏱️ Duração: {duration}s | 💾 {size_str}"
            
            tk.Label(info_frame, text=info_text, bg='#f3e5f5', fg='#4a148c',
                    font=('Arial', 9), justify=tk.LEFT).pack(anchor='w')
            
            # Status de salvamento automático
            save_frame = tk.Frame(audio_frame, bg='#f3e5f5')
            save_frame.pack(fill=tk.X, padx=10, pady=(0, 10))
            
            tk.Label(save_frame, text="💾 Áudio salvo automaticamente na pasta whatsapp_images", 
                    bg='#f3e5f5', fg='#4caf50', font=('Arial', 8, 'bold')).pack(anchor='w')
            
            # Controles
            controls_frame = tk.Frame(audio_frame, bg='#f3e5f5')
            controls_frame.pack(fill=tk.X, padx=10, pady=(5, 10))
            
            # Botão para abrir pasta
            folder_btn = tk.Button(controls_frame, text="📁 Abrir Pasta", 
                                 command=self.open_media_folder,
                                 bg='#7b1fa2', fg='white', font=('Arial', 8),
                                 relief=tk.FLAT, padx=10, pady=3, cursor='hand2')
            folder_btn.pack(side=tk.LEFT, padx=(0, 5))
            
            # Link se disponível
            download_url = audio_data.get('url') if audio_data else message.get('deprecatedMms3Url', '')
            if download_url:
                link_btn = tk.Button(controls_frame, text="🌐 Link Original", 
                                   command=lambda: webbrowser.open(download_url),
                                   bg='#7b1fa2', fg='white', font=('Arial', 8),
                                   relief=tk.FLAT, padx=10, pady=3, cursor='hand2')
                link_btn.pack(side=tk.LEFT)
            
        except Exception as e:
            error_frame = tk.Frame(msg_frame, bg='#ffebee', relief=tk.RAISED, bd=1)
            error_frame.pack(anchor='w', pady=(0, 5))
            
            tk.Label(error_frame, text="🎵 Erro ao processar áudio", 
                    bg='#ffebee', fg='#c62828',
                    font=('Arial', 10), padx=10, pady=10).pack()

def main():
    root = tk.Tk()
    app = WhatsAppTreeInterface(root)
    root.mainloop()

if __name__ == "__main__":
    main()
