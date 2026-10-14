# Ignore the content hash: a stale URL then serves whatever image the course has now, and the
# cache-buster the scrapers depend on is gone.
s/ \|\| \$file->get_contenthash\(\) !== \$hash\) \{/) {/;
