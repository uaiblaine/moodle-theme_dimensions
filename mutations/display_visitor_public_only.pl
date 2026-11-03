# A visitor is shown every referenced course in full, public or not.
s/\$full = array_flip\(publicaccess::are_public\(array_keys\(\$rows\)\)\);/\$full = array_flip(array_keys(\$rows));/;
