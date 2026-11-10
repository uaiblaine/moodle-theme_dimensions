# A section that held only the shortcode would be drawn as an empty section.
s/!html_is_blank\(\(string\) \$html\) && isset/trim((string) \$html) !== '' \&\& isset/;
