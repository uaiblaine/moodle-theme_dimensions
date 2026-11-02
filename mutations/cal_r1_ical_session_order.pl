# Filter the feed as whoever the session held before: the token's user is never made the session's.
s/        self::start_session\(\$user\);\n        return self::feed/        return self::feed/;
