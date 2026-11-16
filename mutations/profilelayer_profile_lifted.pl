# Keep Profile in the list: it is drawn as a row of My area beside the header's View profile.
s/\n[^\n]*if \(\$path === self::PROFILE_PATH && self::is_own_profile\(\$url\)\) \{\n[^\n]*continue;\n[^\n]*\}//;
