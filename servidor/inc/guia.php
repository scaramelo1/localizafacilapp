<?php
// Regras do guia: quem aparece, ordem da vitrine, formato dos dados enviados ao app, notas
declare(strict_types=1);

// Cidades atendidas: tabela cidades (mudança 016), editada em /admin/cidades.php. slug => [nome completo, nome curto, UF].
// O app manda ?cidade=<slug> em todas as chamadas e baixa a lista em api/cidades.php: cidade nova NÃO precisa de APK novo.
// CIDADES = só as ativas (as que aparecem no app); TODAS_CIDADES = também as preparadas (importação, geocodificação).
const CIDADES_RESERVA = [   // usada só se a tabela ainda não existir (antes da mudança 016)
    'sao-joao' => ['São João da Boa Vista', 'São João', 'SP', -21.9696, -46.7981],
    'aguas-da-prata' => ['Águas da Prata', 'Águas da Prata', 'SP', -21.9371, -46.7172],
    'pocos-de-caldas' => ['Poços de Caldas', 'Poços de Caldas', 'MG', -21.7878, -46.5614],
    'vargem-grande-do-sul' => ['Vargem Grande do Sul', 'Vargem Grande', 'SP', -21.8322, -46.8936],
    'aguai' => ['Aguaí', 'Aguaí', 'SP', -22.0597, -46.9803],
];
const NOMES_ESTADOS = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
    'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
];
/** Cidades do banco: slug => [nome, curto, UF, lat, lng, ativa]. */
function carregar_cidades(): array {
    try {
        $r = [];
        foreach (db()->query('SELECT slug, nome, curto, uf, lat, lng, ativa FROM cidades ORDER BY ordem, nome')->fetchAll(PDO::FETCH_ASSOC) as $c)
            if (preg_match('/^[a-z0-9-]{2,40}$/', $c['slug']))   // o slug vai direto no SQL (visivel), então só letras, números e hífen
                $r[$c['slug']] = [$c['nome'], $c['curto'], $c['uf'], $c['lat'] === null ? null : (float)$c['lat'], $c['lng'] === null ? null : (float)$c['lng'], (bool)$c['ativa']];
        if ($r) return $r;
    } catch (Throwable) {
        // tabela ainda não criada
    }
    return array_map(fn($c) => [...$c, true], CIDADES_RESERVA);
}
define('TODAS_CIDADES', carregar_cidades());
define('CIDADES', array_filter(TODAS_CIDADES, fn($c) => $c[5]) ?: TODAS_CIDADES);
// estados que têm cidade ativa, na ordem em que aparecem
$ufs_ativas = array_values(array_unique(array_column(CIDADES, 2)));
define('ESTADOS', array_combine($ufs_ativas, array_map(fn($uf) => NOMES_ESTADOS[$uf] ?? $uf, $ufs_ativas)));
unset($ufs_ativas);
define('CIDADE_PADRAO', isset(CIDADES['sao-joao']) ? 'sao-joao' : (string)array_key_first(CIDADES));

/** Cidade pedida pelo app (?cidade=); desconhecida ou vazia vira a padrão. */
function cidade_pedida(): string {
    $c = (string)($_GET['cidade'] ?? '');
    return isset(CIDADES[$c]) ? $c : CIDADE_PADRAO;
}

/** <option> das cidades ativas, agrupadas por estado, para os formulários do site (a 1ª é "Escolha a cidade"). */
function opcoes_cidades(?string $escolhida = null): string {
    $por_uf = [];
    foreach (CIDADES as $slug => [$nome, , $uf]) $por_uf[$uf][$slug] = $nome;
    ksort($por_uf);
    $html = '<option value="">Escolha a cidade</option>';
    foreach ($por_uf as $uf => $cidades) {
        uasort($cidades, fn($a, $b) => strcmp(sem_acento($a), sem_acento($b)));   // "Águas" antes de "Aguaí" e não no fim
        $html .= '<optgroup label="' . h($uf) . '">';
        foreach ($cidades as $slug => $nome) $html .= '<option value="' . h($slug) . '"' . ($slug === $escolhida ? ' selected' : '') . '>' . h("$nome ($uf)") . '</option>';
        $html .= '</optgroup>';
    }
    return $html;
}
function nome_cidade(string $slug, bool $curto = false): string { return TODAS_CIDADES[$slug][$curto ? 1 : 0] ?? CIDADES[CIDADE_PADRAO][0]; }
function uf_cidade(string $slug): string { return TODAS_CIDADES[$slug][2] ?? 'SP'; }
/** "São João da Boa Vista, Águas da Prata (SP) e Poços de Caldas (MG)": cidades ativas agrupadas por estado, para os textos do site. */
/** "11 cidades de São Paulo e Minas Gerais" (curto, para o topo do site e o rodapé; a lista toda fica em texto_cidades()). */
function resumo_cidades(bool $siglas = false): string {
    $ufs = array_values(array_unique(array_map(fn($c) => $c[2], CIDADES)));
    $nomes = array_map(fn($uf) => $siglas ? $uf : (NOMES_ESTADOS[$uf] ?? $uf), $ufs);
    $estados = count($nomes) > 1 ? implode(', ', array_slice($nomes, 0, -1)) . ' e ' . end($nomes) : ($nomes[0] ?? '');
    return count(CIDADES) === 1 ? nome_cidade((string)array_key_first(CIDADES)) . " ($estados)" : count(CIDADES) . " cidades de $estados";
}
function texto_cidades(): string {
    $por_uf = [];
    foreach (CIDADES as [$nome, , $uf]) $por_uf[$uf][] = $nome;
    $partes = [];
    foreach ($por_uf as $uf => $nomes) $partes[] = (count($nomes) > 1 ? implode(', ', array_slice($nomes, 0, -1)) . ' e ' . end($nomes) : $nomes[0]) . " ($uf)";
    return count($partes) > 1 ? implode(', ', array_slice($partes, 0, -1)) . ' e ' . end($partes) : $partes[0];
}

