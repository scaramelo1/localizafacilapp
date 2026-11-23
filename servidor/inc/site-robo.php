<?php
// 23/11 (passo 61): pedaços comuns aos robôs que leem o site próprio do comércio (sobre-do-site.php e
// horario-do-site.php): baixar a página com limite de tamanho e tempo, respeitar o robots.txt e limpar o texto.

const SOBRE_UA = 'LocalizaFacilApp/1.0 (+https://localizafacilapp.com.br/contato.php)';
const SOBRE_NAO_PROPRIOS = '/(^|\.)(facebook\.com|fb\.com|instagram\.com|wa\.me|whatsapp\.com|linktr\.ee|linkin\.bio|bio\.link|beacons\.ai|ifood\.com\.br|99app\.com|google\.com|goo\.gl|g\.page|bit\.ly|tinyurl\.com|youtube\.com|tiktok\.com|twitter\.com|x\.com|gov\.br|mercadolivre\.com\.br|olx\.com\.br|doctoralia\.com\.br|booking\.com|tripadvisor\.com(\.br)?|airbnb\.com(\.br)?|ueniweb\.com|negocio\.site|business\.site)$/i';
// texto padrão de criador de site / em construção / genérico demais: não serve
const SOBRE_GENERICOS = '/(just another wordpress|site em constru|em manuten|coming soon|under construction|lorem ipsum|create your website|crie seu site|this domain|domínio (está )?à venda|parked|hostgator|locaweb|wix\.com|página inicial$|^home$|^início$|bem-vindo ao site|welcome to)/iu';

function sobre_baixar(string $url, int $max = 600000): array {
    $ch = curl_init($url);
    $corpo = '';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => SOBRE_UA, CURLOPT_ENCODING => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: pt-BR,pt;q=0.9'],
        CURLOPT_WRITEFUNCTION => function ($ch, $d) use (&$corpo, $max) { $corpo .= $d; return strlen($corpo) > $max ? 0 : strlen($d); }]);
    curl_exec($ch);
    $r = ['codigo' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'final' => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
        'tipo' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE), 'corpo' => $corpo, 'erro' => curl_errno($ch) && strlen($corpo) <= $max ? curl_error($ch) : ''];
    curl_close($ch);
    return $r;
}
/** robots.txt: o grupo "User-agent: *" (ou o nosso) proíbe "/"? */
function sobre_robots_proibe(string $base): bool {
    $r = sobre_baixar(rtrim($base, '/') . '/robots.txt', 100000);
    if ($r['codigo'] !== 200 || stripos($r['tipo'], 'text/plain') === false) return false;
    $vale = false; $proibe = false;
    foreach (preg_split('/\R/', $r['corpo']) as $l) {
        $l = trim(preg_replace('/#.*/', '', $l));
        if (preg_match('/^user-agent:\s*(.+)$/i', $l, $m)) { $ua = trim($m[1]); $vale = $ua === '*' || stripos($ua, 'localizafacil') !== false; continue; }
        if ($vale && preg_match('/^disallow:\s*(\S*)/i', $l, $m) && $m[1] === '/') $proibe = true;
    }
    return $proibe;
}
function sobre_limpar(string $t): string {
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
    return trim(preg_replace('/\s+/u', ' ', strip_tags($t)));
}

// ---------- horário escrito no site (23/11, passo 61) ----------
// Lê o horário de dois jeitos: (1) dados estruturados do site (openingHoursSpecification / openingHours) e
// (2) texto da página ("Segunda a sexta, das 8h às 18h", "SEG-SEX 08:00 - 18:00", "Sábado: 8h às 12h", "Domingo fechado").
// Devolve só o que entendeu, no formato da coluna horarios (semana, sab, dom). Sempre passa pela aprovação do admin.
const HR_DIAS = ['segunda' => 1, 'seg' => 1, 'terça' => 2, 'terca' => 2, 'ter' => 2, 'quarta' => 3, 'qua' => 3, 'quinta' => 4, 'qui' => 4,
    'sexta' => 5, 'sex' => 5, 'sábado' => 6, 'sabado' => 6, 'sáb' => 6, 'sab' => 6, 'domingo' => 7, 'dom' => 7];
