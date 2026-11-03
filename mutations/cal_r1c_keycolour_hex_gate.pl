# Trust the stored value for the swatch: a value that is not a hex reaches the inline style.
s/'hex' => branding::hex\(\$value\) \?\? '',/'hex' => \$value,/;
