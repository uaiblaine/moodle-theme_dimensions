# Lift every profile address out of the list: a custom item for another user's profile silently disappears.
s/\$path === self::PROFILE_PATH && self::is_own_profile\(\$url\)/\$path === self::PROFILE_PATH/;
