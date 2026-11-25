# Aparições no Google no admin (passo 87, 25/11)

O admin mostra quantas vezes as páginas do site apareceram na pesquisa do Google (as "impressões" do Search Console),
os cliques, o que as pessoas pesquisaram e quais fichas mais apareceram. Os dados vêm do Search Console pela API, com
2 a 3 dias de atraso (o Google só entrega assim). Onde ver: Admin > Números (cartão "🔎 Apareceram no Google" e duas
tabelas) e Números da ficha.

## Ligar (uma vez só, uns 10 minutos)

1. https://console.cloud.google.com → escolha o mesmo projeto do login com Google do Localiza.
2. Menu > APIs e serviços > Biblioteca → procure **Google Search Console API** → **Ativar**.
3. Menu > IAM e administrador > **Contas de serviço** → **Criar conta de serviço** → nome `localiza-search-console`
   → Criar e continuar → não precisa de papel → Concluir.
4. Clique na conta criada → aba **Chaves** → Adicionar chave → Criar nova chave → **JSON** → baixa um arquivo .json.
5. Copie o e-mail da conta (termina em `@...iam.gserviceaccount.com`).
6. https://search.google.com/search-console → propriedade **https://localizafacilapp.com.br/** → Configurações →
   **Usuários e permissões** → Adicionar usuário → cole o e-mail → permissão **Restrita** → Adicionar.
7. No PC (PowerShell), mande o arquivo para a VPS:
   `scp "$env:USERPROFILE\Downloads\<arquivo>.json" root@localizafacilapp.com.br:/etc/guia-novo/search-console.json`
8. Na VPS:
   `cd /var/www/guia-novo && bash servidor/instalar.sh` (aplica a mudança 049, o cron e a permissão do arquivo)
   `sudo -u www-data php servidor/google-pesquisa.php 90` (puxa os últimos 90 dias de uma vez)

Depois disso o cron atualiza sozinho às 7h41 e 16h41 (log em /var/log/guia-novo-google-pesquisa.log).

## Erros comuns
- "Google respondeu 403 ... User does not have sufficient permission": falta o passo 6 (ou a propriedade é outra;
  se no Search Console ela aparecer como domínio, ponha `'gsc_site' => 'sc-domain:localizafacilapp.com.br'` no
  /etc/guia-novo/config.php).
- "Google respondeu 403 ... API has not been used": falta o passo 2 (espere 2 minutos depois de ativar).
- "Arquivo da chave inválido": o arquivo não está em /etc/guia-novo/search-console.json ou o www-data não lê
  (o instalar.sh acerta a permissão).

A chave é só de leitura (webmasters.readonly) e nunca sai do servidor. Não colocar no git.
