#!/usr/bin/env bash
# Option B migration – Step 1: Prepare directories on bareefers.org (Ubuntu 24.04 LTS)
# Run this on the new Ubuntu server (e.g. bareefers.org) as a user with sudo.
# Usage: sudo ./option-b-01-prepare-directories.sh   OR   ./option-b-01-prepare-directories.sh

set -e

if [[ $EUID -ne 0 ]]; then
   echo "Re-running with sudo..."
   exec sudo "$0" "$@"
fi

echo "Creating system user 'barcode'..."
if ! getent passwd barcode >/dev/null 2>&1; then
    useradd -r -s /bin/false barcode
    echo "  Created user barcode."
else
    echo "  User barcode already exists."
fi

echo "Creating data directories..."
mkdir -p /home/barcode/barcode-data/databases
mkdir -p /home/barcode/barcode-data/uploads

echo "Setting ownership to barcode:barcode..."
chown -R barcode:barcode /home/barcode

echo "Done. Directories:"
ls -la /home/barcode/
ls -la /home/barcode/barcode-data/
