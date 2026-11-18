<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../model/Conexao.php';

function failLogin(string $message): void {
    $_SESSION['login_erro'] = $message;
    header('Location: ../index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../index.php'); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) failLogin('Atualize a página e tente novamente.');
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string)($_POST['senha'] ?? '');
if (!$email || $password === '') failLogin('Informe seu e-mail e sua senha.');

try {
    $con = new Conexao(); $con->conectar(); $pdo = $con->getPdo();
    $query = $pdo->prepare('SELECT id,nome,email,senha,status FROM usuarios WHERE email=? LIMIT 1');
    $query->execute([$email]); $user = $query->fetch();
} catch (Throwable $e) {
    error_log('LineTime login: '.$e->getMessage());
    failLogin('Não foi possível acessar o banco. Confira a configuração.');
}
if (!$user || !password_verify($password, $user['senha'])) failLogin('E-mail ou senha inválidos.');
if ((int)$user['status'] !== 1) failLogin('Confirme seu e-mail antes de entrar.');
session_regenerate_id(true);
$_SESSION['usuarioId']=(int)$user['id'];
$_SESSION['usuarioNome']=$user['nome'];
$_SESSION['usuarioEmail']=$user['email'];
$_SESSION['usuarioStatus']=1;
$_SESSION['csrf']=bin2hex(random_bytes(32));
header('Location: ../social.php');
exit;
