<?php
// 21/11 (passo 32): "De onde vêm (entradas)" foi juntada com Números numa página só. Endereços antigos continuam valendo.
require __DIR__ . '/../../inc/admin.php';
exigir_admin();
$q = array_filter(['dias' => $_GET['dias'] ?? null, 'cidade' => $_GET['cidade'] ?? null, 'origem' => $_GET['origem'] ?? null], fn($v) => $v !== null && $v !== '');
header('Location: numeros.php' . ($q ? '?' . http_build_query($q) : ''), true, 301);
