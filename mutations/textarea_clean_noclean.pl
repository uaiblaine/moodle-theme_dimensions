# A cleaned read of a stored textarea would still honour its trust mark.
s/(\x27trusted\x27 => \$data->get\(\x27valuetrust\x27\),\n                \x27filter\x27 => \$filters,\n            \]) \+ \(\$clean \? \[\x27noclean\x27 => false\] : \[\]\)/$1/;
