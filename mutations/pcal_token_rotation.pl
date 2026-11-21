# Keep a token however old it is: a write no event announces stays cached for good.
s/            && \$now - \(int\) \$stored\[.minted.\] < self::ROTATION\[\$half\];/            ;/;
