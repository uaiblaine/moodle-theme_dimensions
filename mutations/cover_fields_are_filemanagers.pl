# Put the five single-file rows back to file PICKERS: a picker shows a file name, offers no
# thumbnail and has no control that removes what
# it holds, so a cover, a film or a crest uploaded once could never be taken away. Nothing else
# changes - the name, the options and the draft plumbing are identical either way - which is
# exactly why the element type is asserted instead of described.
s/\$mform->addElement\('filemanager', \$area, get_string\(\$key, self::COMPONENT\), null, \$options\);/\$mform->addElement('filepicker', \$area, get_string(\$key, self::COMPONENT), null, \$options);/;