// Planos do dono do comércio (ver inc/mercadopago.php):
//   Grátis (17/11, passo 78, pedido do usuário): assume a ficha com a conta Google, sem pagar, e corrige o que já vem do
//             Google (CAMPOS_GRATIS em inc/moderacao.php: nome, telefone, WhatsApp, endereço, bairro, site, horário).
//             Não é um plano no banco: é o acesso do dono (acessos_dono) sem Básico nem Destaque. Selo "Dados confirmados pelo dono".
//   Básico: pagamento único (logo, fotos, descrição, produtos e serviços, Instagram; vale para sempre)
//   Destaque: assinatura mensal no cartão (tudo do Básico + selo "Verificado", responder avaliações, números da página
//             e o topo da tela inicial e da área)
//   Destaque + Site (10/11, mudança 018): assinatura mensal no cartão com tudo do Destaque + landing page própria feita
//             pela equipe do Localiza Fácil e domínio incluso (a equipe faz o site depois do pagamento; o endereço vai no campo `site` da ficha)
const PLANOS_PRECO = ['basico' => 9.90, 'destaque' => 29.90, 'site' => 89.90];
const PLANOS_NOME = ['basico' => 'Básico', 'destaque' => 'Destaque', 'site' => 'Destaque + Site'];
const PLANOS_DESTAQUE = ['destaque', 'site'];   // planos que dão os recursos do Destaque
function plano_unico(string $plano): bool { return $plano === 'basico'; }
/** A mudança 018 (plano Destaque + Site no banco) já foi aplicada? Antes dela o plano não aparece no site. */
function plano_site_pronto(): bool {
    static $ok = null;
    return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '018-plano-site.sql'");
}
/** Planos que o dono pode escolher agora (o Destaque + Site só depois da mudança 018). */
function planos_disponiveis(): array {
    return array_values(array_filter(array_keys(PLANOS_PRECO), fn($k) => $k !== 'site' || plano_site_pronto()));
}
function preco_plano(string $plano): string { return 'R$ ' . number_format(PLANOS_PRECO[$plano] ?? 0, 2, ',', '.'); }
/** "R$ 5,99 uma vez só" ou "R$ 29,90 por mês". */
function preco_plano_texto(string $plano): string { return preco_plano($plano) . (plano_unico($plano) ? ' uma vez só' : ' por mês'); }

// Assinatura do Destaque em dia: cortesia dada pelo admin, ou paga até hoje (assinatura_ate é estendida a cada
// mensalidade paga; cancelada continua valendo até o fim do mês já pago).
const SQL_ASSINATURA_EM_DIA = "(p.assinatura = 'cortesia' OR (p.assinatura IN ('ativa','cancelada') AND p.assinatura_ate >= CURDATE()))";
// Em dia para aparecer no app: Básico pago (uma vez só, vale para sempre) ou assinatura em dia.
// Destaque atrasado: sai do destaque e, se pagou o Básico, continua no app com o Básico.
const SQL_EM_DIA = '(p.basico_pago_em IS NOT NULL OR ' . SQL_ASSINATURA_EM_DIA . ')';
const SQL_DESTAQUE = "(p.plano IN ('destaque','site') AND " . SQL_ASSINATURA_EM_DIA . ')';
// Ficha paga (passo 59): pagou o Básico (ou se cadastrou sozinha). Nas listas vem logo depois do Destaque.
const SQL_FICHA_PAGA = '(p.importado = 0 OR p.basico_pago_em IS NOT NULL)';
/** 19/11 (passo 116): quem informou o horário (de graça) sobe nas listas, logo depois das fichas pagas, e ganha o selo. */
const SQL_COM_HORARIO = "(p.horarios IS NOT NULL AND p.horarios NOT IN ('', '[]', '{}'))";
// Ficha no Google (16/11, pedido do usuário): só com telefone ou WhatsApp. Sem nenhum dos dois, a ficha continua no
// site e no app, mas sai com noindex e fica fora do sitemap; volta sozinha quando ganhar um telefone ou WhatsApp.
const SQL_TEM_CONTATO = "(COALESCE(p.telefone, '') <> '' OR COALESCE(p.whatsapp, '') <> '')";
/**
 * Nome curto (16/11): muitos comércios põem palavras-chave no nome do Google ("Espaço Vital - A Essência | Clínica
 * Odontológica | Implantes | ..."). Fica a parte antes do primeiro "|" e, se ainda passar de 60 letras, corta na
 * última palavra inteira. O nome completo continua na ficha.
 */
function nome_curto(string $nome, int $max = 60): string {
    $n = trim(preg_split('/\s*[|｜]\s*/u', $nome)[0]);
    if (mb_strlen($n) < 3) $n = trim($nome);
    if (mb_strlen($n) <= $max) return $n;
    $c = mb_substr($n, 0, $max);
    $c = preg_replace('/\s+\S*$/u', '', $c) ?: $c;
    return preg_replace('/[\s\-–,.:;]+$/u', '', $c) . '…';   // 20/11: rtrim com "–" cortava meio "Ó" ou "À"
}
/** Ficha no Google: a regra é sql_no_google() (Plano D, passo 7). */
function ficha_no_google(array $p): bool {
    if (empty($p['id']) || !cidade_no_google((string)($p['cidade'] ?? ''))) return false;
    return (bool)valor('SELECT ' . sql_no_google() . ' FROM profissionais p WHERE p.id = ?', [(int)$p['id']]);
}

// Aparece no app: aprovado e com assinatura em dia.
// Comércios importados da lista de leads aparecem sem assinatura até o dono assumir o perfil.
// Com $cidade, só os daquela cidade (o slug vem de CIDADES, validado em carregar_cidades, então pode ir direto no SQL).
function visivel(?string $cidade = null): string {
    $sql = "p.status = 'ativo' AND (p.importado = 1 OR " . SQL_EM_DIA . ") AND c.ativa = 1";
    return $cidade !== null && isset(CIDADES[$cidade]) ? "$sql AND p.cidade = '$cidade'" : $sql;
}

// Ordem do dia: muda uma vez por dia, para ninguém ficar sempre no topo nem sempre no fim
function semente_dia(): int { return (int)date('Ymd'); }

