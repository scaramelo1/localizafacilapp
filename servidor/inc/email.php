<?php
// E-mails do Localiza Fácil (21/11, pedido do usuário): saem pelo Gmail localizafacilapp@gmail.com com uma "senha de app"
// do Google (config smtp_usuario / smtp_senha em /etc/guia-novo/config.php). Nada é enviado na hora: tudo entra na fila
// (tabela emails) e o enviar-emails.php (cron de minuto em minuto) manda, respeitando o limite do Gmail (~500 por dia).
//   - equipe:  escritos pelos admins em /admin/emails.php (têm link "não quero mais receber")
//   - aviso:   automáticos (pagamento confirmado, alteração aprovada/recusada, resposta do suporte)
//   - contato: formulário /contato.php, chega no contato_email com "Responder" indo para quem escreveu
//   - teste:   botão "Mandar um teste para mim" do admin
// Nunca derruba quem chamou: falha ao pôr na fila só vai para o registro de erros.
declare(strict_types=1);
require_once __DIR__ . '/base.php';

const EMAIL_TIPOS = ['equipe' => 'Da equipe', 'aviso' => 'Aviso automático', 'contato' => 'Formulário de contato', 'teste' => 'Teste'];
const EMAIL_STATUS = ['fila' => 'Na fila', 'enviado' => 'Enviado', 'erro' => 'Não foi', 'pulado' => 'Pulado (pediu para não receber)'];
const EMAIL_LIMITE_PADRAO = 450;      // por 24 h; o Gmail comum bloqueia o envio por um dia acima de ~500
const EMAIL_POR_RODADA = 20;          // por minuto (cron)
const EMAIL_TENTATIVAS = 3;

function email_pronto(): bool {
    static $ok = null;
    try { return $ok ??= (bool)valor("SELECT 1 FROM migracoes WHERE nome = '037-emails.sql'"); } catch (PDOException) { return $ok = false; }
}
function email_usuario(): string { return mb_strtolower(trim((string)(config()['smtp_usuario'] ?? ''))); }
/** A senha de app do Google aparece com espaços ("abcd efgh ijkl mnop"); o Gmail quer sem. */
function email_senha(): string { return str_replace(' ', '', trim((string)(config()['smtp_senha'] ?? ''))); }
function email_configurado(): bool { return email_usuario() !== '' && email_senha() !== ''; }
function email_limite_dia(): int { return max(1, (int)(config()['email_limite_dia'] ?? EMAIL_LIMITE_PADRAO)); }
function email_enviados_24h(): int {
    return (int)valor("SELECT COUNT(*) FROM emails WHERE status = 'enviado' AND enviado_em > NOW() - INTERVAL 1 DAY");
}
/** Último problema geral (login recusado, Gmail fora): mostrado no admin até o próximo envio dar certo. */
function email_arquivo_problema(): string { return config()['dados_dir'] . '/email-problema.txt'; }
function email_problema(): ?string {
    $f = email_arquivo_problema();
    return is_file($f) ? (trim((string)file_get_contents($f)) ?: null) : null;
}

// ---------- descadastro ----------
function email_token(string $email): string {
    return substr(hash_hmac('sha256', mb_strtolower($email), 'descadastro|' . (string)(config()['db_senha'] ?? '')), 0, 32);
}
function email_link_descadastro(string $email): string {
    return url_publica('descadastrar.php?e=' . rawurlencode($email) . '&t=' . email_token($email));
}
function email_descadastrado(string $email): bool {
    return (bool)valor('SELECT 1 FROM emails_descadastro WHERE email = ?', [mb_strtolower($email)]);
}

// ---------- fila ----------
/**
 * Põe um e-mail na fila. $texto é texto simples: linha em branco separa parágrafos e endereços https:// viram link.
 * {nome} no assunto ou no texto vira o primeiro nome da pessoa. Devolve o id, ou null se não entrou.
 */
