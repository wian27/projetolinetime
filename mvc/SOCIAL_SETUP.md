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


## Instalação pelo navegador (PWA)

A pasta mvc inclui o manifesto, ícones Android/iOS e service worker. Depois de publicar em HTTPS:

- Android: abra /mvc/ no Chrome e toque em “Instalar LineTime” ou menu ⋮ > Instalar app.
- iPhone/iPad: abra /mvc/ no Safari, toque em Compartilhar > Adicionar à Tela de Início.
- O modo offline mostra apenas uma tela neutra. A aplicação não grava páginas PHP, conversas, perfis nem fotos pessoais no cache.

A hospedagem deve servir manifest.webmanifest como JSON e permitir service workers em HTTPS. O botão de instalação depende do suporte do navegador; o menu do navegador continua disponível como alternativa.

## O que falta antes da Play Store

A instalação pelo navegador é um PWA e não cria, por si só, um app Android para a Play Store. Uma opção é empacotar o LineTime como Trusted Web Activity (TWA). Para isso, será preciso definir URL pública HTTPS estável, nome de pacote Android, ícone/listagem final, assinar o app e publicar o arquivo Digital Asset Links em /.well-known/assetlinks.json para vincular o domínio ao pacote e à assinatura.

Antes de enviar, ainda faltam:

1. Moderação de conteúdo: termos aceitos antes de publicar, denúncia de posts/perfis e bloqueio de usuários. Isso é obrigatório para uma rede social com conteúdo público e mensagens 1:1.
2. Exclusão de conta: opção dentro do app e página externa para solicitar exclusão, removendo também publicações, fotos, seguidores e dados associados.
3. Política de privacidade pública dentro do app e na ficha da Play Store; preencher a seção Segurança dos dados com os dados reais coletados (cadastro, mensagens, fotos e metadados).
4. Segurança operacional: aplicar a migração, configurar MySQL e envio de e-mail no host, ativar HTTPS e revisar backups, limite de fotos e retenção/exclusão.
5. Empacotamento e revisão Android: criar e assinar um Android App Bundle, usar target API 36 para novos envios a partir de 31/08/2026, preencher classificação etária, público-alvo, acesso do revisor e ficha da loja.
6. Teste fechado se a conta de desenvolvedor for pessoal criada depois de 13/11/2023: mínimo de 12 testadores inscritos continuamente por 14 dias antes de solicitar acesso à produção.

A aprovação final é decidida pela revisão da Play Store e depende das declarações verdadeiras do app e das configurações reais do servidor. Consulte os requisitos oficiais antes do envio:
- PWA: https://developer.chrome.com/docs/modern-web-guidance/guides/pwa/install-web-app
- Trusted Web Activity: https://developer.android.com/develop/ui/views/layout/webapps/trusted-web-activities
- Conteúdo criado por usuários: https://support.google.com/googleplay/android-developer/answer/9876937
- Exclusão de conta e dados: https://support.google.com/googleplay/android-developer/answer/10144311
- Segurança dos dados: https://support.google.com/googleplay/android-developer/answer/10787469
- Target API: https://developer.android.com/google/play/requirements/target-sdk
