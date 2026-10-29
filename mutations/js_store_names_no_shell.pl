# The store imports the visitor's shell: every page that loads the store would load that shell.
s/^(import \* as Listing from '\.\/listing';)$/$1\nimport {start as visitor} from '.\/visitor';/m;
