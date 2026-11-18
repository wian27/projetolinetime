<?php
declare(strict_types=1);
$https=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!empty($_SESSION['usuarioId']) && (int)($_SESSION['usuarioStatus'] ?? 0)===1) { header('Location: social.php'); exit; }
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf'];
$error=$_SESSION['login_erro'] ?? $_SESSION['cadastro_erro'] ?? '';
$success=$_SESSION['cadastro_ok'] ?? '';
unset($_SESSION['login_erro'],$_SESSION['cadastro_erro'],$_SESSION['cadastro_ok']);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#16834b"><link rel="manifest" href="./manifest.webmanifest"><link rel="icon" href="./icons/icon-192.png" type="image/png"><link rel="apple-touch-icon" href="./icons/icon-192.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="LineTime"><title>LineTime — entrar ou criar conta</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f7f3;color:#1d2922;font:16px system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:920px;margin:6vh auto;padding:16px}.brand{text-align:center;font-size:32px;font-weight:800;color:#16834b;margin-bottom:20px}.grid{display:grid;grid-template-columns:1fr 1.2fr;gap:18px}.card{background:#fff;border:1px solid #e1e9e3;border-radius:18px;padding:24px;box-shadow:0 12px 40px #14321c0a}h1{font-size:22px;margin-top:0}label{display:block;margin:12px 0 5px;font-weight:650}input,select{width:100%;padding:12px;border:1px solid #cedbd0;border-radius:10px;font:inherit}button{width:100%;border:0;border-radius:10px;background:#16834b;color:#fff;padding:12px;margin-top:15px;font:700 16px system-ui;cursor:pointer}.alert{padding:12px;border-radius:10px;margin-bottom:16px;background:#fff4d7}.ok{background:#e8f5eb}.muted{color:#657168;font-size:14px}.footer{text-align:center;color:#68746b;margin:18px}a{color:#16834b}@media(max-width:650px){.grid{grid-template-columns:1fr}.wrap{margin:2vh auto;padding:12px}.card{padding:19px}}
</style></head><body><div class="wrap"><div class="brand">LineTime</div><button class="install" id="pwa-install" type="button" style="display:block;max-width:280px;margin:0 auto 18px">Instalar LineTime no celular</button>
<?php if ($error): ?><div class="alert" role="alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert ok" role="status"><?= htmlspecialchars($success,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<div class="grid">
<section class="card"><h1>Entrar</h1><p class="muted">Acesse sua conta para conversar e compartilhar fotos.</p>
<form action="controller/login-controller.php" method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<label for="login-email">E-mail</label><input id="login-email" type="email" name="email" autocomplete="email" required maxlength="190">
<label for="login-password">Senha</label><input id="login-password" type="password" name="senha" autocomplete="current-password" required>
<button type="submit">Entrar</button></form></section>
<section class="card" id="cadastro"><h1>Criar conta</h1><p class="muted">Depois do cadastro, confirme seu endereço de e-mail pelo link enviado.</p>
<form action="controller/cadastrar-usuario.php" method="post">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>">
<label for="name">Nome</label><input id="name" name="nome" autocomplete="name" required maxlength="100">
<label for="email">E-mail</label><input id="email" type="email" name="email" autocomplete="email" required maxlength="190">
<label for="password">Senha</label><input id="password" type="password" name="senha" autocomplete="new-password" minlength="10" required><small class="muted">Use pelo menos 10 caracteres.</small>
<label for="country">País</label><input id="country" name="pais" value="Brasil" maxlength="100">
<label for="city">Cidade</label><input id="city" name="cidade" maxlength="100">
<label for="state">Estado</label><input id="state" name="estado" maxlength="100">
<button type="submit">Cadastrar e confirmar e-mail</button></form></section>
</div><p class="footer">LineTime · Conecte-se, compartilhe e converse.</p></div><script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('./sw.js').catch(() => {}));
}
let installPrompt = null;
const installButton = document.getElementById('pwa-install');
window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); installPrompt = event; });
installButton?.addEventListener('click', async () => {
  if (installPrompt) {
    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
    return;
  }
  alert('No Android: abra o menu ⋮ do navegador e toque em “Instalar app” ou “Adicionar à tela inicial”. No iPhone: Safari > Compartilhar > Adicionar à Tela de Início.');
});
window.addEventListener('appinstalled', () => { if (installButton) installButton.textContent = 'LineTime instalado'; });
</script></body></html>