const HR_DIA_RE = '(segunda|terça|terca|quarta|quinta|sexta|sábado|sabado|domingo|seg|ter|qua|qui|sex|sáb|sab|dom)(?:\.|-feira|-f)?';
const HR_HORA_RE = '(\d{1,2})(?:\s*(?::|h|hs|hrs)\s*(\d{2})?|\s*h)';

/** "8h", "08:00", "8h30", "18hs" -> "08:00" (ou null). */
function hr_hora(string $h, ?string $m): ?string {
    $h = (int)$h; $m = $m === null || $m === '' ? 0 : (int)$m;
    if ($h === 24) $h = 0;
    return $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59 ? sprintf('%02d:%02d', $h, $m) : null;
}
/** Dias (1..7) de "seg a sex", "segunda à sábado", "sábado". */
function hr_dias(string $de, ?string $ate): array {
    $a = HR_DIAS[mb_strtolower($de)] ?? null; $b = $ate !== null ? (HR_DIAS[mb_strtolower($ate)] ?? null) : $a;
    if (!$a || !$b) return [];
    return $a <= $b ? range($a, $b) : [];
}
/** Guarda o horário de cada dia (1..7); o primeiro que aparece no site vale. */
function hr_guardar(array &$dia, array $dias, array $v): void {
    foreach ($dias as $d) $dia[$d] ??= $v;
}
/**
 * Dias -> formato do site: semana só se segunda a sexta estão todos (iguais ou, se diferem, o horário que mais se
 * repete entre eles; o admin vê o trecho e corrige); sab = 6; dom = 7.
 */
