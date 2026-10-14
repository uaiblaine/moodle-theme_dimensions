# Pull the session's star into the shared listing, which the visitor's module imports. An
# entry-file-only check of the visitor's imports stays green over this; the transitive walk is what
# sees it. Swapped, not added, so the build still changes.
s/^import \* as CategoryMark from 'theme_dimensions\/categorymark';$/import * as CategoryMark from 'theme_dimensions\/categorystar';/m;
