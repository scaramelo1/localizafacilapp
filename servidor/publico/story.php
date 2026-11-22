<?php
// Imagem da ficha no formato Stories/Status (1080x1920 JPG) para o botão "Compartilhar" (21/11, passo 35).
// ?id=<ficha>. Gera com story-imagem.py (Pillow) e guarda em <dados_dir>/story/; refaz quando nome, tipo, nota,
// avaliações ou foto mudam (o nome do arquivo leva um resumo desses dados). Só fichas que aparecem no app.
require __DIR__ . '/../inc/base.php';
require_once __DIR__ . '/../inc/guia.php';

$p = profissional_visivel((int)($_GET['id'] ?? 0));
if (!$p) { http_response_code(404); exit; }
$foto = '';
foreach (array_merge([$p['foto']], lista_fotos($p['fotos'] ?? null)) as $f) {
    if ($f && preg_match('/^[a-z0-9-]{8,80}\.(jpg|png|webp)$/', (string)$f) && is_file($arq_f = config()['dados_dir'] . '/fotos/' . $f)) { $foto = $arq_f; break; }
}
$total = (int)$p['total_avaliacoes'];
$dados = [(string)$p['nome'], (string)($p['tipo'] ?: $p['categoria']), nome_cidade((string)$p['cidade']) . ' - ' . uf_cidade((string)$p['cidade']),
    number_format((float)$p['nota_media'], 1, ',', ''), (string)$total, $foto];
$dir = rtrim((string)config()['dados_dir'], '/') . '/story';
$arq = "$dir/" . (int)$p['id'] . '-' . substr(hash('sha256', implode('|', $dados)), 0, 12) . '.jpg';

if (!is_file($arq)) {
    @mkdir($dir, 0750, true);
    foreach (glob("$dir/" . (int)$p['id'] . '-*.jpg') ?: [] as $velho) @unlink($velho);   // versão antiga desta ficha
    exec('python3 ' . escapeshellarg(__DIR__ . '/../story-imagem.py') . ' ' . escapeshellarg($arq) . ' '
        . implode(' ', array_map('escapeshellarg', $dados)) . ' 2>&1', $saida, $erro);
    if ($erro || !is_file($arq)) {
        error_log('story.php: ' . implode(' ', $saida));
        header('Location: ' . url_publica('og.php?c=' . rawurlencode((string)$p['cidade'])), true, 302);
        exit;
    }
}
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');
header('Content-Disposition: inline; filename="localiza-facil-' . (int)$p['id'] . '.jpg"');
header('Content-Length: ' . filesize($arq));
readfile($arq);
