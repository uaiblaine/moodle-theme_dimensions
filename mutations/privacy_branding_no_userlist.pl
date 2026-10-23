# The person who last saved a category is never listed.
s/(\$userlist->add_from_sql\('usermodified',)/return;\n        $1/;
