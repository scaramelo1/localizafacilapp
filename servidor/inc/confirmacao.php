<?php
// Plano D, passos 10 e 11 (20/11): confirmar as fichas que vieram da lista do Google Maps.
//  10) Link de confirmação: o admin manda pelo WhatsApp um link único (publico/confirmar.php?c=...). O dono confere,
//      diz "está tudo certo" (confirma na hora) ou manda correção (vira alteração pendente, como as do painel) e
//      pode informar o horário de graça. Fotos e descrição continuam no plano (regra do negócio: completar é pago).
//  11) Fila "Confirmar por telefone" (/admin/confirmar.php): um comércio por vez, na ordem da prioridade das áreas.
declare(strict_types=1);
require_once __DIR__ . '/guia.php';
require_once __DIR__ . '/moderacao.php';
require_once __DIR__ . '/horarios.php';

const META_CONFIRMADAS = 50;          // por cidade (docs/00-passos.md, passo 14)
const CONFIRMACAO_LINK_DIAS = 60;     // o link vale 60 dias
const FILA_DIAS_LINK = 3;             // mandou o link: volta para a fila depois de 3 dias se o dono não confirmar
const FILA_DIAS_NAO_ATENDE = 1;       // não atendeu: tenta de novo amanhã

/** Cria um link novo para a ficha e devolve o endereço completo. */
function confirmacao_criar_link(int $pid, ?int $admin_id): string {
    $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    inserir('confirmacao_links', ['profissional_id' => $pid, 'token_hash' => hash('sha256', $token), 'admin_id' => $admin_id]);
    return url_publica('confirmar.php?c=' . $token);
}
/** Link válido => ['link' => linha de confirmacao_links, 'p' => ficha ativa]; senão null. */
function confirmacao_do_token(string $token): ?array {
    if (!preg_match('/^[A-Za-z0-9_-]{20,30}$/', $token)) return null;
    $l = linha('SELECT * FROM confirmacao_links WHERE token_hash = ? AND criado_em > NOW() - INTERVAL ' . CONFIRMACAO_LINK_DIAS . ' DAY',
        [hash('sha256', $token)]);
    if (!$l) return null;
    $p = linha("SELECT p.*, c.nome AS categoria FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ? AND p.status = 'ativo'",
        [(int)$l['profissional_id']]);
    return $p ? ['link' => $l, 'p' => $p] : null;
}
/** Número para o WhatsApp do comércio: o WhatsApp informado ou o telefone, se for celular. */
function confirmacao_numero_whats(array $p): ?string {
    if (!empty($p['whatsapp'])) return (string)$p['whatsapp'];
    $t = so_digitos((string)($p['telefone'] ?? ''));
    return strlen($t) === 11 && $t[2] === '9' ? $t : null;
}
function confirmacao_mensagem(array $p, string $url): string {
    return 'Olá! A ' . nome_curto((string)$p['nome']) . ' está no Localiza Fácil, o guia de comércios de ' . nome_cidade((string)$p['cidade'])
        . '. Confira se os seus dados estão certos e informe o horário de funcionamento. Leva 1 minuto e é grátis: ' . $url;
}
/** Link do WhatsApp com a mensagem pronta (null se o comércio não tem WhatsApp nem celular). */
function confirmacao_link_whats(array $p, string $url): ?string {
    $n = confirmacao_numero_whats($p);
    return $n ? link_whatsapp($n, confirmacao_mensagem($p, $url)) : null;
}

