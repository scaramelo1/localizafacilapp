<?php
// Webhook do WhatsApp (API oficial da Meta) para o LocalizaBot (passo 85, inc/localizabot.php).
// No painel da Meta (developers.facebook.com > o app > WhatsApp > Configuração):
//   URL de retorno: https://localizafacilapp.com.br/whatsapp.php · Token de verificação: o 'wa_verificar' do config.php
//   Campos assinados: messages
declare(strict_types=1);
require __DIR__ . '/../inc/localizabot.php';
header('Cache-Control: no-store');

// 1. Verificação (a Meta faz um GET quando o endereço é salvo)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $tok = trim((string)localizabot_cfg('wa_verificar', ''));
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && $tok !== '' && hash_equals($tok, (string)($_GET['hub_verify_token'] ?? ''))) {
        header('Content-Type: text/plain');
        echo preg_replace('/\D/', '', (string)($_GET['hub_challenge'] ?? ''));
        exit;
    }
    http_response_code(403);
    exit;
}

// 2. Mensagens (POST assinado com o Segredo do app)
$corpo = (string)file_get_contents('php://input');
if (!wa_assinatura_ok($corpo, (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''))) { http_response_code(401); exit; }
$j = json_decode($corpo, true);
if (!is_array($j) || !localizabot_pronta()) { http_response_code(200); exit; }

$conversas = [];
try {
    if (localizabot_ligada()) $conversas = wa_receber_webhook($j);
} catch (Throwable $e) {
    error_log('localiza-facil whatsapp webhook: ' . $e->getMessage());
    http_response_code(500);   // a Meta manda de novo depois
    exit;
}

// 3. Responde 200 na hora (a Meta reenvia se demorar) e só depois pensa a resposta, com a conexão já fechada.
// Se este passo falhar, o cron (localizabot-pendentes.php) responde em até 1 minuto.
ignore_user_abort(true);
set_time_limit(120);
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');
header('Connection: close');
header('Content-Length: 11');
echo '{"ok":true}';
flush();
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

foreach ($conversas as $cid) {
    try { localizabot_processar((int)$cid); }
    catch (Throwable $e) { error_log('localiza-facil localizabot processar: ' . $e->getMessage()); }
}
