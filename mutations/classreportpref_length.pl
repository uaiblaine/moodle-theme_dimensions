# A value longer than the bound is stored.
s/while \(\\core_text::strlen\(\$encode\(\$clean\)\) > self::MAX_LENGTH && /while (false \&\& /;
