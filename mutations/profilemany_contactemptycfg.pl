# Drop the guard on an unset coursecontact: explode() is handed null.
s/if \(empty\(\$CFG->coursecontact\) \|\| empty\(\$course->id\)\) \{\n\s*return \[\];\n\s*\}//
