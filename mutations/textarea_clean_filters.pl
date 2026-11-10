# A cleaned read would skip the text filters, so a shortcode in a public section would show as written.
s/(\x27trusted\x27 => \$data->get\(\x27valuetrust\x27\),\n                )\x27filter\x27 => \$filters,/$1\x27filter\x27 => false,/;
