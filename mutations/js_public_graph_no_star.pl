# Pull the session's star into the shared listing, which the visitor's module imports. An entry-file-only check of
# the visitor's imports stays green over this; the transitive walk is what sees it.
s/^(import \{markTitles\} from '\.\.\/areas\/mark';)$/$1\nimport {arm as armStar} from '.\/star';/m;
