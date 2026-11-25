# Localiza Fácil (Plano D) — lista de passos

Projeto em /var/www/guia-novo (pasta e banco com o nome provisório "guia-novo"; marca escolhida: Localiza Fácil). Por enquanto em
https://fsadesigners.com.br/guia-novo/ (pedido do usuário, 20/11), TUDO com noindex (config 'sem_google' + X-Robots-Tag).
Banco guia_novo, config /etc/guia-novo/config.php, dados /var/lib/guia-novo. GitHub: github.com/scaramelo1/localizafacilapp (20/11). Histórico do Acha Fácil: docs/historico-acha-facil.md.
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
2. ✅ Domínio **https://localizafacilapp.com.br** no ar (20/11 ~20h40): DNS (A @ e www -> 177.153.68.212) publicado
   depois de o usuário salvar de novo no registro.br (antes caía no site padrão do Apache, o sistema da PM). ativar.sh
   rodado: vhost localizafacilapp.conf + certbot (vence 18/02/2027, renova sozinho), endereco_publico e google_retorno no
   domínio, fsadesigners.com.br/guia-novo -> 301 para o domínio, www e http -> https://localizafacilapp.com.br, aviso do
   Acha Fácil apontando para o domínio. AINDA FORA DO GOOGLE (sem_google + bloco SEM_GOOGLE no .htaccess) até lancar.sh.
   Backup do Apache: /root/apache-sites-available.antes-localiza.*. Lançamento: servidor/dominio/lancar.sh.
3. ✅ E-mail localizafacilapp@gmail.com (contato_email no config e reserva do pagador no Mercado Pago em inc/ofertas.php) e
   Instagram @localizafacilapp ('instagram' no config) (20/11).
3b. ✅ 21/11 (pedido do usuário): o símbolo oficial passou a ser o COM LUPA (assets/localiza/simbolo-lupa.svg, como o
    ícone antigo de que ele gostava, nas cores novas). Usado em tudo: site (logo, favicon.ico da raiz e de assets 16/32/48,
    favicon.png 192, apple-touch-icon 180 com fundo verde, logo-google-120, imagem de compartilhar) e app (icon, ícone
    adaptável, splash, favicon, logo); foto do Instagram em publico/assets/instagram-perfil.png (1080). Endereços dos
    ícones com ?v=2 (cache de 7 dias). Os sem lupa em /var/lib/guia-novo/backups/icones-sem-lupa-*.
    Marca trocada no site novo (20/11): "Acha Fácil(!)" -> "Localiza Fácil" em 69 arquivos de servidor/ (textos, termos,
    privacidade, e-mails do sistema, títulos, CSVs, logs "localiza-facil ...", sessão LOCALIZAFACIL, MP "LOCALIZAFACIL",
    achafacilapp.com.br -> localizafacilapp.com.br). Ficaram DE PROPÓSITO: pacote br.com.fsadesigner.achafacil e o esquema
    achafacil:// do app (mudam só com o app novo, passo 12). Logo nova: assets/localiza/simbolo.svg (pino verde, círculo
    amarelo, loja verde com toldo) e simbolo-contorno.svg (contorno branco, usada no site porque topo/rodapé são verdes);
    PNGs em publico/assets (logo, favicon, apple-touch-icon com fundo creme, logo-google-120); og-imagem.py com o nome novo.
    Backups: /var/lib/guia-novo/backups/servidor-antes-marca.tgz e assets-acha.tgz.
3c. ✅ Login com Google em projeto próprio (20/11): projeto "Localiza Facil" no Google Cloud (nº 1080734033552), tela de
    login "Localiza Fácil", cliente Web com os retornos https://fsadesigners.com.br/guia-novo/google/retorno.php e
    https://localizafacilapp.com.br/google/retorno.php. ID e chave em /etc/guia-novo/config.php (no lugar das do Acha Fácil).
    20/11 ~20h50: no domínio novo deu redirect_uri_mismatch (o retorno https://localizafacilapp.com.br/google/retorno.php
    não estava cadastrado no cliente). PROVISÓRIO: google_retorno voltou para https://fsadesigners.com.br/guia-novo/google/retorno.php
    (cadastrado e funcionando), que redireciona com os parâmetros para o domínio. Quando o usuário cadastrar o do domínio,
    trocar google_retorno no /etc/guia-novo/config.php.
    20/11 ~20h50: teste direto no Google mostrou que o cliente só aceitava https://localizafacilapp.com.br/google/retorno.php/
    (COM barra; o da FSA tinha saído). google_retorno = esse (o Apache roda retorno.php com a barra). Pedido ao usuário:
    deixar no cliente os 3 (FSA, domínio sem barra e com barra) para nenhuma troca quebrar o login.
    COMO TESTAR um retorno sem logar: curl -A Mozilla -o /dev/null -w '%{redirect_url}' 'https://accounts.google.com/o/oauth2/v2/auth?
    client_id=<id>&redirect_uri=<uri codificado>&response_type=code&scope=openid+email+profile&state=t' -> se contiver
    oauth/error = recusado; signin/identifier = aceito. (Só ver o 302 da 1ª resposta não basta.)
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
   20/11 (opção B, escolhida pelo usuário depois de explicadas A e B): cada onda de 100 é DIVIDIDA entre as áreas com
   GOOGLE_ONDA_PESOS = Veículos 25, Saúde 20, Alimentação 15, Casa 12, Mercados 10, Lojas 10, outras 8; área que esgota
   passa as vagas para as próximas (google_dividir). Cortes por cidade e área guardados 1 h em $DADOS/cache/google-cortes.json.
   Simulado: onda 1 = 25/20/15/12/10/10/8 em Poços e Aguaí; onda 10 em Aguaí = a fila inteira (532).
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
10. ✅ Link de confirmação para o dono (20/11): mudança 036 (confirmacao_links: hash do token, aberto/usado/resultado;
    confirmar_fila). inc/confirmacao.php: confirmacao_criar_link() (token 16 bytes, vale 60 dias), mensagem pronta de
    WhatsApp (WhatsApp ou celular do comércio). publico/confirmar.php?c=...: sem conta; "Está tudo certo" confirma na hora
    (confirmado_por = 'dono'); "Corrija aqui" (telefone, WhatsApp, endereço, bairro, horário) vira alteração pendente sem
    acesso de dono ("Link de confirmação" em Pendências) e também confirma; avisa os admins no celular; convite para os planos.
    Fotos e descrição continuam no plano (regra do negócio). Botão "💬 Mandar link de confirmação ao dono" em
    /admin/comercio.php. Selo: "Dados confirmados pelo dono em mm/aaaa" (ficha) e "Confirmado pelo dono" (cartão).
11. ✅ /admin/confirmar.php "✅ Confirmar fichas" (20/11, no menu Comércios): escolhe a cidade, mostra um comércio por vez
    (fila: a confirmar com contato, ordem GOOGLE_PRIORIDADE_AREAS e mais conhecidos), barra da meta (META_CONFIRMADAS 50),
    Ligar/WhatsApp/Mapa/Ver ficha/Editar tudo, roteiro da ligação, correção rápida (telefone, WhatsApp, endereço, bairro,
    horário; campo que não veio não muda) e botões Confirmado / Mandar link (volta em 3 dias) / Não atende (volta amanhã) /
    Pular (1 h) / Fechou (status pausado, sai da fila). Testado com fichas de Aguaí; dados de teste desfeitos.
11b. ✅ Tags que recolhem (21/11, pedido do usuário: no celular a lista de tipos ficava enorme, ex. Saúde em Poços com 61):
    web_chips() em inc/web.php mostra as 8 primeiras (as com mais comércios) e o botãozinho "+ Mais tipos (N restantes)" / "− Menos tipos"
    (details/summary, sem JavaScript; todos os links continuam no HTML). Usado nos tipos da área, no "Veja também" e no
    "Mais procurados" da cidade. CSS v=53 (sem a caixa e o "+" amarelo do details geral do site, pedido do usuário).
