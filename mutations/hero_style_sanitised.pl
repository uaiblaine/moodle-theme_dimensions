# Hand every colour back unparsed: the sanitiser stops being one, and whatever a row holds is
# written verbatim into the hero's style attribute - the CSS context Mustache does not protect.
s/(public static function hex\(string \$value\): \?string \{\n)/$1        return \$value;\n/;
