# Leave the calendar in the start's mode after a start is pressed: the second press of a range from
# the start box then moves the start again instead of setting the end, so two presses can never
# describe a range there and the reader is left wondering why the end box stays empty.
#
# The pattern is the one assignment and nothing around it: anchor a gate on the guard it breaks.
s/^([ \t]+)mode = TO;$/$1mode = FROM;/m;