12. 🟡 App passado para o Localiza Fácil no código (20/11, pedido do usuário): app.json name "Localiza Fácil", versão
    1.14.0, texto da localização; 14 arquivos de src/ ("Acha Fácil(!)" -> "Localiza Fácil", Abertura e Cabecalho com o
    nome novo; Marca 21 px e traço tamanho*5), todos os endereços -> https://localizafacilapp.com.br; ícones novos
    (icon.png fundo verde + símbolo com contorno, android-icon-foreground com margem do ícone adaptável, splash-icon,
    favicon, logo) a partir de assets/localiza/simbolo-contorno.svg; antigos em /var/lib/guia-novo/backups/app-icones-acha.
    MANTIDOS: pacote br.com.fsadesigner.achafacil, slug acha-facil e projectId do EAS (mesmo app na Play, sem perder o
    teste) e o scheme achafacil:// (o servidor aceita). Falta: gerar o build no PC DEPOIS do domínio no ar (o app chama
    https://localizafacilapp.com.br/api) e trocar nome/ícone/descrição na Play Console.
    21/11 (pedido "gerar o apk e aab"): build no PC preparado — .gitignore completo (credentials.json, credentials/,
    saida/, /android, *.jks fora do git; antes só ignorava node_modules), gerar-local.ps1 gera saida\localiza-facil-*.aab/.apk,
    olha os .aab do ..\acha-facil\saida para o versionCode e publica o APK em /var/www/guia-novo/servidor/publicar-apk.sh.
    21/11 (pedido "mude os nomes e as informações" da ficha da Play): imagens novas em assets/loja — destaque-1024x500.png
    (destaque.py, símbolo com lupa), icone-512.png e capturas/ (telas.py com "Localiza Fácil" e "Mais de 18 mil comércios
    em 5 cidades", dados da API de 21/11); textos e links da ficha em docs/lojas.md seções 1 e 2.
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
17. ✅ Acha Fácil fechado (20/11, pedido do usuário): noindex em tudo; guia público (inicial, cidades, fichas, sitemaps,
    incluir, APK) virou a página "O Acha Fácil agora é Localiza Fácil → conheça" (acha-facil/servidor/publico/fechado.php,
    link comum, SEM redirecionar; inicial 200, resto 410). Só admins usam o admin; api/, google/, painel/, planos,
    termos e privacidade continuam (o app em teste ainda usa). Passo 120 do docs do acha-facil. O ativar.sh troca o link
    do aviso para https://localizafacilapp.com.br/.
18. ✅ LANÇADO em 20/11/2026 ~21h (lancar.sh, pedido do usuário): sem_google false, bloco SEM_GOOGLE tirado do .htaccess,
    robots.txt com Disallow /confirmar.php. 1ª onda começou 20/11 ($DADOS/google-ondas.json): no sitemap São João 102 fichas
    + 13 categorias, Poços 101 + 15, Vargem 101 + 13, Aguaí 100 + 13, Santa Rita 16 + 3, + 5 páginas de cidade (~490 URLs).
    Conferido: inicial, cidade e fichas da onda sem noindex; ficha fora da onda com noindex; admin e confirmar.php fora.
    Search Console (20/11): propriedade "Prefixo do URL" https://localizafacilapp.com.br/ (conta localizafacilapp@gmail.com),
    verificada pelo ARQUIVO publico/google1e88fb9a9da291bf.html (NÃO remover); sitemap.xml enviado e "Processado"
    (Googlebot leu às 20:48; os das cidades vêm depois).
    Favicon (21/11): o publico/favicon.ico da RAIZ ainda era o do Acha Fácil (o Googlebot-Image pegou em 20/11 21:04);
    trocado pelo novo (16/32/48) e favicon.png 192x192 com sizes (o Google pede múltiplo de 48). Backup do antigo em backups/.
    Cron (20/11): /etc/cron.d/guia-novo roda google-visitas.php de hora em hora (minuto 23) para /admin/google.php
    (antes mostrava "nunca" porque não havia cron). Avisos e relatório mensal continuam no cron do acha-facil.
19. ⬜ Publicar o app nas lojas.
20. ⬜ Instagram por cidade desde o 1º dia; cada comerciante confirmado recebe o link da ficha para divulgar.

## Fase 4 — Crescer sem cair de novo
21. ⬜ Conferência semanal: Search Console + registros do servidor.
22. ⬜ Ondas semanais rodando sozinhas (passo 7); pausar em /admin/google.php se o Search Console mostrar queda; aumentar GOOGLE_ONDA se for bem.
23. ⬜ Cidade nova: no máximo 1 ou 2 por mês, e só com as anteriores indo bem no Google.
24. ⬜ Depois de uns 3 meses no ar, decidir se o achafacilapp.com.br sai do ar de vez.
25. ✅ E-mails pelo site (21/11, pedido do usuário; escolheu Gmail): inc/email.php manda pelo localizafacilapp@gmail.com
    (SMTP smtp.gmail.com:465 com senha de app; config smtp_usuario/smtp_senha, opcional email_limite_dia = 450). Tudo
    entra na fila (mudança 037: emails, emails_lotes, emails_descadastro) e enviar-emails.php (cron de minuto em minuto,
    www-data, log /var/log/guia-novo-emails.log) manda 20 por minuto até 450 em 24 h. /admin/emails.php (Pessoas > E-mails):
    escrever para donos (pedidos com e-mail, por cidade), contas do app ou um e-mail; {nome}; teste para si; histórico.
    Automáticos: plano pago (Básico e 1ª confirmação da assinatura, com o link da assinatura.php?c= = acesso ao painel),
    alteração aprovada/recusada (com motivo), resposta do suporte. /contato.php (Fale conosco no rodapé, sem login, 3 por
    IP por hora, Responder vai para quem escreveu). /descadastrar.php (link e List-Unsubscribe one-click; só para os da
    equipe). Senha de app posta pelo usuário em 21/11 (precisou ligar a verificação em duas etapas antes); 1º teste enviado 21/11 10:08.
    21/11: lista "E-mails cadastrados" no /admin/emails.php (donos + contas do app, um por e-mail, com comércio, desde
    quando e botão Escrever que preenche "Um e-mail só") e ?baixar=csv (planilha com ; e BOM para o Excel).
    21/11: caixinhas na lista (e "marcar todos") ligadas ao formulário (atributo form): público "escolhidos" = só e-mails
    que estão na lista e recebem (endereço inventado no POST é ignorado).
26. ✅ Avisos no celular voltando (21/11, "o site não está mandando notificação"): expo_enviar() mandava "data": [] quando o
    aviso não tinha dados (ex.: admin > Pessoas > avisar) e a Expo recusava o lote inteiro com 400. Agora vai {}.
    Teste para a conta 1: dos 3 aparelhos, 1 recebeu; 2 são de instalações antigas (recibo DeviceNotRegistered do FCM),
    que o código só tira quando o erro vem na hora, não no recibo. Limpeza pelos recibos (cron) ficou para o usuário decidir.
27. ✅ Páginas visitadas pelo Google, uma por uma (21/11, pedido do usuário): google-visitas.php (cron de hora em hora)
    guarda em visitas.json 'paginas' => caminho => primeira/última visita, vezes e código HTTP (só conta visita depois da
    última guardada, porque relê o registro); fim do /admin/google.php com abas Fichas / Cidades e páginas / Mapas / Arquivos.
28. ✅ Origem "Site: veio do Acha Fácil" (21/11, pedido do usuário): origem_do_referer() devolve 'acha_facil' para
    achafacilapp.com.br (antes caía em outros_sites); aparece no filtro de /admin/entradas.php e nos números. As 6
    entradas de 21/11 (todas pelo aviso do Acha Fácil, conferidas no registro do Apache) foram passadas para acha_facil.
29. ✅ Buscas só de gente de fora (21/11, pedido do usuário): as 4 buscas de 21/11 eram 3 do teste automático da Google
    Play (108.177.24.188, okhttp) e 1 de um admin pelo site; apagadas. e_robo() agora pega app (okhttp) vindo de IPs do
    Google (vale para buscas, visitas e toques); busca_da_equipe() (inc/busca.php) não grava busca de admin (conta Google
    de admin no app ou no site, ou sessão do admin aberta).

30. ✅ Robô no Localiza Fácil (21/11, pedido do usuário): a cópia em scripts/robo apontava para achafacilapp.com.br.
    Agora rodar-cidades.ps1 -Existentes roda só as 5 cidades do Localiza (pasta D:\robo-listas-localiza, separada da
    do Acha Fácil); enviar-listas.ps1 e robo-config.exemplo.json usam localizafacilapp.com.br. Lista sobe por
    /admin/importar.php (cai em Novidades, nunca publica sozinha); chave do robô (robo-enviar.php) ainda não gerada.
    Objetivo: melhorar as fichas que já existem, sem cidade nova.
31. ✅ Números sem robôs e sem a equipe (21/11, pedido do usuário: "retirando todas as entradas dos admins"). No registro do
    Apache, das 82 entradas de 21/11, 70 eram robôs (varreduras atrás de .env/wp-includes, servidores Amazon/Hetzner,
    rastreador do Facebook) ou admins. As 17 fichas abertas, os 3 contatos e quase todas as aparições até 9h29 eram do
    teste da Google Play (108.177.24.188), e as visitas "pelo Google" das 10h21 e 18h35 eram de admin.
    - Entrada no site só conta quando a página abre num navegador de verdade: registrar_entrada_site() põe um <script>
      antes de </body> (ob_start) que chama api/entrada.php com os dados assinados (entrada_sinal, vale 2 h). Ficha aberta
      no site também só conta pelo script (visita_adiada_js para toda visita, não só pré-carregamento). Pré-carregamento
      do Chrome só conta quando a pessoa abre a página (pre = 2); pre = 1 não aparece mais.
    - nao_contar() = e_robo() || da_equipe(), usado em entradas, visualizações, toques, aparições e buscas
      (busca_da_equipe() agora chama da_equipe()). Admin = sessão do /admin, conta de admin no app ou no site, cookie
      lf_equipe (posto em toda página do /admin por exigir_admin -> marcar_equipe(), vale 1 ano) ou IP que abriu o /admin
      nas últimas 3 h (dados_dir/equipe-ips.json; o IP do app não é guardado porque a rede do celular é dividida).
    - Limpeza de 21/11 (cópia antes em scratchpad/backup-numeros-21-11.sql): ficaram 10 entradas (4 Acha Fácil, 2 direto,
      4 pré-carregamentos do Google), 1 visualização (Agnes Veterinária, início do app) e aparições do início estimadas
      (4 de 15 aberturas eram de gente). 20/11 não foi mexido.
32. ✅ Números e "De onde vêm" numa página só, mais fácil de ler (21/11, pedido do usuário). /admin/numeros.php refeito:
    filtros em botões (período Hoje/7/30/90, cidade, origem: Tudo/App/Site/Google/outra) que valem para a página toda;
    4 cartões (chegaram ao site, apareceram em listas, fichas abertas, contatos) com seta e % contra o período anterior e
    linha de tendência; "O caminho até o comércio" separado em app (apareceu -> abriu -> chamou) e site (chegou -> abriu
    -> chamou); gráfico dia a dia (cada dia abre entradas-dia.php; "ver em tabela") ou hora a hora em "Hoje" com ontem
    ao lado; de onde vêm as fichas abertas (app x site) e como chegaram ao site, com clique que filtra a página;
    cidades, áreas, 10 comércios mais abertos, tipos de contato, horários de pico, páginas de entrada; no fim a tabela
    de todas as fichas (área, tipo, plano, só abertas, CSV, páginas) e "Como ler estes números". /admin/entradas.php
    redireciona para cá; menu Números > "Números e de onde vêm".
33. ✅ Aviso de avaliação (21/11, pedido do usuário). inc/avaliacao-aviso.php, chamado por api/avaliar.php (app e site usam
    o mesmo endereço): o dono recebe aviso no celular (contas do app dos pedidos do comércio: pago, painel liberado ou
    cadastro aprovado) e e-mail (o do pedido ou o da conta Google), com estrelas, comentário e link da ficha; os admins
    recebem aviso no celular, que abre a ficha no app. Avaliação editada só avisa se mudou nota ou comentário, e a
    mesma pessoa na mesma ficha só avisa de novo depois de 10 min (dados_dir/avaliacoes-avisadas.json). O dono
    avaliando a própria ficha não recebe. Ficha sem dono: o aviso dos admins diz "comércio sem dono cadastrado".
34. ✅ Estrela nova aparecendo nas listas do site na hora (21/11, "avaliação nova não aparece na pesquisa"). O banco e a
    busca (app e site) já estavam certos; as páginas de cidade e área do site ficam prontas por até 1 h (web_cache) e
    a avaliação não refazia. recalcular_nota() agora chama web_cache_limpar(). Achado junto: a Studio Movimento Fit
    está duplicada (4557 com foto e avaliação; 45805 sem foto nem telefone, importada em 11/11), as duas aparecem na busca.
35. ✅ Botão "Compartilhar" no site no lugar de "Compartilhar no WhatsApp" (21/11, pedido do usuário). No celular abre o
    menu do próprio celular com a imagem da ficha no formato Stories (publico/story.php + servidor/story-imagem.py,
    1080x1920 JPG com foto, nome, tipo, cidade, estrelas e o site; guardada em dados_dir/story/ e refeita quando a ficha
    muda), então aparecem WhatsApp (conversa ou status), Instagram (Direct ou Stories) e os outros apps; a legenda leva
    "Olha esse lugar" + link. No computador (ou se o celular recusar) abre um menu: WhatsApp, Facebook, imagem para
    Stories e Status, copiar link. Conta como 'compartilhar' só quando compartilha ou escolhe uma opção.
    App: já abria o menu do celular, mas só com texto (Stories do Instagram precisa de imagem): falta versão nova.
36. ✅ Compartilhar com imagem também no app + link copiado para o Stories (21/11, pedido do usuário).
    - Instagram não deixa nenhum site/app pôr link clicável no Stories sozinho (só o adesivo "Link", que a pessoa põe).
      Então, ao compartilhar com imagem, o link vai para a área de transferência e aparece a dica "toque no adesivo
      Link e cole" (site: aviso verde embaixo; app: aviso na tela). WhatsApp com imagem também perde o texto: cola na legenda.
    - App 1.15.0 (src/compartilhar.ts): o botão Compartilhar pergunta "Com imagem (Stories e Status)" ou "Só o link
      (conversa)". Com imagem baixa story.php e abre o menu do celular (expo-sharing). FALTA no PC:
      npx expo install expo-sharing expo-file-system expo-clipboard, depois gerar e testar o app.
37. ✅ Contagem de entradas: volta do login do Google não conta mais como chegada nova "pelo Google" (accounts.google.com
    era lido como Google e a mesma pessoa contava 2x ao entrar para avaliar); story.php recusa robôs e está no robots.txt
    (o ClaudeBot pedia centenas de imagens, cada uma roda o Python). Limpeza de 21/11: tiradas 2 duplicadas do login e 1
    robô da Amazon (54.70.53.60, navegador automático que roda JavaScript); apagadas as 66 imagens geradas por robô.
38. ✅ "Não achou o seu negócio? Inclua o seu negócio de graça" no topo da página inicial, logo abaixo da busca e de
    "Grátis. Não precisa criar conta" (21/11, pedido do usuário; antes só no fim da página e na busca). Leva a /incluir.php.
39. ✅ Topo do site no celular sem cobrir a marca (21/11, pedido do usuário; com admin logado "Baixar o app" ficava por cima
    de "Localiza Fácil"): até 760 px a marca fica em duas linhas (Localiza / Fácil), "Baixar o app" vira "📲 App" e
    "Administração" vira só ⚙️ (texto inteiro no computador). Tirado "Grátis. Não precisa criar conta para procurar." da inicial.
40. ✅ E-mail do comércio (22/11, pedido do usuário, opções 1 e 2). Nenhuma das 18.315 fichas tinha e-mail (o Google
    Maps não fornece). Campo profissionais.email agora: em "Inclua o seu negócio" (opcional), no painel do dono (também
    no Grátis, passa pela conferência como os outros) e na edição do admin (precisava, senão salvar apagava). Validado
    com email_valido(). Na ficha: botão "E-mail" no site (mailto, assunto "Contato pelo Localiza Fácil") e linha
    "E-mail" no app 1.15.0; o toque conta como contato 'email' (mudança 038 no enum de contatos, já aplicada).
41. ✅ iFood e 99Food nas áreas de comida (22/11, pedido do usuário). Mudança 039 (profissionais.ifood e food99, contatos
    'ifood' e 'food99'), já aplicada. Só Alimentação e Mercados e bebidas (AREAS_COMIDA, e_area_comida()). Campos em
    "Inclua o seu negócio" (aparecem ao escolher área de comida), no painel do dono (também no Grátis, com conferência) e
    no admin. link_delivery() aceita só ifood.com.br / 99app.com / 99food, tira o rastreio (?utm, ?share_media) e recusa
    gestor de pedidos e página inicial. Ficha: botões "Pedir no iFood" (vermelho) e "Pedir no 99Food" (amarelo) no
    site e linhas no app 1.15.0; o link abre o app deles se estiver instalado; o toque conta como contato.
    Os 24 comércios que tinham iFood/99 no "site" (vindo do Google): 22 passaram para o campo novo, 2 tiveram o site
    tirado (gestor de pedidos do iFood; só "99app.com"); 33056 "00 Marmitas" ficou como estava (link é da Mr Pincho
    Espetaria). importar-leads.php faz o mesmo sozinho daqui para frente.
42. 🧪 Cardápio em aba (22/11, TESTE para o usuário aprovar). Mudança 040 (profissionais.cardapio, JSON com seções e itens:
    nome, desc, preco, destaque), já aplicada. Ficha do site: abas "Informações | 🍔 Cardápio (N itens)", seções em
    pílulas, preço com pontilhado, selo "Mais pedido", botões de pedir (WhatsApp, iFood, 99Food); #cardapio abre direto.
    Prévia da equipe: ficha fora do app (pausada) abre só para admin (web_ficha_previa, noindex). Ficha de teste 103616
    "Lanchonete Exemplo (teste do cardápio)", pausada, São João:
    https://localizafacilapp.com.br/sao-joao-da-boa-vista/c/lanchonete-exemplo-teste-do-cardapio-103616#cardapio
    Ainda NÃO tem: tela para o dono/admin montar o cardápio, app, plano. Se o usuário não gostar: apagar a ficha 103616.
43. 🧪 Catálogo por segmento (22/11, TESTE, "como adequar a cada segmento"). O cardápio virou catálogo que muda de cara
    pelo tipo/área (CATALOGO_MODELOS, catalogo_modelo() em inc/web.php): comida 🍔 Cardápio, roupas 👕 Produtos, mercado
    🛒 Ofertas (com validade), bebidas 🍺 Bebidas (aviso +18), autopeças 🔧 Peças (aviso de compatibilidade), farmácia
    💊 Produtos (não anuncia medicamentos: só serviços, higiene e beleza), ótica 👓 Produtos (lentes pela receita).
    Itens ganharam: preco_de (de/por), a_partir, unidade (/kg, /fardo), etiquetas (tamanhos, cores, carros); preço 0 =
    "Grátis". Fichas de teste pausadas (prévia só da equipe), São João: 103616 lanchonete, 103617 moda, 103618
    supermercado, 103619 adega, 103620 autopeças, 103621 farmácia, 103622 ótica. Se não aprovar: apagar 103616–103622.
44. ✅ Ajustes do catálogo (22/11, pedido do usuário): só Destaque em dia tem catálogo; a aba só aparece com pelo menos
    1 item; tirada a contagem de itens da aba. Na prévia da equipe (fichas de teste, sem Destaque) a aba aparece com o
    aviso "na ficha de verdade só para Destaque". Ainda falta a tela para o dono montar o catálogo e o app.
45. ✅ Catálogo completo (22/11, pedido do usuário: "pode executar todos os passos"; lembrete semanal por celular e e-mail).
    inc/catalogo.php (modelos por segmento, ler/salvar/indexar, API, busca, pedidos por item); mudança 041
    (catalogo_busca, catalogo_itens, catalogo_em; tabela catalogo_pedidos), já aplicada.
    1) Busca acha itens do catálogo (só Destaque): BUSCA_CAMPO_CATALOGO; cartão mostra "Tem: X-Tudo" (site "Cardápio: X-Tudo").
    2) "💬 Pedir" em cada item: WhatsApp já escrito "Vi no Localiza Fácil: X (R$ Y). Ainda tem?"; api/item.php conta o
       item (catalogo_pedidos) e um toque no WhatsApp; "Mais pedidos pelo WhatsApp (30 dias)" no editor do dono.
    3) Foto opcional por item (600 px, quadrada). 4) "Atualizado em dd/mm" no topo.
    5) Editor /painel/catalogo.php (dono Destaque; admin por ?id= ou "catálogo" em Comércios > editar): + seção, + item,
       subir/descer, apagar, campos por segmento (tamanhos/cores, serve em carros, unidade, validade). Publica na hora.
    6) Primeira vez começa com as seções sugeridas do segmento. 7) "Esgotado" (fica riscado, sem Pedir).
    8) "Ofertas válidas até" (catálogo) e "Oferta até" (item): vencido some da ficha na hora e da busca no
       catalogo-diario.php (cron 3h17). 9) catalogo-semanal.php (cron segunda 10h23): Destaque com dono recebe aviso no
       celular + e-mail ("Seu cardápio está em dia?" / convite para montar); --teste só lista.
    10) Selo "🍔 Cardápio" no cartão (site e app) e, sem catálogo nos segmentos que costumam ter, "Cardápio 🔒 Pedir"
       (pedidos_info tipo 'catalogo'; o dono vê "X pessoas pediram para ver seus produtos").
    App 1.15.0: abas Informações | Catálogo (src/componentes/Catalogo.tsx), Pedir por item, selo e "Tem:" no cartão,
    trancado do catálogo. Sintaxe conferida com esbuild; falta o typecheck e o teste no celular (PC).
