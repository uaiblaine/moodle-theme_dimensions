# Load an AMD module from the visitor's entry instead of its TypeScript shell: the star, a session module, on a page
# served to the internet.
s/^import \{start\} from 'core\/esm!\@moodle\/lms\/theme_dimensions\/listing\/visitor';$/import {init as start} from 'theme_dimensions\/categorystar';/m;
