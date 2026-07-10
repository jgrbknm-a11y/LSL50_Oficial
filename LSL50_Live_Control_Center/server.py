#!/usr/bin/env python3
import json
import os
from pathlib import Path
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler

ROOT = Path(__file__).resolve().parent
STATE_FILE = ROOT / "data" / "state.json"
PORT = 5050

class LSL50Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def end_headers(self):
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(204)
        self.end_headers()

    def do_GET(self):
        if self.path.startswith("/api/state"):
            try:
                data = STATE_FILE.read_text(encoding="utf-8")
                self.send_response(200)
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(data.encode("utf-8"))
            except Exception as e:
                self.send_response(500)
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps({"error": str(e)}).encode("utf-8"))
            return
        return super().do_GET()

    def do_POST(self):
        if self.path.startswith("/api/state"):
            try:
                length = int(self.headers.get("Content-Length", "0"))
                body = self.rfile.read(length).decode("utf-8")
                incoming = json.loads(body)
                STATE_FILE.write_text(json.dumps(incoming, ensure_ascii=False, indent=2), encoding="utf-8")
                self.send_response(200)
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps({"ok": True}).encode("utf-8"))
            except Exception as e:
                self.send_response(500)
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps({"error": str(e)}).encode("utf-8"))
            return
        self.send_error(404)

def main():
    os.chdir(ROOT)
    url = f"http://127.0.0.1:{PORT}/control.html"
    print("")
    print("====================================================")
    print("  LSL50 LIVE CONTROL CENTER")
    print("====================================================")
    print(f"  Panel de control: {url}")
    print(f"  Fuente OBS:       http://127.0.0.1:{PORT}/overlay.html")
    print("  Cuaderno vivo:    http://127.0.0.1:8080/api/live-game-state.php")
    print("  Roster admin:     http://127.0.0.1:8080/api/control-center-players.php")
    print("====================================================")
    print("  Requiere el sitio/admin en :8080 para sync del cuaderno.")
    print("  En el panel: TRAER CUADERNO o AUTO CUADERNO.")
    print("  Deja esta ventana abierta mientras transmites.")
    print("  Para cerrar el sistema, presiona CTRL + C.")
    print("====================================================")
    print("")
    server = ThreadingHTTPServer(("127.0.0.1", PORT), LSL50Handler)
    server.serve_forever()

if __name__ == "__main__":
    main()