46. ✅ Testes feitos do próprio servidor não contam (22/11): as 40 aparições "busca" de 22/11 eram um teste meu
    (curl fingindo celular, 02:08) e a busca "Lanchonete Exemplo"; apagadas. e_robo() agora recusa o IP do próprio
    servidor (SERVER_ADDR, 127.0.0.1): vale para entradas, aberturas, toques, aparições e buscas.
47. 🧪 Pedido de vários itens pelo WhatsApp, "tipo iFood" (22/11, TESTE na ficha 103616). Só no site por enquanto e só
    nos segmentos com 'carrinho' (comida, mercado, bebidas). Cada item: "+ Adicionar" e depois − n +; barra fixa
    "🛒 Ver pedido · N itens · R$ X"; janela com os itens (muda quantidade), total, retirar ou entregar (endereço
    obrigatório), pagamento (Pix, dinheiro com troco, cartão), nome e observação; "Enviar pedido pelo WhatsApp" abre o
    WhatsApp do comércio com a lista, o total (sem a taxa de entrega) e os dados. Carrinho guardado no celular por
    ficha; nome e endereço guardados para o próximo pedido. Item sem preço fixo ("a partir de") vai como "a confirmar".
    api/item.php recebe a lista inteira (cada item conta; 1 toque no WhatsApp). Na prévia sem WhatsApp abre
    wa.me/ para escolher o contato. Testado com jsdom (fluxo completo, mensagem, contagem). Falta: app e aprovação.
