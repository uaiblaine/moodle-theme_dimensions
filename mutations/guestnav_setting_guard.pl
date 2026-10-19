# Make the setting dead: a site that switched the visitor's actions off still gets them, and core's
# "Log in" link never comes back.
s/return \(string\) get_config\(self::COMPONENT, self::SETTING\) !== '0';/return true;/;
