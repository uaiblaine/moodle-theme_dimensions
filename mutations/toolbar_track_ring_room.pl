# Take away the room the chip's focus ring is drawn in. The track is a scroll container, the design's
# ring is outside the chip (3px at 2px), and without the track's own 5px of padding the container
# clips it: the chip the keyboard is on shows a ring cut at the track's edge, or none at the first
# chip's left. The sweep exercises category_scss_test's assertion.
s/(\.dim-toolbar__pills \{\n(?:[^\n]*\n)*?\s*margin: -5px;\n)\s*padding: 5px;\n/$1/;
