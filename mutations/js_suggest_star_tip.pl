# Read the star's word in the suggestions from its title, which it no longer carries.
s/add\(star, star\.getAttribute\(TIP\) \|\| '', '', known\.count\);/add(star, star.title || '', '', known.count);/;
