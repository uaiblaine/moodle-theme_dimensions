# A visitor would be shown every course the ranking holds, the listed and the unlisted ones included.
s/return array_intersect_key\(\$rows, array_flip\(publicaccess::are_public\(array_keys\(\$rows\)\)\)\);/return \$rows;/;
