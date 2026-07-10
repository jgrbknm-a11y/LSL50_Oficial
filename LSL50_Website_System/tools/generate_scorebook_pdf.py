#!/usr/bin/env python3
import sqlite3
import sys
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import landscape, letter
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import Image, PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

ROOT = Path(__file__).resolve().parents[1]
DB_FILE = ROOT / "data" / "lsl50_local.sqlite"
OUT_DIR = ROOT / "output" / "pdf"
LOGO = ROOT / "public" / "uploads" / "logos" / "lsl50_official.png"

NAVY = colors.HexColor("#061b3b")
GOLD = colors.HexColor("#d7a72f")
ORANGE = colors.HexColor("#f97316")
LIGHT = colors.HexColor("#f8fafc")
LINE = colors.HexColor("#d9e0ea")
GREEN = colors.HexColor("#067647")
RED = colors.HexColor("#b42318")

RESULT_LABELS = {
    "OUT": "Out",
    "1B": "Hit sencillo",
    "2B": "Doble",
    "3B": "Triple",
    "HR": "Jonron",
    "BB": "Base por bolas",
    "HBP": "Golpeado",
    "E": "Error",
    "FC": "Fielder choice",
    "SO": "Ponche",
    "SF": "Sacrifice fly",
    "SH": "Sacrifice bunt",
    "SB": "Robo / avance",
    "WP": "Wild pitch",
    "PB": "Passed ball",
    "CR": "Corredor emergente",
}

RESULT_TYPE_LABELS = {
    "pending": "Pendiente / sin cerrar",
    "normal": "Final normal",
    "time_limit": "Final por tiempo 1h 45m",
    "innings_limit": "Final por 7 innings",
    "extra_innings": "Final en extra inning",
    "rain_legal": "Suspendido por lluvia - juego legal",
    "rain_suspended": "Suspendido por lluvia - no legal",
    "forfeit": "Forfeit",
}

BASE_LABELS = {
    "": "-",
    None: "-",
    "STAY": "Se quedo",
    "1B": "1B",
    "2B": "2B",
    "3B": "3B",
    "H": "Anoto",
    "OUT": "Out",
}


def p(text, size=7.2, color=colors.black, bold=False, align=TA_LEFT, leading=None):
    return Paragraph(
        str(text or ""),
        ParagraphStyle(
            name=f"s-{size}-{color}-{bold}-{align}",
            fontName="Helvetica-Bold" if bold else "Helvetica",
            fontSize=size,
            leading=leading or size + 1.7,
            textColor=color,
            alignment=align,
        ),
    )


def fmt_date(value):
    if not value:
        return "-"
    parts = str(value).split("-")
    if len(parts) == 3:
        return f"{int(parts[1])}/{int(parts[2])}/{parts[0]}"
    return str(value)


def player_label(row):
    number = f"#{row['number']} " if row["number"] else ""
    return f"{number}{row['player_name']}"


def result_label(row):
    label = RESULT_LABELS.get(row["result"], row["result"])
    if row["out_detail"]:
        label += f" ({row['out_detail']})"
    return label


def runner_text(row):
    lines = []
    if row["result"] == "CR":
        for base in ("1B", "2B", "3B"):
            if row[f"runner_{base.lower()}_name"]:
                lines.append(f"Sale de {base}: {row[f'runner_{base.lower()}_name']}")
        return "\n".join(lines) or "-"
    for base in ("1B", "2B", "3B"):
        name = row[f"runner_{base.lower()}_name"]
        to = row[f"runner_{base.lower()}_to"]
        if name:
            lines.append(f"{base}: {name} -> {BASE_LABELS.get(to, to or '-')}")
    return "\n".join(lines) or "-"


