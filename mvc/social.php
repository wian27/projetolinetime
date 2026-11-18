<?php
declare(strict_types=1);
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once __DIR__ . '/model/Conexao.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

if (empty($_SESSION['usuarioId']) || (int)($_SESSION['usuarioStatus'] ?? 0) !== 1) {
    header('Location: index.html?erro=login');
    exit;
}
$userId = (int)$_SESSION['usuarioId'];
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

function db(): PDO {
    $con = new Conexao();
    $con->conectar();
    $pdo = $con->getPdo();
    if (!$pdo) throw new RuntimeException('Banco indisponível');
    return $pdo;
}
function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function requireCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) jsonOut(['erro'=>'Sessão expirada. Atualize a página.'], 403);
}
function safeText($value, int $max): string {
    $value = trim((string)$value);
    if ((function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $max) jsonOut(['erro'=>'O texto excede o limite permitido.'], 422);
    return $value;
}
function savePhoto(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) jsonOut(['erro'=>'A foto deve ter no máximo 5 MB.'], 422);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name'] ?? '');
    $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($types[$mime])) jsonOut(['erro'=>'Envie uma foto JPG, PNG ou WebP.'], 422);
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) jsonOut(['erro'=>'Não foi possível preparar o armazenamento de fotos.'], 500);
    $name = bin2hex(random_bytes(20)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) jsonOut(['erro'=>'Não foi possível salvar a foto.'], 500);
    return 'uploads/' . $name;
}

