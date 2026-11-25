<?php
// Rede de segurança da Amarilis (passo 85): responde mensagens que ficaram sem resposta (o webhook caiu no meio,
// a Meta mandou duas de uma vez, o Gemini demorou). Cron de minuto em minuto; na mão: php servidor/amarilis-pendentes.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/amarilis.php';
if (!amarilis_pronta()) exit;
// conversas do WhatsApp guardadas por 90 dias (política de privacidade, item 5); uma vez por hora, no minuto 7
if ((int)date('i') === 7) {
    q("DELETE FROM amarilis_mensagens WHERE criado_em < NOW() - INTERVAL 90 DAY");
    q("DELETE c FROM amarilis_conversas c WHERE c.atualizado_em < NOW() - INTERVAL 90 DAY AND c.bloqueada = 0
        AND NOT EXISTS (SELECT 1 FROM amarilis_mensagens m WHERE m.conversa_id = c.id)");
}
// 'nova' há mais de 20 s (o webhook já devia ter pegado) ou 'respondendo' há mais de 3 min (travou)
$ids = array_column(linhas("SELECT DISTINCT conversa_id FROM amarilis_mensagens WHERE de = 'pessoa' AND (
    (status = 'nova' AND criado_em < NOW() - INTERVAL 20 SECOND) OR (status = 'respondendo' AND criado_em < NOW() - INTERVAL 3 MINUTE))
    AND criado_em > NOW() - INTERVAL 1 DAY LIMIT 20"), 'conversa_id');
foreach ($ids as $cid) {
    try { $r = amarilis_processar((int)$cid); if ($r !== null) echo date('d/m H:i') . " respondeu a conversa $cid\n"; }
    catch (Throwable $e) { echo date('d/m H:i') . " conversa $cid: " . $e->getMessage() . "\n"; }
}
