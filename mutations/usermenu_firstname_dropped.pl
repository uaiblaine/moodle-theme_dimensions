# Keep core's first name in the user menu's context: from Moodle 5.3 core draws it beside the
# avatar from xl up, next to the full name this theme's core/user_menu_metadata override already
# shows there - two names in the navbar, and the stylesheet cannot hide a d-xl-block.
s/\n[^\n]*unset\(\$context\['usermenu'\]\['userfirstname'\]\);//;
