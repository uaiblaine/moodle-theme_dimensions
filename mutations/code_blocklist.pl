# Let a draw that spells a profanity through.
s/        return self::is_well_formed\(\$code\) && !self::is_blocked\(\$code\);/        return self::is_well_formed(\$code);/;
