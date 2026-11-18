<?php
declare(strict_types=1);
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once __DIR__ . '/../model/Conexao.php';

function registrationError(string $message): void {
    $_SESSION['cadastro_erro']=$message;
    header('Location: ../index.php#cadastro');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../index.php'); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) registrationError('Atualize a página e tente novamente.');

$name=trim((string)($_POST['nome'] ?? ''));
$email=filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password=(string)($_POST['senha'] ?? '');
$country=trim((string)($_POST['pais'] ?? ''));
$city=trim((string)($_POST['cidade'] ?? ''));
$state=trim((string)($_POST['estado'] ?? ''));
if ($name==='' || (function_exists('mb_strlen') ? mb_strlen($name,'UTF-8') : strlen($name))>400 || !$email || strlen($password)<10 || strlen($password)>200) {
    registrationError('Informe nome e e-mail válidos e use uma senha com pelo menos 10 caracteres.');
}
$baseUrl=rtrim((string)getenv('LINETIME_BASE_URL'),'/');
$from=(string)(getenv('LINETIME_MAIL_FROM') ?: '');
if ($baseUrl==='' || !filter_var($baseUrl,FILTER_VALIDATE_URL) || $from==='' || !filter_var($from,FILTER_VALIDATE_EMAIL)) {
    registrationError('O servidor ainda precisa configurar o endereço do site e o remetente de e-mail.');
}
try {
    $con=new Conexao(); $con->conectar(); $pdo=$con->getPdo();
    $token=bin2hex(random_bytes(32));
    $expires=(new DateTimeImmutable('+2 hours'))->format('Y-m-d H:i:s');
    $hash=hash('sha256',$token);
    $stmt=$pdo->prepare('INSERT INTO usuarios (nome,email,senha,pais,cidade,estado,status,email_token_hash,email_token_expira) VALUES (?,?,?,?,?,?,0,?,?)');
    $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$country,$city,$state,$hash,$expires]);
    $verifyUrl=$baseUrl.'/controller/confirmar-cadastro-controller.php?token='.rawurlencode($token);
    $subject='Confirme seu e-mail — LineTime';
    $body='<p>Olá '.htmlspecialchars($name,ENT_QUOTES,'UTF-8').',</p><p>Confirme seu e-mail para ativar sua conta:</p><p><a href="'.htmlspecialchars($verifyUrl,ENT_QUOTES,'UTF-8').'">Confirmar e-mail</a></p><p>O link expira em duas horas. Se você não criou esta conta, ignore esta mensagem.</p>';
    $headers="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: LineTime <".$from.">\r\n";
    if (!mail((string)$email,$subject,$body,$headers)) {
        $pdo->prepare('DELETE FROM usuarios WHERE email=? AND email_token_hash=?')->execute([$email,$hash]);
        registrationError('Não foi possível enviar o e-mail. Tente novamente mais tarde.');
    }
    $_SESSION['csrf']=bin2hex(random_bytes(32));
    $_SESSION['cadastro_ok']='Enviamos um link de confirmação para seu e-mail. Ele vale por 2 horas.';
    header('Location: ../index.php#cadastro'); exit;
} catch (PDOException $e) {
    if ($e->getCode()==='23000') registrationError('Este e-mail já está cadastrado.');
    error_log('LineTime cadastro: '.$e->getMessage());
    registrationError('Não foi possível concluir o cadastro. Confira o banco de dados.');
} catch (Throwable $e) {
    error_log('LineTime cadastro: '.$e->getMessage());
    registrationError('Não foi possível concluir o cadastro.');
}
