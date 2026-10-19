# Never splice the armed class into the body: the stylesheet holds nothing and the module refuses to run.
s/\$context\['bodyattributes'\] = compactnav::body_attributes\(\$context\['bodyattributes'\]\);//;
