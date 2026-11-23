# Arm the help dialogue's module on no page: every long help stays a popover nobody can scroll by keyboard.
s/\n[ \t]*\$this->page->requires->js_call_amd\('theme_dimensions\/helpmodal', 'init'\);(?=\n)//;
