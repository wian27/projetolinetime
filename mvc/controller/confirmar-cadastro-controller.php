<?php
declare(strict_types=1);
require_once __DIR__ . '/../model/Conexao.php';
$token=(string)($_GET['token'] ?? '');
$ok=false;
if (preg_match('/^[a-f0-9]{64}$/D',$token)) {
    try {
        $con=new Conexao(); $con->conectar(); $pdo=$con->getPdo();
        $q=$pdo->prepare('UPDATE usuarios SET status=1,email_token_hash=NULL,email_token_expira=NULL WHERE email_token_hash=? AND email_token_expira>NOW() AND status=0');
        $q->execute([hash('sha256',$token)]);
        $ok=$q->rowCount()===1;
    } catch (Throwable $e) {
        error_log('LineTime confirmação: '.$e->getMessage());
    }
}
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Confirmação de e-mail — LineTime</title><body style="font:16px system-ui;max-width:560px;margin:12vh auto;padding:24px;color:#203328"><h1><?= $ok ? 'E-mail confirmado' : 'Link inválido ou expirado' ?></h1><p><?= $ok ? 'Sua conta LineTime está ativa. Agora você pode entrar.' : 'Peça um novo link de confirmação e tente novamente.' ?></p><a href="../index.php">Ir para o LineTime</a></body></html>
