# Modelo Relacional - KroosProject

## Estrutura das Entidades

• **Utilizador** (utilizador_id (PK), nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador, primeiro_nome, último_nome, data_nascimento, password, tipo_utilizador)

• **Validação_Utilizador** (validacao_id (PK), nome_utilizador, foto_perfil, email_utilizador, telefone_utilizador, primeiro_nome, último_nome, data_nascimento, password, tipo_utilizador)

• **Clube** (clube_id (PK), nome_clube, sigla, logotipo, cor, data_fundação, sede_morada, país_clube, cidade_clube, telefone_clube, email_clube, website_clube, presidente_clube, instagram_clube, facebook_clube, youtube_clube, twitter_clube, tiktok_clube, código_clube (UNIQUE))

• **Época** (epoca_id (PK), época (UNIQUE))

• **Equipa** (equipa_id (PK), escalão, hierarquia, epoca_id (FK), clube_id (FK))

• **Acesso_Equipa** (acesso_id (PK), equipa_id (FK), utilizador_id (FK))

• **Estádio** (estadio_id (PK), clube_id (FK), nome_estádio, capacidade)

• **Jogadores** (jogador_id (PK), foto_jogador, nome_completo, alcunha_jogador, número_favorito, posição_principal, posição_secundária, data_nascimento, local_nascimento, nacionalidade, país_nascimento, pé_preferencial, altura, peso, instagram, facebook, twitter, equipa_id (FK))

• **Assiduidade** (assiduidade_id (PK), treino_id (FK), jogador_id (FK), estado, justificação_ausência)

• **Lesões** (lesao_id (PK), jogador_id (FK), nome_lesão, descrição_lesão, tipo_lesão, tempo_recuperação, estado_lesão)

• **Histórico_Carreira** (carreira_id (PK), jogador_id (FK), epoca_id (FK), clube_id (FK), jogos, golos_marcados, golos_sofridos, assistências)

• **Histórico_Transferência** (transferencia_id (PK), jogador_id (FK), clube_origem_id (FK), clube_destino_id (FK), valor)

• **Validação_Transferência** (validacao_transferencia_id (PK), jogador_id (FK), clube_origem_id (FK), clube_destino_id (FK), valor)

• **Exercícios** (exercicio_id (PK), esquema, estrutura, descrição_exercício, variantes, fundamentos_ofensivos, fundamentos_defensivos, ações_ofensivas, ações_defensivas, duração, repetições, séries, pausa_entre_repetições, pausa_entre_séries, volume_exercício, recuperação_para_próximo)

• **Plano_Treino** (plano_treino_id (PK), exercicio_1_id (FK), exercicio_2_id (FK), exercicio_3_id (FK), exercicio_4_id (FK), exercicio_5_id (FK), exercicio_6_id (FK), exercicio_7_id (FK), exercicio_8_id (FK), exercicio_9_id (FK), exercicio_10_id (FK))

• **Treino** (treino_id (PK), número_treino, data, hora, conteúdo, plano_id (FK), observações, dia_da_semana)

• **Fase** (fase_id (PK), fase)

• **Competição_Default** (competicao_default_id (PK), nome_competição, tipo_competição)

• **Competição** (competicao_id (PK), nome_competicao_id (FK), época (FK), número_fases, vencedor_id (FK))

• **Jogos** (jogo_id (PK), competicao_id (FK), fase_id (FK), clube_casa_id (FK), clube_visitante_id (FK), data_jogo, hora_jogo, local_jogo_id (FK), resultado_casa, resultado_fora)

• **Convocatória** (convocatoria_id (PK), jogador_id (FK), jogo_id (FK), estado)

• **Detalhes_Jogo** (detalhes_jogo_id (PK), jogo_id (FK), jogador_id (FK), tipo_detalhes, minuto_detalhe)

• **Eventos_Jogo** (evento_id (PK), jogo_id (FK), jogador_id (FK), tipo_evento, jogador_entrada_id (FK), jogador_saida_id (FK), jogador_assistencia_id (FK), tipo_golo, zona_golo, zona_corpo_utilizado_golo, minuto_evento)

• **Estatísticas_Jogo** (stats_id (PK), jogo_id (FK), posse_casa, posse_fora, remates_casa, remates_fora, remates_baliza_casa, remates_baliza_fora, grandes_oportunidades_casa, grandes_oportunidades_fora, cantos_casa, cantos_fora, passes_casa, passes_fora, passes_certos_casa, passes_certos_fora)

• **Estatísticas_Jogadores** (stats_id (PK), jogador_id (FK), jogo_id (FK), minutos_jogados, golos, remates, remates_baliza, assistências, passes_chave, passes, passes_certos, cruzamentos, cruzamentos_certos, toques_bola, dribles, dribles_certos, perdas, desarmes, desarmes_ganhos, interceções, alívios, bloqueios_remate, duelos, duelos_ganhos, faltas_sofridas, faltas_feitas, amarelos, vermelhos, defesas, remates_baliza_sofridos, golos_sofridos, clean_sheet, saídas, saídas_eficazes, oportunidades_claras_defendidas, class_média)

