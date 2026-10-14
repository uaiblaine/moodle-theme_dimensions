# The guest enrolment hint's call to action is for whoever may configure guest enrolment.
s/            if \(has_capability\('enrol\/guest:config', \$context\)\) \{/            if (false) {/;
