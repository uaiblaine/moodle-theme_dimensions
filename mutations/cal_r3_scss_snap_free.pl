# Keep the snap on while the map drags.
s/(\.dim-cal__trailscroll--free \{\n\s*)scroll-snap-type: none;/$1scroll-snap-type: x proximity;/;
