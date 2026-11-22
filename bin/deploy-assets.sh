#!/usr/bin/env bash
# Push the listing artwork in .wordpress-org/ to the WordPress.org SVN assets
# folder. Assets ONLY: trunk/ and tags/ are never touched, there is no version
# bump and no release. deploy-wporg.sh deploys the code and leaves assets alone;
# this is the other half. Idempotent: new files are added, changed files are
# updated, and a managed file removed from .wordpress-org/ is removed from SVN.
#
#   bin/deploy-assets.sh            commit (needs SVN_USERNAME and SVN_PASSWORD)
#   bin/deploy-assets.sh --dry-run  stage only and show what would change
set -euo pipefail
cd "$(dirname "$0")/.."

slug=an10-ai-crawler-analytics
url="https://plugins.svn.wordpress.org/$slug/assets"
src=.wordpress-org
dry_run=false
if [ "${1:-}" = "--dry-run" ]; then
	dry_run=true
fi

# The managed artwork: screenshots, banners, icons. Nothing else in assets/ is
# touched, so a file WordPress.org has that we do not publish is left in place.
shopt -s nullglob
managed=( "$src"/screenshot-*.png "$src"/banner-*.png "$src"/icon-* )
shopt -u nullglob
if [ ${#managed[@]} -eq 0 ]; then
	echo "No artwork found in $src/ (screenshot-*.png, banner-*.png, icon-*)." >&2
	exit 1
fi

if ! $dry_run && { [ -z "${SVN_USERNAME:-}" ] || [ -z "${SVN_PASSWORD:-}" ]; }; then
	echo "SVN_USERNAME and SVN_PASSWORD must be set." >&2
	exit 1
fi

root=$(pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

svn checkout -q "$url" "$work"

# Copy every managed file in; this adds new ones and overwrites changed ones.
for f in "${managed[@]}"; do
	cp "$root/$f" "$work/"
done

cd "$work"
# Remove from SVN any managed-pattern file that is no longer in the source, so a
# deleted screenshot leaves the listing too. Unmanaged files are never removed.
shopt -s nullglob
for f in screenshot-*.png banner-*.png icon-*; do
	if [ ! -e "$root/$src/$(basename "$f")" ]; then
		svn rm -q "$f"
	fi
done
shopt -u nullglob
# Stage anything new.
svn add -q --force . >/dev/null 2>&1 || true

echo "Asset changes:"
svn status
if [ -z "$(svn status)" ]; then
	echo "Nothing changed. The listing assets are already up to date."
	exit 0
fi
if $dry_run; then
	echo "Dry run: nothing committed."
	exit 0
fi

printf '%s' "$SVN_PASSWORD" | svn commit --non-interactive --no-auth-cache \
	--username "$SVN_USERNAME" --password-from-stdin -m "Update listing assets"
echo "Committed listing assets to WordPress.org. The listing refreshes within a few hours."
