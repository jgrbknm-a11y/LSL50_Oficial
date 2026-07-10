#!/usr/bin/env python3
import sqlite3
import sys
from collections import defaultdict
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import landscape, letter
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import Image, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

ROOT = Path(__file__).resolve().parents[1]
DB_FILE = ROOT / "data" / "lsl50_local.sqlite"
OUT_DIR = ROOT / "output" / "pdf"
LOGO = ROOT / "public" / "uploads" / "logos" / "lsl50_official.png"

NAVY = colors.HexColor("#08285c")
GOLD = colors.HexColor("#c69419")
LIGHT_GOLD = colors.HexColor("#fff3cd")
LIGHT_GRAY = colors.HexColor("#f3f4f6")
RED = colors.HexColor("#c81e1e")


def fmt_date(value):
    year, month, day = value.split("-")
    return f"{int(month)}/{int(day)}/{year}"


def fmt_time(value):
    hour, minute = value.split(":")
    hour_i = int(hour)
    suffix = "AM" if hour_i < 12 else "PM"
    display_hour = hour_i if hour_i <= 12 else hour_i - 12
    return f"{display_hour}:{minute} {suffix}"


def time_sort(value):
    order = {"09:30": 0, "11:30": 1, "13:30": 2, "01:30": 2}
    return order.get(value, 99)


def fetch_rows(season_id):
    con = sqlite3.connect(DB_FILE)
    con.row_factory = sqlite3.Row
    season = con.execute("SELECT * FROM seasons WHERE id=?", (season_id,)).fetchone()
    rows = con.execute(
        "SELECT * FROM schedule_entries WHERE season_id=? ORDER BY game_date, game_time, id",
        (season_id,),
    ).fetchall()
    names = [r["name"] for r in con.execute("SELECT name FROM teams ORDER BY name").fetchall()]
    preferred = ["Caribeños", "Cubs", "Bucaneros", "Hispanos", "Sharks", "Cerveceros"]
    teams = [name for name in preferred if name in names] + [name for name in names if name not in preferred]
    if season is None:
        raise SystemExit(f"Season {season_id} not found")
    return season, rows, teams


def regular_table(rows):
    by_week = defaultdict(list)
    off_rows = []
    for row in rows:
        if row["stage"] == "Regular":
            by_week[row["round_no"]].append(row)
        elif row["stage"] == "OFF":
            off_rows.append(row)

    rows_out = [[
        "Week",
        "Date",
        "9:30 AM\nHome Club",
        "9:30 AM\nVisiting Club",
        "11:30 AM\nHome Club",
        "11:30 AM\nVisiting Club",
        "1:30 PM\nHome Club",
        "1:30 PM\nVisiting Club",
    ]]

    regular_dates = []
    for week, games in sorted(by_week.items()):
        games = sorted(games, key=lambda r: time_sort(r["game_time"]))
        regular_dates.append((games[0]["game_date"], week, games))

    off_by_date = {r["game_date"]: r for r in off_rows}
    all_dates = sorted({date for date, _, _ in regular_dates} | set(off_by_date))
    week_by_date = {date: (week, games) for date, week, games in regular_dates}

    for date in all_dates:
        if date in off_by_date:
            rows_out.append(["OFF", fmt_date(date), off_by_date[date]["notes"] or "League off date", "", "", "", "", ""])
            continue
        week, games = week_by_date[date]
        cells = [f"Week {week}", fmt_date(date)]
        for game in games[:3]:
            cells.extend([game["home_label"], game["away_label"]])
        while len(cells) < 8:
            cells.append("")
        rows_out.append(cells)
    return rows_out


def playoff_table(rows):
    data = [["Phase", "Game", "Participants", "Proposed Date", "Field Time / Note"]]
    for row in rows:
        if row["stage"] not in {"Semifinal", "Final"}:
            continue
        phase = row["notes"].split(" - ")[0] if row["stage"] == "Semifinal" else "Final"
        game = "Championship" if row["stage"] == "Final" else f"Game {row['round_no']}"
        participants = f"{row['home_label']} vs {row['away_label']}"
        note = row["notes"].split(" - ", 1)[1] if " - " in (row["notes"] or "") else row["notes"]
        data.append([phase, game, participants, fmt_date(row["game_date"]), f"{fmt_time(row['game_time'])} - {note}"])
    return data


def make_para(text, size=7.4, color=colors.black, bold=False, leading=None):
    return Paragraph(
        text,
        ParagraphStyle(
            name=f"p-{size}-{color}-{bold}",
            fontName="Helvetica-Bold" if bold else "Helvetica",
            fontSize=size,
            leading=leading or size + 1.5,
            alignment=TA_CENTER,
            textColor=color,
        ),
    )