function email_enfileirar(string $para, ?string $nome, string $assunto, string $texto, string $tipo, array $extra = []): ?int {
    try {
        if (!email_pronto() || !isset(EMAIL_TIPOS[$tipo])) return null;
        $para = email_valido($para);
        if (!$para) return null;
        $assunto = email_trocar_nome($assunto, $nome);
        $texto = email_trocar_nome($texto, $nome);
        $status = $tipo === 'equipe' && email_descadastrado($para) ? 'pulado' : 'fila';
        return inserir('emails', [
            'para' => $para, 'nome' => $nome ? mb_substr($nome, 0, 120) : null, 'assunto' => mb_substr(trim($assunto), 0, 200),
            'texto' => $texto, 'tipo' => $tipo, 'status' => $status, 'lote_id' => $extra['lote_id'] ?? null,
            'responder_para' => isset($extra['responder_para']) ? email_valido($extra['responder_para']) : null,
            'admin_id' => $extra['admin_id'] ?? null, 'ip' => $extra['ip'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('localiza-facil email fila: ' . $e->getMessage());
        return null;
    }
}
function email_trocar_nome(string $s, ?string $nome): string {
    $n = $nome ? preg_split('/\s+/', trim($nome))[0] : '';
    $n = $n !== '' ? mb_convert_case(mb_strtolower($n), MB_CASE_TITLE) : '';
    return preg_replace('/ ?\{nome\}/u', $n !== '' ? " $n" : '', $s);
}

/** E-mail de quem fez o pedido (dono): o do pedido ou, se não tiver, o da conta Google ligada a ele. [email, nome] */
function email_do_pedido(?int $pedido_id): array {
    if (!$pedido_id) return [null, null];
    $p = linha('SELECT pe.email, pe.nome, u.email AS email_conta FROM pedidos pe LEFT JOIN usuarios u ON u.id = pe.usuario_id WHERE pe.id = ?', [$pedido_id]);
    if (!$p) return [null, null];
    return [email_valido($p['email']) ?? email_valido($p['email_conta']), $p['nome']];
}

/** Aviso automático para o dono de um pedido. */
function email_avisar_pedido(?int $pedido_id, string $assunto, string $texto): void {
    [$para, $nome] = email_do_pedido($pedido_id);
    if ($para) email_enfileirar($para, $nome, $assunto, $texto, 'aviso');
}

// ---------- montagem ----------
function email_cabecalho_utf8(string $s): string {
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}
function email_endereco(string $email, ?string $nome): string {
    $nome = trim(str_replace(['"', "\r", "\n"], '', (string)$nome));
    if ($nome === '') return "<$email>";
    return (preg_match('/[^\x20-\x7e]/', $nome) ? email_cabecalho_utf8($nome) : '"' . str_replace('\\', '', $nome) . '"') . " <$email>";
}

/** Texto simples → HTML: parágrafos, quebras de linha e links. */
function email_texto_html(string $texto): string {
    $pars = preg_split('/\n\s*\n/', str_replace("\r", '', trim($texto)));
    $out = '';
    foreach ($pars as $p) {
        $p = h($p);
        $p = preg_replace('~https?://[^\s<]+[^\s<.,;:!?)\]]~', '<a href="$0" style="color:#005C38;font-weight:bold">$0</a>', $p);
        $out .= '<p style="margin:0 0 16px">' . nl2br($p, false) . "</p>\n";
    }
    return $out;
}

function email_html(array $e): string {
    $site = url_publica();
    $logo = url_publica('assets/logo.png');
    $rodape = $e['tipo'] === 'equipe'
        ? 'Você recebeu este e-mail porque tem cadastro no Localiza Fácil. <a href="' . h(email_link_descadastro($e['para'])) . '" style="color:#5B675F">Não quero mais receber</a>.'
        : 'Localiza Fácil · O comércio da sua cidade, pertinho de você.';
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;background:#F4F6F1;font-family:Arial,Helvetica,sans-serif;color:#123222">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6F1"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:#005C38;padding:18px 24px"><a href="' . h($site) . '" style="text-decoration:none;color:#FFFFFF;font-size:22px;font-weight:bold;font-style:italic">'
        . '<img src="' . h($logo) . '" width="32" height="32" alt="" style="vertical-align:middle;border:0;margin-right:8px">Localiza <span style="color:#F7C600">Fácil</span></a></td></tr>'
        . '<tr><td style="padding:28px 24px 12px;font-size:16px;line-height:1.55">' . email_texto_html($e['texto']) . '</td></tr>'
        . '<tr><td style="padding:16px 24px 24px;border-top:1px solid #E2E6DF;font-size:12px;line-height:1.5;color:#5B675F">' . $rodape
        . '<br><a href="' . h($site) . '" style="color:#5B675F">' . h(preg_replace('~^https?://|/$~', '', $site)) . '</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Mensagem completa (cabeçalhos + corpo) no formato que vai depois do DATA. */
function email_mensagem(array $e): string {
    $de = email_usuario();
    $dominio = preg_replace('~^https?://([^/]+).*$~', '$1', url_publica()) ?: 'localizafacilapp.com.br';
    $limite = '=_lf_' . bin2hex(random_bytes(12));
    $texto = $e['texto'] . ($e['tipo'] === 'equipe' ? "\n\n--\nNão quer mais receber? " . email_link_descadastro($e['para']) : '');
    $cab = [
        'From' => email_endereco($de, 'Localiza Fácil'),
        'To' => email_endereco($e['para'], $e['nome'] ?? null),
        'Subject' => email_cabecalho_utf8($e['assunto']),
        'Date' => date('r'),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => "multipart/alternative; boundary=\"$limite\"",
    ];
    if (!empty($e['responder_para'])) $cab['Reply-To'] = '<' . $e['responder_para'] . '>';
    if ($e['tipo'] === 'equipe') {
        $cab['List-Unsubscribe'] = '<' . email_link_descadastro($e['para']) . '>';
        $cab['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
    }
    $m = '';
    foreach ($cab as $k => $v) $m .= "$k: $v\r\n";
    $m .= "\r\n--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto))
        . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode(email_html($e)))
        . "--$limite--\r\n";
    return $m;
}

// ---------- SMTP (Gmail, SSL na porta 465) ----------
final class EmailFalhaGeral extends RuntimeException {}   // login/conexão: para a rodada inteira, sem gastar tentativas

function smtp_ler($s): array {
    $txt = '';
    while (($l = fgets($s, 1024)) !== false) {
        $txt .= $l;
        if (strlen($l) < 4 || $l[3] !== '-') break;
    }
    if ($txt === '') throw new EmailFalhaGeral('O Gmail fechou a conexão.');
    return [(int)substr($txt, 0, 3), trim($txt)];
}
function smtp_cmd($s, string $cmd, array $esperado, bool $geral = false): string {
    if ($cmd !== '') fwrite($s, $cmd . "\r\n");
    [$cod, $txt] = smtp_ler($s);
    if (!in_array($cod, $esperado, true)) {
        $msg = mb_substr(preg_replace('/\s+/', ' ', $txt), 0, 230);
        throw $geral ? new EmailFalhaGeral($msg) : new RuntimeException($msg);
    }
    return $txt;
}
function smtp_abrir() {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $s = @stream_socket_client('ssl://smtp.gmail.com:465', $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) throw new EmailFalhaGeral("Não conectei no Gmail ($errstr).");
    stream_set_timeout($s, 30);
    smtp_cmd($s, '', [220], true);
    smtp_cmd($s, 'EHLO localizafacilapp.com.br', [250], true);
    smtp_cmd($s, 'AUTH LOGIN', [334], true);
    smtp_cmd($s, base64_encode(email_usuario()), [334], true);
    try {
        smtp_cmd($s, base64_encode(email_senha()), [235], true);
    } catch (EmailFalhaGeral $e) {
        throw new EmailFalhaGeral('O Gmail recusou o login (confira smtp_usuario e a senha de app em /etc/guia-novo/config.php): ' . $e->getMessage());
    }
    return $s;
}
function smtp_mandar($s, array $e): void {
    smtp_cmd($s, 'MAIL FROM:<' . email_usuario() . '>', [250], true);
    smtp_cmd($s, 'RCPT TO:<' . $e['para'] . '>', [250, 251]);
    smtp_cmd($s, 'DATA', [354], true);
    // linha que começa com ponto ganha outro ponto (regra do SMTP); o base64 nunca começa com ponto, os cabeçalhos também não
    $m = preg_replace('/^\./m', '..', email_mensagem($e));
    smtp_cmd($s, $m . "\r\n.", [250]);
}
function smtp_fechar($s): void {
    if (!is_resource($s)) return;
    @fwrite($s, "QUIT\r\n");
    @fclose($s);
}

/**
 * Manda o que estiver na fila (até $max e até o limite do dia). Devolve ['enviados','falhas','sobrou','parou'].
 * Erro de um endereço = tenta de novo mais tarde (3 vezes); erro geral (login, conexão) = para tudo e mostra no admin.
 */
function email_processar(int $max = EMAIL_POR_RODADA): array {
    $r = ['enviados' => 0, 'falhas' => 0, 'sobrou' => 0, 'parou' => null];
    if (!email_pronto()) return $r + ['parou' => 'Falta aplicar a mudança 037.'];
    if (!email_configurado()) { $r['parou'] = 'Falta a senha de app do Gmail no config.'; return $r; }
    $livres = email_limite_dia() - email_enviados_24h();
    if ($livres <= 0) { $r['parou'] = 'Limite do dia atingido (' . email_limite_dia() . ' em 24 h); continua sozinho depois.'; return $r; }
    $fila = linhas("SELECT * FROM emails WHERE status = 'fila' AND (proxima_em IS NULL OR proxima_em <= NOW())
        ORDER BY FIELD(tipo, 'teste', 'aviso', 'contato', 'equipe'), id LIMIT " . min($max, $livres));
    if (!$fila) return $r;
    $s = null;
    try {
        $s = smtp_abrir();
        foreach ($fila as $i => $e) {
            // marca antes (outra rodada do cron não pega o mesmo)
            if (q("UPDATE emails SET tentativas = tentativas + 1 WHERE id = ? AND status = 'fila' AND tentativas = ?", [$e['id'], $e['tentativas']])->rowCount() !== 1) continue;
            try {
                smtp_mandar($s, $e);
                q("UPDATE emails SET status = 'enviado', enviado_em = NOW(), erro = NULL WHERE id = ?", [$e['id']]);
                $r['enviados']++;
            } catch (EmailFalhaGeral $g) {
                q('UPDATE emails SET tentativas = tentativas - 1 WHERE id = ?', [$e['id']]);   // não foi culpa deste e-mail
                throw $g;
            } catch (RuntimeException $x) {
                $fim = (int)$e['tentativas'] + 1 >= EMAIL_TENTATIVAS;
                q('UPDATE emails SET status = ?, erro = ?, proxima_em = NOW() + INTERVAL ? MINUTE WHERE id = ?',
                    [$fim ? 'erro' : 'fila', mb_substr($x->getMessage(), 0, 255), 15 * ((int)$e['tentativas'] + 1), $e['id']]);
                $r['falhas']++;
                @fwrite($s, "RSET\r\n");
                smtp_ler($s);
            }
        }
        @unlink(email_arquivo_problema());
    } catch (EmailFalhaGeral $g) {
        $r['parou'] = $g->getMessage();
        @file_put_contents(email_arquivo_problema(), date('d/m/Y H:i') . ' — ' . $g->getMessage());
        error_log('localiza-facil email: ' . $g->getMessage());
    } finally {
        smtp_fechar($s);
    }
    $r['sobrou'] = (int)valor("SELECT COUNT(*) FROM emails WHERE status = 'fila'");
    return $r;
}

// ---------- para quem (admin) ----------
const EMAIL_PUBLICOS = [
    'donos' => 'Donos de comércio (quem pediu ou pagou plano)',
    'contas' => 'Contas do app (entraram com o Google)',
    'um' => 'Um e-mail só',
];

/** Lista [email => nome] do público escolhido, sem repetir e sem quem pediu para não receber. */
function email_destinatarios(string $publico, ?string $cidade, ?string $um = null): array {
    $lista = [];
    if ($publico === 'donos') {
        $sql = "SELECT COALESCE(NULLIF(pe.email, ''), u.email) AS email, pe.nome FROM pedidos pe
            LEFT JOIN usuarios u ON u.id = pe.usuario_id LEFT JOIN profissionais p ON p.id = pe.profissional_id
            WHERE pe.status <> 'recusado' AND pe.tipo IN ('assumir', 'incluir')" . ($cidade ? ' AND p.cidade = ?' : '') . ' ORDER BY pe.id DESC';
        $linhas = linhas($sql, $cidade ? [$cidade] : []);
    } elseif ($publico === 'contas') {
        $linhas = linhas('SELECT email, nome FROM usuarios WHERE ativo = 1 ORDER BY id DESC');
    } elseif ($publico === 'um') {
        $linhas = [['email' => $um, 'nome' => null]];
    } else {
        return [];
    }
    foreach ($linhas as $l) {
        $e = email_valido($l['email'] ?? null);
        if ($e && !isset($lista[$e])) $lista[$e] = $l['nome'];
    }
    if ($lista) {
        $fora = array_column(linhas('SELECT email FROM emails_descadastro'), 'email');
        foreach ($fora as $f) unset($lista[$f]);
    }
    return $lista;
}

/**
 * Todos os e-mails cadastrados (21/11, lista do /admin/emails.php): donos (pedidos) e contas do app, um por e-mail,
 * com de onde veio. [email => ['nome', 'comercios' => [[id, nome, cidade]], 'conta' => bool, 'desde', 'fora' => bool]]
 */
function email_cadastrados(): array {
    $lista = [];
    $junta = function (?string $email, ?string $nome, ?string $quando) use (&$lista): ?string {
        $e = email_valido($email);
        if (!$e) return null;
        $lista[$e] ??= ['nome' => null, 'comercios' => [], 'conta' => false, 'desde' => $quando, 'fora' => false];
        $lista[$e]['nome'] ??= $nome ?: null;
        if ($quando && (!$lista[$e]['desde'] || $quando < $lista[$e]['desde'])) $lista[$e]['desde'] = $quando;
        return $e;
    };
    foreach (linhas("SELECT COALESCE(NULLIF(pe.email, ''), u.email) AS email, pe.nome, pe.criado_em, p.id AS pid, p.nome AS comercio, p.cidade
        FROM pedidos pe LEFT JOIN usuarios u ON u.id = pe.usuario_id LEFT JOIN profissionais p ON p.id = pe.profissional_id
        WHERE pe.status <> 'recusado' AND pe.tipo IN ('assumir', 'incluir') ORDER BY pe.id") as $l) {
        $e = $junta($l['email'], $l['nome'], $l['criado_em']);
        if ($e && $l['pid'] && !in_array((int)$l['pid'], array_column($lista[$e]['comercios'], 0), true)) {
            $lista[$e]['comercios'][] = [(int)$l['pid'], (string)$l['comercio'], (string)$l['cidade']];
        }
    }
    foreach (linhas('SELECT email, nome, criado_em FROM usuarios WHERE ativo = 1 ORDER BY id') as $l) {
        if ($e = $junta($l['email'], $l['nome'], $l['criado_em'])) $lista[$e]['conta'] = true;
    }
    foreach (array_column(linhas('SELECT email FROM emails_descadastro'), 'email') as $f) if (isset($lista[$f])) $lista[$f]['fora'] = true;
    uasort($lista, fn($a, $b) => strcmp((string)$b['desde'], (string)$a['desde']));
    return $lista;
}

