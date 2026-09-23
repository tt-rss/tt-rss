#!/bin/sh -e
#
# Write precompressed .gz siblings for tt-rss's static assets, so nginx can
# serve them via gzip_static instead of compressing on every request.
#
# Because the work happens once, ahead of time, it can use level 9, which is
# not worth paying for on the fly: on tt-rss's cold-load bundle level 6 gives
# 17.1% over nginx's default of level 1, and level 9 gives 17.4%.
#
# Run from the tt-rss root, or pass the root as the first argument.  Safe to
# re-run: a sibling is only rewritten when it is older than its source.
#
# NOTE: gzip_static serves a .gz whenever one exists and never compares it
# against the source, so a stale sibling means stale content on the wire.  This
# has to run whenever the assets change, not just once.

ROOT="${1:-.}"

# Only types nginx is configured to compress.  Anything already compressed
# (png/gif/woff2) is skipped -- re-compressing it would only add bytes.
find "$ROOT/js" "$ROOT/lib" "$ROOT/themes" "$ROOT/images" \
		-type f \( -name '*.js' -o -name '*.css' -o -name '*.svg' -o -name '*.json' \) \
		! -name '*.gz' 2>/dev/null | while read -r f; do

	# Matches gzip_min_length; below it the sibling would never be served.
	[ "$(wc -c < "$f")" -ge 256 ] || continue

	[ "$f.gz" -nt "$f" ] || gzip -9 -c "$f" > "$f.gz"
done

echo "precompress: done"
