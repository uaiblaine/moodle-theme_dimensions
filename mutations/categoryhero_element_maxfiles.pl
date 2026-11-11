# Build the plain file manager row (the cover film) with an option set of its own: a second file. The picker rows
# cannot carry one (tool_imagepicker's element forces a single file), so the film's row is where the list can be lost.
s/\$mform->addElement\('filemanager', \$area, get_string\(\$key, self::COMPONENT\), null, \$options\);/\$mform->addElement('filemanager', \$area, get_string(\$key, self::COMPONENT), null, ['maxfiles' => 2] + \$options);/;
