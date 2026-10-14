# Give the organisation's legend back the whole width Bootstrap's reboot gives every legend. It is
# still a flex item of its group (the reboot floats it too), but a full-width one, so the track
# wraps under it and "Organise:" is a line of its own above the chips again instead of inline at the
# start of its row. The sweep exercises category_scss_test's assertion.
s/(\.dim-toolbar__group--sort > \.dim-toolbar__grouplabel \{\n\s*float: left;\n)\s*width: auto;\n/$1/;
