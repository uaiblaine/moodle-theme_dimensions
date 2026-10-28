# Read the posters as links unless the props say otherwise: the logged-in shell's posters stop being buttons.
s/    links: props\.links === true,/    links: props.links !== false,/;
