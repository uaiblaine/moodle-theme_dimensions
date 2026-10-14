# Take the return type off the public page editor, leaving the element's own default of 15
# (FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK). The media controls then
# declare internal returns for a text that has no file area to store one in, so whatever they hand
# back points at a draft area that expires - and the upload repository comes back into both
# dialogues, with its drop zone, on the one editor of this form that may store nothing.
#
# The pattern is anchored on the single option that IS the guard, not on the whole literal option set:
# the set lives in categoryhero::publictext_editor_options(), and a dead pattern takes the rest of the
# sweep down at that line.
s/\n            'return_types' => FILE_EXTERNAL,//;
