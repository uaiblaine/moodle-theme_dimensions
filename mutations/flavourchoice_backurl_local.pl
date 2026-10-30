# The choice page sends the person back to any address it is given.
s/\$local = clean_param\(\$returnurl, PARAM_LOCALURL\);/$local = $returnurl;/;
