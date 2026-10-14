# Give the width button the label of the action it would perform next, as a state-dependent
# name, so the label flips together with aria-pressed.
s/'widthlabel' => get_string\('focusmode_width', self::COMPONENT\)/'widthlabel' => \$expanded ? 'Narrow' : get_string('focusmode_width', self::COMPONENT)/;
