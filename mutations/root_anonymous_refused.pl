# Let a VISITOR resolve the root of the course index whatever the site said. The root is public
# only on an explicit stored '1' and the guest account is never a visitor; this one
# clause is the whole of both, so without it a site that never opened the root - which is every
# site by default - answers the internet with a directory of its top level,
# and the guest account walks the same path the anonymous shell exists to keep it off.
s/                if \(isguestuser\(\) \|\| !publicaccess::is_public\(0\)\) \{/                if (false) {/;
