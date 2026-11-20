# Localiza Fácil (Plano D) — lista de passos

Projeto em /var/www/guia-novo (pasta e banco com o nome provisório "guia-novo"; marca escolhida: Localiza Fácil). Por enquanto em
https://fsadesigners.com.br/guia-novo/ (pedido do usuário, 20/11), TUDO com noindex (config 'sem_google' + X-Robots-Tag).
Banco guia_novo, config /etc/guia-novo/config.php, dados /var/lib/guia-novo. Histórico do Acha Fácil: docs/historico-acha-facil.md.
Plano criado em 20/11/2026.

Motivo: em 20/11 às 11:52 o Google rebaixou o achafacilapp.com.br inteiro (ver passo 119 do 00-passos.md).
Causa provável: ~111 mil fichas copiadas do Google Maps, entrando de uma vez. O site novo usa o mesmo sistema,
mas nasce pequeno, com dados confirmados e sem levar o problema junto.

Cidades do lançamento: São João da Boa Vista, Poços de Caldas, Vargem Grande do Sul, Aguaí e Santa Rita do Sapucaí.

Status: ⬜ a fazer · 🟡 em andamento · ✅ feito

## Fase 0 — Decisões (usuário)
1. ✅ Nome: **Localiza Fácil** (20/11, escolha do usuário). Descartados: "Achei Fácil" (já existe acheifacil.net,
   rede de negócios do mesmo ramo) e "Acha Fácil" (achafacil.com.br é de terceiros). localizafacil.com.br e .com estão
   registrados por outros, mas parados. Cuidado avisado: "Localiza" (aluguel de carros) é marca forte; pesquisar no INPI.
2. 🟡 Domínio escolhido: **localizafacilapp.com.br** (20/11 ainda livre no registro.br). Falta registrar e apontar o DNS (A) para 177.153.68.212.
3. ✅ E-mail localizafacilapp@gmail.com (contato_email no config e reserva do pagador no Mercado Pago em inc/ofertas.php) e
   Instagram @localizafacilapp ('instagram' no config) (20/11).
3b. ✅ Marca trocada no site novo (20/11): "Acha Fácil(!)" -> "Localiza Fácil" em 69 arquivos de servidor/ (textos, termos,
    privacidade, e-mails do sistema, títulos, CSVs, logs "localiza-facil ...", sessão LOCALIZAFACIL, MP "LOCALIZAFACIL",
    achafacilapp.com.br -> localizafacilapp.com.br). Ficaram DE PROPÓSITO: pacote br.com.fsadesigner.achafacil e o esquema
    achafacil:// do app (mudam só com o app novo, passo 12). Logo nova: assets/localiza/simbolo.svg (pino verde, círculo
    amarelo, loja verde com toldo) e simbolo-contorno.svg (contorno branco, usada no site porque topo/rodapé são verdes);
    PNGs em publico/assets (logo, favicon, apple-touch-icon com fundo creme, logo-google-120); og-imagem.py com o nome novo.
    Backups: /var/lib/guia-novo/backups/servidor-antes-marca.tgz e assets-acha.tgz.
3c. ✅ Login com Google em projeto próprio (20/11): projeto "Localiza Facil" no Google Cloud (nº 1080734033552), tela de
    login "Localiza Fácil", cliente Web com os retornos https://fsadesigners.com.br/guia-novo/google/retorno.php e
    https://localizafacilapp.com.br/google/retorno.php. ID e chave em /etc/guia-novo/config.php (no lugar das do Acha Fácil).
    O Google aceita o redirect (302 para a tela de login). Falta o usuário testar entrando.
## Fase 1 — Preparar o sistema (Claude)
4. ✅ Cópia do projeto em pasta nova e banco novo (20/11): /var/www/guia-novo (git novo), banco guia_novo, instalar.sh
   adaptado (sem cron), base.php lendo /etc/guia-novo/config.php (antes apontava para o do Acha Fácil: corrigido),
   servidor/dominio/ removido, .htaccess com RewriteBase /guia-novo/ e noindex em tudo.
5. ✅ Cidades ligadas (20/11; Santa Rita entrou depois, ver passo 6): cidades, categorias (42) e fichas ativas copiadas do acha_facil:
   São João 4.468, Poços 8.372, Vargem Grande 2.118, Aguaí 1.442 (16.400). Donos, pedidos e pagamentos NÃO copiados (passo 13).
