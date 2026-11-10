# A field's default, shown on a public course without a row, would keep its trusted HTML.
s/(\x27trusted\x27 => true,\n            \x27filter\x27 => \$filters,\n        \]) \+ \(\$clean \? \[\x27noclean\x27 => false\] : \[\]\)/$1/;
