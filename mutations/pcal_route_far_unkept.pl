# Keep a distant window in the shared cache.
s/publiccache::half\(\$half, window::from_iso\(\$from, \$to\), \$near\)/publiccache::half(\$half, window::from_iso(\$from, \$to), true)/;
