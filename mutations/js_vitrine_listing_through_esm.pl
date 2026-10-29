# Load the AMD listing again from the logged-in client, beside the TypeScript one the components' mounts share.
s/^import \* as Listing from 'core\/esm!\@moodle\/lms\/theme_dimensions\/listing\/listing';$/import * as Listing from 'theme_dimensions\/categorylisting';/m;
