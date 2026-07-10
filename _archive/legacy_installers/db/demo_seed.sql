
INSERT INTO teams (name, city) VALUES ('Tamarac Titans','Tamarac, FL'),('Broward Braves','Broward, FL');
INSERT INTO players (team_id, first_name, last_name, number, position) VALUES (1,'Juan','Rodríguez','10','SS'),(2,'José','Martínez','22','1B');
INSERT INTO games (home_team_id,away_team_id,game_date,location,final_home,final_away,notes) VALUES (1,2,DATE_SUB(CURDATE(), INTERVAL 3 DAY),'Mullins Park',6,4,'Juego demo');
INSERT INTO game_player_stats (game_id,team_id,player_id,AB,H,dbl,tpl,R,RBI,HR,BB,SO,SB) VALUES (1,1,1,4,2,1,0,1,2,0,0,1,0),(1,2,2,4,1,0,0,1,1,0,1,1,0);
INSERT INTO team_stats (team_id,wins,losses,ties,runs_for,runs_against) VALUES (1,1,0,0,6,4) ON DUPLICATE KEY UPDATE wins=VALUES(wins),losses=VALUES(losses),ties=VALUES(ties),runs_for=VALUES(runs_for),runs_against=VALUES(runs_against);
INSERT INTO team_stats (team_id,wins,losses,ties,runs_for,runs_against) VALUES (2,0,1,0,4,6) ON DUPLICATE KEY UPDATE wins=VALUES(wins),losses=VALUES(losses),ties=VALUES(ties),runs_for=VALUES(runs_for),runs_against=VALUES(runs_against);
INSERT INTO media (type,title,url,featured) VALUES ('image','Presentación LS+50','https://images.unsplash.com/photo-1517649763962-0c623066013b?w=1200',1);
INSERT INTO weekly_awards (week_start,week_end,player_id,team_id,award_type,description) VALUES (DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1, 1, 'Jugador de la Semana','Actuación ofensiva destacada');
