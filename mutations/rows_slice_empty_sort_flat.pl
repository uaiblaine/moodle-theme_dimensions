# Leave the empty sort grouped for a slice that is dropped: the answer is then the parent's grouped page.
s/\$args\['sort'\] = \$sort === '' \? criteria::SORT_RECENT : \$sort;/\$args['sort'] = \$sort;/;