def fetch(game_id):
    con = sqlite3.connect(DB_FILE)
    con.row_factory = sqlite3.Row
    game = con.execute(
        """SELECT g.*, ht.name home_name, at.name away_name, wp.first_name || ' ' || wp.last_name winning_pitcher
           FROM games g
           JOIN teams ht ON ht.id=g.home_team_id
           JOIN teams at ON at.id=g.away_team_id
           LEFT JOIN players wp ON wp.id=g.winning_pitcher_id
           WHERE g.id=?""",
        (game_id,),
    ).fetchone()
    if game is None:
        raise SystemExit(f"Game {game_id} not found")

    lineups = con.execute(
        """SELECT gl.*, p.number, p.first_name || ' ' || p.last_name player_name, t.name team_name,
             ot.name borrowed_from_team_name
           FROM game_lineups gl
           JOIN players p ON p.id=gl.player_id
           JOIN teams t ON t.id=gl.team_id
           LEFT JOIN game_borrowed_players gbp ON gbp.game_id=gl.game_id AND gbp.player_id=gl.player_id
             AND gbp.borrowed_team_id=gl.team_id AND gbp.active=1
           LEFT JOIN teams ot ON ot.id=gbp.original_team_id
           WHERE gl.game_id=? AND gl.active=1
           ORDER BY CASE WHEN gl.team_id=? THEN 0 ELSE 1 END, gl.batting_order""",
        (game_id, game["away_team_id"]),
    ).fetchall()

    plays = con.execute(
        """SELECT e.*, bt.name batting_team, b.first_name || ' ' || b.last_name batter_name,
             r1.first_name || ' ' || r1.last_name runner_1b_name,
             r2.first_name || ' ' || r2.last_name runner_2b_name,
             r3.first_name || ' ' || r3.last_name runner_3b_name
           FROM game_play_events e
           JOIN teams bt ON bt.id=e.batting_team_id
           JOIN players b ON b.id=e.batter_id
           LEFT JOIN players r1 ON r1.id=e.runner_1b_id
           LEFT JOIN players r2 ON r2.id=e.runner_2b_id
           LEFT JOIN players r3 ON r3.id=e.runner_3b_id
           WHERE e.game_id=?
           ORDER BY e.inning, CASE e.half WHEN 'top' THEN 0 ELSE 1 END, e.id""",
        (game_id,),
    ).fetchall()

    stats = con.execute(
        """SELECT gps.*, p.number, p.first_name || ' ' || p.last_name player_name, t.name team_name
           FROM game_player_stats gps
           JOIN players p ON p.id=gps.player_id
           JOIN teams t ON t.id=gps.team_id
           WHERE gps.game_id=?
           ORDER BY CASE WHEN gps.team_id=? THEN 0 ELSE 1 END, p.last_name, p.first_name""",
        (game_id, game["away_team_id"]),
    ).fetchall()

    borrowed = con.execute(
        """SELECT gbp.*, p.number, p.first_name || ' ' || p.last_name player_name,
             ot.name original_team_name, bt.name borrowed_team_name
           FROM game_borrowed_players gbp
           JOIN players p ON p.id=gbp.player_id
           LEFT JOIN teams ot ON ot.id=gbp.original_team_id
           JOIN teams bt ON bt.id=gbp.borrowed_team_id
           WHERE gbp.game_id=? AND gbp.active=1
           ORDER BY bt.name, p.last_name, p.first_name""",
        (game_id,),
    ).fetchall()
    con.close()
    return game, lineups, plays, stats, borrowed


def styled_table(data, widths, header_color=NAVY, font_size=6.6):
    table = Table(data, colWidths=widths, repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), header_color),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), font_size),
        ("LEADING", (0, 0), (-1, -1), font_size + 1.4),
        ("GRID", (0, 0), (-1, -1), 0.35, LINE),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, LIGHT]),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
        ("LEFTPADDING", (0, 0), (-1, -1), 3),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3),
    ]))
    return table


