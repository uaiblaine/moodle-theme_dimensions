# Put the panel INSIDE the text colour's form row instead of into the fieldset's own container.
# This is the no-relocation rule (a moodleform row rearranged by JavaScript hides fields
# nobody added to the map, and when the source is clipped rather than hidden leaves them focusable
# and announced while painted nowhere), and the assertion that holds it is an allowlist over every
# line of the module that names a row. The module mounts the panel in the fieldset's container, so the
# mutation moves it into a row - which is also the shape that would quietly undo the
# two columns, since the grid places the children of the container and nothing inside a row.
s/    const fieldset = inkinput\.closest\('fieldset'\);/    const inkrow = rowOf(inkinput);\n    inkrow.appendChild(aside);\n    const fieldset = inkinput.closest('fieldset');/;
