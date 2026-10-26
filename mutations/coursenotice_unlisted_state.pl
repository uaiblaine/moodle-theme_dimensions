# An unlisted course says so, not "login only".
s/\} else if \(\$state === self::STATE_UNLISTED\) \{\n            \$notice->add_chip\(/} else if (false) {\n            \$notice->add_chip(/;