6. ✅ Fichas "a confirmar" x "confirmadas" (20/11): mudança 035 (profissionais.confirmado_em e confirmado_por).
   SQL_CONFIRMADA em inc/guia.php = conferida pela equipe OU com dono (SQL_TEM_DONO, automático); ficha_confirmada(),
   marcar_confirmada(), confirmacao_por_cidade(), META_CONFIRMADAS = 50. Pendências (/admin/) mostra o quadro
   "Fichas confirmadas por cidade" com a barra da meta; /admin/comercio.php tem o aviso ⏳/✅ e o botão "Conferi os
   dados: marcar como confirmada" (e "Voltar para a confirmar"), registro confirmou_ficha/desconfirmou_ficha.
   As 16.400 fichas copiadas entram a confirmar; hoje: São João 2, Poços 1, Vargem 0, Aguaí 0 confirmadas.
   Admins (4) copiados do Acha Fácil, mesmos logins.
   Santa Rita do Sapucaí (20/11, pedido do usuário): 5ª cidade, 1.915 fichas + foto da Rosa Maria Massoterapia. Atenção:
   a lista dela veio quase sem número de avaliações (1.885 com popularidade 0), então só 15 passam na regra do Google.
   Na cópia de fichas use a lista de colunas do acha_facil (o guia_novo tem confirmado_em/confirmado_por a mais).
   Fotos (20/11): copiadas de /var/lib/acha-facil/fotos as dos 3 comércios das 4 cidades que tinham (Agnes Veterinária,
   Studio Movimento Fit, Psicólogo Mário Souza; 10 arquivos).
7. ✅ Quem vai para o Google EM ONDAS (20/11, regra do usuário + ondas sugeridas pelo Claude e aprovadas), inc/guia.php:
   - nada enquanto 'sem_google' estiver no config (domínio da FSA);
   - ficha: telefone/WhatsApp E (confirmada OU 5+ avaliações no Google), sem precisar confirmar ninguém;
   - ondas: 1ª onda = as 100 mais conhecidas de cada cidade (popularidade desc, id asc); +100 por cidade a cada semana
     (GOOGLE_ONDA); confirmadas entram sempre, fora da fila. Começa sozinha quando 'sem_google' sair do config
     ($DADOS/google-ondas.json guarda início, pausa e dias pausados). Pausar/retomar em /admin/google.php ("Ondas do Google").
   - página da cidade sempre; área e tipo com 3+ fichas no Google (web_contar_no_google); busca, bairro e Aberto agora: fora.
   - Fila total: Poços 3.878, São João 2.233, Vargem 865, Aguaí 532 (7.508); tudo no Google em ~39 semanas em Poços,
     ~23 em São João, ~9 em Vargem e ~6 em Aguaí. Pendências mostra Confirmadas / A confirmar / No Google / Na fila.
   Testado em cópia: onda 1 = 100 por cidade (+ as com dono), onda 3 = 300, pausa congela; sitemaps e noindex conferidos.
   Prioridade das áreas (20/11, pedido do usuário, "sempre"): GOOGLE_PRIORIDADE_AREAS = Veículos, Saúde, Alimentação,
   Casa e construção, Mercados e bebidas, Lojas e varejo, depois as outras; em cada área, das mais conhecidas.
   Efeito (simulado): Poços passa ~4 semanas só com Veículos (872 na fila); Aguaí já tem todas as áreas na 4ª onda.
   Login: volta_web() tirava a pasta /guia-novo em dobro (corrigido) e o Google login foi ligado com as chaves do Acha Fácil;
   falta o usuário cadastrar https://fsadesigners.com.br/guia-novo/google/retorno.php no Google Cloud.
8. ✅ Selo na ficha (20/11, sugestão aprovada; troca o "mostrar pouco", porque com as ondas fichas a confirmar também vão ao Google):
   conferida pela equipe = "✓ Dados conferidos pela equipe em mm/aaaa" na ficha e "✓ Dados conferidos" no cartão; com dono
   continua "✓ Dados confirmados pelo dono"; a confirmar = linha discreta "ℹ️ Informações públicas, ainda não conferidas com
   o comércio. É o dono? Confirme os dados de graça" (link do assumir). confirmado_em entrou no CAMPOS_RESUMO. CSS v=51.
   Santa Rita sem avaliações: o usuário vai rodar o robô no PC (leads_places_api.py) e subir o HTML em /guia-novo/admin/importar.php
   (o importador atualiza a popularidade das fichas que já existem).
