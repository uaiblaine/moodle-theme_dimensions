# Keep core's toggler attribute on the hidden control: Boost moves the focus to a control nobody can see.
s/index\.removeAttribute\('data-toggler'\);/index.setAttribute('data-toggler', 'drawers');/;
