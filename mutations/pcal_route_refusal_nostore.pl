# Let a cache keep a refusal.
s/return self::uncached\(\$response->withStatus\(404\)/return (\$response->withStatus(404)/;
