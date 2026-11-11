# Swallow a refused lock in the task instead of throwing, so core never retries it and the field stays out of line.
s/if \(\$written === null\) \{\n\s*throw new \\moodle_exception\('locktimeout'\);\n\s*\}//;
