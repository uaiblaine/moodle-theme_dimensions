# The look cohorts are made without the component stamp, so core lets anyone edit, assign or delete them.
s/\s+'component' => self::COMPONENT,\n(\s+'visible' => 1,)/\n$1/;
