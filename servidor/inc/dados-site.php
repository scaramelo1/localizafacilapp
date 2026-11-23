<?php
// 23/11 (passo 70): leitura dos DADOS do site próprio do comércio (robô servidor/dados-do-site.php, aprovação em
// /admin/dados-site.php). Só lê o que o site mostra de forma clara: links de WhatsApp, tel:, Instagram, Facebook,
// mailto:, os dados estruturados (schema.org / JSON-LD) e a lista de serviços debaixo de um título "Serviços".
// Nada vai para a ficha sozinho.
require_once __DIR__ . '/site-robo.php';

const DS_CAMPOS = ['whatsapp' => 'WhatsApp', 'telefone' => 'Telefone', 'instagram' => 'Instagram', 'facebook' => 'Facebook',
    'email' => 'E-mail de contato', 'servicos' => 'O que faz ou vende', 'endereco' => 'Endereço'];
// link perto destas palavras é de quem FEZ o site (agência), não do comércio
const DS_AGENCIA = '/(desenvolvid[oa]|criad[oa] por|feito por|produzid[oa] por|site por|design por|ag[eê]ncia|powered by|made by|developed by|by\s*:)/iu';
const DS_IG_NAO = ['p', 'reel', 'reels', 'explore', 'accounts', 'stories', 'tv', 'direct', 'about', 'developer', 'legal', 'sharer', 'share', 'web', 'privacy', 'terms',
    'wix', 'wixbrasil', 'wordpress', 'hostgator', 'godaddy', 'squarespace', 'instagram'];
const DS_FB_NAO = ['sharer', 'sharer.php', 'share', 'share.php', 'plugins', 'tr', 'dialog', 'login', 'login.php', 'events', 'watch', 'hashtag',
    'policies', 'help', 'privacy', 'ads', 'business', 'groups', 'marketplace', 'gaming', 'legal', 'terms', 'photo.php', 'photo', 'story.php', 'permalink.php', 'l.php',
    // 23/11: perfis do criador de site / genéricos que vinham no rodapé de muitos sites
    'people', 'wix', 'wixportugues', 'wixbrasil', 'wordpress', 'hostgator', 'godaddy', 'squarespace', 'weebly', 'facebook', 'home.php', 'index.php'];
const DS_EMAIL_NAO = '/(@(.*\.)?(wix|wixpress|sentry|sentry-next|example|exemplo|domain|dominio|seudominio|seusite|email\.com$|godaddy|hostgator|locaweb|wordpress|w3|schema|cloudflare|googlemail\.test|test)\b|\.(png|jpe?g|gif|webp|svg|css|js)$|^(seu|your|nome|name|email|exemplo|example|user|usuario|dpo|lgpd|privacidade|privacy|encarregado|noreply|no-reply|naoresponda|nao-responda)@)/i';   // 23/11: dpo/lgpd = encarregado de dados, não é contato

/** Telefone brasileiro só com DDD + número (10 ou 11 dígitos, como na ficha), ou null. */
function ds_fone(string $n): ?string {
    $d = so_digitos($n);
    if (preg_match('/^0?(800|300)\d{7}$/', $d)) return strlen($d) === 10 ? '0' . $d : $d;   // 0800: fica como está
    if (strlen($d) >= 12 && str_starts_with($d, '55')) $d = substr($d, 2);
    if (strlen($d) >= 11 && $d[0] === '0') $d = substr($d, 1);   // "019 3634-..." / "0xx19..."
    if (!in_array(strlen($d), [10, 11], true)) return null;
    if ((int)substr($d, 0, 2) < 11 || $d[2] === '0' || $d[2] === '1') return null;
    if (strlen($d) === 11 && $d[2] !== '9') return null;
    if (preg_match('/^(\d)\1+$/', substr($d, 2))) return null;   // 99999-9999
    if (str_contains('01234567890123456789', $d) || str_contains('98765432109876543210', $d)) return null;   // 1234567890 (exemplo do criador de site)
    return $d;
}

