#!/usr/bin/env bash
# Release the version in readme.txt to WordPress.org, unless it is there already.
# Commits build/<slug>/ (made by bin/build.sh) as trunk and tags/<version> in one
# commit. The artwork in the SVN assets folder is left alone.
#
#   bin/deploy-wporg.sh            commit (needs SVN_USERNAME and SVN_PASSWORD)
#   bin/deploy-wporg.sh --dry-run  stage only and show what would be committed
set -euo pipefail
cd "$(dirname "$0")/.."

slug=an10-ai-crawler-analytics
url="https://plugins.svn.wordpress.org/$slug"
version=$(sed -n 's/^Stable tag: *//p' readme.txt)
dry_run=false
if [ "${1:-}" = "--dry-run" ]; then
	dry_run=true
fi

if [ ! -f "build/$slug/$slug.php" ]; then
	echo "No build found. Run bin/build.sh first." >&2
	exit 1
fi
built=$(sed -n 's/^ \* Version: *//p' "build/$slug/$slug.php")
if [ "$built" != "$version" ]; then
	echo "build/ holds $built but readme.txt says $version. Run bin/build.sh again." >&2
	exit 1
fi

if svn ls "$url/tags/$version/" >/dev/null 2>&1; then
	echo "WordPress.org already has $version. Nothing to release."
	exit 0
fi

if ! $dry_run && { [ -z "${SVN_USERNAME:-}" ] || [ -z "${SVN_PASSWORD:-}" ]; }; then
	echo "SVN_USERNAME and SVN_PASSWORD must be set." >&2
	exit 1
fi

root=$(pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

svn checkout -q --depth immediates "$url" "$work"
svn update -q --set-depth infinity "$work/trunk"
svn update -q --set-depth immediates "$work/tags"

rsync -a --delete --exclude .svn "$root/build/$slug/" "$work/trunk/"
cd "$work"
svn add -q --force trunk
svn status trunk | sed -n 's/^! *//p' | while IFS= read -r gone; do
	svn rm -q "$gone"
done
svn cp -q trunk "tags/$version"

# What goes up must be exactly the build.
diff -r -x .svn trunk "$root/build/$slug"
diff -r -x .svn trunk "tags/$version"

echo "Changes for $version:"
svn status

if $dry_run; then
	echo "Dry run: nothing committed."
	exit 0
fi

printf '%s' "$SVN_PASSWORD" | svn commit --non-interactive --no-auth-cache \
	--username "$SVN_USERNAME" --password-from-stdin -m "Release $version"
echo "Released $version to WordPress.org. Sites get it after the security scan (about six hours)."
