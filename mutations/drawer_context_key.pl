# Never hand the layout context the drawer's data: every logged-in account keeps Boost Union's list.
s/\$context\['dimensionsdrawer'\] = drawernav::context\(/\$unused = drawernav::context(/;
