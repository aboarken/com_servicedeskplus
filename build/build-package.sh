#!/usr/bin/env bash
# Builds the installable Joomla package: dist/com_servicedeskplus-<version>.zip
# Usage: build/build-package.sh   (run from anywhere)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$root/servicedeskplus.xml" | head -n1)"
[ -n "$version" ] || { echo "No <version> in servicedeskplus.xml" >&2; exit 1; }

out="$root/dist/com_servicedeskplus-$version.zip"
mkdir -p "$root/dist"
rm -f "$out"

# Only what the manifest installs, plus the licence, in a stable sorted order. In a git checkout
# only tracked files are packed, so local editor files or test output never end up in the zip.
cd "$root"
paths=(servicedeskplus.xml script.php LICENSE administrator site media)

if git -C "$root" rev-parse --is-inside-work-tree > /dev/null 2>&1; then
  git -C "$root" ls-files -- "${paths[@]}"
else
  find "${paths[@]}" -type f ! -name '.DS_Store' ! -name '*.orig'
fi | LC_ALL=C sort | zip -X -q "$out" -@

echo "$out"
