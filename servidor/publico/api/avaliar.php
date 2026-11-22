<?php
// POST: cria ou troca a avaliação da pessoa. DELETE ?id=: apaga a avaliação dela.
require __DIR__ . '/../../inc/api.php';
$m = metodo('POST', 'DELETE');
$u = exigir_usuario();
if ($m === 'DELETE') {
    $pid = (int)($_GET['id'] ?? 0);
    q('DELETE FROM avaliacoes WHERE profissional_id = ? AND usuario_id = ?', [$pid, $u['id']]);
    recalcular_nota($pid);
    responder(['ok' => true]);
}
$p = profissional_visivel((int)(corpo()['id'] ?? 0)) ?? erro('Profissional não encontrado.', 404);
$estrelas = (int)(corpo()['estrelas'] ?? 0);
if ($estrelas < 1 || $estrelas > 5) erro('Escolha de 1 a 5 estrelas.');
$coment = campo('comentario', 1000);
$antes = linha('SELECT estrelas, comentario FROM avaliacoes WHERE profissional_id = ? AND usuario_id = ?', [$p['id'], $u['id']]);
q('INSERT INTO avaliacoes (profissional_id, usuario_id, estrelas, comentario) VALUES (?,?,?,?)
   ON DUPLICATE KEY UPDATE estrelas = VALUES(estrelas), comentario = VALUES(comentario)',
   [$p['id'], $u['id'], $estrelas, $coment === '' ? null : $coment]);
recalcular_nota((int)$p['id']);
// 21/11 (passo 33): avisa o dono (celular e e-mail) e os admins (celular)
require_once __DIR__ . '/../../inc/avaliacao-aviso.php';
avisar_avaliacao($p, $u, $estrelas, $coment === '' ? null : $coment, $antes);
responder(['profissional' => prof_completo(profissional_visivel((int)$p['id']), (int)$u['id'])]);
