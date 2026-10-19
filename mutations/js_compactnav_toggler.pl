# Leave core's toggler hidden while compact: the navigation is gone and nothing opens the drawer.
s/bar\.toggler\.classList\.toggle\(CLASSES\.TOGGLERHIDDEN, !on\);/bar.toggler.classList.toggle(CLASSES.TOGGLERHIDDEN, true);/;
