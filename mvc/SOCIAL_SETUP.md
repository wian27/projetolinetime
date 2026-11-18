# LineTime: ativar recursos sociais

## O que entrou

- Feed com publicações de texto e foto.
- Upload de foto de perfil.
- Lista de pessoas e botão para seguir/deixar de seguir.
- Conversas privadas por texto entre contas confirmadas.
- Cadastro com link de confirmação de e-mail com token aleatório, hash no banco e validade de 2 horas.
- Senhas com password_hash; sessão protegida; consultas preparadas.
- Upload limitado a JPG, PNG e WebP de até 5 MB.

## Requisitos do servidor

PHP 8.1 ou superior com pdo_mysql e fileinfo, MySQL 5.7+ (recomendado MySQL 8), HTTPS e Apache com .htaccess habilitado para a pasta uploads.

Configure estas variáveis de ambiente no painel da hospedagem:

- LINETIME_DB_HOST: endereço do MySQL.
- LINETIME_DB_NAME: nome do banco (padrão linetime).
- LINETIME_DB_USER: usuário do banco.
- LINETIME_DB_PASSWORD: senha do banco.
- LINETIME_BASE_URL: URL pública que aponta para a pasta mvc, por exemplo https://seudominio.com/mvc.
- LINETIME_MAIL_FROM: endereço remetente autorizado para envio de e-mail.

O host precisa ter envio de e-mail configurado para mail() ou substitua esse envio por SMTP do provedor. Não coloque senha do banco no GitHub.

## Banco de dados

1. Faça backup do banco.
2. Abra social_migration.sql no phpMyAdmin e execute uma vez no banco linetime.
3. Se houver e-mails duplicados, resolva-os antes: a migração cria uma restrição única em usuarios.email.
4. Confirme que a coluna status já existe na tabela usuarios (ela vem de linetime.sql); status=1 significa e-mail confirmado.
5. Publique os arquivos PHP e crie a pasta mvc/uploads com permissão de escrita somente para o usuário do PHP. O .htaccess nela bloqueia execução de scripts.

## Como usar

Acesse /mvc/ para entrar ou criar uma conta. Depois do cadastro, abra o link enviado ao e-mail. Só contas confirmadas podem entrar. Na página social, use Início para publicar, Pessoas para seguir perfis, Mensagens para conversar e a foto do topo para trocar a foto de perfil.

O repositório não contém credenciais nem configuração de hospedagem. As funções dependem da aplicação da migração e das variáveis acima no servidor.