$api = $_GET['api'] ?? '';
if ($api !== '') {
    try {
        $pdo = db();
        if ($api === 'feed' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $q = $pdo->prepare("SELECT p.id,p.usuario_id,p.texto,p.foto_path,p.criado_em,u.nome,u.foto_perfil,
                (SELECT COUNT(*) FROM seguidores s WHERE s.seguido_id=p.usuario_id) seguidores
                FROM publicacoes p JOIN usuarios u ON u.id=p.usuario_id
                WHERE u.status=1 ORDER BY p.id DESC LIMIT 50");
            $q->execute();
            jsonOut(['posts'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($api === 'pessoas' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $q = $pdo->prepare("SELECT u.id,u.nome,u.cidade,u.estado,u.foto_perfil,
                (SELECT COUNT(*) FROM seguidores s WHERE s.seguido_id=u.id) seguidores,
                EXISTS(SELECT 1 FROM seguidores f WHERE f.seguidor_id=:me AND f.seguido_id=u.id) seguindo
                FROM usuarios u WHERE u.status=1 AND u.id<>:me2 ORDER BY u.nome LIMIT 100");
            $q->execute(['me'=>$userId,'me2'=>$userId]);
            jsonOut(['pessoas'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($api === 'seguir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $other = filter_var($data['usuario_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$other || $other === $userId) jsonOut(['erro'=>'Perfil inválido.'], 422);
            $q = $pdo->prepare('SELECT id FROM seguidores WHERE seguidor_id=? AND seguido_id=?');
            $q->execute([$userId,$other]);
            if ($q->fetch()) {
                $pdo->prepare('DELETE FROM seguidores WHERE seguidor_id=? AND seguido_id=?')->execute([$userId,$other]);
                $following = false;
            } else {
                $pdo->prepare('INSERT INTO seguidores (seguidor_id,seguido_id) VALUES (?,?)')->execute([$userId,$other]);
                $following = true;
            }
            jsonOut(['seguindo'=>$following]);
        }
        if ($api === 'publicar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            $text = safeText($_POST['texto'] ?? '', 2000);
            $photo = savePhoto($_FILES['foto'] ?? []);
            if ($text === '' && !$photo) jsonOut(['erro'=>'Escreva uma mensagem ou escolha uma foto.'], 422);
            $pdo->prepare('INSERT INTO publicacoes (usuario_id,texto,foto_path) VALUES (?,?,?)')->execute([$userId,$text,$photo]);
            jsonOut(['ok'=>true], 201);
        }
        if ($api === 'perfil-foto' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            $photo = savePhoto($_FILES['foto'] ?? []);
            if (!$photo) jsonOut(['erro'=>'Escolha uma foto.'], 422);
            $pdo->prepare('UPDATE usuarios SET foto_perfil=? WHERE id=?')->execute([$photo,$userId]);
            jsonOut(['foto'=>$photo]);
        }
        if ($api === 'conversas' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $q = $pdo->prepare("SELECT u.id,u.nome,u.foto_perfil,
                (SELECT m.texto FROM mensagens m WHERE (m.remetente_id=:me AND m.destinatario_id=u.id) OR (m.remetente_id=u.id AND m.destinatario_id=:me2) ORDER BY m.id DESC LIMIT 1) ultima
                FROM usuarios u WHERE u.status=1 AND u.id<>:me3
                AND EXISTS (SELECT 1 FROM mensagens m WHERE (m.remetente_id=:me4 AND m.destinatario_id=u.id) OR (m.remetente_id=u.id AND m.destinatario_id=:me5))
                ORDER BY u.nome");
            $q->execute(['me'=>$userId,'me2'=>$userId,'me3'=>$userId,'me4'=>$userId,'me5'=>$userId]);
            jsonOut(['conversas'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($api === 'mensagens' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $other = filter_var($_GET['com'] ?? null, FILTER_VALIDATE_INT);
            if (!$other || $other === $userId) jsonOut(['erro'=>'Conversa inválida.'], 422);
            $q = $pdo->prepare("SELECT m.id,m.remetente_id,m.destinatario_id,m.texto,m.criado_em,u.nome FROM mensagens m JOIN usuarios u ON u.id=m.remetente_id WHERE (m.remetente_id=? AND m.destinatario_id=?) OR (m.remetente_id=? AND m.destinatario_id=?) ORDER BY m.id DESC LIMIT 100");
            $q->execute([$userId,$other,$other,$userId]);
            jsonOut(['mensagens'=>array_reverse($q->fetchAll(PDO::FETCH_ASSOC))]);
        }
        if ($api === 'mensagens' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            requireCsrf();
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $other = filter_var($data['com'] ?? null, FILTER_VALIDATE_INT);
            $text = safeText($data['texto'] ?? '', 2000);
            if (!$other || $other === $userId || $text === '') jsonOut(['erro'=>'Informe a conversa e a mensagem.'], 422);
            $q = $pdo->prepare('SELECT id FROM usuarios WHERE id=? AND status=1');
            $q->execute([$other]);
            if (!$q->fetch()) jsonOut(['erro'=>'Perfil não encontrado.'], 404);
            $pdo->prepare('INSERT INTO mensagens (remetente_id,destinatario_id,texto) VALUES (?,?,?)')->execute([$userId,$other,$text]);
            jsonOut(['ok'=>true], 201);
        }
        jsonOut(['erro'=>'Ação não encontrada.'], 404);
    } catch (Throwable $e) {
        error_log('LineTime social: ' . $e->getMessage());
        jsonOut(['erro'=>'Não foi possível concluir. Confira a configuração do banco e tente novamente.'], 500);
    }
}

$pdo = db();
$q = $pdo->prepare('SELECT nome,email,foto_perfil FROM usuarios WHERE id=? AND status=1');
$q->execute([$userId]);
$me = $q->fetch(PDO::FETCH_ASSOC);
if (!$me) { session_destroy(); header('Location: index.html'); exit; }
$displayName = htmlspecialchars($me['nome'], ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#16834b"><link rel="manifest" href="./manifest.webmanifest"><link rel="icon" href="./icons/icon-192.png" type="image/png">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<title>LineTime — sua rede</title>
<style>
:root{--green:#16834b;--green2:#e9f5ed;--ink:#1d2922;--muted:#66736b;--line:#e1e9e3;--bg:#f4f7f4}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px system-ui,-apple-system,Segoe UI,sans-serif}header{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid var(--line);padding:12px max(16px,calc((100% - 1080px)/2));display:flex;align-items:center;gap:18px}header strong{font-size:23px;color:var(--green)}header span{color:var(--muted);margin-right:auto}.wrap{max-width:1080px;margin:24px auto;padding:0 16px;display:grid;grid-template-columns:220px minmax(0,1fr);gap:20px}.side,.card{background:white;border:1px solid var(--line);border-radius:16px;padding:16px}.side{height:max-content;position:sticky;top:78px}.side button,.tab{display:block;width:100%;text-align:left;border:0;background:white;padding:11px;border-radius:10px;font-weight:700;color:var(--ink);cursor:pointer}.side button:hover,.tab.active{background:var(--green2);color:var(--green)}main{min-width:0}.card{margin-bottom:15px}.row{display:flex;gap:10px;align-items:center}.row input,.card textarea,.search{border:1px solid var(--line);border-radius:10px;padding:11px;font:inherit}.row input{flex:1}.card textarea{width:100%;min-height:76px;margin:10px 0}.btn{border:0;border-radius:10px;background:var(--green);color:#fff;padding:10px 14px;font-weight:700;cursor:pointer}.muted{color:var(--muted);font-size:13px}.post{border-bottom:1px solid var(--line);padding:14px 0}.post:last-child{border:0}.post b{color:var(--green)}.post img{display:block;max-width:100%;max-height:480px;border-radius:12px;margin-top:10px}.item{display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--line);padding:12px 0}.item:last-child{border:0}.item .grow{flex:1}.msglist{max-height:50vh;overflow:auto;padding:12px;background:#f8faf8;border-radius:12px}.bubble{width:fit-content;max-width:85%;padding:9px 12px;margin:7px 0;background:#e7eee8;border-radius:12px}.bubble.mine{margin-left:auto;background:var(--green2)}.hidden{display:none}.notice{background:#fff5d9;padding:12px;border-radius:10px;margin-bottom:12px}@media(max-width:700px){.wrap{grid-template-columns:1fr;margin-top:12px}.side{position:static;display:flex;overflow:auto;padding:7px}.side button{white-space:nowrap;width:auto}.wrap{padding:0 10px}header{padding:12px}header span{display:none}}
</style></head><body>
<header><strong>LineTime</strong><span>Conecte-se, compartilhe e converse</span><button class="btn" id="pwa-install" type="button">Instalar app</button><label title="Alterar foto do perfil" style="display:flex;align-items:center;gap:8px;cursor:pointer"><img id="profile-photo" src="<?= htmlspecialchars($me['foto_perfil'] ?: '', ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover" <?= empty($me['foto_perfil']) ? 'hidden' : '' ?>><b><?= $displayName ?></b><input id="profile-photo-input" type="file" accept="image/jpeg,image/png,image/webp" hidden></label><a class="btn" href="controller/deslogar-controller.php" style="text-decoration:none">Sair</a></header>
<div class="wrap"><nav class="side"><button class="tab active" data-page="feed">Início</button><button class="tab" data-page="pessoas">Pessoas e seguidores</button><button class="tab" data-page="mensagens">Mensagens</button></nav>
<main><div id="status" class="muted" role="status"></div>
<section id="page-feed"><div class="card"><form id="post-form"><textarea name="texto" maxlength="2000" placeholder="O que você quer compartilhar?"></textarea><div class="row"><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"><button class="btn">Publicar</button></div><p class="muted">Fotos JPG, PNG ou WebP até 5 MB.</p></form></div><div class="card"><h2>Publicações</h2><div id="feed"></div></div></section>
<section id="page-pessoas" class="hidden"><div class="card"><h2>Pessoas</h2><p class="muted">Siga perfis para acompanhar suas publicações.</p><div id="people"></div></div></section>
<section id="page-mensagens" class="hidden"><div class="card"><h2>Mensagens</h2><div class="row"><select id="conversation" class="search" aria-label="Escolha uma conversa"><option value="">Escolha uma pessoa</option></select><button class="btn" id="load-conversation" type="button">Abrir</button></div><div id="messages" class="msglist" style="margin:12px 0"></div><form id="message-form" class="row"><input id="message-text" class="search" maxlength="2000" placeholder="Escreva uma mensagem" required><button class="btn">Enviar</button></form><p class="muted">As conversas são privadas entre os participantes. Evite enviar dados sensíveis.</p></div></section></main></div>
<script>
const token=document.querySelector('meta[name="csrf-token"]').content;
const statusBox=document.getElementById('status');
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function api(url,options={}){const headers=options.headers||{};headers['X-CSRF-Token']=token;if(!(options.body instanceof FormData))headers['Content-Type']='application/json';const r=await fetch(url,{...options,headers,credentials:'same-origin'});const data=await r.json();if(!r.ok)throw new Error(data.erro||'Falha na solicitação');return data}
function say(s){statusBox.textContent=s}
async function loadFeed(){const d=await api('?api=feed');document.getElementById('feed').innerHTML=d.posts.length?d.posts.map(p=>'<article class="post"><b>'+esc(p.nome)+'</b> <span class="muted"> · '+esc(p.criado_em)+'</span><p>'+esc(p.texto).replace(/\n/g,'<br>')+'</p>'+(p.foto_path?'<img loading="lazy" src="'+esc(p.foto_path)+'" alt="Foto da publicação">':'')+'</article>').join(''):'<p class="muted">Ainda não há publicações. Faça a primeira!</p>'}
async function loadPeople(){const d=await api('?api=pessoas');document.getElementById('people').innerHTML=d.pessoas.length?d.pessoas.map(p=>'<div class="item"><div class="grow"><b>'+esc(p.nome)+'</b><div class="muted">'+esc([p.cidade,p.estado].filter(Boolean).join(', '))+' · '+esc(p.seguidores)+' seguidores</div></div><button class="btn follow" data-id="'+Number(p.id)+'">'+(Number(p.seguindo)?'Deixar de seguir':'Seguir')+'</button><button class="btn message-person" data-id="'+Number(p.id)+'">Mensagem</button></div>').join(''):'<p class="muted">Nenhum outro perfil confirmado ainda.</p>';const select=document.getElementById('conversation');const old=select.value;select.innerHTML='<option value="">Escolha uma pessoa</option>'+d.pessoas.map(p=>'<option value="'+Number(p.id)+'">'+esc(p.nome)+'</option>').join('');if(old)select.value=old}
async function loadMessages(){const id=document.getElementById('conversation').value;if(!id)return say('Escolha uma pessoa para abrir a conversa.');const d=await api('?api=mensagens&com='+encodeURIComponent(id));document.getElementById('messages').innerHTML=d.mensagens.map(m=>'<div class="bubble '+(Number(m.remetente_id)===<?= $userId ?>?'mine':'')+'"><b>'+esc(m.nome)+'</b><div>'+esc(m.texto).replace(/\n/g,'<br>')+'</div><small class="muted">'+esc(m.criado_em)+'</small></div>').join('')||'<p class="muted">Envie a primeira mensagem.</p>';const box=document.getElementById('messages');box.scrollTop=box.scrollHeight}
document.querySelectorAll('.tab').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));b.classList.add('active');['feed','pessoas','mensagens'].forEach(x=>document.getElementById('page-'+x).classList.toggle('hidden',x!==b.dataset.page));say('');if(b.dataset.page==='pessoas')loadPeople().catch(e=>say(e.message))}));
document.getElementById('profile-photo-input').addEventListener('change',async e=>{if(!e.target.files[0])return;const form=new FormData();form.append('foto',e.target.files[0]);try{const r=await fetch('?api=perfil-foto',{method:'POST',body:form,headers:{'X-CSRF-Token':token},credentials:'same-origin'});const d=await r.json();if(!r.ok)throw new Error(d.erro);const img=document.getElementById('profile-photo');img.src=d.foto;img.hidden=false;say('Foto do perfil atualizada.')}catch(err){say(err.message)}});
document.getElementById('post-form').addEventListener('submit',async e=>{e.preventDefault();try{const d=new FormData(e.currentTarget);const r=await fetch('?api=publicar',{method:'POST',body:d,headers:{'X-CSRF-Token':token},credentials:'same-origin'});const x=await r.json();if(!r.ok)throw new Error(x.erro);e.currentTarget.reset();await loadFeed();say('Publicação enviada.')}catch(err){say(err.message)}});
document.getElementById('people').addEventListener('click',async e=>{const f=e.target.closest('.follow');if(f){f.disabled=true;try{await api('?api=seguir',{method:'POST',body:JSON.stringify({usuario_id:Number(f.dataset.id)})});await loadPeople();say('Lista de seguidores atualizada.')}catch(err){say(err.message)}return}const m=e.target.closest('.message-person');if(m){await loadPeople();document.getElementById('conversation').value=m.dataset.id;document.querySelector('[data-page="mensagens"]').click();loadMessages().catch(err=>say(err.message))}});
document.getElementById('load-conversation').addEventListener('click',()=>loadMessages().catch(e=>say(e.message)));
document.getElementById('message-form').addEventListener('submit',async e=>{e.preventDefault();const text=document.getElementById('message-text');try{await api('?api=mensagens',{method:'POST',body:JSON.stringify({com:Number(document.getElementById('conversation').value),texto:text.value})});text.value='';await loadMessages();say('Mensagem enviada.')}catch(err){say(err.message)}});
loadFeed().catch(e=>say(e.message));loadPeople().catch(e=>say(e.message));
<script>
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