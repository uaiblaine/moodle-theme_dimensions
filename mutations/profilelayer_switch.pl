# Ignore the switch: a site that stored '0' still gets the profile layer.
s/return \(string\) get_config\(self::COMPONENT, self::SETTING\) !== '0';/return true;/;
