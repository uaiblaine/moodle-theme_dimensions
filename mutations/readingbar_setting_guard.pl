# Make the context bar's setting dead: a site that switched it off still gets the layer over its navbar.
s/return \(string\) get_config\(self::COMPONENT, self::SETTING_CONTEXT\) !== '0';/return true;/;
