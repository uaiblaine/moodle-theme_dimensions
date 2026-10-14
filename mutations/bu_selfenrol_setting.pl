# Boost Union's self enrolment hint needs its setting.
s/        if \(get_config\(self::BU, 'showhintcourseselfenrol'\) != THEME_BOOST_UNION_SETTING_SELECT_YES\) \{/        if (false) {/;
