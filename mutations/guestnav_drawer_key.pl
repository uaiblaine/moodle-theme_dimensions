# Never hand the layout context the visitor's data: the mobile drawer loses its "New here?" card, the one
# place below md where "Create account" is offered.
s/\n[^\n]*\$context\['dimensionsguest'\] = guestnav::context\(\);//;
