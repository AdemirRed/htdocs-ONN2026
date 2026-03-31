# -*- mode: python ; coding: utf-8 -*-


a = Analysis(
    ['editor-plano-corte.py'],
    pathex=[],
    binaries=[],
    datas=[('data_parser.py', '.'), ('tree_view_manager.py', '.'), ('canvas_renderer.py', '.'), ('edit_controls.py', '.')],
    hiddenimports=[],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.datas,
    [],
    name='Editor-Plano-Corte-Titanium',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon='editor_icon.ico',
)
