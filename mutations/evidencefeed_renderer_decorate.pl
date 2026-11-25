# Render core's export undecorated: the first paint is in core's order of names, with no month and no platform flag.
s/\$data = evidencelist::decorate\(\$page->export_for_template\(\$this\)\);/\$data = \$page->export_for_template(\$this);/;
