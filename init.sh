#!/data/data/com.termux/files/usr/bin/bash
set -euo pipefail

# WatrBX first-time setup for Termux/Linux.
# Run from the WatrBX project directory or pass the project directory as $1.

RESET='\033[0m'; BOLD='\033[1m'; CYAN='\033[36m'; GREEN='\033[32m'; YELLOW='\033[33m'; RED='\033[31m'
info(){ printf "${CYAN}==>${RESET} %s\n" "$*"; }
ok(){ printf "${GREEN}[OK]${RESET} %s\n" "$*"; }
warn(){ printf "${YELLOW}[!]${RESET} %s\n" "$*"; }
die(){ printf "${RED}[ERROR]${RESET} %s\n" "$*" >&2; exit 1; }
prompt(){ local var="$1" text="$2" default="${3:-}"; if [[ -n "$default" ]]; then read -r -p "$text [$default]: " "$var"; [[ -z "${!var}" ]] && printf -v "$var" '%s' "$default"; else read -r -p "$text: " "$var"; fi; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${1:-$PWD}"
[[ -d "$PROJECT_DIR" ]] || die "Project directory does not exist: $PROJECT_DIR"
PROJECT_DIR="$(cd "$PROJECT_DIR" && pwd)"

printf "\n${BOLD}${CYAN}WatrBX First-Time Setup${RESET}\n"
printf "This wizard configures a fresh/local WatrBX installation.\n\n"

[[ -f "$PROJECT_DIR/structure.sql" ]] || die "structure.sql was not found in $PROJECT_DIR"
[[ -f "$PROJECT_DIR/.env.example" ]] || die ".env.example was not found in $PROJECT_DIR"

command -v php >/dev/null 2>&1 || die "PHP is not installed. Install it with: pkg install php"
command -v openssl >/dev/null 2>&1 || die "OpenSSL is not installed. Install it with: pkg install openssl"
DBCLI=""
if command -v mariadb >/dev/null 2>&1; then DBCLI="mariadb"; elif command -v mysql >/dev/null 2>&1; then DBCLI="mysql"; else die "MariaDB client is not installed. Install it with: pkg install mariadb"; fi
ok "PHP: $(php -r 'echo PHP_VERSION;')"
ok "OpenSSL: $(openssl version | head -1)"
ok "Database client: $($DBCLI --version | head -1)"

cd "$PROJECT_DIR"

# Database server detection / optional start.
if ! "$DBCLI" -u root -e 'SELECT 1' >/dev/null 2>&1; then
    warn "MariaDB is not responding on the default local socket."
    if command -v mysqld >/dev/null 2>&1 && command -v mysqld_safe >/dev/null 2>&1; then
        read -r -p "Start MariaDB automatically with mysqld_safe? [Y/n]: " START_DB
        START_DB="${START_DB:-Y}"
        if [[ "$START_DB" =~ ^[Yy]$ ]]; then
            DATADIR="${PREFIX:-$HOME}/var/lib/mysql"
            mkdir -p "$DATADIR"
            if [[ ! -d "$DATADIR/mysql" ]]; then
                info "Initializing MariaDB data directory..."
                mariadb-install-db --datadir="$DATADIR" >/dev/null
            fi
            mysqld_safe --datadir="$DATADIR" >/tmp/watrbx-mysqld.log 2>&1 &
            DB_PID=$!
            for _ in {1..20}; do
                if "$DBCLI" -u root -e 'SELECT 1' >/dev/null 2>&1; then break; fi
                sleep 1
            done
            "$DBCLI" -u root -e 'SELECT 1' >/dev/null 2>&1 || die "MariaDB failed to start. Check /tmp/watrbx-mysqld.log"
            ok "MariaDB started."
        else
            die "Start MariaDB first, then run this setup again."
        fi
    else
        die "MariaDB is not running and mysqld_safe was not found."
    fi
fi

printf "\n${BOLD}1. Installation${RESET}\n"
read -r -p "Installation name [WatrBX]: " INSTALL_NAME
INSTALL_NAME="${INSTALL_NAME:-WatrBX}"
read -r -p "Base URL [http://localhost:8080]: " BASE_URL
BASE_URL="${BASE_URL:-http://localhost:8080}"

printf "\n${BOLD}2. Database${RESET}\n"
read -r -p "Database host [127.0.0.1]: " DB_HOST
DB_HOST="${DB_HOST:-127.0.0.1}"
read -r -p "Database port [3306]: " DB_PORT
DB_PORT="${DB_PORT:-3306}"
read -r -p "Database name [watrbx2015]: " DB_NAME
DB_NAME="${DB_NAME:-watrbx2015}"
if [[ ! "$DB_NAME" =~ ^[a-zA-Z0-9_]+$ ]]; then
    die "Invalid database name. Use only letters, numbers, and underscores."
fi
read -r -p "Database user [root]: " DB_USER
DB_USER="${DB_USER:-root}"
read -r -s -p "Database password [empty]: " DB_PASS
printf "\n"

# DB_NAME is validated above, so it is safe to use as a MySQL identifier.

info "Checking database credentials..."
if [[ -n "$DB_PASS" ]]; then
    "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e 'SELECT 1' >/dev/null 2>&1 || die "Could not connect to MariaDB with the supplied credentials."
else
    "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -e 'SELECT 1' >/dev/null 2>&1 || die "Could not connect to MariaDB with the supplied credentials."
fi
ok "Database credentials work."

read -r -p "Create/import database '$DB_NAME'? [Y/n]: " DO_DB
DO_DB="${DO_DB:-Y}"
if [[ "$DO_DB" =~ ^[Yy]$ ]]; then
    info "Creating database if necessary..."
    if [[ -n "$DB_PASS" ]]; then
        "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        TABLE_COUNT=$("$DBCLI" -N -s -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
        if [[ "$TABLE_COUNT" == "0" ]]; then
            "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < structure.sql
            ok "structure.sql imported."
        else
            warn "Database already contains $TABLE_COUNT table(s). Skipping structure.sql import to avoid overwriting data."
        fi
    else
        "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        TABLE_COUNT=$("$DBCLI" -N -s -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
        if [[ "$TABLE_COUNT" == "0" ]]; then
            "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" < structure.sql
            ok "structure.sql imported."
        else
            warn "Database already contains $TABLE_COUNT table(s). Skipping structure.sql import to avoid overwriting data."
        fi
    fi
fi

printf "\n${BOLD}3. Environment${RESET}\n"
if [[ -f .env ]]; then
    read -r -p ".env already exists. Replace it with the values from this wizard? [y/N]: " REPLACE_ENV
    REPLACE_ENV="${REPLACE_ENV:-N}"
else
    REPLACE_ENV="Y"
fi
if [[ "$REPLACE_ENV" =~ ^[Yy]$ ]]; then
    cp .env.example ".env.backup.$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true
    cat > .env <<ENV
APP_NAME="$(printf '%s' "$INSTALL_NAME" | sed 's/"/\\"/g')"
APP_DESC="User-generated MMO gaming site for kids, teens, and adults."
APP_ENV=local
APP_DEBUG=true

MAIL_USER=""
MAIL_PASS=""

TurnStileKey=""
ROBLOSECURITY=""

MAINTENANCE=false
ACTUAL_MAINTENANCE=false
CAN_PURCHASE=true
CAN_REGISTER=true
CAN_LOGIN=true

DISCORD_BOT_AUTH=""
internalwebhook=""
CONTAINERURL=""
CONTAINERPORT=""
CONTAINERADMINKEY=""

DB_HOST="$(printf '%s' "$DB_HOST" | sed 's/"/\\"/g')"
DB_NAME="$(printf '%s' "$DB_NAME" | sed 's/"/\\"/g')"
DB_USER="$(printf '%s' "$DB_USER" | sed 's/"/\\"/g')"
DB_PASS="$(printf '%s' "$DB_PASS" | sed 's/"/\\"/g')"

# Local development helper. The application may ignore this if unsupported.
BASE_URL="$(printf '%s' "$BASE_URL" | sed 's/"/\\"/g')"
ENV
    ok ".env created."
else
    warn "Keeping the existing .env. Make sure its DB_* values are correct."
fi

printf "\n${BOLD}4. PrivateNut RSA key${RESET}\n"
KEY_FILE="$PROJECT_DIR/storage/PrivateNut.pem"
mkdir -p "$PROJECT_DIR/storage"
if [[ -f "$KEY_FILE" ]]; then
    warn "Existing key found: $KEY_FILE"
    read -r -p "Keep this existing key? [Y/n]: " KEEP_KEY
    KEEP_KEY="${KEEP_KEY:-Y}"
    if [[ "$KEEP_KEY" =~ ^[Nn]$ ]]; then
        cp "$KEY_FILE" "$KEY_FILE.backup.$(date +%Y%m%d-%H%M%S)"
        read -r -p "RSA key size [2048]: " KEY_BITS
        KEY_BITS="${KEY_BITS:-2048}"
        openssl genrsa -out "$KEY_FILE" "$KEY_BITS" >/dev/null 2>&1
        chmod 600 "$KEY_FILE"
        ok "New PrivateNut.pem generated. Old key was backed up."
    else
        ok "Keeping existing PrivateNut.pem."
    fi
else
    read -r -p "RSA key size for PrivateNut.pem [2048]: " KEY_BITS
    KEY_BITS="${KEY_BITS:-2048}"
    [[ "$KEY_BITS" =~ ^(2048|3072|4096)$ ]] || die "Use 2048, 3072, or 4096 bits."
    openssl genrsa -out "$KEY_FILE" "$KEY_BITS" >/dev/null 2>&1
    chmod 600 "$KEY_FILE"
    ok "Generated $KEY_FILE"
fi
openssl rsa -in "$KEY_FILE" -check -noout >/dev/null 2>&1 || die "PrivateNut.pem failed RSA validation."
ok "PrivateNut.pem is valid."

printf "\n${BOLD}5. First administrator${RESET}\n"
read -r -p "Create/promote an administrator now? [Y/n]: " MAKE_ADMIN
MAKE_ADMIN="${MAKE_ADMIN:-Y}"
if [[ "$MAKE_ADMIN" =~ ^[Yy]$ ]]; then
    read -r -p "Admin username: " ADMIN_USER
    [[ "$ADMIN_USER" =~ ^[a-zA-Z0-9[:space:]]{3,20}$ ]] || die "Username must be 3-20 characters and contain only letters, numbers, and spaces."
    read -r -s -p "Admin password: " ADMIN_PASS
    printf "\n"
    [[ ${#ADMIN_PASS} -ge 1 ]] || die "Password cannot be empty."
    read -r -s -p "Confirm admin password: " ADMIN_PASS2
    printf "\n"
    [[ "$ADMIN_PASS" == "$ADMIN_PASS2" ]] || die "Passwords do not match."

    if [[ -n "$DB_PASS" ]]; then
        EXISTING_ID=$("$DBCLI" -N -s -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT id FROM users WHERE username='$(sql_quote "$ADMIN_USER")' LIMIT 1;" || true)
    else
        EXISTING_ID=$("$DBCLI" -N -s -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" -e "SELECT id FROM users WHERE username='$(sql_quote "$ADMIN_USER")' LIMIT 1;" || true)
    fi

    PASSWORD_HASH=$(ADMIN_PASSWORD="$ADMIN_PASS" php -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_DEFAULT);')
    REGTIME=$(date +%s)
    HASH_SQL="$(sql_quote "$PASSWORD_HASH")"
    USER_SQL="$(sql_quote "$ADMIN_USER")"

    if [[ -n "$EXISTING_ID" ]]; then
        info "User already exists with ID $EXISTING_ID. Promoting it to admin and updating its password."
        SQL="UPDATE users SET password='$HASH_SQL', is_admin=1 WHERE id=$EXISTING_ID;"
    else
        SQL="INSERT INTO users (username,password,gender,regtime,robux,tix,membership,blurb,is_admin) VALUES ('$USER_SQL','$HASH_SQL',NULL,$REGTIME,100,50,'None','',1);"
    fi
    if [[ -n "$DB_PASS" ]]; then
        "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$SQL"
    else
        "$DBCLI" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" -e "$SQL"
    fi
    ok "Administrator '$ADMIN_USER' is ready."
fi

printf "\n${BOLD}6. WatrBX directories${RESET}\n"
for dir in storage/assets storage/thumbnails storage/logs; do
    mkdir -p "$dir"
    ok "Created/checked $dir"
done

printf "\n${BOLD}7. PHP/project checks${RESET}\n"
php -l init.php >/dev/null && ok "init.php syntax OK"
php -l routes/webhandler.php >/dev/null && ok "routes/webhandler.php syntax OK"
php -l routes/apihandler.php >/dev/null && ok "routes/apihandler.php syntax OK"

# Verify the configured database through PHP/PDO as the application does.
if [[ -f .env ]]; then
    if php -r 'require "vendor/autoload.php"; $d=Dotenv\Dotenv::createImmutable(getcwd()); $d->load(); $pdo=new PDO("mysql:host=".$_ENV["DB_HOST"].";dbname=".$_ENV["DB_NAME"].";charset=utf8mb4",$_ENV["DB_USER"],$_ENV["DB_PASS"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); echo "DB OK\\n";' >/tmp/watrbx-db-test.txt 2>&1; then
        ok "PHP/PDO can connect using .env"
    else
        warn "PHP/PDO database test failed:"
        sed -n '1,8p' /tmp/watrbx-db-test.txt
    fi
fi

printf "\n${BOLD}${GREEN}Setup complete.${RESET}\n"
printf "Project: %s\n" "$PROJECT_DIR"
printf "URL:     %s\n" "$BASE_URL"
printf "Database: %s\n" "$DB_NAME"
printf "Private key: %s\n" "$KEY_FILE"
if [[ "$MAKE_ADMIN" =~ ^[Yy]$ ]]; then printf "Admin:    %s\n" "$ADMIN_USER"; fi
printf "\nStart the local server with:\n  cd %q\n  php -S 0.0.0.0:8080 -t public router.php\n\n"
printf "Keep storage/PrivateNut.pem private and do not commit it to Git.\n"

