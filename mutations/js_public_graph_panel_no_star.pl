# Import the star into the shared panel module, in the place of the title marker: the visitor's page
# then loads a module that stars courses for a session it does not have.
s/^import \* as CategoryMark from 'theme_dimensions\/categorymark';$/import * as CategoryMark from 'theme_dimensions\/categorystar';/m;
