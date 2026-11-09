# Answer yes whatever is stored: Boost Union's "no" no longer turns the progress row off.
s/return \$value === false \|\| \$value === self::YES;/return true;/;
