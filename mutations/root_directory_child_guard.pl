# Give the root's directory a card for every top-level category, clamped ones included: a hidden
# branch and one local_unlistedcourses withholds are both named, with a count, to a viewer who
# may not know they exist.
s/            if \(\$scope->is_open\(\$childid\)\) \{/            if (true) {/;
