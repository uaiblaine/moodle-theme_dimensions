# Keep Profile in the list: it is drawn as a row of My area beside the header's View profile.
s/\n[^\n]*if \(\$path === self::PROFILE_PATH\) \{\n[^\n]*continue;\n[^\n]*\}//;