9. ✅ Robô não publica sozinho (20/11): robo-enviar.php nunca importa nem liga cidade (nem com importar=1/ligar=1 nem com o
   modo automático); a lista sempre espera "Importar para o app" em /admin/novidades.php, onde o modo automático ficou
   desligado de propósito (o botão só desliga). Novos entram "a confirmar" e só vão ao Google pelas ondas.
   20/11: o teste grátis da API do Google Maps acabou; a importação de Santa Rita (avaliações) ficou para depois (usuário).
10. ⬜ Botão de confirmação rápida: link por WhatsApp para o dono conferir os dados, informar horário e foto, de graça.
11. ⬜ Tela no admin "Confirmar por telefone" (a equipe liga, confere e marca como confirmada).
12. ⬜ App com nome (Localiza Fácil), ícone e servidor novos, MANTENDO o pacote br.com.fsadesigner.achafacil (20/11: app em
    teste na Play Console, 10º dia; trocar o pacote vira outro app e recomeça o teste).
13. 🟡 Cadastros trazidos do Acha Fácil (20/11, pedido do usuário: "admin igual ao do Acha Fácil", opção Cadastros):
    contas (16, mesmos números), sessões do app, aparelhos/avisos, cupons, pedidos/pagamentos das 5 cidades (13 de 16),
    eventos_mp, acessos de dono (7), alterações, oferta da Agnes, avaliações, favoritos, suporte e pedidos de informação
    das 5 cidades. NÃO trazidos de propósito: números (contatos, entradas, aparições, vistas, buscas, registro_admin,
    notificações enviadas). A conta de teste localizafacilapp@gmail.com virou o nº 1000; o admin 4 "Acha Fácil"
    virou "Localiza Fácil" com localizafacilapp@gmail.com (entra no admin pelo Google). Backup antes:
    /var/lib/guia-novo/backups/guia_novo-antes-cadastros.sql.gz. Falta: avisar cada dono/cliente na hora do lançamento.

## Fase 2 — Confirmar fichas (usuário + equipe; contínuo, não trava o lançamento: as ondas já levam fichas ao Google)
14. ⬜ Meta: 50 a 100 fichas confirmadas por cidade, começando pelos mais procurados ("Quem chamar"). Confirmadas entram no Google fora da fila.
15. ⬜ Prioridade: comércios com mais visitas no Acha Fácil, mais avaliações no Google, e os que já pediram informação.
16. ⬜ Cada ficha confirmada com algo próprio: horário, foto, descrição, WhatsApp conferido ou oferta.

## Fase 3 — Lançamento
17. ⬜ Site antigo: noindex em tudo, aviso no topo "O Acha Fácil agora é Localiza Fácil → conheça", link comum.
    SEM redirecionamento (o Google levaria a nota ruim junto).
18. ⬜ Domínio novo no Search Console; enviar só o sitemap.xml principal (ele cresce sozinho com as ondas).
19. ⬜ Publicar o app nas lojas.
20. ⬜ Instagram por cidade desde o 1º dia; cada comerciante confirmado recebe o link da ficha para divulgar.

## Fase 4 — Crescer sem cair de novo
21. ⬜ Conferência semanal: Search Console + registros do servidor.
22. ⬜ Ondas semanais rodando sozinhas (passo 7); pausar em /admin/google.php se o Search Console mostrar queda; aumentar GOOGLE_ONDA se for bem.
23. ⬜ Cidade nova: no máximo 1 ou 2 por mês, e só com as anteriores indo bem no Google.
24. ⬜ Depois de uns 3 meses no ar, decidir se o achafacilapp.com.br sai do ar de vez.

## Nunca fazer
- Subir milhares de fichas de uma vez para o Google (usar as ondas).
- Redirecionar o domínio antigo para o novo.
- Ter as mesmas páginas abertas para o Google nos dois domínios ao mesmo tempo.
- Publicar dados do Google Maps como se fossem nossos.
- Pedir indexação em massa no Search Console.
