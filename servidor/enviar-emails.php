<?php
// Manda os e-mails da fila (inc/email.php). Roda pelo cron de minuto em minuto (/etc/cron.d/guia-novo);
// na mão: php servidor/enviar-emails.php [quantos]
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/email.php';

// uma rodada por vez (se o Gmail demorar, a próxima do cron não começa por cima)
$trava = fopen(sys_get_temp_dir() . '/guia-novo-emails.lock', 'c');
if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) exit;
if (!email_pronto() || !valor("SELECT 1 FROM emails WHERE status = 'fila' LIMIT 1")) exit;

$r = email_processar(max(1, (int)($argv[1] ?? EMAIL_POR_RODADA)));
if ($r['enviados'] || $r['falhas'] || $r['parou'] || PHP_SAPI === 'cli' && isset($argv[1])) {
    echo date('d/m H:i') . " enviados {$r['enviados']}, falhas {$r['falhas']}, na fila {$r['sobrou']}" . ($r['parou'] ? " — parou: {$r['parou']}" : '') . "\n";
}
