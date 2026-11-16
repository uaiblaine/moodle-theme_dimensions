# Treat a foreign address as a local one when a key is placed by its path.
s/ \|\| !\$url->is_local_url\(\)//;
