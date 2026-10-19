# Key the drawer on the navigation alone: a context that is not a logged-in account's layout - no avatars in its
# user menu - gets the theme's drawer too, built from a user menu core never wrote.
s/isset\(\$context\['mobileprimarynav'\], \$context\['usermenu'\]\['avatardata'\]\)/isset(\$context['mobileprimarynav'])/;
