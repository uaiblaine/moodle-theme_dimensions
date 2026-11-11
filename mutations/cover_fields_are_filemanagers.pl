# Put the five single-file rows back to file PICKERS: a picker shows a file name, offers no
# thumbnail and has no control that removes what
# it holds, so a cover, a film or a crest uploaded once could never be taken away. Nothing else
# changes - the name, the options and the draft plumbing are identical either way - which is
# exactly why the element type is asserted instead of described.
# The image rows reach addElement() through local\imagepicker since the image picker integration, so this
# line now breaks the cover video's row alone; imagepicker_fallback_filemanager breaks the image rows' fallback.
s/\$mform->addElement\('filemanager', \$area, get_string\(\$key, self::COMPONENT\), null, \$options\);/\$mform->addElement('filepicker', \$area, get_string(\$key, self::COMPONENT), null, \$options);/;
