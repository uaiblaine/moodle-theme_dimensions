# Count the state pills without the favourites in force: each pill's number then promises courses
# pressing it would not show.
s/if \(\$favourite \|\| !\$criteria->favourites\) \{/if (true) {/;
