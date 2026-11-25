# Return the acting user's name as fullname() writes it, markup and all, into a field typed PARAM_TEXT.
s/plancards::plain\(fullname\(\$actor\), \$system\)/fullname(\$actor)/;