/** Links (href) da página, com a posição no HTML. */
function ds_links(string $html): array {
    preg_match_all('#\b(?:href|data-href|data-url)\s*=\s*["\']([^"\']{4,500})["\']#i', $html, $mm, PREG_OFFSET_CAPTURE);
    $r = [];
    foreach ($mm[1] as [$u, $pos]) $r[] = [html_entity_decode(trim($u), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $pos];
    return $r;
}
/** O texto logo antes do link fala "desenvolvido por", "agência"...? */
function ds_de_agencia(string $html, int $pos): bool {
    $antes = sobre_limpar(substr($html, max(0, $pos - 400), min(400, $pos)));
    return (bool)preg_match(DS_AGENCIA, mb_substr($antes, -90));
}

/** Todos os objetos dos dados estruturados (JSON-LD) da página, achatados. */
function ds_jsonld(string $html): array {
    $nos = [];
    if (!preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $mm)) return [];
    foreach ($mm[1] as $js) {
        $d = json_decode(html_entity_decode(trim($js), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true) ?? json_decode(trim($js), true);
        if (!is_array($d)) continue;
        $pilha = [$d];
        while ($pilha) {
            $x = array_pop($pilha);
            if (!is_array($x)) continue;
            foreach ($x as $v) if (is_array($v)) $pilha[] = $v;
            if (!array_is_list($x)) $nos[] = $x;
        }
    }
    return $nos;
}

/** Instagram "@perfil" de um link, ou null. */
function ds_instagram(string $u): ?string {
    if (!preg_match('#^(?:https?:)?//(?:www\.|m\.)?instagram\.com/([^/?\#]+)#i', $u, $m)) return null;
    $h = strtolower(rtrim($m[1], '.'));
    if (in_array($h, DS_IG_NAO, true) || !preg_match('/^[a-z0-9._]{2,30}$/', $h)) return null;
    return $h;
}
/** Link limpo da página do Facebook, ou null. */
function ds_facebook(string $u): ?string {
    if (!preg_match('#^(?:https?:)?//(?:www\.|m\.|pt-br\.|web\.)?(?:facebook|fb)\.com/([^?\#]*)(\?[^\#]*)?#i', $u, $m)) return null;
    $partes = array_values(array_filter(explode('/', $m[1]), 'strlen'));
    if (!$partes) return null;
    $p0 = strtolower($partes[0]);
    if ($p0 === 'profile.php') return preg_match('/[?&]id=(\d{5,})/', $m[2] ?? '', $i) ? 'https://www.facebook.com/profile.php?id=' . $i[1] : null;
    if ($p0 === 'pages' && count($partes) >= 3) return 'https://www.facebook.com/pages/' . rawurlencode(rawurldecode($partes[1])) . '/' . $partes[2];
    if (in_array($p0, DS_FB_NAO, true) || !preg_match('/^[a-z0-9.\-]{3,80}$/i', $partes[0])) return null;
    return 'https://www.facebook.com/' . $partes[0];
}
function ds_email(string $e): ?string {
    $e = strtolower(trim(rawurldecode(preg_replace('/\?.*$/', '', $e))));
    if (!filter_var($e, FILTER_VALIDATE_EMAIL) || mb_strlen($e) > 160 || preg_match(DS_EMAIL_NAO, $e)) return null;
    return $e;
}

/** Itens curtos de uma lista de serviços: tira "Saiba mais", repetidos, frases longas. */
function ds_itens_servico(array $itens): array {
    $r = [];
    foreach ($itens as $t) {
        $t = trim(sobre_limpar((string)$t), " \t-–•·:|,.");
        if (mb_strlen($t) < 3 || mb_strlen($t) > 50 || !preg_match('/\p{L}{3,}/u', $t)) continue;
        if (preg_match('/^(saiba mais|ver mais|leia mais|veja mais|clique|confira|contato|fale conosco|home|início|inicio|menu|voltar|agende|agendar|orçamento|whatsapp|ligue|compre|comprar|enviar|todos( os)?|mais)\b/iu', $t)) continue;
        if (preg_match('/[.!?].+/u', $t) || substr_count($t, ' ') > 6) continue;   // frase, não nome de serviço
        $r[mb_strtolower($t)] ??= $t;
    }
    return array_values($r);
}
/** Lista de serviços debaixo de um título "Serviços", "Especialidades", "Tratamentos", "Nossos produtos"... */
function ds_servicos_html(string $html): array {
    $h = preg_replace('#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', ' ', $html);
    if (!preg_match_all('#<h([1-6])\b[^>]*>(.*?)</h\1>#is', $h, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) return [];
    $titulo_re = '/^(conhe[çc]a\s+)?(os\s+|as\s+)?(nossos?|nossas)?\s*(servi[çc]os|especialidades|tratamentos|procedimentos|o que fazemos|[áa]reas de atua[çc][ãa]o|produtos|solu[çc][õo]es|exames|cursos|modalidades)\b/iu';
    foreach ($mm as $i => $m) {
        $nivel = (int)$m[1][0];
        $t = sobre_limpar($m[2][0]);
        if (mb_strlen($t) > 40 || !preg_match($titulo_re, $t)) continue;
        // títulos mais "fundos" (h3 debaixo do h2) até o próximo título do mesmo nível
        $itens = []; $fim = strlen($h);
        for ($k = $i + 1; $k < count($mm); $k++) {
            if ((int)$mm[$k][1][0] <= $nivel) { $fim = $mm[$k][0][1]; break; }
            $itens[] = $mm[$k][2][0];
        }
        $itens = ds_itens_servico($itens);
        if (count($itens) < 2) {   // sem subtítulos: os itens de lista (<li>) logo abaixo do título
            $pedaco = substr($h, $m[0][1], min(6000, $fim - $m[0][1]));
            preg_match_all('#<li\b[^>]*>(.*?)</li>#is', $pedaco, $li);
            $itens = ds_itens_servico($li[1]);
        }
        if (count($itens) >= 2) return array_slice($itens, 0, 12);
    }
    return [];
}

/**
 * Lê as páginas de um site (inicial + contato) e devolve o que achou:
 * ['whatsapp' => [valores...], 'telefone' => [...], 'instagram' => [...], 'facebook' => [...], 'email' => [...],
 *  'servicos' => 'a, b, c', 'endereco' => ['rua' => ..., 'cidade' => ..., 'cep' => ..., 'bairro' => ...]]
 * Listas em ordem do mais repetido; links que parecem da agência que fez o site ficam de fora.
 */
function dados_extrair(array $paginas, string $host_site): array {
    $conta = ['whatsapp' => [], 'telefone' => [], 'instagram' => [], 'facebook' => [], 'email' => []];
    $soma = function (string $campo, ?string $v, int $peso = 1) use (&$conta) { if ($v !== null && $v !== '') $conta[$campo][$v] = ($conta[$campo][$v] ?? 0) + $peso; };
    $servicos = []; $endereco = null;
    foreach ($paginas as $html) {
        foreach (ds_links($html) as [$u, $pos]) {
            if (preg_match('#(wa\.me/|whatsapp\.com/|whatsapp:)#i', $u)) {
                if (preg_match('#(?:wa\.me/|[?&]phone=)(?:%2B|\+)?([\d\s().%-]{10,20})#i', $u, $m) && !ds_de_agencia($html, $pos)) $soma('whatsapp', ds_fone(rawurldecode($m[1])));
            } elseif (preg_match('#^tel:(.+)#i', $u, $m)) $soma('telefone', ds_fone(rawurldecode($m[1])));
            elseif (preg_match('#^mailto:(.+)#i', $u, $m)) { if (!ds_de_agencia($html, $pos)) $soma('email', ds_email($m[1]), 2); }
            elseif (stripos($u, 'instagram.com') !== false) { if (!ds_de_agencia($html, $pos)) $soma('instagram', ds_instagram($u)); }
            elseif (preg_match('#(facebook|fb)\.com#i', $u)) { if (!ds_de_agencia($html, $pos)) $soma('facebook', ds_facebook($u)); }
        }
        foreach (ds_jsonld($html) as $n) {
            foreach ((array)($n['telephone'] ?? []) as $t) if (is_string($t)) $soma('telefone', ds_fone($t), 2);
            foreach ((array)($n['email'] ?? []) as $e) if (is_string($e)) $soma('email', ds_email(preg_replace('/^mailto:/i', '', $e)), 2);
            foreach ((array)($n['sameAs'] ?? []) as $s) if (is_string($s)) { $soma('instagram', ds_instagram($s), 3); $soma('facebook', ds_facebook($s), 3); }
            $a = $n['address'] ?? null;
            if (!$endereco && is_array($a) && !empty($a['streetAddress']) && is_string($a['streetAddress']))
                $endereco = ['rua' => sobre_limpar($a['streetAddress']), 'cidade' => is_string($a['addressLocality'] ?? null) ? sobre_limpar($a['addressLocality']) : '',
                    'cep' => is_string($a['postalCode'] ?? null) ? sobre_limpar($a['postalCode']) : '', 'uf' => is_string($a['addressRegion'] ?? null) ? sobre_limpar($a['addressRegion']) : ''];
            // serviços: catálogo de ofertas (hasOfferCatalog / makesOffer) e knowsAbout
            foreach (['hasOfferCatalog', 'makesOffer'] as $k) if (isset($n[$k]) && is_array($n[$k])) {
                $pilha = [$n[$k]];
                while ($pilha) { $x = array_pop($pilha); if (!is_array($x)) continue;
                    foreach ($x as $kk => $v) { if (is_array($v)) $pilha[] = $v; elseif ($kk === 'name' && is_string($v) && ($x['@type'] ?? '') !== 'OfferCatalog') $servicos[] = $v; } }
            }
            foreach ((array)($n['knowsAbout'] ?? []) as $s) if (is_string($s)) $servicos[] = $s;
        }
        // e-mail escrito na página (sem link): só do domínio do próprio site
        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', hr_texto($html), $em))
            foreach ($em[0] as $e) { $e = ds_email($e); if ($e && str_ends_with($e, '@' . preg_replace('/^www\./', '', $host_site))) $soma('email', $e); }
        if (!$servicos) $servicos = ds_servicos_html($html);
    }
    $r = [];
    // (string): número vira chave inteira no PHP e não bate com o da ficha
    foreach ($conta as $campo => $vals) { arsort($vals); if ($vals) $r[$campo] = array_map('strval', array_keys($vals)); }
    // e-mail do mesmo domínio do site vem primeiro
    if (!empty($r['email'])) usort($r['email'], fn($a, $b) => (int)str_ends_with($b, '@' . preg_replace('/^www\./', '', $host_site)) <=> (int)str_ends_with($a, '@' . preg_replace('/^www\./', '', $host_site)));
    // telefone que já é o WhatsApp sai da lista de telefones
    if (!empty($r['telefone']) && !empty($r['whatsapp'])) $r['telefone'] = array_values(array_diff($r['telefone'], $r['whatsapp'])) ?: null;
    if (empty($r['telefone'])) unset($r['telefone']);
    $servicos = ds_itens_servico($servicos);
    if (count($servicos) >= 2) $r['servicos'] = mb_substr(implode(', ', array_slice($servicos, 0, 12)), 0, 500);
    if ($endereco && !endereco_invalido($endereco['rua']) && mb_strlen($endereco['rua']) >= 6 && preg_match('/\p{L}{3,}/u', $endereco['rua'])) $r['endereco'] = $endereco;
    return $r;
}

/** "São João da Boa Vista" ~ "sao joao da boa vista" (sem acento, minúsculo) */
function ds_sem_acento(string $t): string {
    $t = mb_strtolower(trim($t));
    return trim(preg_replace('/[^a-z0-9 ]+/', ' ', strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c'])));
}

/**
 * Compara o que o site tem com a ficha e devolve só o que serve:
 * [[campo, valor, valor_ficha|null, 'preencher'|'diferente', trecho], ...]
 * Nunca propõe trocar serviços nem endereço válido (só preencher o que falta / corrigir endereço inválido).
 */
function dados_comparar(array $achado, array $p): array {
    $r = [];
    $outros = fn(array $l) => count($l) > 1 ? 'o site tem também: ' . implode(', ', array_slice($l, 1, 3)) : null;
    foreach (['whatsapp' => 'whatsapp', 'telefone' => 'telefone'] as $campo => $col) {
        if (empty($achado[$campo])) continue;
        $ficha = so_digitos((string)$p[$col]);
        $todos = array_merge($achado['whatsapp'] ?? [], $achado['telefone'] ?? []);
        if ($ficha !== '' && in_array($ficha, $todos, true)) continue;   // o site confirma o da ficha
        if ($campo === 'telefone' && $ficha === '' && so_digitos((string)$p['whatsapp']) === $achado['telefone'][0]) continue;
        $r[] = [$campo, $achado[$campo][0], $ficha ?: null, $ficha === '' ? 'preencher' : 'diferente', $outros($achado[$campo])];
    }
    if (!empty($achado['instagram'])) {
        $ficha = strtolower(ltrim(trim((string)$p['instagram']), '@'));
        if ($ficha === '' || !in_array($ficha, $achado['instagram'], true))
            $r[] = ['instagram', $achado['instagram'][0], $ficha ?: null, $ficha === '' ? 'preencher' : 'diferente', $outros(array_map(fn($x) => "@$x", $achado['instagram']))];
    }
    if (!empty($achado['facebook'])) {
        $ficha = (string)($p['facebook'] ?? '');
        $igual = fn($a, $b) => strtolower(rtrim(preg_replace('#^https?://(www\.|m\.)?#i', '', $a), '/')) === strtolower(rtrim(preg_replace('#^https?://(www\.|m\.)?#i', '', $b), '/'));
        if ($ficha === '' || !array_filter($achado['facebook'], fn($f) => $igual($f, $ficha)))
            $r[] = ['facebook', $achado['facebook'][0], $ficha ?: null, $ficha === '' ? 'preencher' : 'diferente', $outros($achado['facebook'])];
    }
    if (!empty($achado['email'])) {
        $ficha = strtolower((string)(($p['email_contato'] ?? '') ?: ($p['email'] ?? '')));
        if ($ficha === '' || !in_array($ficha, $achado['email'], true))
            $r[] = ['email', $achado['email'][0], $ficha ?: null, $ficha === '' ? 'preencher' : 'diferente', $outros($achado['email'])];
    }
    if (!empty($achado['servicos']) && trim((string)$p['servicos']) === '')
        $r[] = ['servicos', $achado['servicos'], null, 'preencher', null];
    if (!empty($achado['endereco']) && (trim((string)$p['endereco']) === '' || endereco_invalido($p['endereco']))) {
        $e = $achado['endereco'];
        $cidade_ficha = ds_sem_acento(nome_cidade((string)$p['cidade']));
        $cidade_site = ds_sem_acento($e['cidade']);
        // endereço de outra cidade (matriz da rede): não serve
        if ($cidade_site === '' || str_contains($cidade_site, $cidade_ficha) || str_contains($cidade_ficha, $cidade_site))
            $r[] = ['endereco', mb_substr($e['rua'], 0, 200), trim((string)$p['endereco']) ?: null, 'preencher',
                trim(implode(' · ', array_filter([$e['cidade'] !== '' ? $e['cidade'] . ($e['uf'] ? ' - ' . $e['uf'] : '') : 'o site não diz a cidade', $e['cep'] ? 'CEP ' . $e['cep'] : '']))) ];
    }
    return $r;
}
