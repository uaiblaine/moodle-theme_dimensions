# Store the profile layer for any user menu with items: a context without core's avatars gets a layer as well.
s/isset\(\$context\['usermenu'\]\['avatardata'\], \$context\['usermenu'\]\['items'\]\)\) \{\n(\s+)\$this->profilelayer =/isset(\$context['usermenu']['items'])) {\n$1\$this->profilelayer =/;
