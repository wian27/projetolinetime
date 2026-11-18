-- Migração LineTime: recursos sociais
-- Execute uma única vez no banco "linetime" depois de criar um backup.

ALTER TABLE usuarios
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  MODIFY nome VARCHAR(100) NOT NULL,
  MODIFY email VARCHAR(190) NOT NULL,
  MODIFY senha VARCHAR(255) NOT NULL,
  ADD COLUMN email_token_hash CHAR(64) NULL,
  ADD COLUMN email_token_expira DATETIME NULL,
  ADD COLUMN foto_perfil VARCHAR(255) NULL,
  ADD UNIQUE KEY uq_usuarios_email (email);

CREATE TABLE IF NOT EXISTS seguidores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seguidor_id INT NOT NULL,
  seguido_id INT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seguidor (seguidor_id, seguido_id),
  KEY idx_seguido (seguido_id, criado_em),
  CONSTRAINT fk_seguidor_usuario FOREIGN KEY (seguidor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_seguido_usuario FOREIGN KEY (seguido_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT chk_nao_seguir_a_si CHECK (seguidor_id <> seguido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publicacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  texto VARCHAR(2000) NOT NULL DEFAULT '',
  foto_path VARCHAR(255) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_publicacoes_data (criado_em, id),
  KEY idx_publicacoes_usuario (usuario_id, criado_em),
  CONSTRAINT fk_publicacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  remetente_id INT NOT NULL,
  destinatario_id INT NOT NULL,
  texto VARCHAR(2000) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mensagens_conversa (remetente_id, destinatario_id, id),
  KEY idx_mensagens_destino (destinatario_id, remetente_id, id),
  CONSTRAINT fk_mensagem_remetente FOREIGN KEY (remetente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_mensagem_destinatario FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