48. ✅ Um pedido por loja (22/11, pedido do usuário). O carrinho é um só no celular (lf_carrinho, guarda a loja). Com
    pedido aberto numa loja, a outra mostra "Você tem um pedido aberto em X" e, ao tentar adicionar, a janela
    "Limpar o pedido?" (Limpar pedido e adicionar / Voltar ao pedido de X / Cancelar); o item só entra depois de
    limpar. Ficha de teste 103616 com o WhatsApp do usuário (19992302598) para testar o pedido de verdade.
    Testado com jsdom (duas lojas: aviso, janela, cancelar, limpar e adicionar, 2º item direto).
49. ✅ Pedido de vários itens no app (22/11, pedido do usuário: "ficou perfeito, pode implantar no app"), app 1.15.0.
    src/carrinho.ts (um pedido por vez, de uma loja, guardado no celular; mensagem igual à do site, sem emoji),
    src/componentes/Pedido.tsx (barra "Ver pedido - N itens - R$ X" e janela: itens com - +, total, retirar ou entregar,
    pagamento Pix/Dinheiro com troco/Cartão, nome, observação, "Enviar pedido pelo WhatsApp", esvaziar), Catalogo.tsx
    ("Adicionar" e - n + em cada item; trocar de loja pergunta "Limpar o pedido?": Cancelar / Voltar ao pedido / Limpar e
    adicionar; aviso de pedido aberto em outra loja). API: catalogo.carrinho, catalogo.whatsapp, item.precoNum;
    registrarPedido() conta todos os itens. Prévia da equipe também no app: api/profissional.php abre ficha fora do app
    para conta de admin (catálogo como Destaque). Administração do app: "Abrir ficha pelo número" (ex.: 103616).
    Testes: sintaxe (esbuild), lógica do pedido em node, API como visitante (404) e admin (catálogo com carrinho).
50. ✅ Catálogo/cardápio no plano Destaque (22/11): planos.php e app (planos.tsx) listam cardápio/catálogo com preços e
    fotos, pedido de vários itens no WhatsApp (comida, mercado, bebidas) e itens na busca; termos.php ganhou o item do
    catálogo (venda é do comércio; Destaque vencido = catálogo some, mas fica guardado). E-mail modelo para lanchonetes
    (enviar por Admin > E-mails > Um e-mail só).
    Ficha de teste 103623 "Lanchonete Exemplo 2 (teste de pedidos)": cópia da 103616 (mesmo cardápio), pausada, só
    admin vê, pedidos para o WhatsApp 19992799811. NÃO APAGAR: fica pausada para futuros testes (pedido do usuário).
