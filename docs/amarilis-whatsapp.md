# Amarilis no WhatsApp

Passo 85 de `00-passos.md` (25/11/2026, pedido do usuário). A Amarilis responde os moradores pelo WhatsApp com os dados
do guia: procura comércios, diz quem está aberto agora, mostra o cardápio ou catálogo dos Destaque e manda recado ao dono.

- **Canal:** WhatsApp oficial da Meta (Cloud API).
- **IA:** Gemini, na cota grátis do Google. Quando a cota do `gemini-2.5-flash` acaba, ela passa para o `gemini-2.5-flash-lite`.
- **Dados:** só os do banco, com as mesmas regras do site (busca, `ficha_publica()`, catálogo só do Destaque).

## Arquivos

| Arquivo | O que faz |
|---|---|
| `servidor/inc/amarilis.php` | Instruções da IA, as 4 ferramentas, a chamada ao Gemini e o envio pelo WhatsApp |
| `servidor/publico/whatsapp.php` | Webhook da Meta: confere a assinatura, responde 200 e depois pensa a resposta |
| `servidor/amarilis-pendentes.php` | Cron de minuto em minuto: responde o que ficou sem resposta e apaga conversas com mais de 90 dias |
| `servidor/publico/admin/amarilis.php` | Admin > Pessoas > 🤖 Amarilis: situação, liga/desliga, **conversa de teste**, conversas e bloqueio |
| `servidor/sql/migracoes/047-amarilis.sql` | Tabelas `amarilis_conversas`, `amarilis_mensagens` e `amarilis_recados` |

Ferramentas que a IA pode usar:
- `buscar_comercios`: até 6 resultados, com o filtro "aberto agora".
- `ver_comercio`: a ficha pública.
- `ver_catalogo`: só para os Destaque.
- `mandar_recado`: e-mail ao dono, só para comércio com dono e no máximo 3 por dia. Na tela de teste não manda o e-mail.

## O que você precisa fazer

### 1. Chave do Gemini (grátis, 2 minutos)
1. Abra https://aistudio.google.com/apikey e clique em **Create API key**. Pode ser a mesma chave da Amarilis da sala.
2. Na VPS, cole a chave em `/etc/guia-novo/config.php`, no campo `'gemini_chave' => '...'`.
3. Abra Admin > Pessoas > 🤖 Amarilis e converse na **Testar a conversa**. Isso já funciona sem WhatsApp.

### 2. WhatsApp oficial (Meta)
Você precisa de um **número só para o bot**: um chip novo ou um fixo. Esse número **não pode estar instalado** no
WhatsApp do celular. Se estiver, apague a conta nele antes.

1. Crie a conta empresarial em https://business.facebook.com, com o nome "Localiza Fácil App".
2. Crie o app em https://developers.facebook.com > Meus apps > Criar app > tipo **Empresa**, depois adicione o produto **WhatsApp**.
3. Vá em WhatsApp > Configuração da API > **Adicionar número de telefone**. Coloque o número do bot, confirme pelo código
   de SMS ou ligação e escolha o nome de exibição "Localiza Fácil App". A Meta precisa aprovar esse nome.
4. Copie a **Identificação do número de telefone** e cole em `wa_numero_id`.
5. Crie o token permanente. Em Meta Business > Configurações > Usuários > **Usuários do sistema**, adicione um usuário
   (Admin), clique em **Gerar token**, escolha o app e marque as permissões `whatsapp_business_messaging` e
   `whatsapp_business_management`, com validade "Nunca". Cole o token em `wa_token`.
6. Em Configurações do app > Básico, copie a **Chave secreta do aplicativo** e cole em `wa_segredo_app`.
7. Em WhatsApp > Configuração > Webhook, clique em **Editar**:
   - URL de retorno: `https://localizafacilapp.com.br/whatsapp.php`
   - Verificar token: o valor de `wa_verificar`, que já está no config.php e também aparece no admin
   - Em "Campos do webhook", assine **messages**.
8. Publique o app (modo **Ao vivo**). Isso pede a política de privacidade: `https://localizafacilapp.com.br/privacidade.php`.
9. No admin, clique em **Ligar no WhatsApp**. Depois mande "oi" do seu celular para o número do bot.

**Custo:** responder quem escreve primeiro é grátis (é "atendimento", dentro da janela de 24 h). A Amarilis nunca
escreve primeiro, então não usa mensagens pagas.

**Limites da cota grátis do Gemini:** são algumas centenas de chamadas por dia, e cada resposta usa de 1 a 3. Se
passar disso, a Amarilis avisa "muita procura, tente daqui a pouco". O admin mostra quantas chamadas foram feitas no dia.

## Regras de segurança
- A IA não altera nada no guia. O único efeito fora de uma consulta é o recado por e-mail, com limite.
- Cada número pode mandar até 60 mensagens por dia, e o admin pode **bloquear** um número.
- Ela não fala de preços nem de planos do Localiza. Para o dono que quer aparecer, manda o link do cadastro grátis.
- A política de privacidade já fala da Amarilis: o Gemini lê as mensagens e as conversas ficam guardadas por 90 dias.

## Próximas ideias (não feitas)
- Receber a **localização** e responder "perto de mim" com `perto()`.
- Mandar a foto do item do catálogo junto da resposta.
- Pôr o botão "Fale com a Amarilis" (`wa.me/<número>`) no site e no app, quando o número estiver no ar.
