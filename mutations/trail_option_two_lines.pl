# The upgrade appends a trail line after an administrator's own third line, which then becomes the trail.
s/if \(count\(array_filter\(\$lines, 'strlen'\)\) !== 2\) \{\n(\s*)return false;\n(\s*)\}(\n\s*\$lines\[\] = get_string\('landingfield_model_trail')/\$3/;
