# Judge a session key that held null as absent, and remove it.
s/property_exists\(\$SESSION, 'calendarshoweventtype'\)/isset(\$SESSION->calendarshoweventtype)/;
