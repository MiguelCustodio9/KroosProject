# Modelo Relacional - KroosProject

## Estrutura das Entidades

• **Utilizador** (utilizador_id (PK), nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador, primeiro_nome, último_nome, data_nascimento, password, tipo_utilizador, tipo_treinador, clube_id (FK))

• **Validação_Utilizador** (validacao_id (PK), nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador, primeiro_nome, último_nome, data_nascimento, password, tipo_utilizador)

• **Clube** (clube_id (PK), nome_clube, sigla, logotipo, cor, data_fundação, sede_morada, país_clube, cidade_clube, telefone_clube, email_clube, website_clube, presidente_clube, instagram_clube, facebook_clube, youtube_clube, twitter_clube, tiktok_clube, código_clube (UNIQUE))

• **Época** (epoca_id (PK), época (UNIQUE))

• **Equipa** (equipa_id (PK), escalão, hierarquia, epoca_id (FK), clube_id (FK))

• **Acesso_Equipa** (acesso_id (PK), equipa_id (FK), utilizador_id (FK))

• **Estádio** (estadio_id (PK), clube_id (FK), nome_estádio, capacidade)

• **Jogadores** (jogador_id (PK), foto_jogador, nome_completo, alcunha_jogador, número_favorito, posição_principal, posição_secundária, data_nascimento, local_nascimento, nacionalidade, país_nascimento, pé_preferencial, altura, peso, instagram, facebook, twitter, equipa_id (FK), utilizador_id (FK))

• **Lesões** (lesao_id (PK), jogador_id (FK), nome_lesão, descrição_lesão, tipo_lesão, tempo_recuperação, estado_lesão)

• **Histórico_Carreira** (carreira_id (PK), jogador_id (FK), epoca_id (FK), clube_id (FK), jogos, golos_marcados, golos_sofridos, assistências)

• **Treino** (treino_id (PK), número_treino, data, hora, conteúdo, plano_id, observações, dia_da_semana, equipa_id (FK), evento_clube_id (FK))

• **Treino_Exercicio** (exercicio_id (PK), treino_id (FK), ordem, dados do exercício visual do plano de treino)

• **Eventos_Clube** (evento_id (PK), equipa_id (FK), tipo_evento, descrição_evento, estado_evento, data_evento, hora_evento, local_evento)

• **Mensagens** (mensagem_id (PK), origem_id (FK), destino_id (FK), conteúdo, estado, enviada_em)

• **Notificacao** (notificacao_id (PK), utilizador_id (FK), clube_id (FK), titulo, mensagem, tipo, estado, criada_em, lida_em, link_acao)

• **Configuracoes_Plataforma** (chave_configuracao (PK), valor_configuracao, atualizado_em)

• **Competicoes_Clube** (competicao_id (PK), clube_id (FK), equipa_id (FK), nome, tipo, epoca, estado, descricao)

• **Jogos_Clube** (jogo_id (PK), competicao_id (FK), adversario, data_jogo, hora_jogo, casa, local_jogo, resultado_nos, resultado_adv, estado, evento_clube_id (FK))

• **Jogo_Configuracao** (jogo_id (PK, FK), numero_partes, minutos_por_parte, presenca_equipa_tecnica, tatica, posicoes_titulares, parte_atual, jogo_terminado)

• **Jogo_Participantes** (participante_id (PK), jogo_id (FK), jogador_id (FK), tipo)

• **Jogo_Substituicoes** (substituicao_id (PK), jogo_id (FK), jogador_entrada_id (FK), jogador_saida_id (FK), minuto)

• **Jogo_Golos** (golo_id (PK), jogo_id (FK), jogador_marcador_id (FK), jogador_assistente_id (FK), minuto, zona, forma)

• **Jogo_Estatisticas_Coletivas** (jogo_id (PK, FK), estatísticas coletivas de posse, remates, passes, etc)

• **Jogo_Estatisticas_Individuais** (estatistica_id (PK), jogo_id (FK), jogador_id (FK), estatísticas individuais de minutos, golos, passes, etc)

---

## Cardinalidades Principais

