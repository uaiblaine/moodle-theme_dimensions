# Write a number into the "Show" template: the client finds no place to write each answer's number.
s/'showtemplate' => get_string\('category_showcount', self::COMPONENT, self::SHOW_PLACEHOLDER\),/'showtemplate' => get_string('category_showcount', self::COMPONENT, '0'),/;
