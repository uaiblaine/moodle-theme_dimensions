# Answer the reader's own listing with addresses that leave the organisation out.
s/'remembered' => !\$anonymous && empty\(\$args\['withcategories'\]\) && remembered::for_category\(\$categoryid\) !== null,/'remembered' => false,/;
