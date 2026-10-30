# Name the account's shell in a comment of the component's panel, which the visitor's page loads.
s/^(export const EVENTS = \{\.\.\.AREAS, \.\.\.LISTING\};\n)/\/\/ Answered on the logged-in shell by listing\/account.\n$1/m;