// Comércio com dono: pagou o Básico, tem o Destaque em dia ou um admin liberou o painel (acesso ativo). Some o
// "Este comércio é seu?" e ninguém mais pede para assumir ou tirar pelo site/app.
const SQL_TEM_DONO = '(p.importado = 0 OR p.basico_pago_em IS NOT NULL OR ' . SQL_DESTAQUE . '
    OR EXISTS (SELECT 1 FROM acessos_dono ad WHERE ad.profissional_id = p.id AND ad.ativo = 1))';
/**
 * Plano D, passo 6 (20/11): ficha confirmada = conferida pela equipe (confirmado_em) ou com dono (SQL_TEM_DONO,
 * automático). As outras estão "a confirmar": vieram da lista do Google Maps e ninguém conferiu ainda.
 */
const SQL_CONFIRMADA = '(p.confirmado_em IS NOT NULL OR ' . SQL_TEM_DONO . ')';
function ficha_confirmada(array $p): bool {
    if (array_key_exists('confirmada', $p)) return (bool)$p['confirmada'];
    return !empty($p['confirmado_em']) || tem_dono($p);
}
/** Marca (ou desmarca, com $por = null) a ficha como conferida pela equipe. */
function marcar_confirmada(int $id, ?string $por): void {
    q('UPDATE profissionais SET confirmado_em = ' . ($por === null ? 'NULL' : 'NOW()') . ', confirmado_por = ? WHERE id = ?', [$por, $id]);
}
/** Por cidade: [cidade => ['total' =>, 'confirmadas' =>]] (só fichas ativas). */
function confirmacao_por_cidade(): array {
    $r = [];
    foreach (linhas('SELECT p.cidade, COUNT(*) AS total, SUM(' . SQL_CONFIRMADA . ") AS confirmadas FROM profissionais p
        WHERE p.status = 'ativo' GROUP BY p.cidade") as $l) $r[$l['cidade']] = ['total' => (int)$l['total'], 'confirmadas' => (int)$l['confirmadas']];
    return $r;
}
/**
 * Plano D, passo 7 (20/11, revisto a pedido do usuário): quem vai para o Google, sem precisar confirmar ninguém.
 *  - Nada enquanto 'sem_google' estiver no config (site no domínio da FSA, mesmas fichas do Localiza Fácil).
 *  - Ficha: telefone ou WhatsApp E (confirmada OU 5+ avaliações no Google [popularidade]), liberada EM ONDAS: começa com
 *    GOOGLE_ONDA fichas de cada cidade e a cada semana entram mais GOOGLE_ONDA, divididas entre as áreas com os pesos de
 *    GOOGLE_ONDA_PESOS (Veículos 25, Saúde 20, Alimentação 15, Casa 12, Mercados 10, Lojas 10, outras 8) e, em cada área,
 *    das mais conhecidas. Confirmadas entram sempre, fora da fila. Pausar/retomar as ondas em /admin/google.php.
 *  - Página da cidade sempre; área e tipo com 3+ fichas no Google; busca, bairro e "Aberto agora" fora.
 * Motivo: o Localiza Fácil caiu ao subir 59 mil fichas copiadas do Google Maps de uma vez; aqui o Google vê o site crescer devagar.
 */
const GOOGLE_ONDA = 100;            // fichas por cidade em cada onda (1 onda por semana)
const GOOGLE_MIN_AVALIACOES = 5;    // avaliações no Google para entrar na fila sem confirmação
/** 20/11 (pedido do usuário): ordem das áreas na fila do Google. Dentro de cada área, das mais conhecidas para as menos. */
const GOOGLE_PRIORIDADE_AREAS = ['veiculos', 'saude', 'alimentacao', 'casa', 'mercados', 'lojas'];   // depois: todas as outras
/** SQL com o lugar da área na fila (1 = Veículos ... 7 = outras), pelo id da categoria (não precisa do JOIN). */
function sql_prioridade_area(): string {
    static $sql = null;
    if ($sql !== null) return $sql;
    $ids = array_column(linhas('SELECT id, slug FROM categorias'), 'id', 'slug');
    $casos = '';
    foreach (GOOGLE_PRIORIDADE_AREAS as $i => $slug) if (isset($ids[$slug])) $casos .= ' WHEN ' . (int)$ids[$slug] . ' THEN ' . ($i + 1);
    return $sql = '(CASE p.categoria_id' . $casos . ' ELSE ' . (count(GOOGLE_PRIORIDADE_AREAS) + 1) . ' END)';
}
function cidades_no_google(): array {
    return !empty(config()['sem_google']) ? [] : array_keys(CIDADES);
}
function cidade_no_google(string $cidade): bool { return in_array($cidade, cidades_no_google(), true); }

/** Estado das ondas: ['inicio' => 'Y-m-d'|null, 'pausado_em' => 'Y-m-d'|null, 'dias_pausados' => int]. */
function google_ondas_arquivo(): string { return config()['dados_dir'] . '/google-ondas.json'; }
function google_ondas_estado(): array {
    $e = is_file(google_ondas_arquivo()) ? (json_decode((string)file_get_contents(google_ondas_arquivo()), true) ?: []) : [];
    return ['inicio' => $e['inicio'] ?? null, 'pausado_em' => $e['pausado_em'] ?? null, 'dias_pausados' => (int)($e['dias_pausados'] ?? 0)];
}
function google_ondas_gravar(array $e): void {
    @file_put_contents(google_ondas_arquivo(), json_encode($e, JSON_PRETTY_PRINT), LOCK_EX);
}
/** Número da onda atual (1 = primeira). 0 = ainda fechado. A 1ª onda começa sozinha quando 'sem_google' sai do config. */
function google_onda_atual(): int {
    if (!empty(config()['sem_google'])) return 0;
    $e = google_ondas_estado();
    if (!$e['inicio']) { $e['inicio'] = hoje(); google_ondas_gravar($e); }
    $fim = $e['pausado_em'] ?: hoje();
    $dias = (int)floor((strtotime($fim) - strtotime($e['inicio'])) / 86400) - $e['dias_pausados'];
    return 1 + intdiv(max(0, $dias), 7);
}
function google_limite_por_cidade(): int { return google_onda_atual() * GOOGLE_ONDA; }

/**
 * 20/11 (opção B, escolhida pelo usuário): cada onda de GOOGLE_ONDA fichas é dividida entre as áreas, na ordem de
 * GOOGLE_PRIORIDADE_AREAS + "outras", com estes pesos (somam 100). Área que esgota a fila na cidade passa as vagas
 * para as próximas, na ordem de prioridade.
 */
const GOOGLE_ONDA_PESOS = [25, 20, 15, 12, 10, 10, 8];   // Veículos, Saúde, Alimentação, Casa, Mercados, Lojas, outras

/** Divide $total vagas entre os grupos (pesos, fila de cada um); sobra vai para os próximos, na ordem. */
function google_dividir(int $onda, array $fila): array {
    $pesos = GOOGLE_ONDA_PESOS;
    $vagas = [];
    $usado = 0;
    foreach ($pesos as $i => $peso) { $g = $i + 1; $vagas[$g] = min((int)($fila[$g] ?? 0), $peso * $onda); $usado += $vagas[$g]; }
    $sobra = $onda * GOOGLE_ONDA - $usado;
    foreach ($vagas as $g => $v) {
        if ($sobra <= 0) break;
        $mais = min($sobra, (int)($fila[$g] ?? 0) - $v);
        if ($mais > 0) { $vagas[$g] += $mais; $sobra -= $mais; }
    }
    return $vagas;
}
/** Cortes: [cidade => [grupo => null (o grupo inteiro) | [popularidade, id] (até essa ficha)]]. Guardado 1 h por onda. */
function google_cortes(): array {
    static $r = null;
    if ($r !== null) return $r;
    $r = [];
    $onda = google_onda_atual();
    if ($onda < 1) return $r;
    $arq = config()['dados_dir'] . '/cache/google-cortes.json';
    $c = is_file($arq) ? (json_decode((string)file_get_contents($arq), true) ?: []) : [];
    if (($c['onda'] ?? 0) === $onda && ($c['quando'] ?? 0) > time() - 3600 && ($c['cidades'] ?? null) === cidades_no_google())
        return $r = $c['cortes'];
    $prio = sql_prioridade_area();
    $base = "p.status = 'ativo' AND " . SQL_TEM_CONTATO . ' AND p.popularidade >= ' . GOOGLE_MIN_AVALIACOES;
    foreach (cidades_no_google() as $cid) {
        $fila = array_map('intval', array_column(linhas("SELECT $prio AS g, COUNT(*) AS n FROM profissionais p WHERE p.cidade = ? AND $base GROUP BY g", [$cid]), 'n', 'g'));
        foreach (google_dividir($onda, $fila) as $g => $n) {
            if ($n < 1) continue;
            if ($n >= ($fila[$g] ?? 0)) { $r[$cid][$g] = null; continue; }
            $l = linha("SELECT p.popularidade, p.id FROM profissionais p WHERE p.cidade = ? AND $base AND $prio = ?
                ORDER BY p.popularidade DESC, p.id ASC LIMIT 1 OFFSET " . ($n - 1), [$cid, $g]);
            if ($l) $r[$cid][$g] = [(int)$l['popularidade'], (int)$l['id']];
        }
        $r[$cid] ??= [];
    }
    @mkdir(dirname($arq), 0775, true);
    @file_put_contents($arq, json_encode(['onda' => $onda, 'quando' => time(), 'cidades' => cidades_no_google(), 'cortes' => $r]), LOCK_EX);
    return $r;
}
/** SQL da ficha que vai para o Google (slugs vêm de CIDADES, já validados em carregar_cidades()). */
function sql_no_google(): string {
    $cortes = google_cortes();
    if (!$cortes) return '(1 = 0)';
    $prio = sql_prioridade_area();
    $fila = [];
    foreach ($cortes as $c => $grupos) {
        $partes = [];
        foreach ($grupos as $g => $corte)
            $partes[] = "($prio = " . (int)$g . ($corte ? " AND (p.popularidade > {$corte[0]} OR (p.popularidade = {$corte[0]} AND p.id <= {$corte[1]}))" : '') . ')';
        if ($partes) $fila[] = "(p.cidade = '$c' AND (" . implode(' OR ', $partes) . '))';
    }
    return '(' . SQL_TEM_CONTATO . " AND p.cidade IN ('" . implode("','", array_keys($cortes)) . "') AND (" . SQL_CONFIRMADA
        . ($fila ? ' OR (p.popularidade >= ' . GOOGLE_MIN_AVALIACOES . ' AND (' . implode(' OR ', $fila) . '))' : '') . '))';
}
/** Quantas fichas de cada cidade estão no Google e quantas esperam na fila. */
function google_ondas_por_cidade(): array {
    $r = [];
    $no = sql_no_google();
    foreach (linhas("SELECT p.cidade, SUM($no) AS no_google, SUM(" . SQL_TEM_CONTATO . ' AND p.popularidade >= ' . GOOGLE_MIN_AVALIACOES . " AND NOT ($no)) AS na_fila
        FROM profissionais p WHERE p.status = 'ativo' GROUP BY p.cidade") as $l) $r[$l['cidade']] = ['no_google' => (int)$l['no_google'], 'na_fila' => (int)$l['na_fila']];
    return $r;
}
/** Pagou o Básico ou se cadastrou sozinho (não veio da lista pública). */
function ficha_paga(array $p): bool {
    if (array_key_exists('ficha_paga', $p)) return (bool)$p['ficha_paga'];
    return !$p['importado'] || !empty($p['basico_pago_em']);
}
/**
 * 23/11 (passo 72, pedido do usuário): Sobre (do dono e o do site), "O que faz ou vende", Instagram e "Atende em casa /
 * entrega" só aparecem para quem pagou o Básico (ou tem o Destaque em dia). Os dados continuam no banco (o robô e o admin
 * podem preencher); só não são mostrados em lugar nenhum (site, app, Google, listas) até pagar.
 */
const CAMPOS_DO_BASICO = ['descricao', 'servicos', 'instagram', 'facebook', 'atende_em_casa', 'sobre_site', 'sobre_site_url'];   // facebook: 23/11, pedido do usuário
const SQL_BASICO_LIBERADO = '(' . SQL_FICHA_PAGA . ' OR ' . SQL_DESTAQUE . ')';
function basico_liberado(array $p): bool {
    if (array_key_exists('basico_liberado', $p)) return (bool)$p['basico_liberado'];
    if (ficha_paga($p) || !empty($p['destaque_pago'])) return true;
    if (array_key_exists('destaque_pago', $p) || empty($p['id'])) return false;
    return (bool)valor('SELECT ' . SQL_DESTAQUE . ' FROM profissionais p WHERE p.id = ?', [(int)$p['id']]);
}
/** A ficha como o público pode ver: sem os campos do Básico se não pagou. */
function ficha_publica(array $p): array {
    $lib = basico_liberado($p);
    $p['basico_liberado'] = $lib;
    if (!$lib) foreach (CAMPOS_DO_BASICO as $c) if (array_key_exists($c, $p)) $p[$c] = $c === 'atende_em_casa' ? 0 : null;
    return $p;
}
function tem_dono(array $p): bool {
    if (array_key_exists('tem_dono', $p)) return (bool)$p['tem_dono'];
    return (bool)valor('SELECT ' . SQL_TEM_DONO . ' FROM profissionais p WHERE p.id = ?', [(int)$p['id']]);
}

const CAMPOS_RESUMO = 'p.id, p.nome, p.tipo, p.cidade, p.importado, p.servicos, p.bairro, p.atende_em_casa, p.foto, p.fotos, p.verificado, p.plano,
    ' . SQL_DESTAQUE . ' AS destaque_pago, ' . SQL_TEM_DONO . ' AS tem_dono, ' . SQL_FICHA_PAGA . ' AS ficha_paga,
    p.nota_media, p.total_avaliacoes, p.whatsapp, p.telefone, p.horarios, p.confirmado_em, p.confirmado_por, c.id AS categoria_id, c.nome AS categoria, c.icone, c.cor,
    p.catalogo_busca, p.catalogo_itens, c.slug AS categoria_slug';   // 22/11 (passo 45): catálogo na busca e no cartão

require_once __DIR__ . '/horarios.php';

function foto_url(?string $foto): ?string { return $foto ? url_publica('foto.php?f=' . rawurlencode($foto)) : null; }

/** Galeria do comércio (coluna fotos, lista JSON de arquivos) -> nomes dos arquivos, na ordem. */
/** Quantas fotos da galeria aparecem: Destaque em dia 10, os outros 2 (17/11). Fotos a mais ficam guardadas, só não aparecem. */
function max_fotos_plano(array $p): int { return !empty($p['destaque_pago']) ? 10 : 2; }
function lista_fotos(?string $json): array {
    $l = $json ? json_decode($json, true) : null;
    return is_array($l) ? array_values(array_filter($l, fn($f) => is_string($f) && preg_match('/^[a-z0-9-]{8,80}\.(jpg|png|webp)$/', $f))) : [];
}

// Ícone e cor do comércio: os da área, menos para tipos que pedem outros (19/11: barbearia fica em Beleza, mas com
// tesoura no lugar do batom e fundo marrom no lugar do rosa). Tipo => [ícone, cor].
const ICONES_TIPO = ['/barbear/iu' => ['content-cut', '#8B5A3C']];
function visual_tipo(array $p): ?array {
    foreach (ICONES_TIPO as $re => $v) if (preg_match($re, (string)($p['tipo'] ?? ''))) return $v;
    return null;
}
function icone_comercio(array $p): string { return visual_tipo($p)[0] ?? (string)$p['icone']; }
function cor_comercio(array $p): string { return visual_tipo($p)[1] ?? (string)$p['cor']; }

function prof_resumo(array $p): array {
    $p = ficha_publica($p);   // 23/11 (passo 72)
    return [
        'id' => (int)$p['id'],
        'nome' => $p['nome'],
        'categoria' => $p['categoria'],
        'categoriaId' => (int)$p['categoria_id'],
        'icone' => icone_comercio($p),
        'cor' => cor_comercio($p),
        'tipo' => $p['tipo'] ?: $p['categoria'],
        'servicos' => $p['servicos'] ? array_values(array_filter(array_map('trim', explode(',', $p['servicos'])))) : [],
        'bairro' => $p['bairro'],
        'cidade' => nome_cidade($p['cidade'], true),
        'atendeEmCasa' => (bool)$p['atende_em_casa'],
        'foto' => foto_url($p['foto']),   // logo do comércio
        'capa' => foto_url(lista_fotos($p['fotos'] ?? null)[0] ?? null),   // 1ª foto da galeria (faixa do destaque)
        'verificado' => (bool)$p['verificado'] && (bool)($p['destaque_pago'] ?? false),   // o selo é do plano Destaque
        'destaque' => (bool)($p['destaque_pago'] ?? false),   // plano Destaque com a assinatura em dia
        // 22/11 (passo 45): Destaque com catálogo: selo no cartão ("Cardápio", "Ofertas"...) e o item que a busca achou
        'catalogoAba' => !empty($p['destaque_pago']) && (int)($p['catalogo_itens'] ?? 0) > 0 ? catalogo_modelo($p)['aba'] : null,
        'catalogoAchou' => $p['catalogo_achou'] ?? null,
        'nota' => round((float)$p['nota_media'], 1),
        'avaliacoes' => (int)$p['total_avaliacoes'],
        'temWhatsapp' => (bool)$p['whatsapp'],
        'temTelefone' => (bool)$p['telefone'],
        'cadastrado' => !$p['importado'] || tem_dono($p),   // false = veio da lista pública, o dono ainda não assumiu
        // 17/11 (plano Grátis, passo 78): selos sem ser o Destaque. fichaCompleta = pagou o Básico (ou se cadastrou sozinho);
        // donoConfirmado = o dono assumiu (grátis ou pago): "Dados confirmados pelo dono". Apps antes do 1.9.0 não leem.
        'fichaCompleta' => ficha_paga($p),
        'donoConfirmado' => !$p['importado'] || tem_dono($p),
        // 19/11: true/false pelo horário do painel; null = horário não informado (filtro "Aberto agora" do app 1.12.0)
        'abertoAgora' => aberto_agora(horarios_ler($p['horarios'] ?? null)),
    ];
}

function categorias_ativas(?string $cidade = null): array {
    return array_map(fn($c) => [
        'id' => (int)$c['id'], 'nome' => $c['nome'], 'slug' => $c['slug'], 'icone' => $c['icone'], 'cor' => $c['cor'],
        'naInicial' => (bool)$c['na_inicial'], 'total' => (int)$c['total'],
    ], linhas('SELECT c.*, (SELECT COUNT(*) FROM profissionais p WHERE p.categoria_id = c.id AND ' . visivel($cidade) . ') AS total
        FROM categorias c WHERE c.ativa = 1 ORDER BY c.ordem, c.nome'));
}

function destaques(string $cidade, int $limite = 10): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND ' . SQL_DESTAQUE . " ORDER BY RAND(?) LIMIT $limite", [semente_dia()]));
}

// "Recomendados em <cidade>" da tela inicial do app (passo 23, 19/11, pedido do usuário): só quem pagou o Básico
// ou assina o Destaque (inclui Destaque + Site), sorteados uma vez por dia. Sem nenhum pagante, a faixa some no app.
const SQL_RECOMENDADO = '(p.basico_pago_em IS NOT NULL OR ' . SQL_DESTAQUE . ')';
function recomendados(string $cidade, int $limite = 12): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . ' AND ' . SQL_RECOMENDADO . " ORDER BY RAND(?) LIMIT $limite", [semente_dia()]));
}

/** "Comércios em destaque" da tela inicial: só quem paga o plano Destaque (nunca completa com outros). */
function vitrine_inicio(string $cidade, int $total = 10): array {
    return destaques($cidade, $total);
}

// Atalhos do "Perto de você": rótulo, ícone e partes do tipo de negócio que entram (LIKE). Ficam no servidor
// para poder mudar sem lançar app novo.
const ATALHOS_PERTO = [
    'restaurantes' => ['Restaurantes', 'silverware-fork-knife', ['Restaurante', 'Brasileira', 'Japonesa', 'Churrasc', 'Self-service', 'Marmit', 'Comida']],
    'lanches' => ['Lanches', 'hamburger', ['Lanchonete', 'Lanche', 'Fast Food', 'Hamburg', 'Pastéis', 'Pastel']],
    'pizzarias' => ['Pizzarias', 'pizza', ['Pizza']],
    'padarias' => ['Padarias', 'baguette', ['Padaria', 'Confeitaria']],
    'mercados' => ['Mercados', 'cart-outline', ['Mercado', 'Mercearia', 'Hortifruti', 'Empório', 'Açougue']],
    'farmacias' => ['Farmácias', 'pill', ['Farmácia', 'Drogaria']],
    'floriculturas' => ['Floriculturas', 'flower-tulip-outline', ['Floricultura', 'Flores']],
    'pet' => ['Pet shops', 'paw', ['Pet', 'Veterin', 'animais']],
    'beleza' => ['Beleza', 'content-cut', ['Salão de Beleza', 'Barbearia', 'Manicure', 'Cabeleireir', 'Estética', 'Sobrancelha']],
    'sorvetes' => ['Sorvetes', 'ice-cream', ['Sorvet', 'Açaí', 'Gelat']],
    'oficinas' => ['Oficinas', 'car-wrench', ['Oficina', 'Autopeças', 'Pneu', 'Borracharia', 'Lava-rápido']],
    'postos' => ['Postos', 'gas-station', ['Posto de combustível', 'Conveniência']],
    'saude' => ['Saúde', 'medical-bag', ['Dentista', 'Clínica', 'Médic', 'Fisioterap', 'Psicólog', 'Odonto', 'Laboratório']],
];

function atalhos_perto(): array {
    return array_map(fn($id, $a) => ['id' => $id, 'rotulo' => $a[0], 'icone' => $a[1]], array_keys(ATALHOS_PERTO), ATALHOS_PERTO);
}

/**
 * Comércios mais perto de um ponto (todas as cidades), do mais perto ao mais longe, até $raio_km.
 * $atalho (de ATALHOS_PERTO) filtra pelo tipo de negócio. A distância é em linha reta e aproximada
 * (muitos endereços só foram achados pela rua).
 */
// só comércios da cidade escolhida no app (quem está em São João olhando Poços não vê os de São João)
function perto(float $lat, float $lng, ?string $atalho, string $cidade, float $raio_km = 15, int $limite = 30): array {
    $filtro = '';
    $params = [$lat, $lat];
    if ($atalho !== null && isset(ATALHOS_PERTO[$atalho])) {
        $partes = ATALHOS_PERTO[$atalho][2];
        $filtro = ' AND (' . implode(' OR ', array_fill(0, count($partes), 'p.tipo LIKE ?')) . ')';
        foreach ($partes as $t) $params[] = '%' . $t . '%';
    }
    // caixa para usar o índice, depois a distância de verdade (Haversine, raio da Terra 6371 km)
    $dlat = $raio_km / 111; $dlng = $raio_km / (111 * max(0.2, cos(deg2rad($lat))));
    $params = array_merge($params, [$lat - $dlat, $lat + $dlat, $lng - $dlng, $lng + $dlng, $raio_km]);
    return array_map(fn($p) => prof_resumo($p) + ['distancia' => round((float)$p['distancia'], 2)], linhas('SELECT ' . CAMPOS_RESUMO . ',
        6371 * 2 * ASIN(SQRT(POW(SIN(RADIANS(p.lat - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(p.lat)) * POW(SIN(RADIANS(p.lng - ' . (float)$lng . ') / 2), 2))) AS distancia
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . " AND p.lat IS NOT NULL$filtro AND p.lat BETWEEN ? AND ? AND p.lng BETWEEN ? AND ?
        HAVING distancia <= ? ORDER BY distancia LIMIT $limite", $params));
}

// tipos de negócio dentro de uma área (filtros da tela da área), do mais comum ao menos
function tipos_da_categoria(int $categoria_id, string $cidade): array {
    return array_map(fn($t) => ['nome' => $t['tipo'], 'total' => (int)$t['total']], linhas('SELECT p.tipo, COUNT(*) AS total
        FROM profissionais p JOIN categorias c ON c.id = p.categoria_id WHERE ' . visivel($cidade) . ' AND p.categoria_id = ? AND p.tipo IS NOT NULL
        GROUP BY p.tipo ORDER BY total DESC, p.tipo LIMIT 30', [$categoria_id]));
}

// destaques primeiro, depois ficha paga (Básico ou cadastro próprio), depois quem tem nota; rodízio diário dentro de cada grupo
function da_categoria(int $categoria_id, string $cidade): array {
    return array_map('prof_resumo', linhas('SELECT ' . CAMPOS_RESUMO . ' FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE ' . visivel($cidade) . " AND p.categoria_id = ?
        ORDER BY " . SQL_DESTAQUE . " DESC, " . SQL_FICHA_PAGA . " DESC, " . SQL_COM_HORARIO . " DESC, p.total_avaliacoes > 0 DESC, RAND(?)",
        [$categoria_id, semente_dia()]));
}

// $cidade null = todas (busca do site, para o dono achar o negócio)
/** Busca do app (19/11, passo 106: regras em inc/busca.php, a mesma do site). */
function buscar(string $termo, ?string $cidade, int $limite = 50, bool $so_com_horario = false): array {
    require_once __DIR__ . '/busca.php';
    return array_map('prof_resumo', busca_executar($termo, $cidade, $limite, $so_com_horario)['lista']);
}

function profissional_visivel(int $id): ?array {
    return $id ? linha('SELECT p.*, ' . SQL_DESTAQUE . ' AS destaque_pago, ' . SQL_TEM_DONO . ' AS tem_dono, c.nome AS categoria, c.icone, c.cor FROM profissionais p JOIN categorias c ON c.id = p.categoria_id
        WHERE p.id = ? AND ' . visivel(), [$id]) : null;
}

function prof_completo(array $p, ?int $usuario_id): array {
    $p = ficha_publica($p);   // 23/11 (passo 72)
    $r = prof_resumo($p);
    $p += ['site' => null, 'mapa' => null];
    $r += [
        'responsavel' => $p['responsavel'],
        'descricao' => $p['descricao'],
        'telefone' => $p['telefone'] && telefone_central($p['telefone']) ? telefone_discar($p['telefone']) : $p['telefone'],   // central: sem DDD
        'telefoneFormatado' => $p['telefone'] ? telefone_formatado($p['telefone']) : null,
        'telefoneCentral' => $p['telefone'] ? telefone_central($p['telefone']) : false,   // 16/11: número nacional da rede, não da loja
        'whatsapp' => $p['whatsapp'] ? link_whatsapp($p['whatsapp'], 'Olá! Encontrei você no Localiza Fácil.') : null,
        'instagram' => $p['instagram'] ? 'https://instagram.com/' . ltrim($p['instagram'], '@') : null,
        'endereco' => $p['endereco'],
        'mapa' => $p['mapa'] ?: ($p['endereco'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($p['endereco'] . ', ' . nome_cidade($p['cidade']) . ' - ' . uf_cidade($p['cidade'])) : null),
        'site' => $p['site'],
        'email' => ($p['email'] ?? null) ?: (($p['email_contato'] ?? null) ?: null),   // 22/11 (passo 40); 23/11 (passo 70): ou o e-mail de contato do site
        'facebook' => ($p['facebook'] ?? null) ?: null,   // 23/11 (passo 70): página do Facebook (link completo)
        // 22/11 (passo 41): pedir pelo iFood e pelo 99Food (só áreas de comida)
        'ifood' => !empty($p['ifood']) && e_area_comida((int)$p['categoria_id']) ? $p['ifood'] : null,
        'food99' => !empty($p['food99']) && e_area_comida((int)$p['categoria_id']) ? $p['food99'] : null,
        'catalogo' => ($cat = catalogo_api($p)),   // 22/11 (passo 45): só Destaque com 1+ item
        // sem catálogo num segmento que costuma ter (comida, roupas, mercado...): espaço trancado "Pedir cardápio"
        'catalogoTrancado' => !$cat && ($m = catalogo_modelo($p))['chave'] !== 'geral' ? $m['curto'] : null,
        'linkSite' => url_publica(caminho_ficha((int)$p['id'], (string)$p['nome'], (string)$p['cidade'])),   // 19/11: compartilhar no app
        'linkAssumir' => $p['importado'] && !tem_dono($p) ? url_publica('planos.php?id=' . $p['id']) : null,
        'linkRemover' => $p['importado'] && !tem_dono($p) ? url_publica('perfil.php?id=' . $p['id'] . '&acao=remover') : null,
        'horario' => $p['horario'],
        'horarios' => horarios_api(horarios_ler($p['horarios'] ?? null)),   // um campo por linha (Segunda a sexta, Sábado...)
        'fotos' => array_map('foto_url', array_slice(lista_fotos($p['fotos'] ?? null), 0, max_fotos_plano($p))),   // carrossel da capa (Básico 2, Destaque 10)
        'favorito' => $usuario_id ? (bool)valor('SELECT 1 FROM favoritos WHERE usuario_id = ? AND profissional_id = ?', [$usuario_id, $p['id']]) : false,
        'minhaAvaliacao' => null,
        'comentarios' => array_map(fn($a) => [
            'id' => (int)$a['id'], 'autor' => primeiro_nome($a['autor']), 'estrelas' => (int)$a['estrelas'],
            'comentario' => $a['comentario'], 'resposta' => $a['resposta'], 'data' => data_br($a['criado_em']),
        ], linhas("SELECT a.*, u.nome AS autor FROM avaliacoes a JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.profissional_id = ? AND a.status = 'publicada' ORDER BY a.comentario IS NULL, a.atualizado_em DESC LIMIT 30", [$p['id']])),
    ];
    if ($usuario_id) {
        $m = linha('SELECT estrelas, comentario FROM avaliacoes WHERE profissional_id = ? AND usuario_id = ?', [$p['id'], $usuario_id]);
        if ($m) $r['minhaAvaliacao'] = ['estrelas' => (int)$m['estrelas'], 'comentario' => $m['comentario']];
    }
    require_once __DIR__ . '/ofertas.php';
    $r['oferta'] = oferta_api(oferta_no_ar((int)$p['id']));   // 19/11: Oferta da semana (apps antes da versão com ofertas ignoram)
    return $r;
}

// "Maria Aparecida Silva" → "Maria A." (privacidade de quem comenta)
function primeiro_nome(string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return $p[0] . (isset($p[1]) ? ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : '');
}

// ---------- iFood e 99Food (22/11, passo 41) ----------
/** Áreas de comida: só nelas aparecem os campos e os botões de iFood e 99Food. */
const AREAS_COMIDA = ['alimentacao', 'mercados'];
function e_area_comida(int $categoria_id): bool {
    static $ids = null;
    $ids ??= array_map('intval', array_column(linhas('SELECT id FROM categorias WHERE slug IN (' . implode(',', array_fill(0, count(AREAS_COMIDA), '?')) . ')', AREAS_COMIDA), 'id'));
    return in_array($categoria_id, $ids, true);
}
/**
 * Confere e limpa o link da página do comércio: 'ifood' só ifood.com.br; 'food99' só 99app.com/99food.
 * Tira o rastreio (?utm..., ?share_media...). Página de gestão do lojista e a página inicial do app não valem.
 * Devolve o link limpo ou null.
 */
function link_delivery(string $tipo, ?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    $u = parse_url($url);
    $host = strtolower((string)($u['host'] ?? ''));
    $caminho = (string)($u['path'] ?? '');
    if ($tipo === 'ifood') {
        if (!preg_match('/(^|\.)ifood\.com\.br$/', $host) || preg_match('/^(gestordepedidos|portal|parceiros)\./', $host)) return null;
        if (strlen(trim($caminho, '/')) < 3) return null;   // só "ifood.com.br": não é a página do comércio
    } else {
        if (!preg_match('/(^|\.)(99app\.com|99food\.com\.br|99food\.com)$/', $host)) return null;
        if (strlen(trim($caminho, '/')) < 3) return null;
    }
    return mb_substr('https://' . $host . $caminho, 0, 300);
}

function recalcular_nota(int $profissional_id): void {
    q("UPDATE profissionais SET
        nota_media = COALESCE((SELECT AVG(estrelas) FROM avaliacoes WHERE profissional_id = ? AND status = 'publicada'), 0),
        total_avaliacoes = (SELECT COUNT(*) FROM avaliacoes WHERE profissional_id = ? AND status = 'publicada')
        WHERE id = ?", [$profissional_id, $profissional_id, $profissional_id]);
    // 21/11 (passo 34): as páginas de cidade e área do site ficam prontas por até 1 h; sem isto a estrela nova só
    // aparecia nas listas do site depois que a cópia vencia. Avaliação é rara: refazer as páginas não pesa.
    web_cache_limpar();
}

// contar_contato(), contar_visita() e contar_aparicoes() ficam em inc/numeros.php (passo 20)
require_once __DIR__ . '/numeros.php';
require_once __DIR__ . '/catalogo.php';   // 22/11 (passo 45): catálogo da ficha (só Destaque)

// Municípios do Brasil (IBGE, dados/municipios.json: {estados: {UF: nome}, cidades: {UF: [nomes]}}) para o
// "Quero minha Cidade!": o app escolhe o estado e a cidade sai desta lista (nada de nome inventado).
function municipios(): array {
    static $m = null;
    return $m ??= json_decode((string)file_get_contents(__DIR__ . '/../dados/municipios.json'), true) ?: ['estados' => [], 'cidades' => []];
}
function sem_acento(string $s): string {
    return mb_strtolower(strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','î'=>'i',
        'ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ü'=>'u','ù'=>'u','ç'=>'c','Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e',
        'Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c']));
}
/** Nome oficial da cidade daquele estado (sem ligar para acento e maiúscula), ou null se não existe. */
function municipio_oficial(string $uf, string $nome): ?string {
    $alvo = sem_acento(trim(preg_replace('/\s+/', ' ', $nome)));
    foreach (municipios()['cidades'][$uf] ?? [] as $c) if (sem_acento($c) === $alvo) return $c;
    return null;
}

// ---------- endereços das páginas públicas (passo 21: versão web com SEO) ----------
// Cidade com o nome completo no endereço (pedido do usuário): localizafacilapp.com.br/sao-joao-da-boa-vista/saude/dentista/
/** "Clínica Odontológica São José" -> "clinica-odontologica-sao-jose". */
function slug_web(string $s): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', sem_acento($s)), '-');
}
/** Slug da cidade no endereço do site (nome completo), a partir do slug interno (sao-joao -> sao-joao-da-boa-vista). */
function cidade_web(string $cidade): string { return slug_web(TODAS_CIDADES[$cidade][0] ?? $cidade); }
/** Slug do endereço -> slug interno, só entre as cidades ativas. */
function cidade_da_web(string $web): ?string {
    foreach (CIDADES as $slug => $c) if (slug_web($c[0]) === $web) return $slug;
    return null;
}
function url_cidade(string $cidade): string { return url(cidade_web($cidade) . '/'); }
function url_area(string $cidade, string $area): string { return url(cidade_web($cidade) . "/$area/"); }
function url_tipo(string $cidade, string $area, string $tipo): string { return url(cidade_web($cidade) . "/$area/" . slug_web($tipo) . '/'); }
/** Ficha: /<cidade>/c/<nome>-<id> (o número no fim é o que vale; nome trocado redireciona para o certo). */
function caminho_ficha(int $id, string $nome, string $cidade): string {
    return cidade_web($cidade) . '/c/' . (rtrim(mb_substr(slug_web($nome), 0, 70), '-') ?: 'comercio') . '-' . $id;
}
function url_ficha(array $p): string { return url(caminho_ficha((int)$p['id'], (string)$p['nome'], (string)$p['cidade'])); }

// Cache das páginas públicas (inc/web.php): /var/lib/guia-novo/cache/web. Fica aqui porque o admin também limpa
// (aprovar alteração, ligar/desligar cidade).
function web_cache_dir(): ?string {
    $d = config()['dados_dir'] . '/cache/web';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return is_dir($d) && is_writable($d) ? $d : null;
}
/** Apaga as páginas guardadas (todas, ou só as mais velhas que $idade segundos). */
function web_cache_limpar(int $idade = 0): void {
    $dir = web_cache_dir();
    if (!$dir) return;
    foreach (glob("$dir/*.html") ?: [] as $f) if ($idade === 0 || filemtime($f) < time() - $idade) @unlink($f);
}
