# Take a field that accepts a type which is not a web image for one the picker can hold.
s/return !array_diff\(\$types, \$typesutil->expand\('web_image'\)\);/return true;/;
