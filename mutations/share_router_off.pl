# Mint and spell a short address on a site whose router is not configured: the address
# becomes /r.php/p/<code>, longer than the page's own, and rows pile up for nothing.
s/        if \(!self::routing_enabled\(\)\) \{\n            return self::page\(\$courseid\);\n        \}\n//;
