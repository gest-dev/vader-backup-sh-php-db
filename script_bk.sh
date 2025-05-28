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
rm -f /home/u431758052/domains/gestclin.com.br/script_bk/backups_mysql/db_backup_*

# Gera backup compactado
mysqldump --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" --default-character-set=utf8 "$DB_NAME" | gzip > "$fullpathbackupfile"

# Permissões apropriadas para ambiente compartilhado
chmod 644 "$fullpathbackupfile"

S3_STORAGE_ACCESS_KEY_ID="$S3_STORAGE_ACCESS_KEY_ID" \
S3_STORAGE_SECRET_ACCESS_KEY="$S3_STORAGE_SECRET_ACCESS_KEY" \
S3_STORAGE_ENDPOINT="$S3_STORAGE_ENDPOINT" \
S3_STORAGE_BUCKET="$S3_STORAGE_BUCKET" \
HUBOOT_URL="$HUBOOT_URL" \
HUBOOT_KEY="$HUBOOT_KEY" \
HUBOOT_TOKEN="$HUBOOT_TOKEN" \
HUBOOT_GROUP_ID="$HUBOOT_GROUP_ID" \
# Executa o script PHP de upload
php "/home/u431758052/domains/gestclin.com.br/script_bk/upload_backup.php"

# Retorna caminho do backup
echo "$fullpathbackupfile"

exit 0
