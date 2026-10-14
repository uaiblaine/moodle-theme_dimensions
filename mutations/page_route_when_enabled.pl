# Keep spelling the script as the canonical on a site whose router is configured.
s/        if \(!self::routing_enabled\(\)\) \{\n            return self::legacy\(\$courseid\);/        if (true) {\n            return self::legacy(\$courseid);/;
