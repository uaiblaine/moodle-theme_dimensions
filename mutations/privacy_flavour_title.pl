# The export names a chosen flavour by its id alone.
s/format_string\(\$title, true, \$options\) \. ' \(' \. \$flavourid \. '\)'/(string) \$flavourid/;