51. ✅ Aviso de avaliação (22/11): conferido que o aviso da avaliação da Sr Burgers saiu (e-mail ao dono enviado; aviso
    aos admins disparado). 2 aparelhos mortos do admin removidos. Registro de cada aviso no celular em
    /var/lib/guia-novo/avisos-enviados.log (últimos 500).
52. ✅ Robô com Chrome antigo (22/11): 3 entradas de 22/11 (10:58, 11:16, 11:30; servidores OVH, Chrome 102 no Windows,
    entrada mandada de outro IP) apagadas; e_robo() trata Chrome/Edge abaixo do 120 como robô.
53. ✅ Limpeza dos números (22/11, pedido do usuário): 20/11 (lançamento) apagado inteiro (robôs de domínio novo, testes
    e admin; nenhum contato real); 21/11 17:26 (4 pré-carregamentos do Chrome vindos do Google) e 22/11 13:24 (robô Hetzner
    com Chrome 131) apagados; limite subiu para Chrome/Edge < 140. Cópia antes: /var/lib/guia-novo/backup-numeros-20261122-1818.sql.

## Nunca fazer
- Subir milhares de fichas de uma vez para o Google (usar as ondas).
- Redirecionar o domínio antigo para o novo.
- Ter as mesmas páginas abertas para o Google nos dois domínios ao mesmo tempo.
- Publicar dados do Google Maps como se fossem nossos.
- Pedir indexação em massa no Search Console.
54. ✅ Nome da marca "Localiza Fácil App" (22/11, pedido do usuário). Site: títulos das páginas ("... | Localiza Fácil App"),
    título da inicial, WebSite/Organization para o Google (nome "Localiza Fácil App", alternativo "Localiza Fácil"),
    og:site_name e application-name, 1º item da trilha, rodapé e e-mails (remetente e rodapé). O topo continua
    "Localiza Fácil" (cabe no celular) e os textos corridos ("no Localiza Fácil") ficaram como estavam. App: o nome embaixo
    do ícone continua "Localiza Fácil" (app.json; o Android corta nome longo); na Play o nome é "Localiza Fácil App" (docs/lojas.md).
55. ✅ Ficha com mais conteúdo próprio (22/11, pedido do usuário: subir a nota da ficha de 4 para 6–7; itens A, B, C, D, F e E).
    A) Menos texto repetido: caixa "Este comércio é seu?" curta (dados públicos · informação errada · tirar numa linha só),
       "Faltam nesta ficha" mais curto, avaliar sem login em 1 frase, sem o "Leve o Localiza Fácil no bolso" na ficha.
    B) Vizinhos de áreas diferentes (web_perto, inc/web.php): com ponto exato (geo_origem 'google', 3.078 fichas) "Perto de X"
       com a distância (até 1,5 km); senão "Também na Av. X" com os da mesma rua pelo número mais perto (web_rua_numero).
       ATENÇÃO: geo_origem 'rua' é UM ponto para a rua inteira (11.633 fichas em 1.812 pontos): nunca usar para distância.
    C) "Localização": "Fica no bairro X, a 2,5 km do centro de Y. No bairro, o Localiza Fácil tem N comércios" (km só com
       ponto exato; centro = cidades.lat/lng). Sem horário, a coluna Horário some ("Não informado" repetia o quadro Faltam).
    D) Descrição para o Google só com o que a ficha tem (bairro, endereço, WhatsApp/telefone, "Veja o horário" só com horário).
    F) /admin/confirmar.php: fila começa pelas fichas que já estão no Google (selo "🌐 já está no Google").
    E) "Sobre (do site do comércio)": mudança 042 (profissionais.sobre_site/sobre_site_url, tabela sobre_site), já aplicada.
       servidor/sobre-do-site.php lê a página inicial do site próprio (meta description), respeita robots.txt, pula
       Facebook/Instagram/Linktree/iFood/gov.br etc., grava como pendente. Admin > Comércios > "🌐 Sobre pelo site"
       (/admin/sobre-site.php): aprovar (corrige antes), recusar, tirar da ficha; aba "Sites que não abrem" com
       "Tirar o site da ficha". Descrição do dono tem prioridade. 1ª leitura: as 242 fichas no Google com site (22/11).
       Falta: mostrar o sobre_site no app.
56. ✅ Revisão dos "Sobre pelo site" (22/11, pedido do usuário: aprovar todos e resumir os grandes no tamanho do exemplo
    da Perfumaria Emy, ~180 letras). Dos 201 pendentes: 125 aprovados (resumidos, média 95 letras, máx. 178, só com o que
    o site diz; tirados "Agende agora", "Confira!") e 76 recusados com o motivo: texto da plataforma (MenuDino, Cardápio
    Web, Meu Carrinho, encurtador, software), de outra empresa (CITUR na cachoeira/café/mercado, Partage na Polo Wear,
    Oral Lite na NioClinic, Germânica de Campinas), texto de rede igual em várias fichas (Drogal 7, San Michel 5, Magalu 5,
    Ipiranga 3, modelo "Supermercados Online" em 4 mercados...) e fichas duplicadas (Forte Mix 1478, Art Pel 34962,
    Cervejaria Gonçalves 34479, Pizza na Roça 35132: aprovado só na outra). Registro em registro_admin (admin NULL).
    Cópia antes: /var/lib/guia-novo/backups/antes-sobre-site-20261122.sql. Descrição do Google corta o Sobre em palavra
    inteira (web_corte_palavra, 150 letras).
57. ✅ Recusados podem ser aprovados (22/11, pedido do usuário: "alguns estão certos, vou verificar todos"). Em
    /admin/sobre-site.php, aba Recusados: mostra o motivo, o texto em caixa editável e "✅ Aprovar" (vai para a ficha na
    hora). Todas as abas com páginas de 100 (?pagina=), e aprovar/recusar volta para a mesma página.
58. ✅ Aprovar sem recarregar (22/11, pedido do usuário: "está demorando atualizando toda hora"). /admin/sobre-site.php:
    Aprovar, Recusar, Tirar da ficha e Tirar o site mandam por fetch (ajax=1, resposta JSON em sobre_resposta()); o
    quadro fica verde/cinza com o resultado e some em 1,5 s; o número da aba desce. Ctrl+Enter na caixa = Aprovar.
    Sem JavaScript continua funcionando como antes. Tirado o web_cache_limpar() de cada clique (a ficha não fica em
    cache e as listas não mostram o Sobre).
59. ✅ 2ª revisão dos "Sobre pelo site" (23/11, pedido do usuário: aprovar todos e resumir os grandes no tamanho da Emy).
    O usuário rodou o robô em todas as fichas com site (--todas): 1.214 pendentes. 810 aprovados (783 resumidos, média
    72 letras, máx. 149; 27 mantidos como estavam) e 404 recusados com motivo: plataforma/site de terceiro (MenuDino,
    Brendi, Cardápio Web, encurtadores, Cliquei Achei, Trinks...), texto genérico de rede igual em várias fichas
    (Correios, Drogal, Ipiranga, Pague Menos, Cacau Show, igrejas nacionais...), outra empresa/cidade, sem informação
    (menu, aviso de site novo) e fichas duplicadas. Total no ar: 1.036 fichas com Sobre. Recusados podem ser aprovados
    na aba Recusados (passo 57). Cópia antes: /var/lib/guia-novo/backups/antes-sobre-site-2-20261123.sql.gz.
60. ✅ App 1.16.0 com o conteúdo próprio da ficha (23/11, pedido do usuário; a 1.15.0 já foi testada por ele).
    api/profissional.php manda sobreSite/sobreSiteDe (Sobre do site, só sem descrição do dono), localizacao (web_local_texto)
    e perto {titulo, itens: id, nome, tipo, icone, cor, onde} (web_perto: "a 150 m" ou "nº 1935"). Tela da ficha
    (src/app/profissional/[id].tsx): seção Sobre com "Do site do comércio: x", Localização e a lista de vizinhos (toque
    abre a ficha, origem 'perto'); o "Pedir detalhes" some quando tem Sobre do site. app.json 1.16.0. Apps antigos ignoram.
    Não deu para conferir a sintaxe com esbuild na VPS (download bloqueado): conferir no PC com npx tsc --noEmit.
