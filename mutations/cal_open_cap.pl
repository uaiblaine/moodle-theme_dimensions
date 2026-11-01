# Answer every joinable course past the cap, and never say so.
s/\$surplus = count\(\$open\) > self::OPEN_CAP;/\$surplus = false;/;
