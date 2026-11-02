# Read an address naming no key as none.
s/        if \(!isset\(\$_GET\[state::PARAM_KEYS\]\) && !isset\(\$_POST\[state::PARAM_KEYS\]\)\) \{\n            return self::KEYS;/        if (!isset(\$_GET[state::PARAM_KEYS]) && !isset(\$_POST[state::PARAM_KEYS])) {\n            return [];/;
