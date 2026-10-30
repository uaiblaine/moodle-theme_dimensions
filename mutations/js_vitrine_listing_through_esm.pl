# Load the old AMD client from the account's entry instead of its TypeScript shell: a second listing, with its own
# counters and its own history writer.
s/^import \{start\} from 'core\/esm!\@moodle\/lms\/theme_dimensions\/listing\/account';$/import {init as start} from 'theme_dimensions\/categoryvitrine';/m;
