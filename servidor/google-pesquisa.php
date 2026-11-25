<?php
// 25/11 (passo 87): baixa do Search Console as aparições do site na pesquisa do Google (impressões e cliques) por dia,
// por página e por pesquisa. Cron 2x por dia (instalar.sh). Uso: php servidor/google-pesquisa.php [dias, padrão 10]
// Na 1ª vez, para pegar o histórico todo: php servidor/google-pesquisa.php 60
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/inc/base.php';
require __DIR__ . '/inc/guia.php';
require __DIR__ . '/inc/google-pesquisa.php';
if (!gp_pronto()) { fwrite(STDERR, "Falta a mudança 049 (bash servidor/instalar.sh)\n"); exit(1); }
if (!gp_ligado()) { fwrite(STDERR, 'Sem a chave do Search Console em ' . gp_chave_arquivo() . " (docs/search-console-api.md)\n"); exit(1); }
try {
    echo date('d/m H:i '), gp_atualizar(max(3, min(480, (int)($argv[1] ?? 10)))), "\n";
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fwrite(STDERR, date('d/m H:i ') . 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