61. ✅ Robô de horário pelo site (23/11, pedido do usuário: "pode criar o robô que eu já rodo"). Mudança 043 (tabela
    horario_site), já aplicada. inc/site-robo.php (comum aos dois robôs: baixar, robots.txt, limpar; e horario_extrair:
    schema.org openingHoursSpecification/openingHours ou texto "Segunda a sexta, das 8h às 18h", "SEG-SEX 08:00 - 18:00",
    "Domingo fechado", almoço "8h às 12h e 14h às 18h" vira 8h às 18h, dia a dia "Seg: 11h-23h | Ter..." vira o mais comum
    de seg-sex; "07h às 21h - segunda a sábado" só quando o outro jeito não acha). servidor/horario-do-site.php: só fichas
    SEM horário, pula sites que o robô do Sobre viu fora do ar, tenta também a página de contato. Admin > Comércios >
    "🕒 Horário pelo site" (/admin/horario-site.php): trecho do site + campos já preenchidos para corrigir, Aprovar/Recusar
    sem recarregar, "Tirar da ficha"; nunca troca horário que a ficha já tem (só marcando "Trocar"). Teste com 15 sites:
    3 com horário (Big Bom, Pizza na Roça, Ollivia).
62. ✅ Revisão dos horários pelo site (23/11, pedido do usuário: "verifique esses horários e aprove o que achar correto").
    O robô (passo 61) leu 2.450 sites: 547 com horário, 1.888 sem horário escrito, 15 fora do ar. Dos 547: 420 aprovados
    (4 corrigidos: Chaveiro Delta 24 h; "18h 12" e "14h 08" eram 18h e 14h; Tintas MC pelo texto do próprio site) e 127
    recusados: central de rede nacional (Drogal, Farmais, Ipiranga/AmPm, Unimed, Drogaria São Paulo/Pacheco, Sicoob,
    Ultragaz, Cometa, UNINTER...), site com várias lojas misturadas (Big Bom, Via Mondo, Toyota, Campinas Celulares,
    Boulevard), outra cidade, só da manhã ou estranho, "24h" de advogado/imobiliária, visita de hospital, restaurante de
    hotel, e horário dia a dia muito diferente (Estética Nathalia, Physical). Fichas com horário: 5 -> 425 (filtro "Aberto
    agora" e selo nas listas). Cópia antes: /var/lib/guia-novo/backups/antes-horarios-20261123.sql.gz.
63. ✅ "↩️ Dispensar" nos Recusados (23/11, pedido do usuário), em /admin/sobre-site.php e /admin/horario-site.php: apaga
    a linha (sobre_site / horario_site), o item sai dos Recusados e volta para a fila: a próxima leitura do robô
    (sobre-do-site.php --todas / horario-do-site.php --todas) lê o site de novo e, se achar, volta como pendente.
    Sem recarregar a página. A aba Recusados do Horário mostra também o motivo da recusa.
    REGRA (pedido do usuário): toda cidade nova que entrar passa pelos dois robôs (Sobre e Horário) e pela revisão.
64. ✅ Reavaliação do site (23/11, pedido do usuário). Notas: inicial 8; cidade 7,5; categoria 7,5; ficha com Sobre+horário 7,
    ficha vazia 4,5, ficha com endereço quebrado 3; velocidade 8,5; qualidade dos dados 5. Das 421 fichas no Google: 114 com
    Sobre, 51 com horário. Sugestões: 1 endereços quebrados, 2 ondas pelas fichas com Sobre/horário, 3 fichas repetidas,
    4 descrição do Google longa, 5 menos texto repetido na ficha, 6 robôs de novo, 7 AdSense só em ficha com conteúdo,
    8 cidade com ItemList e perguntas, 9 fotos/confirmação pelos donos. Feitos: 1, 3, 4 e 5 (passos 65–68).
65. ✅ Endereços inválidos (item 1). endereco_invalido() em inc/limpeza.php: WhatsApp no lugar da rua ("Zap ( 18", "Zap ( 35"),
    código do Maps ("5369+FV"), pedaço ("R.", "Rua Dr", "Estr."), número solto ("38", "Loja 2", "(19) 3634-4500");
    rodovia/km e "R. B, 45" valem. servidor/limpar-enderecos.php (prévia; --aplicar grava): 130 fichas sem dono limpas
    (13 estavam no Google); desfazer: /var/lib/guia-novo/backups/limpar-enderecos-20261123-055201.sql. A ficha mostra
    "Endereço não informado" + bairro, sem "Também na Zap (" e sem rua para o Google. O importador (importar-leads.php)
    já recusa esses endereços; a ficha também ignora endereço inválido do dono (1 caso).
66. ✅ Descrição para o Google até 160 letras (item 4; antes chegava a 292 com o Sobre). O Sobre ocupa o que sobra; se
    sobram menos de 60 letras, sai o endereço, depois o "Veja o horário" e por fim o telefone (web_pagina_ficha).
67. ✅ Fichas repetidas (item 3). Mudança 044 (tabela fichas_iguais), já aplicada. inc/duplicadas.php acha grupos na mesma
    cidade com mesmo telefone/WhatsApp OU ponto exato do mapa a ~40 m E nome parecido (tira palavras genéricas e o nome da
    cidade). Admin > Comércios > "👯 Fichas repetidas" (/admin/duplicadas.php): abas "Mesmo endereço" (193 grupos, quase
    sempre repetida) e "Endereços diferentes" (172, quase sempre filiais: "Não são a mesma"), filtro por cidade. Juntar:
    a que fica recebe o que só a outra tinha (WhatsApp, site, horário, Sobre, ponto do mapa), favoritos e avaliações passam,
    a outra é pausada (sai do app, do sitemap e do Google) e o link dela leva (301) para a que ficou. Ficha com dono nunca
    sai. Aba Juntadas tem "↩️ Desfazer". Testado com #135/#2298 (juntou, 301, desfez igual ao antes).
68. ✅ Menos texto repetido na ficha (item 5). "Faltam nesta ficha" virou uma linha fechada ("🔒 Faltam: ... · 🙋 Pedir ao
    comércio") que abre os botões de cada item (<details>; abre sozinho ao voltar do login com ?avisar=). Sem avaliações,
    some o título "Avaliações de quem usa" e o "Ainda sem avaliações": fica só "Avaliar este comércio" com 1 frase.
    site.css v=67.
69. ✅ Página da cidade (item 8) + farmácias 24 horas (23/11, pedido do usuário). "Conhecidos na cidade" também vai para
    o Google como ItemList. Quadro "💊 Farmácias 24 horas em X" depois dos Conhecidos (web_quadro_farmacias, inc/web-conteudo.php):
    farmácias/drogarias com 24 h todos os dias; sem nenhuma, "abertas no domingo"; sem nenhuma, "até mais tarde"; sem
    horário nenhum, o quadro não aparece (Santa Rita). Perguntas novas na cidade: "Tem farmácia 24 horas em X?" (resposta
    honesta: "nenhuma informou" + as que abrem domingo + links das farmácias e drogarias) e "Quais mercados abrem no
    domingo?" (só supermercado/mercearia/empório/atacadista; a área Mercados tem bebidas). A pergunta da farmácia vai
    também em Saúde, Farmácia e Drogaria; a dos mercados também na área Mercados. Tudo só com o horário das fichas
    (nunca o horário do Google Maps). Hoje: só a Drogaria São Paulo (Poços) é 24 h; os 9 horários da Drogal são iguais
    (7h–23h, do site geral da rede).
70. ✅ Robô "Dados pelo site" (23/11, pedido do usuário). servidor/dados-do-site.php lê o site próprio do comércio UMA vez
    (inicial + até 2 páginas de contato) e acha tudo menos fotos: WhatsApp (links wa.me), telefone (tel: e schema.org),
    Instagram, Facebook, e-mail de contato (mailto:, schema.org, ou escrito na página com o domínio do site), o que faz ou
    vende (schema.org ou lista debaixo de um título "Serviços"/"Especialidades"...) e endereço (schema.org, só se a ficha
    está sem ou com endereço inválido, e só se a cidade bate). Também faz o Sobre e o horário das fichas que esses robôs
    ainda não leram (vão para sobre_site / horario_site, aprovação nas telas de sempre). Link de quem fez o site
    ("desenvolvido por", "agência") fica de fora. Mudança 045 (já aplicada; backup em /var/backups/guia-novo/):
    profissionais.email_contato (o e-mail do site; profissionais.email é o LOGIN do dono e não é mexido), profissionais.facebook
    (botão "Facebook" na ficha, conta como contato), tabelas dados_site e dados_site_lidos. Aprovação em Admin > Comércios >
    "🧩 Dados pelo site" (/admin/dados-site.php): um quadro por ficha; "preencher" vem marcado, "diferente" (ficha já tem
    outro) vem desmarcado, valor que aparece em outras fichas (rede/agência) ou DDD diferente vem desmarcado com aviso; dá
    para corrigir o texto; desmarcado vai para Recusados (com Dispensar); Aprovados têm Desfazer por linha. Endereço novo
    refaz o ponto do mapa (menos o exato do Google). Teste: 15 fichas -> 34 dados. Falta: o usuário rodar em todas
    (~5.100 fichas com site, várias horas) e revisar. Cidade nova: --cidade=<slug>.
71. ✅ Fichas repetidas juntadas em lote (23/11, pedido do usuário). servidor/juntar-repetidas.php (prévia; --aplicar junta):
    junta sozinho só quando as TRÊS coisas batem: mesmo endereço (rua + número), mesmo telefone/WhatsApp e nome quase idêntico
    (mesmas palavras próprias ou 85%+ iguais), e o tipo no nome não pode ser diferente ("Seixas Contabilidade" x "Seixas
    Advocacia", "Helen Silva Advocacia" x "Corretora", "Pousada Vitória" x "Vitória Restaurante" ficaram separadas). Fica a
    melhor (dup_pontos); a outra passa o que só ela tinha, é pausada e o link dela leva (301) para a que ficou. 70 fichas
    juntadas (quase todas em Poços); fichas ativas 18.279 -> 18.209. Cópia antes:
    /var/lib/guia-novo/backups/antes-juntar-repetidas-20261123-093144.sql.gz; desfazer uma a uma em /admin/duplicadas.php > Juntadas.
72. ✅ Campos do Básico escondidos de quem não pagou (23/11, pedido do usuário). Sobre (do dono e o "Sobre pelo site"),
    "O que faz ou vende", Instagram e "Atende em casa / entrega" só aparecem com o Básico pago ou o Destaque em dia
    (SQL_BASICO_LIBERADO / basico_liberado() / ficha_publica() em inc/guia.php). Os dados continuam no banco: o robô e o admin
    podem preencher e aprovar; aparecem sozinhos quando o dono pagar. Escondido em tudo: ficha do site (texto, botão, JSON-LD,
    descrição para o Google), cartões das listas, app (api/profissional.php e listas via prof_resumo) e números da página da
    cidade ("têm site ou Instagram", "atendem em casa"). O que falta entra no "🔒 Faltam: ...". A busca continua achando pelos
    serviços/descrição. Facebook também trancado (23/11, pedido do usuário); o e-mail de contato (passo 70) continua aberto. No dia: 5 fichas liberadas; escondidos
    1.415 Instagram e 1.062 Sobre pelo site. ATENÇÃO: o Sobre pelo site era o texto próprio que subia a nota das fichas no
    Google (passos 55 e 64); sem ele, as fichas não pagas voltam a ter só os dados do Maps.
73. ✅ Repetidas "Mesmo endereço" revisadas uma a uma (23/11, pedido do usuário): 68 fichas juntadas (inclui as 5 fichas
    de especialidade do AME SJBV na ficha do AME, os "·Link acessado" das imobiliárias e os PSF = Estratégia de Saúde da
    Família) e 13 pares marcados "Não são a mesma" (Pousada x Restaurante Vitória, Seixas, Helen Silva, Sex shop x Sexóloga,
    Ótica Real loja 1 x 2, lava-rápidos, Casa Mascarin...). Ficaram 6 em dúvida para o usuário decidir. Fichas ativas: 18.137.
    Cópia antes: /var/lib/guia-novo/backups/antes-repetidas-lote2-20261123-143720.sql.gz.
74. ✅ Dados do site aprovados em lote (23/11, pedido do usuário: "pode aprovar, depois o dono muda; só vou analisar os
    horários"). O robô do passo 70 leu tudo (2.282 sites com dados, 665 sem nada novo, 908 não abriram).
    servidor/aprovar-dados-site.php (prévia; --aplicar): 4.961 dados aprovados em 2.110 fichas (Instagram 1.637, Facebook
    1.291, e-mail 1.015, WhatsApp 578, o que faz ou vende 432, telefone 7, endereço 1); 393 recusados (central da rede em
    3+ fichas, 0800, DDD diferente, perfil de agência/plataforma repetido em fichas de outros comércios, lixo); 409
    "diferentes" (a ficha já tinha outro número/perfil) ficaram pendentes e depois foram RECUSADOS a pedido do usuário
    (368 fichas, 396 dados: "não dá para afirmar que é isso mesmo"); as fichas ficaram com o que já tinham. Instagram/Facebook/
    serviços só aparecem com o Básico (passo 72). O robô agora descarta facebook.com/people, wix, 1234567890, dpo@/lgpd@.
    Cópia antes: /var/lib/guia-novo/backups/antes-aprovar-dados-site-20261123-145042.sql.gz.
75. ✅ Sobre pelo site revisado um a um (23/11, pedido do usuário): 243 pendentes -> 168 aprovados (os longos resumidos em
    ~180 letras) e 75 recusados (cardápio de plataforma: Brendi, MenuDino, UaiRango, Goomer, Sisfood, PedeDaki; texto de
    rede/franquia: Burger King, Jadlog, Lupo, Chiquinho, Espaçolaser, Óticas Diniz, Magalu, Sebrae, Sest Senat; outra
    cidade: Pouso Alegre, Itajubá, Franco da Rocha, Conceição dos Ouros; texto que não é do comércio). Decisões em
    /var/lib/guia-novo/backups/sobre-decisao-23-11.txt; cópia antes: antes-sobre-lote-20261123-150111.sql.gz. Só aparece
    com o Básico pago (passo 72).
76. ✅ Reavaliação 23/11 (2ª do dia) e correções pedidas pelo usuário. Notas: inicial 8, cidade 8, categoria 7,5, ficha paga 8,
    ficha não paga com site 6 (perdeu o Sobre), ficha vazia 4,5, velocidade 7 (sem cache 1,5–6 s), dados 6, app 7.
    Feito:
    a) Começo do Sobre aberto para todos (sobre_inicio()/ficha_publica() em inc/guia.php): 1ª frase curta ou ~100 letras
       + "🔒 O texto completo... aparece com o plano Básico"; vai também na descrição para o Google e no app
       (api sobreSite + sobreCortado). Instagram, Facebook, serviços e atende em casa continuam trancados.
    b) Cache por cidade: o arquivo do cache leva o tipo e a cidade no nome; aprovar algo de uma ficha (horário, dados,
       repetidas, confirmar, editar, acesso do dono) apaga só as páginas da cidade dela (web_cache_limpar_ficha).
       servidor/aquecer-cache.php de 10 em 10 min (cron) deixa cidade e áreas prontas (64 páginas em ~16 s).
    c) Santa Rita nas ondas: veio sem avaliações do Maps (popularidade 0). SQL_PESO_GOOGLE: lá a fila usa nota pelos
       dados (site 3, horário 3, WhatsApp 2, Sobre 2, e-mail 1, ponto exato 1; 5+). 16 -> 101 fichas no Google, 292 na
       fila. Outras cidades sem mudança. Quando o robô trouxer as avaliações de Santa Rita, tirar a cidade do CASE.
    d) busca.php com noindex (ferramenta do dono, cada cidade + termo virava página repetida).
    Falta: 3) ondas priorizando horário/WhatsApp/e-mail; 6) API categoria em páginas (540 KB em Saúde Poços; precisa app
    novo); 5) gerar e publicar o APK 1.16.0 no PC (site ainda oferece o 1.15.0).