/** O dono confirmou pelo link (com ou sem correção). */
function confirmacao_registrar_uso(array $link, string $resultado): void {
    q('UPDATE confirmacao_links SET usado_em = NOW(), resultado = ? WHERE id = ?', [$resultado, (int)$link['id']]);
    marcar_confirmada((int)$link['profissional_id'], 'dono');
    q('DELETE FROM confirmar_fila WHERE profissional_id = ?', [(int)$link['profissional_id']]);
    registrar(null, $resultado === 'ok' ? 'dono_confirmou_link' : 'dono_corrigiu_link', (int)$link['profissional_id']);
    web_cache_limpar_ficha((int)$link['profissional_id']);
}
/** Correção mandada pelo link: vira alteração pendente (o admin aprova em Pendências), sem acesso de dono. */
function confirmacao_propor(array $p, array $valores): ?int {
    $novos = $antes = [];
    foreach ($valores as $c => $v) {
        if (!isset(CAMPOS_DONO[$c])) continue;
        $atual = $p[$c] === null ? null : (string)$p[$c];
        if ($v !== $atual) { $novos[$c] = $v; $antes[$c] = $atual; }
    }
    if (!$novos) return null;
    $velha = alteracao_pendente((int)$p['id']);
    if ($velha && !array_intersect(['foto', 'fotos'], array_keys($velha['novos']))) q("DELETE FROM alteracoes WHERE id = ? AND status = 'pendente'", [$velha['id']]);
    return inserir('alteracoes', ['profissional_id' => $p['id'], 'acesso_id' => null,
        'novos' => json_encode($novos, JSON_UNESCAPED_UNICODE), 'anteriores' => json_encode($antes, JSON_UNESCAPED_UNICODE), 'ip' => ip()]);
}

// ---------- fila "Confirmar por telefone" ----------
/** Põe/atualiza a ficha na fila: some até $dias dias ('fechou' sai de vez). */
function fila_marcar(int $pid, string $situacao, ?int $admin_id, float $dias): void {
    q('INSERT INTO confirmar_fila (profissional_id, situacao, tentativas, proxima_em, admin_id) VALUES (?, ?, 1, NOW() + INTERVAL ? MINUTE, ?)
        ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), tentativas = tentativas + 1, proxima_em = VALUES(proxima_em), admin_id = VALUES(admin_id)',
        [$pid, $situacao, (int)round($dias * 1440), $admin_id]);
}
/** SQL: a confirmar, com contato, fora da espera da fila. */
function fila_sql(string $cidade): string {
    return visivel($cidade) . ' AND ' . SQL_TEM_CONTATO . ' AND NOT ' . SQL_CONFIRMADA . "
        AND NOT EXISTS (SELECT 1 FROM confirmar_fila f WHERE f.profissional_id = p.id AND (f.situacao = 'fechou' OR f.proxima_em > NOW()))";
}
/**
 * 23/11 (passo 78, pedido do usuário): dentro das fichas do Google (e depois das outras), primeiro as que têm WhatsApp
 * (ou celular) e ainda NÃO têm horário: dá para mandar o link pelo WhatsApp e o dono informa o horário (o que mais falta).
 */
const FILA_ORDEM_WHATS = "(COALESCE(p.whatsapp, '') <> '' OR p.telefone REGEXP '^[0-9]{2}9[0-9]{8}$') DESC,
    (p.horarios IS NULL OR p.horarios IN ('', '[]', '{}')) DESC";
/** A próxima ficha da fila (pular = quantas passar). Ordem: no Google, WhatsApp sem horário, áreas, mais conhecidas. */
function fila_proxima(string $cidade, int $pular = 0): ?array {
    // 22/11 (passo 55, F): primeiro as fichas que já estão no Google (as que o Google julga), depois a ordem de antes
    $no = sql_no_google();
    return linha("SELECT p.*, c.nome AS categoria, f.situacao AS fila_situacao, f.tentativas AS fila_tentativas, f.atualizado_em AS fila_quando,
        $no AS no_google
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id LEFT JOIN confirmar_fila f ON f.profissional_id = p.id
        WHERE " . fila_sql($cidade) . " ORDER BY $no DESC, " . FILA_ORDEM_WHATS . ', ' . sql_prioridade_area() . ', p.popularidade DESC, p.id LIMIT 1 OFFSET ' . max(0, $pular));
}
/** Números da cidade para o topo da tela. */
function fila_numeros(string $cidade): array {
    $conf = confirmacao_por_cidade()[$cidade] ?? ['total' => 0, 'confirmadas' => 0];
    $r = linha('SELECT COUNT(*) AS n FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . fila_sql($cidade));
    $esp = linhas("SELECT f.situacao, COUNT(*) AS n FROM confirmar_fila f JOIN profissionais p ON p.id = f.profissional_id
        WHERE p.cidade = ? AND (f.situacao = 'fechou' OR f.proxima_em > NOW()) GROUP BY f.situacao", [$cidade]);
    return ['confirmadas' => $conf['confirmadas'], 'na_fila' => (int)($r['n'] ?? 0), 'esperando' => array_column($esp, 'n', 'situacao')];
}