def build_pdf(game_id):
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    out_file = OUT_DIR / f"lsl50_scorebook_game_{game_id}.pdf"
    game, lineups, plays, stats, borrowed = fetch(game_id)

    doc = SimpleDocTemplate(
        str(out_file),
        pagesize=landscape(letter),
        leftMargin=0.28 * inch,
        rightMargin=0.28 * inch,
        topMargin=0.28 * inch,
        bottomMargin=0.25 * inch,
    )

    story = []
    logo = Image(str(LOGO), width=0.62 * inch, height=0.62 * inch) if LOGO.exists() else ""
    title = [
        p("LEGENDS SOFTBALL LEAGUE 50 PLUS", 18, NAVY, True, TA_CENTER, 20),
        p("Consulta Oficial del Cuaderno de Anotacion", 10.2, GOLD, True, TA_CENTER, 12),
        p(f"Juego #{game['id']} - {game['home_name']} vs {game['away_name']} - {fmt_date(game['game_date'])}", 8.4, colors.black, True, TA_CENTER, 10),
    ]
    header = Table([[logo, title]], colWidths=[1.0 * inch, 9.3 * inch])
    header.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ALIGN", (0, 0), (0, 0), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
    ]))
    story.append(header)
    story.append(Table([[""]], colWidths=[10.45 * inch], rowHeights=[0.015 * inch], style=[("BACKGROUND", (0, 0), (-1, -1), GOLD)]))
    story.append(Spacer(1, 0.05 * inch))

    status = RESULT_TYPE_LABELS.get(game["result_type"] or "pending", game["result_type"] or "Pendiente")
    closed = (game["status"] in ("final", "suspended")) and (game["result_type"] or "pending") != "pending"
    legal = "Si" if game["is_legal_game"] else "No"
    summary = [
        ["Marcador", f"{game['home_name']} {game['final_home']} - {game['final_away']} {game['away_name']}", "Estado", status],
        ["Innings completos", game["completed_innings"] or "-", "Juego legal", legal],
        ["Pitcher ganador", game["winning_pitcher"] or "-", "Lugar", game["location"] or "-"],
        ["Motivo / nota oficial", game["official_result_note"] or "Sin motivo registrado", "Cierre", game["ended_at"] if closed else "-"],
    ]
    summary_table = Table(summary, colWidths=[1.45 * inch, 4.0 * inch, 1.35 * inch, 3.65 * inch])
    summary_table.setStyle(TableStyle([
        ("GRID", (0, 0), (-1, -1), 0.35, LINE),
        ("BACKGROUND", (0, 0), (0, -1), LIGHT),
        ("BACKGROUND", (2, 0), (2, -1), LIGHT),
        ("FONTNAME", (0, 0), (0, -1), "Helvetica-Bold"),
        ("FONTNAME", (2, 0), (2, -1), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 7.2),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    story.append(summary_table)
    story.append(Spacer(1, 0.08 * inch))

    story.append(p("Lineup Oficial", 11, NAVY, True, TA_LEFT))
    lineup_data = [["Eq.", "Turno", "#", "Jugador", "Pos.", "Nota"]]
    for row in lineups:
        note = f"Prestado de {row['borrowed_from_team_name']}" if row["borrowed_from_team_name"] else ""
        lineup_data.append([row["team_name"], row["batting_order"], row["number"] or "", row["player_name"], row["field_position"] or "-", note])
    if len(lineup_data) == 1:
        lineup_data.append(["-", "-", "-", "Sin lineup guardado", "-", "-"])
    story.append(styled_table(lineup_data, [1.1 * inch, 0.55 * inch, 0.45 * inch, 2.3 * inch, 0.55 * inch, 1.7 * inch], NAVY, 6.8))

    if borrowed:
        story.append(Spacer(1, 0.06 * inch))
        story.append(p("Jugadores Prestados", 10, NAVY, True, TA_LEFT))
        borrowed_data = [["Jugador", "Equipo original", "Juega con", "Motivo", "Aprobado por"]]
        for row in borrowed:
            borrowed_data.append([player_label(row), row["original_team_name"] or "-", row["borrowed_team_name"] or "-", row["reason"] or "-", row["approved_by"] or "-"])
        story.append(styled_table(borrowed_data, [1.8 * inch, 1.4 * inch, 1.4 * inch, 2.15 * inch, 1.4 * inch], GOLD, 6.6))

    story.append(PageBreak())
    story.append(p("Bitacora de Jugadas", 12, NAVY, True, TA_LEFT))
    play_data = [["#", "Inn", "Equipo", "Bateador", "Jugada", "Corredores", "Outs", "RBI", "C", "Notas"]]
    for idx, row in enumerate(plays, 1):
        half = "Alta" if row["half"] == "top" else "Baja"
        batter_to = BASE_LABELS.get(row["batter_to"], row["batter_to"] or "-")
        batter = row["batter_name"] if row["result"] in ("WP", "PB") else f"{row['batter_name']} -> {batter_to}"
        play_data.append([
            idx,
            f"{row['inning']} {half}",
            row["batting_team"],
            batter,
            result_label(row),
            runner_text(row),
            row["outs_on_play"] or 0,
            row["rbi"] or 0,
            row["runs_scored"] or 0,
            row["notes"] or "",
        ])
    if len(play_data) == 1:
        play_data.append(["-", "-", "-", "-", "Sin jugadas registradas", "-", "-", "-", "-", "-"])
    story.append(styled_table(play_data, [0.35 * inch, 0.6 * inch, 1.05 * inch, 1.55 * inch, 1.05 * inch, 2.25 * inch, 0.38 * inch, 0.38 * inch, 0.35 * inch, 1.55 * inch], NAVY, 5.8))

    story.append(PageBreak())
    story.append(p("Box Score Oficial", 12, NAVY, True, TA_LEFT))
    stat_cols = ["Eq.", "#", "Jugador", "AB", "H", "2B", "3B", "R", "RBI", "HR", "BB", "SO", "HBP", "SH", "SF", "E"]
    stat_data = [stat_cols]
    for row in stats:
        stat_data.append([
            row["team_name"], row["number"] or "", row["player_name"], row["AB"], row["H"], row["dbl"],
            row["tpl"], row["R"], row["RBI"], row["HR"], row["BB"], row["SO"], row["HBP"], row["SH"], row["SF"], row["E"],
        ])
    if len(stat_data) == 1:
        stat_data.append(["-", "-", "Sin estadisticas guardadas", 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])
    story.append(styled_table(stat_data, [0.88 * inch, 0.35 * inch, 1.65 * inch, 0.38 * inch, 0.38 * inch, 0.38 * inch, 0.38 * inch, 0.38 * inch, 0.45 * inch, 0.38 * inch, 0.38 * inch, 0.38 * inch, 0.45 * inch, 0.38 * inch, 0.38 * inch, 0.38 * inch], NAVY, 6.0))
    story.append(Spacer(1, 0.08 * inch))
    story.append(p("Documento generado desde el cuaderno digital LSL50. Derechos reservados de Legends Softball League 50 Plus.", 6.4, colors.black, False, TA_CENTER))

    doc.build(story)
    print(out_file)


def main():
    game_id = int(sys.argv[1]) if len(sys.argv) > 1 else 0
    if game_id <= 0:
        raise SystemExit("Usage: generate_scorebook_pdf.py GAME_ID")
    build_pdf(game_id)


if __name__ == "__main__":
    main()
