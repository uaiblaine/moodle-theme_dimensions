# Hand a tag's text on as format_string() left it: with tags kept, the service's PARAM_TEXT return type refuses it.
s/return trim\(clean_param\(\$text, PARAM_TEXT\)\);/return trim(\$text);/;
