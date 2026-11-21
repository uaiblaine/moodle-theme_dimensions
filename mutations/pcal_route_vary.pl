# Keep core's Vary: Accept-Language: Google Cloud CDN refuses to cache the halves on a multilingual site.
s/            header_remove\('Vary'\);/            ;/;
