# A band with nothing to list would still draw its heading.
s/'band' => \$found \? \(new related_band\(\$found, 'dimensions-landing-related'\)\)->export_for_template\(\$output\) : null,/'band' => (new related_band(\$found, 'dimensions-landing-related'))->export_for_template(\$output),/;
