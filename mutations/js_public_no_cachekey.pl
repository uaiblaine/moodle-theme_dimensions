# Hand core/ajax a cachekey. It then sends a GET, and service.php answers it with ninety days of
# public, immutable caching: a course withdrawn tomorrow stays in shared caches under an address
# anybody can publish. The service refuses the request, so every visitor's request falls back.
s/\}\], true, false\)\[0\];/}], true, false, false, 0, Config.langrev)[0];/;
