# Splice the armed class into every class attribute of the body string instead of the first only.
s/(\\bclass="\(\[\^"\]\*\)"\/', 'class="\$1 ' \. self::BODY_ARMED \. '"', \$attributes), 1\)/$1)/;
