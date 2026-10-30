# Start the visitor's client from the account's entry instead of the account's shell: a listing that asks the
# visitor's service and remembers nothing.
s/^import \{start\} from 'core\/esm!\@moodle\/lms\/theme_dimensions\/listing\/account';$/import {init as start} from 'theme_dimensions\/listingvisitor';/m;
