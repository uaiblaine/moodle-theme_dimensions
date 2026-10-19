# Make the compact navbar's setting dead: a site that switched it off still has its navigation held and measured.
s/return \(string\) get_config\(self::COMPONENT, self::SETTING\) !== '0';/return true;/;
