-- ============================================================
-- SEED DE DADOS DE TESTE — KroosProject
-- Importar em: http://localhost/phpmyadmin → kroosproject
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- ── Época ──────────────────────────────────────────────────
INSERT IGNORE INTO `época` (`id_época`,`época`) VALUES
(1,'2022/2023'),(2,'2023/2024'),(3,'2024/2025'),(4,'2025/2026');

-- ── Clube de teste ─────────────────────────────────────────
-- (assumindo que já existe id_clube = 1 criado no arranque)

-- ── Épocas → Equipas ───────────────────────────────────────
-- (assumindo que id_clube=1 já tem algumas equipas; inserir mais se necessário)
INSERT IGNORE INTO `equipa` (`id_equipa`,`escalão`,`hierarquia`,`id_época`,`id_clube`) VALUES
(1,'S11','A',3,1),(2,'S13','A',3,1),(3,'S15','A',3,1),(4,'Seniores','A',4,1);

-- ── Utilizadores (treinadores e jogadores) ─────────────────
INSERT IGNORE INTO `utilizador`
    (`id_utilizador`,`nome_utilizador`,`email_utilizador`,`primeiro_nome`,`último_nome`,`password`,`tipo_utilizador`,`id_clube`)
VALUES
(10,'joao.silva','joao.silva@clube.pt','João','Silva',MD5('12345'),'jogador',1),
(11,'mario.costa','mario.costa@clube.pt','Mário','Costa',MD5('12345'),'jogador',1),
(12,'pedro.lopes','pedro.lopes@clube.pt','Pedro','Lopes',MD5('12345'),'jogador',1),
(13,'rui.ferreira','rui.ferreira@clube.pt','Rui','Ferreira',MD5('12345'),'jogador',1),
(14,'tiago.martins','tiago.martins@clube.pt','Tiago','Martins',MD5('12345'),'jogador',1),
(15,'carlos.santos','carlos.santos@clube.pt','Carlos','Santos',MD5('12345'),'jogador',1),
(16,'andre.alves','andre.alves@clube.pt','André','Alves',MD5('12345'),'jogador',1),
(17,'nuno.pereira','nuno.pereira@clube.pt','Nuno','Pereira',MD5('12345'),'jogador',1),
(18,'luis.oliveira','luis.oliveira@clube.pt','Luís','Oliveira',MD5('12345'),'jogador',1),
(19,'goncalo.rodrigues','goncalo.rodrigues@clube.pt','Gonçalo','Rodrigues',MD5('12345'),'jogador',1),
(20,'treinador1','treinador1@clube.pt','Miguel','Faria',MD5('12345'),'treinador',1),
(21,'treinador2','treinador2@clube.pt','Sérgio','Neves',MD5('12345'),'treinador',1);

-- ── Jogadores S11 A ────────────────────────────────────────
INSERT IGNORE INTO `jogadores`
    (`id_jogador`,`foto_jogador`,`nome_completo`,`alcunha_jogador`,`número_favorito`,`posição_principal`,`data_nascimento`,`nacionalidade`,`país_nascimento`,`id_equipa`,`id_utilizador`)
VALUES
(1,'','João Silva','Jojo','1','Guarda-Redes','2013-03-10','Portuguesa','Portugal',1,10),
(2,'','Mário Costa','Mário','5','Defesa Central','2013-07-22','Portuguesa','Portugal',1,11),
(3,'','Pedro Lopes','PL','3','Defesa Esquerdo','2013-09-01','Portuguesa','Portugal',1,12),
(4,'','Rui Ferreira','Ruizinho','8','Médio Centro','2013-11-15','Portuguesa','Portugal',1,13),
(5,'','Tiago Martins','Tiagão','10','Médio Ofensivo','2014-02-28','Portuguesa','Portugal',1,14);

-- Jogadores S13 A
INSERT IGNORE INTO `jogadores`
    (`id_jogador`,`foto_jogador`,`nome_completo`,`alcunha_jogador`,`número_favorito`,`posição_principal`,`data_nascimento`,`nacionalidade`,`país_nascimento`,`id_equipa`,`id_utilizador`)
VALUES
(6,'','Carlos Santos','Carlitos','1','Guarda-Redes','2011-04-05','Portuguesa','Portugal',2,15),
(7,'','André Alves','André','4','Defesa Central','2011-06-18','Portuguesa','Portugal',2,16),
(8,'','Nuno Pereira','Nuno','7','Médio Defensivo','2012-01-30','Portuguesa','Portugal',2,17),
(9,'','Luís Oliveira','Luizão','9','Ponta de Lança','2011-08-12','Portuguesa','Portugal',2,18),
(10,'','Gonçalo Rodrigues','Gonças','11','Extremo Esquerdo','2012-05-25','Portuguesa','Portugal',2,19);

