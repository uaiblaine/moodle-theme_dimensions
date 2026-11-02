# Answer every course whose own row says public, hidden or under an unlisted category.
s/        \$public = array_flip\(publicaccess::are_public\(array_keys\(\$rows\)\)\);/        \$public = array_flip(array_keys(\$rows));/;