def build_pdf(season_id):
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    out_file = OUT_DIR / f"lsl50_schedule_season_{season_id}.pdf"
    season, rows, teams = fetch_rows(season_id)
    regular = regular_table(rows)
    playoffs = playoff_table(rows)

    doc = SimpleDocTemplate(
        str(out_file),
        pagesize=landscape(letter),
        leftMargin=0.23 * inch,
        rightMargin=0.23 * inch,
        topMargin=0.25 * inch,
        bottomMargin=0.20 * inch,
    )

    story = []
    logo = Image(str(LOGO), width=0.62 * inch, height=0.62 * inch) if LOGO.exists() else ""
    title_block = [
        make_para("LEGENDS SOFTBALL LEAGUE 50 PLUS, INC.", 18, NAVY, True, 20),
        make_para("Updated Official Field Schedule / Calendar | 6 Teams | Double Round Robin", 9.5, GOLD, True, 11),
        make_para("Opening Day: Sunday, June 14, 2026 &nbsp; | &nbsp; Father's Day Off: Sunday, June 21, 2026 &nbsp; | &nbsp; 4th of July Weekend Off: Sunday, July 5, 2026 &nbsp; | &nbsp; One field requested", 6.6),
        make_para("Regular season: 10 Sundays | 30 games | each team plays every opponent twice | 3 games per Sunday.", 6.6),
        make_para("Playoffs: Top 4 teams qualify. Semifinals are best-of-3. Championship Final is one single game.", 6.6),
        make_para("Teams: " + ", ".join(teams) + ". The first team listed in each matchup is the Home Club and the second team is the Visiting Club.", 6.6),
    ]
    header = Table([[logo, title_block]], colWidths=[1.08 * inch, 9.25 * inch])
    header.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ALIGN", (0, 0), (0, 0), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 1),
    ]))
    story.append(header)
    story.append(Table([[""]], colWidths=[10.45 * inch], rowHeights=[0.015 * inch], style=[
        ("BACKGROUND", (0, 0), (-1, -1), GOLD),
    ]))
    story.append(make_para(
        "Modification applied: Week 3 was moved from 7/5/2026 to 7/12/2026 because there were no games during the 4th of July weekend; all following regular-season and playoff dates were moved down one week.",
        7.4,
        RED,
        True,
        8.2,
    ))
    story.append(make_para("Regular Season Schedule", 11.5, NAVY, True, 12.5))

    regular_table_obj = Table(
        regular,
        colWidths=[0.72 * inch, 0.74 * inch, 0.88 * inch, 0.88 * inch, 0.88 * inch, 0.88 * inch, 0.88 * inch, 0.88 * inch],
        repeatRows=1,
    )
    regular_style = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 6.2),
        ("LEADING", (0, 0), (-1, -1), 6.8),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("GRID", (0, 0), (-1, -1), 0.35, NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, LIGHT_GRAY]),
        ("TOPPADDING", (0, 0), (-1, -1), 3.1),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3.1),
        ("LEFTPADDING", (0, 0), (-1, -1), 2),
        ("RIGHTPADDING", (0, 0), (-1, -1), 2),
    ]
    for idx, row in enumerate(regular):
        if idx > 0 and row[0] == "OFF":
            regular_style.extend([
                ("BACKGROUND", (0, idx), (-1, idx), LIGHT_GOLD),
                ("SPAN", (2, idx), (-1, idx)),
                ("FONTNAME", (0, idx), (-1, idx), "Helvetica-Bold"),
            ])
    regular_table_obj.setStyle(TableStyle(regular_style))
    story.append(regular_table_obj)
    story.append(Spacer(1, 0.02 * inch))
    story.append(make_para("Playoffs - Proposed Dates", 11.5, NAVY, True, 12.5))

    playoff_table_obj = Table(
        playoffs,
        colWidths=[1.1 * inch, 0.95 * inch, 2.5 * inch, 1.25 * inch, 2.05 * inch],
        repeatRows=1,
    )
    playoff_table_obj.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 6.3),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("GRID", (0, 0), (-1, -1), 0.35, NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, LIGHT_GRAY]),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
    ]))
    story.append(playoff_table_obj)
    story.append(Spacer(1, 0.03 * inch))
    story.append(Table([[""]], colWidths=[10.45 * inch], rowHeights=[0.012 * inch], style=[
        ("BACKGROUND", (0, 0), (-1, -1), GOLD),
    ]))
    story.append(make_para(
        "Administrative note: Schedule is preliminary and subject to City of Plantation field availability, permit approval, weather conditions, and final confirmation of team participation. Legends Softball League 50 Plus respectfully requests one field for the development of league activities.",
        5.8,
        colors.black,
        False,
        6.4,
    ))
    story.append(make_para("Legends Softball League 50 Plus, Inc. | Broward - Florida | " + season["name"], 5.8, colors.black, False, 6.4))

    doc.build(story)
    print(out_file)


def main():
    season_id = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    build_pdf(season_id)


if __name__ == "__main__":
    main()