-- ── Acesso equipa (treinadores) ────────────────────────────
INSERT IGNORE INTO `acesso_equipa` (`id_acesso`,`id_equipa`,`id_utilizador`) VALUES
(1,1,20),(2,2,21);

-- ── Histórico carreira ────────────────────────────────────
INSERT IGNORE INTO `histórico_carreira`
    (`id_carreira`,`id_jogador`,`id_época`,`id_clube`,`jogos`,`golos_marcados`,`assistências`)
VALUES
(1,1,2,1,18,0,2),(2,1,3,1,22,1,3),
(3,4,2,1,16,3,5),(4,4,3,1,20,7,8),
(5,9,2,1,15,10,4),(6,9,3,1,19,14,6);

-- ── Lesões ────────────────────────────────────────────────
INSERT IGNORE INTO `lesões`
    (`id_lesão`,`id_jogador`,`nome_lesão`,`descrição_lesão`,`tipo_lesão`,`tempo_recuperação`,`estado_lesão`)
VALUES
(1,4,'Entorse tornozelo direito','Ligeira entorse no tornozelo','Ligamentar/Articular','1 - 2 semanas','Recuperado'),
(2,9,'Rotura fibrilar gémeo','Rotura parcial no gémeo esquerdo','Muscular','3 - 1 mês','Em recuperação'),
(3,1,'Fractura dedo mão','Fractura no 3º dedo da mão direita','Óssea','5 dias - 1 semana','Recuperado');

-- ── Competições ───────────────────────────────────────────
INSERT IGNORE INTO `competicoes_clube`
    (`id_competicao`,`id_clube`,`id_equipa`,`nome`,`tipo`,`epoca`,`estado`,`descricao`)
VALUES
(1,1,1,'Campeonato Distrital Sub-11','Campeonato','2024/2025','A decorrer','Campeonato da Associação de Futebol de Lisboa'),
(2,1,1,'Taça Sub-11','Taça','2024/2025','A decorrer','Taça distrital Sub-11'),
(3,1,2,'Campeonato Distrital Sub-13','Campeonato','2024/2025','A decorrer','Liga regional Sub-13'),
(4,1,4,'Liga Amadora Senior','Liga','2025/2026','A decorrer','Campeonato da liga local sénior'),
(5,1,3,'Torneio de Verão Sub-15','Torneio','2024/2025','Finalizada','Torneio de pré-época');

-- ── Jogos ─────────────────────────────────────────────────
INSERT IGNORE INTO `jogos_clube`
    (`id_jogo`,`id_competicao`,`adversario`,`data_jogo`,`hora_jogo`,`casa`,`local_jogo`,`resultado_nos`,`resultado_adv`,`estado`)
VALUES
-- Campeonato Sub-11
(1,1,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -30 DAY),'10:00',1,'Campo Principal',3,1,'Realizado'),
(2,1,'Benfica',DATE_ADD(CURDATE(), INTERVAL -20 DAY),'11:00',0,'Estádio da Luz',2,2,'Realizado'),
(3,1,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -10 DAY),'10:30',1,'Campo Principal',1,0,'Realizado'),
(4,1,'Vitória FC',DATE_ADD(CURDATE(), INTERVAL 7 DAY),'10:00',0,'Campo da Vitória',NULL,NULL,'Agendado'),
(5,1,'Belenenses',DATE_ADD(CURDATE(), INTERVAL 14 DAY),'11:00',1,'Campo Principal',NULL,NULL,'Agendado'),
-- Taça Sub-11
(6,2,'FC Porto',DATE_ADD(CURDATE(), INTERVAL -45 DAY),'14:00',1,'Campo Principal',4,0,'Realizado'),
(7,2,'GD Estoril',DATE_ADD(CURDATE(), INTERVAL 21 DAY),'14:00',0,NULL,NULL,NULL,'Agendado'),
-- Campeonato Sub-13
(8,3,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -25 DAY),'09:30',1,'Campo Principal',2,3,'Realizado'),
(9,3,'Benfica',DATE_ADD(CURDATE(), INTERVAL -12 DAY),'10:00',0,'Caixa Futebol Campus',1,1,'Realizado'),
(10,3,'Casa Pia',DATE_ADD(CURDATE(), INTERVAL 5 DAY),'10:00',1,'Campo Principal',NULL,NULL,'Agendado'),
-- Liga Sénior
(11,4,'Atlético Queluz',DATE_ADD(CURDATE(), INTERVAL -35 DAY),'15:30',1,'Campo Principal',2,1,'Realizado'),
(12,4,'Leões de Lisboa',DATE_ADD(CURDATE(), INTERVAL -18 DAY),'16:00',0,'Estádio Municipal',0,2,'Realizado'),
(13,4,'FC Loures',DATE_ADD(CURDATE(), INTERVAL 3 DAY),'15:00',1,'Campo Principal',NULL,NULL,'Agendado'),
(14,4,'GD Vendas Novas',DATE_ADD(CURDATE(), INTERVAL 10 DAY),'15:30',0,NULL,NULL,NULL,'Agendado'),
-- Torneio Verão (finalizado)
(15,5,'Sporting CP',DATE_ADD(CURDATE(), INTERVAL -90 DAY),'09:00',0,'Campo Neutro',1,2,'Realizado'),
(16,5,'FC Porto',DATE_ADD(CURDATE(), INTERVAL -89 DAY),'11:00',0,'Campo Neutro',3,1,'Realizado'),
(17,5,'Benfica',DATE_ADD(CURDATE(), INTERVAL -89 DAY),'15:00',0,'Campo Neutro',2,2,'Realizado');

