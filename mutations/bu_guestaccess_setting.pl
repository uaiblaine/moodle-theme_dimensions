# Boost Union's guest access hint needs its setting.
s/        if \(get_config\(self::BU, 'showhintcourseguestaccess'\) != THEME_BOOST_UNION_SETTING_SELECT_YES\) \{/        if (false) {/;
