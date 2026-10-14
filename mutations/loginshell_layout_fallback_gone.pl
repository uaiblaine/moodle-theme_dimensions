# Lose the fallback to Boost Union's layout: every login-layout page goes through the shell branch.
s~if \(\$loginshell === null\) \{~if (false) {~;