-- ── Criar eventos_clube automáticos para os jogos ─────────
INSERT IGNORE INTO `eventos_clube`
    (`id_evento`,`id_equipa`,`tipo_evento`,`descrição_evento`,`estado_evento`,`data_evento`,`hora_evento`)
SELECT
    jc.id_jogo + 1000,
    cc.id_equipa,
    'Jogo',
    CONCAT('Jogo vs ', jc.adversario),
    CASE jc.estado WHEN 'Realizado' THEN 'Realizado' WHEN 'Cancelado' THEN 'Cancelado' ELSE 'Por realizar' END,
    jc.data_jogo,
    jc.hora_jogo
FROM jogos_clube jc
JOIN competicoes_clube cc ON cc.id_competicao = jc.id_competicao;

-- ── Mensagens entre utilizadores ──────────────────────────
-- (assumindo que o admin_clube tem id_utilizador = 1)
INSERT IGNORE INTO `mensagens`
    (`id_mensagem`,`origem`,`destino`,`conteúdo`,`estado`,`enviada_em`)
VALUES
(1,1,20,'Olá Miguel! Amanhã o treino é às 18h, confirmas?','Lida',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(2,20,1,'Confirmado, estarei lá às 17h45 para preparar.','Lida',DATE_SUB(NOW(),INTERVAL 3 DAY)),
(3,1,20,'Ótimo! Traz os coletes e os cones.','Lida',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(4,20,1,'Já tratei disso. Podemos falar sobre a táctica para o próximo jogo?','Lida',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(5,1,20,'Claro, vemo-nos amanhã antes do treino.','Lida',DATE_SUB(NOW(),INTERVAL 2 DAY)),
(6,21,1,'Bom dia! Tenho uma dúvida sobre a convocatória do Gonçalo.','Não Lida',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(7,1,21,'Bom dia Sérgio! O Gonçalo está lesionado, não vai jogar.','Lida',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(8,21,1,'Ah entendo, obrigado! Quem convoco então?','Não Lida',DATE_SUB(NOW(),INTERVAL 23 HOUR)),
(9,1,21,'O André Alves está disponível e em boa forma.','Lida',DATE_SUB(NOW(),INTERVAL 22 HOUR)),
(10,20,1,'Treinador: os pais do João Silva ligaram sobre os equipamentos.','Não Lida',DATE_SUB(NOW(),INTERVAL 10 HOUR)),
(11,1,20,'Vou tratar disso. Obrigado pelo aviso!','Lida',DATE_SUB(NOW(),INTERVAL 9 HOUR)),
(12,20,21,'Sérgio, tens o plano de treino para esta semana?','Lida',DATE_SUB(NOW(),INTERVAL 6 HOUR)),
(13,21,20,'Sim, partilhei no grupo. Viste?','Não Lida',DATE_SUB(NOW(),INTERVAL 5 HOUR)),
(14,20,21,'Encontrei! Muito bom plano, obrigado.','Não Lida',DATE_SUB(NOW(),INTERVAL 4 HOUR)),
(15,21,1,'Presidente, quando é a próxima reunião de dirigentes?','Não Lida',DATE_SUB(NOW(),INTERVAL 2 HOUR));

SET FOREIGN_KEY_CHECKS=1;
