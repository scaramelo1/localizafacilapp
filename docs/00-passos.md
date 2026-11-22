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

## Nunca fazer
- Subir milhares de fichas de uma vez para o Google (usar as ondas).
- Redirecionar o domínio antigo para o novo.
- Ter as mesmas páginas abertas para o Google nos dois domínios ao mesmo tempo.
- Publicar dados do Google Maps como se fossem nossos.
- Pedir indexação em massa no Search Console.
