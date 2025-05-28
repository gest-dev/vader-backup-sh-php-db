#!/bin/sh

# Carrega variáveis do arquivo .env (no mesmo diretório do script)
set -o allexport
. "/home/u431758052/domains/gestclin.com.br/script_bk/.env"
set +o allexport

# Pasta e nome do backup
now="$(date +'%d_%m_%Y_%H_%M_%S')"
filename="db_backup_ativ_novo_$now.gz"
fullpathbackupfile="$BACKUP_FOLDER/$filename"

# Limpa todos os backups anteriores (opcional)
rm -f "$BACKUP_FOLDER"/db_backup_*

# Gera backup compactado
mysqldump --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" --default-character-set=utf8 "$DB_NAME" | gzip > "$fullpathbackupfile"

# Permissões apropriadas para ambiente compartilhado
chmod 644 "$fullpathbackupfile"

# Executa o script PHP de upload
php "$PHP_UPLOAD_SCRIPT"

# Retorna caminho do backup
echo "$fullpathbackupfile"

exit 0
