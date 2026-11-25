<?php
// Aparições na pesquisa do Google (25/11, passo 87): lê o Search Console pela API com uma conta de serviço do Google
// Cloud (arquivo JSON em /etc/guia-novo/search-console.json, ou 'gsc_chave' no config). A conta de serviço precisa
// estar adicionada como usuário (Restrito basta) na propriedade do site no Search Console. Passo a passo:
// docs/search-console-api.md. O Google entrega os dados com 2 a 3 dias de atraso.
declare(strict_types=1);

function gp_chave_arquivo(): string { return (string)(config()['gsc_chave'] ?? '/etc/guia-novo/search-console.json'); }
/** Propriedade do Search Console (verificada pelo arquivo HTML = prefixo de URL). */
function gp_site(): string { return (string)(config()['gsc_site'] ?? 'https://localizafacilapp.com.br/'); }
function gp_ligado(): bool { return is_readable(gp_chave_arquivo()); }
/** A mudança 049 já foi aplicada? */
function gp_pronto(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '049-google-pesquisa.sql'"); } catch (PDOException) { return $ok = false; }
}

function gp_b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

/** Token de acesso (JWT assinado com a chave da conta de serviço). */
function gp_token(): string {
    $k = json_decode((string)@file_get_contents(gp_chave_arquivo()), true);
    if (!is_array($k) || empty($k['client_email']) || empty($k['private_key'])) throw new RuntimeException('Arquivo da chave inválido: ' . gp_chave_arquivo());
    $agora = time();
    $jwt = gp_b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . gp_b64(json_encode([
        'iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $agora, 'exp' => $agora + 3600]));
    if (!openssl_sign($jwt, $ass, $k['private_key'], OPENSSL_ALGO_SHA256)) throw new RuntimeException('Não deu para assinar com a chave.');
    $r = gp_http('https://oauth2.googleapis.com/token', http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . gp_b64($ass)]), 'application/x-www-form-urlencoded');
    if (empty($r['access_token'])) throw new RuntimeException('Google recusou a chave: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
    return $r['access_token'];
}

function gp_http(string $url, string $corpo, string $tipo, ?string $token = null): array {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array_filter(["Content-Type: $tipo", $token ? "Authorization: Bearer $token" : null])]);
    $txt = (string)curl_exec($c);
    $cod = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    $r = json_decode($txt, true);
    if ($cod !== 200) throw new RuntimeException("Google respondeu $cod: " . mb_substr($txt, 0, 400));
    return is_array($r) ? $r : [];
}

/** Uma consulta à API (searchAnalytics.query), já paginada. */
function gp_consultar(string $token, string $ini, string $fim, array $dimensoes): array {
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode(gp_site()) . '/searchAnalytics/query';
    $todas = [];
    for ($inicio = 0; $inicio < 100000; $inicio += 25000) {
        $r = gp_http($url, json_encode(['startDate' => $ini, 'endDate' => $fim, 'dimensions' => $dimensoes, 'rowLimit' => 25000,
            'startRow' => $inicio, 'dataState' => 'all']), 'application/json', $token);
        $linhas = $r['rows'] ?? [];
        array_push($todas, ...$linhas);
        if (count($linhas) < 25000) break;
    }
    return $todas;
}

/** Ficha e cidade de um endereço do site (/sao-joao-da-boa-vista/c/nome-123). */
function gp_ficha_da_pagina(string $url): array {
    $caminho = (string)parse_url($url, PHP_URL_PATH);
    $partes = array_values(array_filter(explode('/', $caminho), 'strlen'));
    $cidade = $partes ? cidade_da_web($partes[0]) : null;
    $id = preg_match('#/c/[^/]*-(\d+)/?$#', $caminho, $m) ? (int)$m[1] : null;
    return [$id, $cidade];
}

/** Busca os últimos $dias dias e regrava (o Google ainda ajusta os dias mais recentes). Devolve um resumo. */
function gp_atualizar(int $dias = 10): string {
    $fim = date('Y-m-d', strtotime('-1 day'));
    $ini = date('Y-m-d', strtotime("-$dias days"));
    $token = gp_token();
    $paginas = gp_consultar($token, $ini, $fim, ['date', 'page']);
    $consultas = gp_consultar($token, $ini, $fim, ['date', 'query']);
    $existe = array_flip(array_map('intval', array_column(linhas('SELECT id FROM profissionais'), 'id')));
    db()->beginTransaction();
    q('DELETE FROM google_paginas WHERE dia BETWEEN ? AND ?', [$ini, $fim]);
    q('DELETE FROM google_consultas WHERE dia BETWEEN ? AND ?', [$ini, $fim]);
    foreach ($paginas as $l) {
        [$dia, $pag] = $l['keys'];
        [$id, $cidade] = gp_ficha_da_pagina($pag);
        if ($id !== null && !isset($existe[$id])) $id = null;
        q('INSERT INTO google_paginas (dia, pagina, profissional_id, cidade, impressoes, cliques, posicao) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE impressoes = impressoes + VALUES(impressoes), cliques = cliques + VALUES(cliques)',
            [$dia, mb_substr((string)parse_url($pag, PHP_URL_PATH), 0, 255), $id, $cidade, (int)$l['impressions'], (int)$l['clicks'], round((float)$l['position'], 1)]);
    }
    foreach ($consultas as $l) {
        [$dia, $termo] = $l['keys'];
        q('INSERT INTO google_consultas (dia, consulta, impressoes, cliques, posicao) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE impressoes = impressoes + VALUES(impressoes), cliques = cliques + VALUES(cliques)',
            [$dia, mb_substr((string)$termo, 0, 190), (int)$l['impressions'], (int)$l['clicks'], round((float)$l['position'], 1)]);
    }
    db()->commit();
    @file_put_contents(config()['dados_dir'] . '/google-pesquisa-ultima.txt', date('Y-m-d H:i:s'));
    $imp = array_sum(array_map(fn($l) => (int)$l['impressions'], $paginas));
    return "$ini a $fim: " . count($paginas) . ' linhas de páginas (' . $imp . ' impressões), ' . count($consultas) . ' de pesquisas';
}

/** Última atualização feita (texto) ou null. */
function gp_ultima(): ?string {
    $a = config()['dados_dir'] . '/google-pesquisa-ultima.txt';
    return is_file($a) ? trim((string)file_get_contents($a)) : null;
}