| Relação | Cardinalidade | Descrição |
|---------|---------------|-----------|
| Clube → Equipa | 1:N | Um clube tem várias equipas |
| Clube → Estádio | 1:N | Um clube tem vários estádios |
| Clube → Jogadores (via Equipa) | 1:N | Um clube tem vários jogadores nas suas equipas |
| Época → Equipa | 1:N | Uma época tem várias equipas |
| Equipa → Jogadores | 1:N | Uma equipa tem vários jogadores |
| Equipa → Acesso_Equipa | 1:N | Uma equipa tem vários acessos de utilizadores |
| Equipa → Eventos_Clube | 1:N | Uma equipa tem vários eventos |
| Equipa → Treino | 1:N | Uma equipa tem várias sessões de treino |
| Utilizador → Acesso_Equipa | 1:N | Um utilizador acede a várias equipas |
| Utilizador → Mensagens | 1:N | Um utilizador envia/recebe mensagens |
| Utilizador → Notificacao | 1:N | Um utilizador recebe várias notificações |
| Jogadores → Lesões | 1:N | Um jogador pode ter várias lesões |
| Jogadores → Histórico_Carreira | 1:N | Um jogador tem histórico de carreira em várias épocas |
| Treino → Treino_Exercicio | 1:N | Um treino tem vários exercícios visuais |
| Equipa → Competicoes_Clube | 1:N | Uma equipa participa em várias competições |
| Competicoes_Clube → Jogos_Clube | 1:N | Uma competição tem vários jogos |
| Jogos_Clube → Jogo_Configuracao | 1:1 | Um jogo tem uma configuração (tática, partes) |
| Jogos_Clube → Jogo_Participantes | 1:N | Um jogo tem vários jogadores convocados/titulares/suplentes |
| Jogos_Clube → Jogo_Substituicoes | 1:N | Um jogo tem várias substituições |
| Jogos_Clube → Jogo_Golos | 1:N | Um jogo tem vários golos |
| Jogos_Clube → Jogo_Estatisticas_Coletivas | 1:1 | Um jogo tem uma estatística coletiva |
| Jogos_Clube → Jogo_Estatisticas_Individuais | 1:N | Um jogo tem estatísticas de vários jogadores |

---

## Restrições de Integridade

1. **Password encriptada** em `UTILIZADOR` e `VALIDAÇÃO_UTILIZADOR` via TRIGGERS MD5
2. **Código de clube UNIQUE** - Garante código único por clube
3. **Época UNIQUE** - Garante única época por ano
4. **Email UNIQUE** em UTILIZADOR - Cada utilizador tem email único
5. **Chaves estrangeiras** com `ON UPDATE CASCADE` - Alterações propagam em cascata
6. **Estados ENUM** - Garantem valores válidos em campos críticos

---

## Notas de Estrutura

### Dados de Utilizadores
- Tabela `UTILIZADOR` armazena utilizadores do sistema ativos
- Tabela `VALIDAÇÃO_UTILIZADOR` armazena pedidos de registo pendentes

### Dados Desportivos
- **Jogadores**: Dados pessoais, posições e redes sociais
- **Equipas**: Agrupam jogadores por escalão e hierarquia numa época
- **Competicoes_Clube / Jogos_Clube**: Registam competições, calendário e resultados do clube

### Análise de Desempenho
- **Jogo_Estatisticas_Coletivas**: Estatísticas coletivas do jogo (posse, remates, passes)
- **Jogo_Estatisticas_Individuais**: Estatísticas individuais por jogador (golos, defesas, dribles, etc)
- **Jogo_Golos / Jogo_Substituicoes / Jogo_Participantes**: Eventos e presenças detalhadas de cada jogo

### Treino e Preparação
- **Treino**: Regista sessões de treino, ligadas a uma equipa e a um evento de calendário
- **Treino_Exercicio**: Exercícios visuais (esquema no campo) associados a cada treino

### Gestão de Lesões
- **Lesões**: Registam tipo, duração e estado de recuperação

### Comunicação e Eventos
- **Mensagens**: Comunicação entre utilizadores
- **Notificacao**: Alertas e avisos dirigidos a um utilizador
- **Eventos_Clube**: Treinos, jogos, reuniões tácticas, etc, no calendário do clube
- **Configuracoes_Plataforma**: Parâmetros globais definidos pelo admin do sistema
- **Eventos_Clube**: Treinos, jogos, reuniões tácticas, etc
