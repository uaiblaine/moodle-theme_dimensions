# Read the bands for every page, both images shown or not.
s/\$bands = boost_union::show_images\(boost_union::VIEW_CARD\) && boost_union::show_images\(boost_union::VIEW_LIST\)/\$bands = false/;
