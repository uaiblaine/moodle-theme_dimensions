# A course set to public that the predicate refuses gets its own chip, not "login only".
s/(\} else if \(\$state === self::STATE_PUBLIC\) \{\n            \$notice->add_chip\()/} else if (false) {\n            \$notice->add_chip(/;
