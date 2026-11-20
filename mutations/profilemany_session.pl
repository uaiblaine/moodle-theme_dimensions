# Drop the no-session return: the guest viewer and a visitor would be prevented by the batch.
s/if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n\s*return \$answers;\n\s*\}\n\s*unset/unset/
