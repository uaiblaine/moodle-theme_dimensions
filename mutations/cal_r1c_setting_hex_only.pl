# Store whatever was typed: names, rgb() and hsl() reach the stored colour, as core's picker keeps them.
s/return branding::hex\(str_starts_with\(\$data, '#'\) \? \$data : '#' \. \$data\) \?\? false;/return \$data;/;
