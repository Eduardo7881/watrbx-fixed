#!/usr/bin/bash
set -e

PROJECT_DIR="${1:-.}"
KEY_FILE="$PROJECT_DIR/storage/PrivateNut.pem"

mkdir -p "$PROJECT_DIR/storage"

if [ -f "$KEY_FILE" ]; then
    echo "PrivateNut.pem already exists:"
    echo "$KEY_FILE"
    exit 0
fi

if ! command -v openssl >/dev/null 2>&1; then
    echo "OpenSSL is not installed."
    echo "Install it with: pkg install openssl"
    exit 1
fi

echo "Generating PrivateNut.pem..."

openssl genrsa -out "$KEY_FILE" 2048

chmod 600 "$KEY_FILE"

echo
echo "PrivateNut.pem generated successfully:"
echo "$KEY_FILE"