function hr_campos(array $dia): array {
    $h = [];
    if (count(array_intersect_key($dia, array_flip([1, 2, 3, 4, 5]))) === 5) {
        $conta = [];
        foreach ([1, 2, 3, 4, 5] as $d) $conta[json_encode($dia[$d])] = ($conta[json_encode($dia[$d])] ?? 0) + 1;
        arsort($conta);
        $h['semana'] = json_decode((string)array_key_first($conta), true);
    }
    if (isset($dia[6])) $h['sab'] = $dia[6];
    if (isset($dia[7])) $h['dom'] = $dia[7];
    return $h;
}
/** Dados estruturados (JSON-LD) do site. */
function hr_do_jsonld(string $html): array {
    $h = [];
    $mapa = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7,
        'mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6, 'su' => 7];
    if (!preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $mm)) return [];
    foreach ($mm[1] as $js) {
        $d = json_decode(html_entity_decode(trim($js)), true);
        if (!is_array($d)) continue;
        $pilha = [$d];
        while ($pilha) {
            $x = array_pop($pilha);
            if (!is_array($x)) continue;
            foreach ($x as $v) if (is_array($v)) $pilha[] = $v;
            if (isset($x['dayOfWeek'], $x['opens'], $x['closes'])) {
                $dias = [];
                foreach ((array)$x['dayOfWeek'] as $dw) { $k = strtolower(basename((string)$dw)); if (isset($mapa[$k])) $dias[] = $mapa[$k]; }
                $ab = substr((string)$x['opens'], 0, 5); $fe = substr((string)$x['closes'], 0, 5);
                if ($dias && hora_valida($ab) && hora_valida($fe) && $ab !== $fe) hr_guardar($h, $dias, ['sit' => 'aberto', 'abre' => $ab, 'fecha' => $fe]);
            }
            if (isset($x['openingHours'])) {
                foreach ((array)$x['openingHours'] as $oh) {
                    if (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)(?:-(Mo|Tu|We|Th|Fr|Sa|Su))?\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/i', trim((string)$oh), $m)) {
                        $a = $mapa[strtolower($m[1])]; $b = $m[2] ? $mapa[strtolower($m[2])] : $a;
                        if ($a <= $b && $m[3] !== $m[4]) hr_guardar($h, range($a, $b), ['sit' => 'aberto', 'abre' => $m[3], 'fecha' => $m[4]]);
                    }
                }
            }
        }
    }
    return hr_campos($h);
}
/** Texto visível da página, sem scripts, estilos e menus. */
function hr_texto(string $html): string {
    $t = preg_replace('#<(script|style|noscript|svg|nav)\b[^>]*>.*?</\1>#is', ' ', $html);
    $t = preg_replace('#<(br|p|div|li|tr|h\d)\b[^>]*>#i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
    return preg_replace('/[ \t\x{00A0}]+/u', ' ', $t);
}
/**
 * [horarios (semana/sab/dom), trecho do site onde achou] ou [[], null].
 * Junta intervalos com almoço ("8h às 12h e 14h às 18h") num só: abre na 1ª hora e fecha na última.
 */
function horario_extrair(string $html): array {
    $h = hr_do_jsonld($html);
    if ($h) return [$h, 'dados do site (schema.org)'];
    $t = hr_texto($html);
    $hora = HR_HORA_RE; $ate = '(?:às|as|a|-|–|até)';
    $faixa = "$hora\\s*$ate\\s*$hora(?:\\s*(?:e|\\/|,)\\s*(?:das\\s*)?$hora\\s*$ate\\s*$hora)?";
    $diasre = '(?<![a-zà-ú])' . HR_DIA_RE . '(?:\\s*(?:a|à|ao|até|-|–)\\s*' . HR_DIA_RE . ')?';
    $dia = [];
    $trechos = [];
    // horas: grupos 1-8 da faixa (4 horas); devolve [abre, fecha] ou null
    $ler = function (array $m, int $i): ?array {
        $g = fn($k) => isset($m[$k]) && $m[$k][1] >= 0 ? $m[$k][0] : null;
        $ab = $g($i) !== null ? hr_hora($g($i), $g($i + 1)) : null;
        $k = $g($i + 4) !== null ? $i + 6 : $i + 2;   // com almoço, a última hora fecha
        $fe = $g($k) !== null ? hr_hora($g($k), $g($k + 1)) : null;
        return $ab && $fe && $ab !== $fe ? [$ab, $fe] : null;
    };
    // (1) dia(s) e depois a hora: "Segunda a sexta: 8h às 18h", "Domingo fechado"
    if (preg_match_all("/$diasre\\s*[:\\-–,]?\\s*(?:das|de|:)?\\s*(fechado|24\\s*h(?:oras)?|$faixa)/iu", $t, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($mm as $m) {
            $dias = hr_dias($m[1][0], isset($m[2]) && $m[2][1] >= 0 ? $m[2][0] : null);
            if (!$dias) continue;
            $o = mb_strtolower($m[3][0]);
            if (str_starts_with($o, 'fechado')) $v = ['sit' => 'fechado'];
            elseif (preg_match('/^24/', $o)) $v = ['sit' => '24h'];
            elseif ($x = $ler($m, 4)) $v = ['sit' => 'aberto', 'abre' => $x[0], 'fecha' => $x[1]];
            else continue;
            hr_guardar($dia, $dias, $v);
            $trechos[] = trim(preg_replace('/\\s+/u', ' ', $m[0][0]));
        }
    // (2) a hora e depois os dias: "07h às 21h - segunda a sábado". Só se (1) não achou nada: em "Segunda a sexta 8h às
    // 18h Sábado 8h às 12h" este jeito colaria as horas de segunda a sexta no "Sábado" seguinte.
    if (!$dia && preg_match_all("/$faixa\\s*[:\\-–,]?\\s*(?:de\\s*)?$diasre/iu", $t, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        foreach ($mm as $m) {
            $dias = hr_dias($m[9][0], isset($m[10]) && $m[10][1] >= 0 ? $m[10][0] : null);
            if (!$dias || !($x = $ler($m, 1))) continue;
            hr_guardar($dia, $dias, ['sit' => 'aberto', 'abre' => $x[0], 'fecha' => $x[1]]);
            $trechos[] = trim(preg_replace('/\\s+/u', ' ', $m[0][0]));
        }
    $h = hr_campos($dia);
    if (!$h) return [[], null];
    // horário de missa, culto ou aula não é horário de funcionamento: o admin vê o trecho e decide
    return [$h, mb_substr(implode(' | ', array_unique($trechos)), 0, 300)];
}