• **Eventos_Clube** (evento_id (PK), equipa_id (FK), tipo_evento, descrição_evento, estado_evento, data_evento)

• **Mensagens** (mensagem_id (PK), origem_id (FK), destino_id (FK), conteúdo, estado)

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
| Utilizador → Acesso_Equipa | 1:N | Um utilizador acede a várias equipas |
| Utilizador → Mensagens | 1:N | Um utilizador envia/recebe mensagens |
| Jogadores → Assiduidade | 1:N | Um jogador tem várias registos de assiduidade |
| Jogadores → Lesões | 1:N | Um jogador pode ter várias lesões |
| Jogadores → Histórico_Carreira | 1:N | Um jogador tem histórico de carreira em várias épocas |
| Jogadores → Histórico_Transferência | 1:N | Um jogador pode ter várias transferências |
| Jogadores → Convocatória | 1:N | Um jogador é convocado para vários jogos |
| Jogadores → Detalhes_Jogo | 1:N | Um jogador tem vários detalhes de jogo |
| Jogadores → Eventos_Jogo | 1:N | Um jogador tem vários eventos em jogos |
| Jogadores → Estatísticas_Jogadores | 1:N | Um jogador tem estatísticas em vários jogos |
| Treino → Assiduidade | 1:N | Um treino tem várias registos de assiduidade |
| Treino → Plano_Treino | N:1 | Vários treinos usam o mesmo plano |
| Plano_Treino → Exercícios | N:N | Um plano contém múltiplos exercícios (até 10) |
| Competição → Jogos | 1:N | Uma competição tem vários jogos |
| Fase → Jogos | 1:N | Uma fase tem vários jogos |
| Estádio → Jogos | 1:N | Um estádio realiza vários jogos |
| Jogos → Convocatória | 1:N | Um jogo tem várias convocatórias |
| Jogos → Detalhes_Jogo | 1:N | Um jogo tem detalhes de vários jogadores |
| Jogos → Eventos_Jogo | 1:N | Um jogo tem vários eventos (golos, cartões, etc) |
| Jogos → Estatísticas_Jogo | 1:1 | Um jogo tem uma estatística global |
| Jogos → Estatísticas_Jogadores | 1:N | Um jogo tem estatísticas de vários jogadores |

---

## Restrições de Integridade

1. **Password encriptada** em `UTILIZADOR` e `VALIDAÇÃO_UTILIZADOR` via TRIGGERS MD5
2. **Código de clube UNIQUE** - Garante código único por clube
3. **Época UNIQUE** - Garante única época por ano
4. **Email UNIQUE** em UTILIZADOR - Cada utilizador tem email único
5. **Chaves estrangeiras** com `ON UPDATE CASCADE` - Alterações propagam em cascata
6. **Relacionamentos N:N** - PLANO_TREINO → EXERCÍCIOS (1 plano com até 10 exercícios)
7. **Estados ENUM** - Garantem valores válidos em campos críticos

---

## Notas de Estrutura

### Dados de Utilizadores
- Tabela `UTILIZADOR` armazena utilizadores do sistema ativos
- Tabela `VALIDAÇÃO_UTILIZADOR` armazena pedidos de registo pendentes

### Dados Desportivos
- **Jogadores**: Dados pessoais, posições e redes sociais
- **Equipas**: Agrupam jogadores por escalão e hierarquia numa época
- **Jogos**: Registam competições, fases e resultados
- **Eventos de Jogo**: Golos, cartões e substituições com minuto preciso

### Análise de Desempenho
- **Estatísticas_Jogo**: Estatísticas colectivas do jogo (posse, remates, passes)
- **Estatísticas_Jogadores**: Estatísticas individuais (golos, defesas, dribles, etc)
- **Eventos_Jogo**: Registos de eventos específicos com assistências

### Treino e Preparação
- **Treinos**: Registam sessões de treino com plano e observações
- **Planos_Treino**: Contêm até 10 exercícios específicos
- **Exercícios**: Detalham esquemas, fundamentos e durações
- **Assiduidade**: Registam presença/ausência em treinos

### Gestão de Transferências
- **Histórico_Transferência**: Histórico oficial de transferências
- **Validação_Transferência**: Transferências pendentes de aprovação

### Gestão de Lesões
- **Lesões**: Registam tipo, duração e estado de recuperação

### Comunicação e Eventos
- **Mensagens**: Comunicação entre utilizadores
- **Eventos_Clube**: Treinos, jogos, reuniões tácticas, etc
