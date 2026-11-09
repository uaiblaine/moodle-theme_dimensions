# A limit that is not a number, or none, would list one course instead of the site's count.
s/return ctype_digit\(\$limit\) \? \(int\) \$limit : band::count\(\);/return (int) \$limit;/;
