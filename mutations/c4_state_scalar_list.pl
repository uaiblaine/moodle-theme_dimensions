# Hand a list for the city to optional_param(), which throws, on the logged-in page.
s/\(string\) self::scalar_param\(self::PARAM_CITY, '', PARAM_TEXT\)/(string) optional_param(self::PARAM_CITY, '', PARAM_TEXT)/;
