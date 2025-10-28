#!/bin/sh
# Usage: ./push.sh "commit message" 1.2.3

if [ $# -lt 2 ]; then echo "Usage: $0 \"message\" version"; exit 1; fi
MSG="$1"; VER="$2"

# 1) Git
git add .
git commit -m "$MSG"
git push origin main

# 2) Sync to SVN working copy (do NOT touch svn/tags here)
rsync -ravzp --exclude-from './push.exclude' ./ ./svn/trunk
rsync -ravzp ./assets/ ./svn/assets

# 3) SVN: update, add, commit trunk, tag, commit tag
cd svn
svn update
svn add --force trunk assets >/dev/null 2>&1 || true
svn commit -m "$MSG"
svn cp trunk "tags/$VER"
svn commit -m "Tagging version $VER"
cd ..

