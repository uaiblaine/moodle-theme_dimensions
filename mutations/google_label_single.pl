# Name the product on every Google issuer, however many there are.
s/\(\$servicetype === self::GOOGLE_SERVICETYPE && \$googlecount === 1\)/(\$servicetype === self::GOOGLE_SERVICETYPE)/;
