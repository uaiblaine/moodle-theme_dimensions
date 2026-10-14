# Hand the stored colours to the preview's style attribute unparsed. The editor still renders,
# the band still looks right for every category anybody has saved through the form - and a value
# that reached the column another way (a migration, a hand edit, an older release) is written
# straight into a CSS context, which is the one sink Mustache's escaping does not protect.
s/        \$bgcolour = branding::hex\(\(string\) \(\$row\?->bgcolour \?\? ''\)\) \?\? branding::DEFAULT_BG;\n        \$inkcolour = branding::hex\(\(string\) \(\$row\?->inkcolour \?\? ''\)\) \?\? branding::DEFAULT_INK;/        \$bgcolour = (string) (\$row?->bgcolour ?? branding::DEFAULT_BG);\n        \$inkcolour = (string) (\$row?->inkcolour ?? branding::DEFAULT_INK);/;
