# The popover imports core's description service again: the visitor's page reaches it.
s/(import \{keyLabel\} from '\.\/KeyChips';)/import {description} from '.\/description';\n$1/;