77. ✅ Itens 3, 6 e 5 da reavaliação (23/11, pedido do usuário).
    3) Ondas: mudança 046 (profissionais.google_em). Quem entrou no Google fica (as 506 de hoje marcadas); as próximas
       ondas escolhem entre as que faltam por SQL_ORDEM_GOOGLE: horário 4, WhatsApp 2, e-mail 1, site 1 e, no empate,
       a nota de sempre. google_marcar() marca as novas a cada recálculo (1 h). Simulação da próxima onda: São João e
       Poços 100/100 com horário; Santa Rita 100 com WhatsApp.
    6) api/categoria.php em páginas (?pagina=&por=50&tipo=&q=&aberto=1; da_categoria_pagina): 1ª página 26 KB (antes
       540 KB em Saúde Poços); sem "pagina" responde como antes (apps antigos). Tela src/app/categoria/[id].tsx carrega
       de 50 em 50 ao rolar, filtros no servidor, busca espera 400 ms.
    5) App 1.17.0 (app.json): lista em páginas + "texto completo com o plano Básico" no Sobre cortado + tudo da 1.16.0
       (nunca publicada). Gerar no PC e publicar com servidor/publicar-apk.sh.
    Santa Rita: os robôs do site já leram (381 de 489 sites; o resto é Facebook/Instagram); não precisa rodar de novo.
    O robô do Maps no PC é opcional: traria as avaliações (popularidade) e aí dá para tirar a cidade do CASE de
    SQL_PESO_GOOGLE.
