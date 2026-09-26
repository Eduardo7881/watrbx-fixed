#!/data/data/com.termux/files/usr/bin/bash

set -e

PROJECT_DIR="${1:-.}"

STORAGE_DIR="$PROJECT_DIR/storage"
PRIVATE_KEY="$STORAGE_DIR/PrivateNut.pem"
PUBLIC_KEY="$PROJECT_DIR/public_key.pem"

mkdir -p "$STORAGE_DIR"

if ! command -v openssl >/dev/null 2>&1; then
    echo "OpenSSL is not installed."
    echo "Install it with: pkg install openssl"
    exit 1
fi

if [ -f "$PRIVATE_KEY" ] || [ -f "$PUBLIC_KEY" ]; then
    echo "A key file already exists."
    echo "Private key: $PRIVATE_KEY"
    echo "Public key:  $PUBLIC_KEY"
    echo "No files were changed."
    exit 1
fi

echo "Generating RSA private key..."

openssl genrsa \
    -out "$PRIVATE_KEY" \
    2048

echo "Generating matching RSA public key..."

openssl rsa \
    -in "$PRIVATE_KEY" \
    -pubout \
    -out "$PUBLIC_KEY"

chmod 600 "$PRIVATE_KEY"
chmod 644 "$PUBLIC_KEY"

echo
echo "Key pair generated successfully."
echo
echo "Private key: $PRIVATE_KEY"
echo "Public key:  $PUBLIC_KEY"
echo
