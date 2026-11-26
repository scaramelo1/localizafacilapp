<?php
// "Quero minha Cidade!" (25/11): funções do pedido e da página /admin/cidades-pedidas.php.
declare(strict_types=1);

/** A mudança 050 (quem pediu, origem, visto) já foi aplicada? */
function cidades_pedidas_completas(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '050-cidades-pedidas-quem.sql'"); } catch (PDOException) { return $ok = false; }
}
/** Pedidos que a equipe ainda não viu (contador do menu). */
function cidades_pedidas_novas(): int {
    if (!cidades_pedidas_completas()) return 0;
    try { return (int)valor('SELECT COUNT(*) FROM cidades_pedidas WHERE visto = 0'); } catch (PDOException) { return 0; }
}