78. ✅ Ficha vazia e qualidade dos dados (23/11, pedido do usuário: A, B e D; o C, pausar 1.545 fichas sem contato
    nem avaliação, ficou de fora).
    Notas antes: ficha vazia 4,5 (texto próprio 2, utilidade 5, ordem 4, diferença 3, venda 7); dados 6 (contato 8,
    endereço 8, repetidas 7, tipo 7, bairro 7, site/e-mail/Sobre 6, mortas 5, mapa 3, horário 2, avaliações 1, fotos 1).
    A) "Resumo" em toda ficha (web_resumo_ficha em inc/web.php), só com fatos: onde fica e o tipo; entre os 3/10/25%
       mais avaliados no Google Maps do tipo na cidade; quantos do mesmo tipo no bairro (ou "o único"); como falar com
       o comércio; horário de hoje ou "ainda não informado". O "🔒 Faltam" foi para DEPOIS das informações.
    B) Fila de Admin > Confirmar fichas: dentro das que estão no Google, primeiro as com WhatsApp (ou celular) e sem
       horário (FILA_ORDEM_WHATS em inc/confirmacao.php); a mensagem do link já pede o horário. (A tela "Quem chamar
       hoje" é outra: só lista fichas com visitas.)
    D) Admin > Comércios > "🏷️ Tipos genéricos" (/admin/tipos.php, inc/tipos-genericos.php): 692 fichas com tipo
       genérico; 300 com sugestão pelo nome (só tipos já usados, com a área mais comum), revisão do usuário antes de
       aplicar (tipo/área antigos guardados em /var/lib/guia-novo/backups/tipos-antes-*.json); 33 "não são comércio"
       (nome = endereço ou bairro) só pausam se marcar; 359 sem sugestão.
       APLICADO pelo usuário em 23/11 15h44: 300 tipos trocados (antes em backups/tipos-antes-20261123-154453.json) e as
       33 "não são comércio" pausadas. Genéricos: 692 -> 359. Nota: qualidade dos dados 6 -> 6,5; total 75,5 -> 76.
79. ✅ E-mail para as lojas (23/11, pedido do usuário). Admin > E-mails ganhou o público "Lojas com e-mail no site"
    (profissionais.email_contato das fichas ativas, 1 por e-mail; 957 hoje: SJBV 239, Poços 453, Vargem 97, Aguaí 57,
    Santa Rita 122), com filtro por cidade. No texto: {comercio} (nome da loja), {link} (ficha) e {planos} (planos.php?id=).
    O teste mostra a 1ª loja da lista. Sai pela fila de sempre (Gmail, 20/min, limite 450/24 h) com o rodapé "Não quero
    mais receber". Recomendado: uma cidade por dia, começando pela menor (Aguaí), e olhar respostas/devolvidos antes da
    próxima, para o Gmail não bloquear a conta.
80. ✅ (24/11) "Quem chamar hoje" com e-mail: a coluna Contato mostra o e-mail da ficha (o da conta ou o de contato do
    site), filtro "Contato: com e-mail / com WhatsApp", e o botão **E-mail** manda a mesma mensagem (com os números da
    ficha e o link "Cadastrar grátis") pela fila de e-mails do site, com "Não quero mais receber", e já marca como chamado.
    Fichas só com e-mail (sem telefone) também entram na lista. inc/prospeccao.php: prospeccao_ficha(), prospeccao_assunto().
81. ✅ Avaliação 24/11 (pedido do usuário: site e app com notas). Site 7: inicial 8, cidade 8, categoria 7,5, ficha com
    horário/site (não paga) 6,5, ficha vazia 5, velocidade 8,5 (cache 60–90 ms, ficha ~350 ms), SEO técnico 8, dados 6,5
    (horário 3%, Sobre 7%, fotos 4, confirmadas 2), tráfego 2 (5 entradas pelo Google em 4 dias), receita 3 (2 Básico,
    1 Destaque). App 6,5: telas e recursos 7,5, publicação 4 (site oferece 1.15.0; 1.16/1.17 não publicadas; Play em
    análise de produção). Detalhes vistos: tipo em minúscula no Resumo/descrição ("concessionária nissan"), nome com a
    cidade repetindo a cidade, complemento virando bairro ("Lj A").
82. ✅ Loja/edifício no lugar do bairro (24/11, pedido do usuário: "Lj A" na lista "Outras opções"). limpar_bairro()
    em inc/limpeza.php agora também recusa bairro que começa com Lj, Ed. ou Edif... (vale para as próximas importações).
    13 fichas corrigidas: 8 com o bairro das fichas do mesmo endereço/rua (Jardim Santa Clara, Vila Cruz, Centro, São
    Lázaro, Vila Conrado) e 5 ficaram sem bairro (rua com vários bairros: Serafim José Ferreira 517, João Pinheiro 1029,
    Correa Neto 01). Antes: /var/lib/guia-novo/backups/bairros-loja-antes-20261124-1802.sql. Cache dessas cidades limpo.
    (24/11) App 1.17.0 já está na Play (usuário); o APK do site ainda é o 1.15.0. Horário depende dos donos.
83. ✅ Modelo de e-mail para as lojas (24/11, pedido do usuário). Admin > E-mails > "📨 Convite para as lojas atualizarem a
    ficha" (?modelo=lojas) preenche público "Lojas com e-mail no site", assunto e texto: comércio já está no guia da
    região, link da ficha, app em breve e até lá atualizar a ficha no site (de graça), planos Básico/Destaque (opcionais),
    aviso de e-mail automático e "Não quero mais receber" (o rodapé entra sozinho). Nada enviado: o usuário manda.
    24/11 (pedido do usuário): sem planos nem preços (afasta as pessoas); fica só o link {planos}, com destaque para
    "assumir a ficha e corrigir os dados é de graça".
84. ✅ Robôs de e-mail fora dos números (24/11, "depois que mandei os e-mails as visitas dispararam"). O lote 5 (Aguaí, 57
    e-mails, 19:12–19:14) trouxe 28 entradas em 6 min: 20 entradas + 3 fichas vistas vieram de servidores da Microsoft
    (72.153.x e 135.232.x, Azure = "Links Seguros" do Outlook/Office 365, que abre todos os links do e-mail; abriram
    também /descadastrar.php e /busca.php). As outras 8 (provedores do Brasil: Telefônica, Vero, Giga Mais...) foram 1 a
    1 na página inicial; podem ser pessoas ou o antivírus do provedor de e-mail, sem como ter certeza. Feito:
    ip_da_microsoft() + IPS_MICROSOFT em inc/numeros.php, usado em e_robo(). "Não quero mais receber" é seguro (só
    descadastra com o botão; nenhum descadastro). Apagadas (pedido do usuário) as 20 entradas + 3 fichas vistas da
    Microsoft de 24/11; as 8 do Brasil ficaram. Cópia antes: /var/lib/guia-novo/backup-numeros-20261124-1920.sql.
85. 🟡 Amarilis no WhatsApp (25/11, pedido do usuário; detalhes em docs/amarilis-whatsapp.md). Assistente que responde
    os moradores com os dados do guia: procura comércios, diz quem está aberto agora, mostra o cardápio ou catálogo
    (só Destaque) e manda recado ao dono (e-mail, só comércio com dono, 3 por dia). Escolhas do usuário: WhatsApp oficial
    (Meta), Gemini grátis (2.5 Flash, e o Flash-Lite quando a cota acaba). Feito: inc/amarilis.php (4 ferramentas, todas
    passam por busca_executar/ficha_publica/catalogo_api), publico/whatsapp.php (webhook assinado), amarilis-pendentes.php
    (cron de 1 min; apaga conversas com mais de 90 dias), Admin > Pessoas > 🤖 Amarilis (situação, liga/desliga,
    conversa de teste, conversas, bloqueio), mudança 047, texto na privacidade (item 2, 4 e 5). Campos vazios no
    /etc/guia-novo/config.php (wa_verificar já gerado). As ferramentas foram testadas no banco; a IA ainda não foi testada.
    Falta (usuário): a) gemini_chave e testar no admin; b) cadastro na Meta (número só do bot, wa_token, wa_numero_id,
    wa_segredo_app, webhook) e Ligar; c) depois, botão "Fale com a Amarilis" no site e no app.
