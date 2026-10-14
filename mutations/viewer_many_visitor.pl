# Answer a visitor as a logged-in reader.
s/if \(!\$courses \|\| !isloggedin\(\) \|\| isguestuser\(\)\) \{/if (!\$courses) {/;
