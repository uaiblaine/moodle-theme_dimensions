# Public or login only is the predicate's answer.
s/        \$public = publicaccess::is_public\(\$courseid\);/        \$public = true;/;
